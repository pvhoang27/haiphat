#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
hpv_root=/var/www/html/hpv-deploy-qa
docker compose -f dev/compose.yaml exec -T wordpress test ! -e "$hpv_root"
docker compose -f dev/compose.yaml exec -T wordpress mkdir -p "$hpv_root/wp-content/themes"
docker compose -f dev/compose.yaml exec -T wordpress cp -a /usr/src/wordpress/. "$hpv_root/"
docker compose -f dev/compose.yaml cp dev/fixtures/deploy-wp-config.php "wordpress:$hpv_root/wp-config.php"
docker compose -f dev/compose.yaml cp dev/fixtures/legacy-theme "wordpress:$hpv_root/wp-content/themes/hai-phat-vina"
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/deploy-fixture.php install
for hpv_attempt in 1 2 3; do
  hpv_revision="$(printf '%040d' "$hpv_attempt")"
  hpv_stage="$hpv_root/wp-content/.hpv-releases/$hpv_revision"
  docker compose -f dev/compose.yaml exec -T wordpress mkdir -p "$hpv_stage"
  docker compose -f dev/compose.yaml cp hai-phat-vina "wordpress:$hpv_stage/theme"
  docker compose -f dev/compose.yaml cp hai-phat-vina-core "wordpress:$hpv_stage/plugin"
  docker compose -f dev/compose.yaml cp dev/deploy/. "wordpress:$hpv_stage/"
  if [ "$hpv_attempt" = 3 ]; then
    docker compose -f dev/compose.yaml cp dev/fixtures/failed-release.php "wordpress:$hpv_stage/theme/functions.php"
    if docker compose -f dev/compose.yaml exec -T wordpress bash "$hpv_stage/remote.sh" "$hpv_root" "$hpv_revision"; then
      printf '%s\n' 'FAIL: intentionally broken release unexpectedly succeeded.' >&2
      exit 1
    fi
  else
    docker compose -f dev/compose.yaml exec -T wordpress bash "$hpv_stage/remote.sh" "$hpv_root" "$hpv_revision"
  fi
  docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/deploy-fixture.php check
  if [ "$hpv_attempt" = 1 ]; then docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/deploy-fixture.php edit; fi
done
printf '%s\n' 'PASS: legacy upgrade, repeat deployment and failure rollback on real isolated WordPress.'
