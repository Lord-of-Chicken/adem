#!/usr/bin/env bash
set -euo pipefail
cnf=${MYSQL_CNF:?Provide a protected MySQL client defaults file via MYSQL_CNF}
database=${1:?Usage: scripts/backup.sh DATABASE /absolute/new-backup.sql}
output=${2:?Missing backup output}
[[ "$database" =~ ^[a-zA-Z0-9_]+$ && "$output" = /* && ! -e "$output" && -r "$cnf" ]] || exit 2
umask 077
# Single transaction gives an InnoDB-consistent snapshot; do not alter schema during the dump.
set -o noclobber
mysqldump --defaults-extra-file="$cnf" --single-transaction --no-tablespaces --routines --events --triggers "$database" > "$output"
test -s "$output"
echo 'Backup written. Its restorability still requires an isolated restore test.'
