#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
hpv_export_file="${1:?Validated SSH export file required}"
hpv_branch=automation/wordpress-design
hpv_sync_time="$(TZ=Asia/Ho_Chi_Minh date '+%Y-%m-%d %H:%M:%S ICT')"
hpv_pr_title="Sync WordPress design: $hpv_sync_time"
git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git fetch origin main
if git ls-remote --exit-code --heads origin "$hpv_branch" > /dev/null; then
  git fetch origin "$hpv_branch"
  # This branch is reserved for generated sources. Refuse unrelated changes.
  while IFS= read -r hpv_path; do
    [[ "$hpv_path" == site-design/current/* ]] || { printf '%s\n' 'Unexpected changes on design branch; inspect it before syncing.' >&2; exit 1; }
  done < <(git diff --name-only "origin/main...origin/$hpv_branch")
  git switch --create "$hpv_branch" --track "origin/$hpv_branch"
  git merge --no-edit origin/main
else
  git switch --create "$hpv_branch" origin/main
fi
python3 dev/sync-design.py "$hpv_export_file"
git add -- site-design/current
if git diff --cached --quiet; then
  printf '%s\n' 'No design changes; no commit created.'
  # Retry PR creation if a previous push succeeded but the API call failed.
  if git diff --quiet origin/main -- site-design/current; then exit 0; fi
else
  git commit -m "$hpv_pr_title"
  # A normal push refuses concurrent/foreign branch changes instead of overwriting.
  git push --set-upstream origin "$hpv_branch"
fi
hpv_pr="$(gh pr list --state open --base main --head "$hpv_branch" --json number --jq '.[0].number // empty')"
if [ -z "$hpv_pr" ]; then
  gh pr create --base main --head "$hpv_branch" --title "$hpv_pr_title" --body-file .github/design-sync-pr.md
else
  gh pr edit "$hpv_pr" --title "$hpv_pr_title"
  printf 'Updated design PR #%s\n' "$hpv_pr"
fi
