# Veldrin Store

WordPress-based e-commerce store with optimized image handling and production-ready Docker configuration.

## 🏗️ Project Structure

```
store/
├── src/                          # WordPress root (core, uploads, 3rd-party plugins are NOT in git)
│   ├── wp-config.php             # Tracked; reads every secret from .env
│   └── wp-content/
│       ├── themes/veldrin/       # Custom Veldrin theme (tracked)
│       ├── plugins/f-pro-*/      # Our plugins (tracked)
│       └── mu-plugins/           # Must-use plugins (tracked)
├── docker-compose.yml            # Shared base: MySQL 8.4 + WordPress (PHP 8.3, Apache)
├── docker-compose.dev.yml        # Local additions: ports 8000 / 3306, wp-cli
├── docker-compose.prod.yml       # Production additions: Traefik, memory limits
├── docker-compose.override.yml   # NOT tracked: symlink to dev.yml (local) or prod.yml (server)
├── apache-mpm.conf               # Apache prefork tuning (mounted read-only)
├── apache-tuning.conf            # Apache KeepAlive tuning (mounted read-only)
├── .env.example                  # Environment template (.env is never committed)
└── IMPROVEMENTS.md               # Technical improvement roadmap
```

Docker Compose automatically loads `docker-compose.yml` + `docker-compose.override.yml`,
so the symlink decides whether you get the local or the production setup.

## 🚀 Local Development

1. **Clone and configure:**
   ```bash
   git clone git@github.com:ultrinnan/store.git && cd store
   cp .env.example .env        # fill in DB names/passwords and keys (any values work locally)
   ln -s docker-compose.dev.yml docker-compose.override.yml
   ```

2. **Get WordPress core, plugins and uploads from production** (they are not in git;
   tracked files are excluded so your local work is not overwritten; drop the uploads
   exclude if you want all ~2.8 GB of images):
   ```bash
   rsync -az --exclude 'wp-content/themes/veldrin/' --exclude 'wp-content/plugins/f-pro*/' \
     --exclude 'wp-content/mu-plugins/' --exclude 'wp-config.php' --exclude 'wp-content/uploads/' \
     vps:/srv/veldrin-store/src/ ./src/
   ```

3. **Start containers and import a database dump** (the local volume starts empty):
   ```bash
   docker compose up -d
   ssh vps 'ls -t /var/backups/vps/daily/*/veldrin/veldrin_db.sql.gz | head -1 | xargs cat' > prod.sql.gz
   gunzip -c prod.sql.gz | docker exec -i veldrin-db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
   docker compose run --rm wpcli search-replace https://veldrin.store http://localhost:8000 --all-tables
   ```
   ⚠️ A prod dump contains real customer data: keep it local, never commit it (`*.sql*` is gitignored),
   and keep `DISABLE_WP_CRON` on (see `.env.example`) so the local copy never sends order emails or
   runs scheduled WooCommerce jobs.

4. **Theme assets (Gulp + LiveReload):**
   ```bash
   cd src/wp-content/themes/veldrin
   pnpm install
   pnpm run watch
   ```

5. **Access:** http://localhost:8000 (DB on `127.0.0.1:3306` with the credentials from your `.env`).

## 🏭 Production (VPS)

- Lives in `/srv/veldrin-store` with `docker-compose.override.yml -> docker-compose.prod.yml`.
- Traefik (external network `traefik-public`) serves `veldrin.store` over HTTPS; no host ports are published.
- Deploy code changes: `git pull` (theme/plugin files are bind-mounted, no restart needed), then
  build theme assets if they changed: `cd src/wp-content/themes/veldrin && pnpm install && pnpm run build`.
- Config changes: `docker compose up -d` (this recreates changed containers — plan for downtime).
- `.env` must set `WORDPRESS_DEBUG=false` and `WP_ENVIRONMENT_TYPE=production`.

## 🔧 Configuration

### Environment Variables (.env)

Create a `.env` file from `.env.example`:

