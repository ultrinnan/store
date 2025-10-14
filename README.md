# Veldrin Store

WordPress-based e-commerce store with optimized image handling and production-ready Docker configuration.

## 🏗️ Project Structure

```
store/
├── src/                    # WordPress files (for production)
│   ├── wp-admin/
│   ├── wp-content/
│   ├── wp-includes/
│   └── wp-*.php
├── docker-compose.yml      # Development environment
├── docker-compose.dev.yml  # Legacy development config
├── docker-compose.prod.yml # Production environment
├── .env                    # Environment variables (create from .env.example)
└── README.md
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

1. **Prepare environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your production credentials
   ```

2. **Generate WordPress security keys:**
   ```bash
   curl -s https://api.wordpress.org/secret-key/1.1/salt/
   ```

3. **Deploy with production config:**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

## 🔧 Configuration

### Environment Variables (.env)

```bash
# Database
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_DATABASE=veldrin
MYSQL_USER=veldrin
MYSQL_PASSWORD=your_secure_db_password

# WordPress
WORDPRESS_DB_HOST=db
WORDPRESS_DB_USER=veldrin
WORDPRESS_DB_PASSWORD=your_secure_db_password
WORDPRESS_DB_NAME=veldrin
WORDPRESS_DEBUG=false
WORDPRESS_DEBUG_LOG=false

# Security Keys (generate new ones)
WORDPRESS_AUTH_KEY=your_auth_key_here
WORDPRESS_SECURE_AUTH_KEY=your_secure_auth_key_here
# ... (other security keys)
```

## 📊 Optimization Features

- ✅ **Image Optimization**: Reduced uploads from 55,000+ to 12,000+ files
- ✅ **Custom Image Sizes**: Only necessary sizes generated
- ✅ **Database Optimization**: Clean and optimized structure
- ✅ **Production Ready**: Secure configuration for deployment

## 🛠️ Development Commands

```bash
# Start development environment
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Access database
docker exec -it veldrin-db-dev mysql -u store -pstore store

# Backup database
docker exec veldrin-db-dev mysqldump -u store -pstore store > backup.sql
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