# shellcheck shell=bash
#
# Replays WorkingHoursCalculator over an arbitrary window of attendance
# rows on the production VPS. Backfills late / early-leave / overtime
# minutes and the row status against the current rules — the owner's
# 2026-10-05 rule change (raw minutes, grace only governs the label)
# applies to historical rows this way.
#
# Runs on the VPS through run-on-vps.sh (recompute-attendance workflow),
# prefixed by lib/vps-common.sh.
#
# Inputs:
#   APP_PATH             the compose working directory
#   RECOMPUTE_FROM       Y-m-d (inclusive)
#   RECOMPUTE_TO         Y-m-d (inclusive)
#   RECOMPUTE_DRY_RUN    "true" to log deltas without persisting (optional)

enter_app_dir

: "${RECOMPUTE_FROM:?RECOMPUTE_FROM (Y-m-d) missing}"
: "${RECOMPUTE_TO:?RECOMPUTE_TO (Y-m-d) missing}"

section "Recomputing attendance hours from $RECOMPUTE_FROM to $RECOMPUTE_TO"

artisan_args=(
  "attendance:recompute-hours"
  "--from=$RECOMPUTE_FROM"
  "--to=$RECOMPUTE_TO"
)

if [ "${RECOMPUTE_DRY_RUN:-false}" = "true" ]; then
  info "dry-run mode: deltas will be logged but no row is persisted"
  artisan_args+=("--dry-run")
fi

dc exec -T api php artisan "${artisan_args[@]}"

section "Recompute done"
