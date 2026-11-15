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
hpv_export_file="$(mktemp)"
trap 'rm -f "$hpv_export_file"' EXIT
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-deploy-tools/export-design.php "$hpv_root" > "$hpv_export_file"
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-checks/deploy-fixture.php check-sync-context < "$hpv_export_file"
mapfile -t hpv_context < <(python3 - "$hpv_export_file" <<'PY'
import importlib.util, json, pathlib, sys
spec = importlib.util.spec_from_file_location('sync', 'dev/sync-design.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
raw = pathlib.Path(sys.argv[1]).read_bytes()
module.validate(raw)
context = json.loads(raw)['sync']
print(context['hash'])
print(context['generation'])
PY
)
docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-deploy-tools/ack-design-sync.php "$hpv_root" "${hpv_context[0]}" "${hpv_context[1]}"
hpv_healthy_revision="$(docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-deploy-tools/read-deployed-revision.php "$hpv_root")"
[[ "$hpv_healthy_revision" == 0000000000000000000000000000000000000002 ]]
docker compose -f dev/compose.yaml exec -T wordpress touch "$hpv_root/.maintenance"
if docker compose -f dev/compose.yaml exec -T wordpress php /opt/hpv-deploy-tools/read-deployed-revision.php "$hpv_root"; then
  docker compose -f dev/compose.yaml exec -T wordpress rm "$hpv_root/.maintenance"
  printf '%s\n' 'FAIL: interrupted maintenance was treated as a healthy unchanged release.' >&2
  exit 1
fi
docker compose -f dev/compose.yaml exec -T wordpress rm "$hpv_root/.maintenance"
printf '%s\n' 'PASS: interrupted maintenance cannot be mistaken for an unchanged healthy release.'
printf '%s\n' 'PASS: SSH export, validated ACK and healthy production revision after rollback.'
printf '%s\n' 'PASS: legacy upgrade, repeat deployment and failure rollback on real isolated WordPress.'
