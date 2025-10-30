# Veldrin Store

WordPress-based e-commerce store with optimized image handling and production-ready Docker configuration.

## 🏗️ Project Structure

```
store/
├── src/                    # WordPress installation root
│   ├── wp-admin/           # WordPress admin (gitignored)
│   ├── wp-content/         # Content directory
│   │   └── themes/
│   │       └── veldrin/    # Custom Veldrin theme (tracked in git)
│   ├── wp-includes/        # WordPress core (gitignored)
│   ├── wp-config.php       # WordPress config (gitignored, auto-generated)
│   └── wp-*.php            # WordPress core files (gitignored)
├── docker-compose.yml      # Development environment (current)
├── docker-compose.override.yml # Local Docker overrides
├── .env                    # Environment variables (create from .env.example)
├── .env.example            # Environment template
├── README.md               # Project documentation
└── IMPROVEMENTS.md         # Technical improvement roadmap
```

## 🚀 Quick Start (Development)

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd store
   ```

2. **Create and configure .env (enable WordPress debug for LiveReload):**
   - Ensure `WORDPRESS_DEBUG=true` (and optionally `WORDPRESS_DEBUG_LOG=true`)
   ```bash
   # .env
   WORDPRESS_DEBUG=true
   WORDPRESS_DEBUG_LOG=true
   ```

3. **Start development environment:**
   ```bash
   docker-compose up -d
   ```

4. **Install theme deps and start watcher (Gulp + LiveReload):**
   ```bash
   cd src/wp-content/themes/veldrin
   pnpm install
   pnpm run watch
   ```

5. **Access the site:**
   - Website: http://localhost:8000
   - Database: localhost:3306 (user: store, password: store)

## 🏭 Production Deployment

**Note:** A dedicated `docker-compose.prod.yml` file is planned but not yet implemented. Current production deployment uses the main `docker-compose.yml` with production-specific environment variables.

1. **Prepare environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your production credentials
   ```

2. **Generate WordPress security keys:**
   ```bash
   curl -s https://api.wordpress.org/secret-key/1.1/salt/
   # Copy the generated keys to your .env file
   ```

3. **Update WordPress debug settings in .env:**
   ```bash
   WORDPRESS_DEBUG=false
   WORDPRESS_DEBUG_LOG=false
   WORDPRESS_DEBUG_DISPLAY=false
   WP_ENVIRONMENT_TYPE=production
   ```

4. **Build theme assets:**
   ```bash
   cd src/wp-content/themes/veldrin
   pnpm install
   pnpm run build
   ```

5. **Deploy with Docker:**
   ```bash
   docker-compose up -d
   ```

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

**⚠️ IMPORTANT**: The `wp-config.php` currently has hardcoded fallback security keys. These MUST be replaced with unique keys from environment variables before production deployment.

## 📊 Optimization Features

- ✅ **Image Optimization**: Reduced uploads from 55,000+ to 12,000+ files
- ✅ **Custom Image Sizes**: Only necessary sizes generated
- ✅ **Database Optimization**: Clean and optimized structure
- ✅ **Production Ready**: Secure configuration for deployment

## 🛠️ Development Commands

### Docker Commands
```bash
# Start development environment
docker-compose up -d

# Stop services
docker-compose down

# View logs (all services)
docker-compose logs -f

# View logs (specific service)
docker-compose logs -f wordpress
docker-compose logs -f db

# Access database (container name: veldrin-db)
docker exec -it veldrin-db mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME]

# Backup database
docker exec veldrin-db mysqldump -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] > backup_$(date +%Y%m%d).sql

# Restore database
docker exec -i veldrin-db mysql -u [DB_USER] -p[DB_PASSWORD] [DB_NAME] < backup.sql

# Restart containers
docker-compose restart

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

## 📁 File Organization

- **Development**: Files in root directory for easy access
- **Production**: Files in `src/` directory for clean deployment
- **Backups**: Excluded from Git, stored separately
- **Uploads**: Optimized and cleaned regularly

## 🚀 Deployment Checklist

- [ ] Update `.env` with production credentials
- [ ] Generate new WordPress security keys
- [ ] Test with `docker-compose.prod.yml`
- [ ] Configure reverse proxy (nginx/traefik)
- [ ] Set up SSL certificates
- [ ] Configure backups
- [ ] Test all functionality
- [ ] Monitor performance

## 📞 Support

For issues or questions, please check the documentation or contact the development team.