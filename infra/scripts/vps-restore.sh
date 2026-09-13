# shellcheck shell=bash
#
# Restores the production database from a backup written by vps-backup.sh.
# Runs on the VPS through run-on-vps.sh (.github/workflows/restore.yml),
# prefixed by lib/vps-common.sh.
#
# Destructive by design, so it is guarded twice: the workflow and this script
# both require the typed confirmation, and a fresh safety dump is taken before
# anything changes. If the import fails, the safety dump is put back.
#
# Inputs:
#   APP_PATH
#   RESTORE_BACKUP   "latest", or a file name such as db-20260914-233000.sql.gz
#   RESTORE_CONFIRM  must be exactly "restore production database"
#
# Uploaded files are not touched; see docs/deployment/backup-restore.md.

enter_app_dir

CONFIRMATION="restore production database"
STAMP="$(date -u +%Y%m%d-%H%M%S)"

if [ "${RESTORE_CONFIRM:-}" != "$CONFIRMATION" ]; then
  fail "RESTORE_CONFIRM does not match — nothing was changed"
  exit 1
fi

resolve_backup_name() {
  local requested="${RESTORE_BACKUP:-latest}"
  if [ "$requested" = "latest" ]; then
    find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz' -printf '%f\n' | sort | tail -n 1
    return 0
  fi
  # A strict pattern keeps the input from pointing anywhere outside BACKUP_DIR.
  printf '%s\n' "$requested" | grep -Ex 'db-[0-9]{8}-[0-9]{6}\.sql\.gz'
}

replace_database() {
  local database="$1" dump="$2" charset="" collation=""
  read -r charset collation < <(
    printf "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.schemata WHERE schema_name = '%s';\n" "$database" \
      | mysql_root | tr -d '\r'
  ) || true

  # shellcheck disable=SC2016  # backticks are SQL identifiers
  if ! printf 'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s;\n' \
      "$database" "$database" "${charset:-utf8mb4}" "${collation:-utf8mb4_unicode_ci}" | mysql_root; then
    return 1
  fi

  gzip -dc "$dump" | mysql_root "$database"
}

reopen_app() {
  dc start queue scheduler || true
  dc exec -T api php artisan up < /dev/null || true
}

section "1/6  Checking the backup"
backup_name="$(resolve_backup_name || true)"
if [ -z "$backup_name" ] || [ ! -f "$BACKUP_DIR/$backup_name" ]; then
  fail "backup '${RESTORE_BACKUP:-latest}' not found. RESTORE_BACKUP must be 'latest' or one of:"
  find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz' -printf '     %f\n' 2>/dev/null | sort
  exit 1
fi
backup_file="$BACKUP_DIR/$backup_name"
if ! dump_is_complete "$backup_file"; then
  fail "$backup_name is corrupt or truncated — nothing was changed"
  exit 1
fi
app_db="$(app_database)"
if [ -z "$app_db" ]; then
  fail "could not read MYSQL_DATABASE from the db container"
  exit 1
fi
info "restoring $backup_name into '$app_db'"

section "2/6  Taking a safety dump of the current database"
safety_file="$BACKUP_DIR/pre-restore-$STAMP.sql.gz"
if ! dump_database_to "$safety_file"; then
  fail "safety dump failed — nothing was changed"
  exit 1
fi
info "$(basename "$safety_file"): $(du -h "$safety_file" | cut -f1)"

section "3/6  Stopping writers"
dc exec -T api php artisan down --retry=60 < /dev/null
trap reopen_app EXIT
dc stop queue scheduler

section "4/6  Replacing the database"
if ! replace_database "$app_db" "$backup_file"; then
  fail "import failed — putting the safety dump back"
  if replace_database "$app_db" "$safety_file"; then
    info "the data from before this run is back in place"
  else
    fail "could not put the safety dump back — restore $(basename "$safety_file") manually"
  fi
  exit 1
fi

section "5/6  Bringing the schema up to date"
# A backup can predate the running code; additive migrations catch it up.
if ! dc exec -T api php artisan migrate --force < /dev/null; then
  fail "migrate failed after the restore — the app stays in maintenance mode for inspection"
  trap - EXIT
  exit 1
fi

section "6/6  Reopening the app"
trap - EXIT
reopen_app
if ! wait_for_health; then
  warn "restore finished but /api/health is not answering 200 yet"
fi

section "✓ Restored $backup_name (safety copy: $(basename "$safety_file"))"
