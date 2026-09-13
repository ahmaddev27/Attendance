# TAQAT Backup & Restore Runbook

Everything here runs through GitHub Actions; nobody needs to SSH into the VPS.

| What | Workflow | When |
|------|----------|------|
| Back up database + uploaded files, prove the dump restores | `Backup` (`.github/workflows/backup.yml`) | Daily 23:30 UTC (02:30 Amman), or **Run workflow** |
| Restore the production database from a backup | `Restore database` (`.github/workflows/restore.yml`) | Manual only |
| Probe production health | `Uptime` (`.github/workflows/uptime.yml`) | Every 10 minutes |

> The repository is **public**, so workflow logs are public too. The scripts never print secrets, data or file contents — only file names, sizes and table counts. Never add a step that uploads a backup as a workflow artifact.

---

## What a backup contains

Written on the VPS to `~/taqat-backups` of the deploy user (outside the git checkout):

| File | Contents |
|------|----------|
| `db-YYYYmmdd-HHMMSS.sql.gz` | `mysqldump --single-transaction` of the application database (routines, triggers, events included) |
| `files-YYYYmmdd-HHMMSS.tar.gz` | `storage/app` from the `api_storage` volume: leave attachments, task files, avatars |
| `minio-YYYYmmdd-HHMMSS.tar.gz` | The whole MinIO data volume |
| `pre-restore-YYYYmmdd-HHMMSS.sql.gz` | Safety dump taken automatically before every restore |

Meilisearch is not backed up: the index is rebuilt from the database with `php artisan search:seed`.

### How each run proves the backup is usable

1. Checks free disk space first: at least 1 GiB, and three times the size of the previous set. It refuses to run otherwise, so a backup can never fill the disk MySQL writes to.
2. Dumps the database and checks that the gzip is intact and that the dump ends with mysqldump's `Dump completed` footer.
3. **Restores the dump into a scratch database** (`taqat_backup_verify`), compares the table count and the `migrations` row count with the live database, then drops the scratch database. A mismatch fails the run.
4. Archives the file volumes and checks each archive.
5. Uploads encrypted copies off-site (when configured), then deletes local backups older than `BACKUP_KEEP_DAYS`.

A failed run is a red run in the Actions tab, and GitHub emails whoever last changed the schedule.

---

## Off-site copies (required to survive losing the server)

Until this is configured every run succeeds with a **"Backups are not off-site"** warning: the backups sit on the same disk as the data they protect.

1. Create a private bucket on any S3-compatible provider. Examples:

   | Provider | `BACKUP_S3_ENDPOINT` | `BACKUP_S3_REGION` |
   |----------|----------------------|--------------------|
   | AWS S3 | leave empty | the bucket's region, e.g. `eu-central-1` |
   | Backblaze B2 | `https://s3.<region>.backblazeb2.com` | e.g. `eu-central-003` |
   | Cloudflare R2 | `https://<account-id>.r2.cloudflarestorage.com` | `auto` |

2. Create an access key that can only write to that bucket (no delete permission is needed).
3. Add a lifecycle rule on the bucket that deletes objects after the retention you want (for example 30 days).
4. In GitHub → Settings → Secrets and variables → Actions, add:

   | Name | Kind | Value |
   |------|------|-------|
   | `BACKUP_S3_BUCKET` | Secret | bucket name |
   | `BACKUP_S3_ACCESS_KEY_ID` | Secret | access key id |
   | `BACKUP_S3_SECRET_ACCESS_KEY` | Secret | secret access key |
   | `BACKUP_PASSPHRASE` | Secret | a long random passphrase — **also store it in your password manager; without it the off-site copies cannot be decrypted** |
   | `BACKUP_S3_ENDPOINT` | Secret | only for non-AWS providers |
   | `BACKUP_S3_REGION` | Variable | see the table above |
   | `BACKUP_S3_PREFIX` | Variable | optional folder name, default `taqat` |
   | `BACKUP_KEEP_DAYS` | Variable | optional local retention in days, default `7` |

5. Run the `Backup` workflow manually and check that step 5 says `uploaded N encrypted file(s) off-site`.

Each run uploads to `s3://<bucket>/<prefix>/<stamp>/` as `*.enc` files, encrypted with AES-256-CBC and a PBKDF2-derived key (200 000 iterations).

### Decrypting an off-site copy

```bash
export BACKUP_PASSPHRASE='…'
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 \
  -pass env:BACKUP_PASSPHRASE \
  -in db-20260914-233000.sql.gz.enc -out db-20260914-233000.sql.gz
gzip -t db-20260914-233000.sql.gz
```

---

## Restoring the production database

Use this after data loss or corruption. The restore replaces **all** data written since the backup.

1. Actions → **Restore database** → **Run workflow**.
2. `backup`: `latest`, or an exact name such as `db-20260914-233000.sql.gz`. The run lists the available names if the one you typed does not exist.
3. `confirm`: type exactly `restore production database`.

The run then:

1. Checks the backup is complete.
2. Takes a fresh safety dump (`pre-restore-…sql.gz`).
3. Puts the API in maintenance mode (`php artisan down`) and stops the queue worker and scheduler.
4. Drops and recreates the database, then imports the backup. **If the import fails, the safety dump is imported back automatically.**
5. Runs `php artisan migrate --force`, because a backup can be older than the running code.
6. Brings the app back up and waits for `/api/health`.

If step 5 fails, the app stays in maintenance mode on purpose, so nobody writes to a half-migrated schema. Fix the migration and deploy; the deploy brings the app back.

To undo a restore, run the workflow again with the `pre-restore-…sql.gz` file's timestamp. Rename is not needed — pass the name as `backup`.

### Restoring uploaded files

There is no workflow for this yet; it needs access to the server:

```bash
cd ~/taqat-backups
docker run --rm --volumes-from taqat_api -v "$PWD:/backup:ro" alpine:3.20 \
  sh -c 'tar -C /var/www/html/storage/app -xzf /backup/files-YYYYmmdd-HHMMSS.tar.gz'
```

---

## Health checks and alerts

`GET /api/health` answers:

- **200 `ok`** — database, cache, storage (and Redis when it is used) work, and the scheduler and queue worker left a heartbeat in the last few minutes.
- **200 `degraded`** — the API works, but the scheduler or queue worker is not running (notifications, SMS and sweeps have stopped).
- **503 `down`** — a critical dependency failed.

The body lists each check as `ok`, `failed`, `stale` or `missing`; the reason is only in the Laravel log.

The `Uptime` workflow fails on anything other than `200 ok` after three attempts a minute apart. GitHub pauses scheduled workflows in public repositories after 60 days without commits; re-enable it from the Actions tab if that ever happens.
