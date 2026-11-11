#!/usr/bin/env bash
set -euo pipefail
cnf=${MYSQL_CNF:?Provide an isolated MySQL client defaults file via MYSQL_CNF}
database=${1:?Usage: scripts/restore-check.sh NEW_DATABASE_restorecheck /absolute/backup.sql}
backup=${2:?Missing backup file}
[[ "$database" =~ ^[a-zA-Z0-9_]+_restorecheck$ && "$backup" = /* && -r "$backup" && -r "$cnf" ]] || exit 2
# Refuse any existing schema. Restore testing must never overwrite data.
exists=$(mysql --defaults-extra-file="$cnf" --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$database'")
[[ "$exists" = 0 ]] || { echo 'Restore target already exists; refusing overwrite.' >&2; exit 2; }
mysql --defaults-extra-file="$cnf" -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql --defaults-extra-file="$cnf" "$database" < "$backup"
mysql --defaults-extra-file="$cnf" --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$database'"
echo 'Import completed into an isolated new schema. Validate row counts and business invariants separately.'
