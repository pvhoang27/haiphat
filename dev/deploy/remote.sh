#!/usr/bin/env bash
set -Eeuo pipefail
hpv_wp_root="${1:?WordPress root required}"
hpv_revision="${2:?Commit SHA required}"
[[ "$hpv_wp_root" =~ ^/[a-zA-Z0-9_./-]+$ && "$hpv_wp_root" != *..* ]]
[[ "$hpv_revision" =~ ^[a-f0-9]{40}$ ]]
hpv_script="$hpv_wp_root/wp-content/.hpv-releases/$hpv_revision/release.php"
hpv_backup_root="$(php "$hpv_script" backup-root "$hpv_wp_root" "$hpv_revision")"
[[ "$hpv_backup_root" =~ ^/[a-zA-Z0-9_./-]+/haiphat-deploy-backups$ && "$hpv_backup_root" != *..* ]]
mkdir -p "$hpv_backup_root"
chmod 700 "$hpv_backup_root"
exec 9>"$hpv_backup_root/deploy.lock"
flock -w 60 9
if php "$hpv_script" current "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"; then
  php "$hpv_script" health "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
  exit 0
fi
php "$hpv_script" prepare "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
hpv_rollback() {
  trap - ERR HUP INT TERM
  printf '%s\n' 'Deployment failed; restoring previous code and database.' >&2
  php "$hpv_script" rollback "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
  exit 1
}
trap hpv_rollback ERR HUP INT TERM
php "$hpv_script" install "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
php "$hpv_script" finish "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
php "$hpv_script" open "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
php "$hpv_script" health "$hpv_wp_root" "$hpv_revision" "$hpv_backup_root"
trap - ERR HUP INT TERM
