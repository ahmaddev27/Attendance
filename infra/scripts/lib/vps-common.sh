# shellcheck shell=bash
#
# Shared helpers for scripts that run on the production VPS. run-on-vps.sh
# concatenates this file in front of the entry script; it is never executed
# on its own.

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.simple.yml}"
# shellcheck disable=SC2034  # read by the backup and restore entry scripts
BACKUP_DIR="${BACKUP_DIR:-$HOME/taqat-backups}"

section() {
  printf '\n════════════════════════════════════════\n▶ %s\n════════════════════════════════════════\n' "$*"
}

info() { printf '   %s\n' "$*"; }
warn() { printf '   ⚠ %s\n' "$*"; }
fail() { printf '   ✗ %s\n' "$*" >&2; }

enter_app_dir() {
  : "${APP_PATH:?APP_PATH missing}"
  cd "$APP_PATH" || exit 1
  if [ ! -f "$COMPOSE_FILE" ]; then
    fail "$COMPOSE_FILE missing in $APP_PATH"
    exit 1
  fi
}

dc() {
  docker compose -f "$COMPOSE_FILE" "$@"
}

# Runs the mysql client as root inside the db container, reading SQL (or a
# dump) from stdin. The root password never leaves the container: it is read
# from the container's own MYSQL_ROOT_PASSWORD.
mysql_root() {
  # shellcheck disable=SC2016  # expanded inside the container
  dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot -N -B "$@"' sh "$@"
}

app_database() {
  dc exec -T db printenv MYSQL_DATABASE < /dev/null | tr -d '\r\n'
}

# Streams a consistent dump of the application database into a gzip file and
# proves it is complete before it gets its final name.
dump_database_to() {
  local destination="$1"
  local partial="$destination.partial"

  # shellcheck disable=SC2016  # expanded inside the container
  if ! dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot \
        --single-transaction --quick --routines --triggers --events --hex-blob \
        --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"' < /dev/null \
      | gzip -6 > "$partial"; then
    rm -f "$partial"
    return 1
  fi

  if ! gzip -t "$partial" || ! gzip -dc "$partial" | tail -n 1 | grep -q '^-- Dump completed'; then
    rm -f "$partial"
    return 1
  fi

  chmod 600 "$partial"
  mv "$partial" "$destination"
}

dump_is_complete() {
  gzip -t "$1" && gzip -dc "$1" | tail -n 1 | grep -q '^-- Dump completed'
}

# Asks the app itself, through nginx, whether it is ready. Prints the health
# JSON and succeeds only on HTTP 200 — the same answer the uptime monitor sees.
api_health() {
  # shellcheck disable=SC2016  # PHP code, not shell
  dc exec -T api php -r '
    $context = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 5]]);
    $body = @file_get_contents("http://nginx/api/health", false, $context);
    if ($body === false) { exit(2); }
    echo $body;
    preg_match("#HTTP/\S+\s+(\d{3})#", $http_response_header[0] ?? "", $match);
    exit(($match[1] ?? "") === "200" ? 0 : 1);
  ' < /dev/null 2>/dev/null
}

wait_for_health() {
  local attempt body=""
  for attempt in $(seq 1 30); do
    if body="$(api_health)"; then
      info "healthy after ${attempt} attempt(s): $body"
      return 0
    fi
    sleep 2
  done
  fail "health check never passed: ${body:-no response}"
  return 1
}

wait_for_api() {
  local attempt
  for attempt in $(seq 1 60); do
    if dc exec -T api php artisan --version > /dev/null 2>&1 < /dev/null; then
      info "api ready after ${attempt}s"
      return 0
    fi
    sleep 1
  done
  fail "api never became ready"
  dc logs --tail=50 api || true
  return 1
}
