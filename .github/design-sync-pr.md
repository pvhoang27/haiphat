WordPress editors saved changes to the published website. This PR records the latest page block markup, active-theme template overrides, shared design dependencies and global styles under `site-design/current/`.

The SSH export is read-only and validated against an allowlist of source paths and metadata before committing. It excludes drafts, private/password-protected pages, posts, products, customer requests, user accounts, database dumps and media files. Published page copy and image references remain part of the design source.

Review the source diff before merging. This PR does not automatically merge, write back to WordPress, or replace the theme defaults. The website already contains these edits; subsequent edits are collected into this PR on the next scheduled sync. Database and uploads still require their normal backups.
