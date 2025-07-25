# Local Development Guide - Veldrin Store

## Quick Start

### 1. Start the Development Environment
```bash
# Start Docker containers
docker compose up -d

# Install theme dependencies
cd wp-content/themes/veldrin
pnpm install

# Build assets
npx gulp development

# Start development server with file watching
npx gulp
```

### 2. Access Your Site
- **Frontend**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/wp-admin
- **Database**: localhost:3306 (user: store, password: store, database: store)

### 3. Development Workflow

#### Frontend Development
```bash
# Watch for changes and auto-compile
npx gulp

# Build for production
npx gulp development
```

#### File Structure
```
wp-content/themes/veldrin/
├── sass/           # Source SCSS files
├── css/            # Compiled CSS (auto-generated)
├── js/             # JavaScript files
├── img/            # Images and icons
└── partials/       # PHP template parts
```

## Database Setup

### Option 1: Fresh Install
1. Visit http://localhost:8000
2. Follow WordPress installation wizard
3. Use these database settings:
   - Database Name: `store`
   - Username: `store`
   - Password: `store`
   - Database Host: `db`

### Option 2: Import Existing Database
```bash
# Export from live site using WP Migrate DB plugin
# Then import using your database tool (e.g., phpMyAdmin, TablePlus)
```

## Theme Development

### Activating the Theme
1. Go to http://localhost:8000/wp-admin
2. Navigate to Appearance > Themes
3. Activate "Veldrin store" theme

### Customization Points
- **Colors**: Edit `sass/helpers/_variables.scss`
- **Layout**: Edit `sass/components/` files
- **Admin Settings**: Edit `partials/admin/` files
- **Templates**: Edit PHP files in theme root

### Adding New Features
1. Create SCSS file in `sass/components/`
2. Import in `sass/components/_index.scss`
3. Add JavaScript in `js/` directory
4. Create PHP templates in `partials/`

## Troubleshooting

### Common Issues

#### SASS Compilation Errors
```bash
# Check for undefined variables
npx gulp development
```

#### Docker Issues
```bash
# Restart containers
docker compose down
docker compose up -d

# Rebuild everything
docker compose down --rmi all
docker compose up -d
```

#### Permission Issues
```bash
# Fix file permissions
sudo chown -R $USER:$USER .
```

### Debug Mode
Debug mode is enabled by default. Check the bottom-right corner for debug indicator.

## Production Deployment

### Building for Production
```bash
# Build optimized assets
npx gulp development

# Test locally
docker compose up -d
```

### Security Checklist
- [ ] Disable debug mode
- [ ] Update database credentials
- [ ] Set up SSL certificate
- [ ] Configure caching
- [ ] Set up backups

## Useful Commands

```bash
# View logs
docker compose logs wordpress
docker compose logs db

# Access database
docker compose exec db mysql -u store -p store

# Access WordPress container
docker compose exec wordpress bash

# Update dependencies
cd wp-content/themes/veldrin
pnpm update
```

## Next Steps

1. **Set up version control** (Git)
2. **Add automated testing**
3. **Implement CI/CD pipeline**
4. **Set up staging environment**
5. **Configure monitoring and analytics** 