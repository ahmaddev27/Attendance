# shellcheck shell=bash
#
# Nightly backup of the production data. Runs on the VPS through
# run-on-vps.sh (.github/workflows/backup.yml), prefixed by lib/vps-common.sh.
#
# Writes to $BACKUP_DIR (default ~/taqat-backups, outside the git checkout):
#   db-<stamp>.sql.gz      consistent mysqldump of the application database
#   files-<stamp>.tar.gz   uploaded files on the api storage volume (storage/app)
#   minio-<stamp>.tar.gz   the MinIO data volume
#
# Every dump is restored into a scratch database and compared with the live
# one before the run counts as a success: a backup nobody has restored is a
# hope, not a backup.
#
# Off-site copy: when BACKUP_S3_BUCKET is set, each file is encrypted with
# BACKUP_PASSPHRASE (AES-256-CBC, PBKDF2) and uploaded to that S3-compatible
# bucket (AWS S3, Backblaze B2, Cloudflare R2, Wasabi...). Local copies older
# than BACKUP_KEEP_DAYS (default 7) are deleted.

enter_app_dir

KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
VERIFY_DATABASE="taqat_backup_verify"
MIN_FREE_KB=$((1024 * 1024))
AWS_CLI_IMAGE="amazon/aws-cli:2.17.0"
TOOLS_IMAGE="alpine:3.20"

case "$KEEP_DAYS" in
  '' | *[!0-9]*)
    fail "BACKUP_KEEP_DAYS must be a whole number of days, got '$KEEP_DAYS'"
    exit 1
    ;;
esac

ensure_free_space() {
  mkdir -p "$BACKUP_DIR"
  chmod 700 "$BACKUP_DIR"

  local free_kb latest latest_stamp previous_kb=0 required_kb
  free_kb="$(df -Pk "$BACKUP_DIR" | awk 'NR == 2 { print $4 }')"
  latest="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'db-*.sql.gz' -printf '%f\n' | sort | tail -n 1)"
  if [ -n "$latest" ]; then
    latest_stamp="${latest#db-}"
    latest_stamp="${latest_stamp%.sql.gz}"
    previous_kb="$(du -ck "$BACKUP_DIR"/*-"$latest_stamp".* | tail -n 1 | cut -f1)"
  fi

  # Room for tonight's set plus headroom, never less than 1 GiB, so a backup
  # can't be the thing that fills the disk MySQL writes to.
  required_kb=$((previous_kb * 3))
  [ "$required_kb" -lt "$MIN_FREE_KB" ] && required_kb="$MIN_FREE_KB"

  info "free: $((free_kb / 1024)) MiB · required: $((required_kb / 1024)) MiB"
  [ "$free_kb" -ge "$required_kb" ]
}

count_tables() {
  # shellcheck disable=SC2016  # backticks are SQL identifiers
  printf "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '%s';\n" "$1" | mysql_root | tr -d '\r'
}

count_migrations() {
  # shellcheck disable=SC2016  # backticks are SQL identifiers
  printf 'SELECT COUNT(*) FROM `%s`.`migrations`;\n' "$1" | mysql_root | tr -d '\r'
}

drop_verify_database() {
  # shellcheck disable=SC2016  # backticks are SQL identifiers
  printf 'DROP DATABASE IF EXISTS `%s`;\n' "$VERIFY_DATABASE" | mysql_root > /dev/null || true
}

verify_database_dump() {
  local dump="$1" app_db live_tables restored_tables live_migrations restored_migrations
  app_db="$(app_database)"
  if [ -z "$app_db" ]; then
    fail "could not read MYSQL_DATABASE from the db container"
    return 1
  fi

  # shellcheck disable=SC2016  # backticks are SQL identifiers
  if ! printf 'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s`;\n' "$VERIFY_DATABASE" "$VERIFY_DATABASE" | mysql_root; then
    return 1
  fi

  if ! gzip -dc "$dump" | mysql_root "$VERIFY_DATABASE"; then
    drop_verify_database
    return 1
  fi

  live_tables="$(count_tables "$app_db")"
  restored_tables="$(count_tables "$VERIFY_DATABASE")"
  live_migrations="$(count_migrations "$app_db")"
  restored_migrations="$(count_migrations "$VERIFY_DATABASE")"
  drop_verify_database

  info "tables: live=$live_tables restored=$restored_tables · migrations: live=$live_migrations restored=$restored_migrations"
  [ -n "$restored_tables" ] && [ "$restored_tables" -gt 0 ] \
    && [ "$live_tables" = "$restored_tables" ] \
    && [ "$live_migrations" = "$restored_migrations" ]
}

archive_volume() {
  local container="$1" source_path="$2" destination="$3"
  local partial="$destination.partial"

  if ! docker inspect "$container" > /dev/null 2>&1; then
    warn "$container not found — skipping $(basename "$destination")"
    return 0
  fi

  if ! docker run --rm --volumes-from "$container:ro" "$TOOLS_IMAGE" tar -C "$source_path" -czf - . < /dev/null > "$partial"; then
    rm -f "$partial"
    return 1
  fi
  if ! gzip -t "$partial"; then
    rm -f "$partial"
    return 1
  fi

  chmod 600 "$partial"
  mv "$partial" "$destination"
  info "$(basename "$destination"): $(du -h "$destination" | cut -f1)"
}