```bash
# Database
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_DATABASE=your_database_name
MYSQL_USER=your_database_user
MYSQL_PASSWORD=your_secure_db_password

# WordPress Database Connection
WORDPRESS_DB_HOST=db
WORDPRESS_DB_USER=your_database_user
WORDPRESS_DB_PASSWORD=your_secure_db_password
WORDPRESS_DB_NAME=your_database_name

# WordPress Debug Settings
WORDPRESS_DEBUG=true              # Set to false in production
WORDPRESS_DEBUG_LOG=true          # Set to false in production
WORDPRESS_DEBUG_DISPLAY=true      # Set to false in production

# Environment Type
WP_ENVIRONMENT_TYPE=development   # Options: development, staging, production

# Security Keys (generate new ones for production)
WORDPRESS_AUTH_KEY=generate_unique_key_here
WORDPRESS_SECURE_AUTH_KEY=generate_unique_key_here
WORDPRESS_LOGGED_IN_KEY=generate_unique_key_here
WORDPRESS_NONCE_KEY=generate_unique_key_here
WORDPRESS_AUTH_SALT=generate_unique_salt_here
WORDPRESS_SECURE_AUTH_SALT=generate_unique_salt_here
WORDPRESS_LOGGED_IN_SALT=generate_unique_salt_here
WORDPRESS_NONCE_SALT=generate_unique_salt_here
```

**Note:** `wp-config.php` has no fallback keys; all 8 keys/salts must be set in `.env` (generate them with `curl -s https://api.wordpress.org/secret-key/1.1/salt/`).

## 📊 Optimization Features

- ✅ **Image Optimization**: Reduced uploads from 55,000+ to 12,000+ files
- ✅ **Custom Image Sizes**: Only necessary sizes generated
- ✅ **Database Optimization**: Clean and optimized structure
- ✅ **Production Ready**: Secure configuration for deployment

## 🛠️ Development Commands

### Docker Commands
```bash
# Start development environment
docker compose up -d

# Stop services
docker compose down

# View logs (all services)
docker compose logs -f

# View logs (specific service)
docker compose logs -f wordpress
docker compose logs -f db

# Access database (container name: veldrin-db)
docker exec -it veldrin-db mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]

# Backup database
docker exec veldrin-db mysqldump -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] > backup_$(date +%Y%m%d).sql

# Restore database
docker exec -i veldrin-db mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < backup.sql

# Restart containers
docker compose restart

# Check container status
docker ps --filter "name=veldrin"
```

### Theme Development (Gulp + LiveReload)

- Styles: `sass/main.scss` compiles to `css/main.min.css` with sourcemaps.
- Components are composed via `sass/components/_index.scss` and imported in `sass/main.scss`.
- Scripts: entry `js/index.js` is bundled/minified to `js/main.min.js` via esbuild.
- LiveReload: When `WORDPRESS_DEBUG=true`, the theme enqueues `http://localhost:35729/livereload.js` and auto-reloads on changes.

Commands:
```bash
cd src/wp-content/themes/veldrin
pnpm install
pnpm run watch
```

Troubleshooting:
- Port in use (35729):
  ```bash
  lsof -n -i :35729 | awk 'NR>1 {print $2}' | xargs -r kill -9
  pnpm run watch
  ```
- No auto-reload:
  - Confirm `.env` has `WORDPRESS_DEBUG=true` and containers are up.
  - Ensure the watcher is running and recompiling on file changes.
  - Hard refresh the browser; check that `livereload.js` loads (Network tab).
  - Disable extensions that might block LiveReload/websockets.

## 🔒 Security Notes

- Change default passwords in production
- Use strong security keys
- Enable HTTPS in production
- Regular backups recommended
- Keep WordPress and plugins updated

## 💾 Backups

The VPS takes a nightly DB dump + file snapshot to `/var/backups/vps` (7 daily / 4 weekly / 6 monthly).

## 📞 Support

For issues or questions, please check the documentation or contact the development team.