# Development Workflow Guide

## Daily Development Workflow

### Starting Your Day
```bash
# 1. Start the development environment
docker compose up -d

# 2. Start the theme build process (in a new terminal)
cd wp-content/themes/veldrin
npx gulp  # This watches for changes and auto-compiles
```

### Making Changes

#### Frontend Changes (CSS/SCSS)
1. Edit files in `wp-content/themes/veldrin/sass/`
2. Changes are automatically compiled to `css/main.min.css`
3. Refresh your browser to see changes

#### JavaScript Changes
1. Edit files in `wp-content/themes/veldrin/js/`
2. Changes are automatically compiled to `js/main.min.js`
3. Refresh your browser to see changes

#### PHP Changes
1. Edit PHP files in the theme directory
2. Changes are immediately available (no compilation needed)
3. Refresh your browser to see changes

### Testing Your Changes

#### Quick Tests
```bash
# Run the test script
./test-local-setup.sh

# Check for PHP errors
find wp-content/themes/veldrin -name "*.php" -exec php -l {} \;

# Check SASS compilation
cd wp-content/themes/veldrin && npx gulp development
```

#### Browser Testing
- **Desktop**: http://localhost:8000
- **Mobile**: Use browser dev tools or test on actual device
- **Cross-browser**: Test in Chrome, Firefox, Safari, Edge

### Database Management

#### Export/Import
```bash
# Export database
docker compose exec db mysqldump -u store -pstore store > backup.sql

# Import database
docker compose exec -T db mysql -u store -pstore store < backup.sql
```

#### Reset Database
```bash
# Remove all data and start fresh
docker compose down
docker volume rm store_store_db_data
docker compose up -d
```

### Common Development Tasks

#### Adding New Pages
1. Create new PHP template file in theme root
2. Add page in WordPress admin
3. Style with SCSS in `sass/components/`

#### Adding New Components
1. Create SCSS file in `sass/components/`
2. Import in `sass/components/_index.scss`
3. Add JavaScript if needed in `js/`
4. Create PHP template in `partials/`

#### Updating Dependencies
```bash
cd wp-content/themes/veldrin
pnpm update
npx gulp development  # Rebuild assets
```

### Debugging

#### WordPress Debug
- Debug mode is enabled by default
- Check bottom-right corner for debug indicator
- Logs: `docker compose logs wordpress`

#### Database Debug
```bash
# Access database directly
docker compose exec db mysql -u store -pstore store

# View database logs
docker compose logs db
```

#### Asset Debug
```bash
# Check if assets are loading
curl -I http://localhost:8000/wp-content/themes/veldrin/css/main.min.css

# Rebuild assets
cd wp-content/themes/veldrin && npx gulp development
```

### Performance Testing

#### Local Performance
```bash
# Check page load time
curl -w "@curl-format.txt" -o /dev/null -s http://localhost:8000

# Check asset sizes
ls -lh wp-content/themes/veldrin/css/main.min.css
ls -lh wp-content/themes/veldrin/js/main.min.js
```

#### Browser Performance
- Use Chrome DevTools Performance tab
- Check Network tab for asset loading
- Use Lighthouse for performance audit

### Git Workflow (When Ready)

#### Initial Setup
```bash
git init
git add .
git commit -m "Initial commit"
```

#### Daily Commits
```bash
git add .
git commit -m "Description of changes"
```

#### Before Pushing
```bash
# Test everything works
./test-local-setup.sh

# Build for production
cd wp-content/themes/veldrin && npx gulp development

# Commit and push
git add .
git commit -m "Ready for production"
git push
```

## Troubleshooting

### Common Issues

#### Assets Not Loading
```bash
# Rebuild assets
cd wp-content/themes/veldrin && npx gulp development

# Check file permissions
ls -la css/ js/
```

#### Database Connection Issues
```bash
# Restart containers
docker compose restart

# Check database status
docker compose exec db mysql -u store -pstore -e "SHOW DATABASES;"
```

#### SASS Compilation Errors
```bash
# Check for undefined variables
cd wp-content/themes/veldrin && npx gulp development

# Look for error messages in output
```

#### WordPress Not Loading
```bash
# Check container status
docker compose ps

# View logs
docker compose logs wordpress

# Restart everything
docker compose down && docker compose up -d
```

### Getting Help

1. **Check logs**: `docker compose logs [service]`
2. **Run test script**: `./test-local-setup.sh`
3. **Check documentation**: `LOCAL_DEVELOPMENT.md`
4. **Google the error message**
5. **Ask for help with specific error details** 