encrypt_file() {
  local source="$1" destination="$2"
  # OpenSSL older than 1.1.1 (still common on cPanel hosts) has no -pbkdf2,
  # so fall back to a throwaway container rather than weaken the cipher.
  if openssl enc -aes-256-cbc -pbkdf2 -pass pass:probe -in /dev/null -out /dev/null > /dev/null 2>&1; then
    openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE -in "$source" -out "$destination"
  else
    # shellcheck disable=SC2016  # expanded inside the container
    docker run --rm -i -e BACKUP_PASSPHRASE "$TOOLS_IMAGE" sh -c \
      'apk add --no-cache -q openssl > /dev/null && exec openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE' \
      < "$source" > "$destination"
  fi
}

upload_offsite() {
  local name missing=()
  for name in BACKUP_PASSPHRASE BACKUP_S3_ACCESS_KEY_ID BACKUP_S3_SECRET_ACCESS_KEY; do
    [ -n "${!name:-}" ] || missing+=("$name")
  done
  if [ "${#missing[@]}" -gt 0 ]; then
    fail "BACKUP_S3_BUCKET is set but these secrets are empty: ${missing[*]}"
    return 1
  fi

  local staging file endpoint_args=() status=0
  staging="$(mktemp -d)"
  chmod 700 "$staging"
  [ -n "${BACKUP_S3_ENDPOINT:-}" ] && endpoint_args=(--endpoint-url "$BACKUP_S3_ENDPOINT")

  for file in "$@"; do
    if ! encrypt_file "$file" "$staging/$(basename "$file").enc"; then
      rm -rf "$staging"
      return 1
    fi
  done

  export AWS_ACCESS_KEY_ID="$BACKUP_S3_ACCESS_KEY_ID"
  export AWS_SECRET_ACCESS_KEY="$BACKUP_S3_SECRET_ACCESS_KEY"
  export AWS_DEFAULT_REGION="${BACKUP_S3_REGION:-us-east-1}"
  docker run --rm -e AWS_ACCESS_KEY_ID -e AWS_SECRET_ACCESS_KEY -e AWS_DEFAULT_REGION \
    -v "$staging:/backup:ro" "$AWS_CLI_IMAGE" \
    s3 cp /backup "s3://$BACKUP_S3_BUCKET/${BACKUP_S3_PREFIX:-taqat}/$STAMP/" \
    --recursive --only-show-errors "${endpoint_args[@]}" < /dev/null || status=$?
  unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION
  rm -rf "$staging"

  [ "$status" -eq 0 ] && info "uploaded ${#} encrypted file(s) off-site"
  return "$status"
}

prune_old_backups() {
  find "$BACKUP_DIR" -maxdepth 1 -type f \
    \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' -o -name 'minio-*.tar.gz' -o -name 'pre-restore-*.sql.gz' \) \
    -mtime +"$KEEP_DAYS" -print -delete
  find "$BACKUP_DIR" -maxdepth 1 -type f -name '*.partial' -mmin +360 -delete
}

section "1/5  Checking free space in $BACKUP_DIR"
if ! ensure_free_space; then
  fail "not enough free space for tonight's backup — nothing was written"
  exit 1
fi

section "2/5  Dumping the database"
db_file="$BACKUP_DIR/db-$STAMP.sql.gz"
if ! dump_database_to "$db_file"; then
  fail "database dump failed"
  exit 1
fi
info "$(basename "$db_file"): $(du -h "$db_file" | cut -f1)"

section "3/5  Restoring the dump into a scratch database"
if ! verify_database_dump "$db_file"; then
  fail "the dump did not restore to the same schema — kept for inspection, but this backup is not trustworthy"
  exit 1
fi
info "✓ dump restores cleanly"

section "4/5  Archiving uploaded files"
files_file="$BACKUP_DIR/files-$STAMP.tar.gz"
minio_file="$BACKUP_DIR/minio-$STAMP.tar.gz"
if ! archive_volume taqat_api /var/www/html/storage/app "$files_file"; then
  fail "archiving the api storage volume failed"
  exit 1
fi
if ! archive_volume taqat_minio /data "$minio_file"; then
  fail "archiving the MinIO volume failed"
  exit 1
fi

produced=("$db_file")
[ -f "$files_file" ] && produced+=("$files_file")
[ -f "$minio_file" ] && produced+=("$minio_file")

section "5/5  Off-site copy and retention"
if [ -n "${BACKUP_S3_BUCKET:-}" ]; then
  if ! upload_offsite "${produced[@]}"; then
    fail "off-site upload failed — local backup is complete, but it exists on this server only"
    exit 1
  fi
else
  warn "off-site copy is not configured — these backups live on the same server as the data"
  echo "::warning title=Backups are not off-site::Set the BACKUP_S3_* secrets and BACKUP_PASSPHRASE (docs/deployment/backup-restore.md)."
fi

prune_old_backups
info "backups kept in $BACKUP_DIR:"
find "$BACKUP_DIR" -maxdepth 1 -type f ! -name '*.partial' -printf '   %TY-%Tm-%Td %TH:%TM  %10s  %f\n' | sort

section "✓ Backup $STAMP complete"
