#!/usr/bin/env bash
#
# Runs one or more script files on the production VPS as a single bash
# program. Used by the deploy, backup and restore workflows.
#
#   bash infra/scripts/run-on-vps.sh infra/scripts/lib/vps-common.sh infra/scripts/vps-backup.sh
#
# Environment:
#   VPS_HOST, VPS_USER, VPS_SSH_PORT   where to connect (key from .github/actions/vps-ssh)
#   REMOTE_ENV                         space-separated variable names to forward, e.g. "APP_PATH NO_CACHE"
#
# Forwarded values travel inside the program over the encrypted SSH stream,
# never on a command line, so secrets don't show up in `ps` on the shared host.

set -euo pipefail

: "${VPS_HOST:?VPS_HOST missing}"
: "${VPS_USER:?VPS_USER missing}"
: "${VPS_SSH_PORT:?VPS_SSH_PORT missing}"

if [ "$#" -eq 0 ]; then
  echo "usage: $0 <script>..." >&2
  exit 2
fi

build_program() {
  echo 'set -euo pipefail'
  local name
  for name in ${REMOTE_ENV:-}; do
    printf 'export %s=%q\n' "$name" "${!name-}"
  done
  cat "$@"
}

# The program is written to a private temp file before it runs. Under
# `bash -s` the script itself would be stdin, and any command inside it that
# reads stdin (docker exec, mysql) could swallow the rest of the program.
# shellcheck disable=SC2016  # $script must expand on the VPS, not here
build_program "$@" | ssh -T \
  -o StrictHostKeyChecking=yes \
  -o ServerAliveInterval=30 \
  -o ServerAliveCountMax=10 \
  -i "${VPS_SSH_KEY_PATH:-$HOME/.ssh/id_deploy}" \
  -p "$VPS_SSH_PORT" \
  "$VPS_USER@$VPS_HOST" \
  'script="$(mktemp)" && trap '\''rm -f "$script"'\'' EXIT && cat > "$script" && bash "$script"'
