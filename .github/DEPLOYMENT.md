# Deploy theme to DirectAdmin

Workflow `.github/workflows/deploy.yml` checks the PHP syntax and deploys only
`hai-phat-vina/` after a successful push to `main`.

Create a GitHub environment named `production`, then add these environment
secrets:

- `DEPLOY_HOST`: SSH host or server IP.
- `DEPLOY_USER`: DirectAdmin/SSH username.
- `DEPLOY_PORT`: SSH port (often `22`; DirectAdmin port `2222` is not normally
  the SSH port).
- `DEPLOY_PRIVATE_KEY`: private key dedicated to this deployment.
- `DEPLOY_KNOWN_HOSTS`: verified SSH host-key line for this server.
- `THEME_PATH`: absolute server path ending in
  `/wp-content/themes/hai-phat-vina`.

The matching public key must be authorized in DirectAdmin. Do not commit a
private key, password, database export, `wp-config.php`, or any secret to this
repository.

The destination must already contain `style.css`. This prevents the first run
from deploying to an incorrect or overly broad directory. Install the theme
once through WordPress/DirectAdmin, or create the exact theme directory and put
the theme's `style.css` there before enabling automatic deployment.

The deploy uses `rsync --delete-delay`, so files removed from the local theme
are also removed from the remote theme. The destination-path validation only
accepts a path ending in `/wp-content/themes/hai-phat-vina`.
