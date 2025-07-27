# Veldrin Craftworks Store

A modern WordPress e-commerce store specializing in leather goods, archery equipment, and tools. Built with a custom WordPress theme and WooCommerce integration.

## 🏗️ System Overview

### Technology Stack
- **Backend**: WordPress 6.7+ with WooCommerce
- **Frontend**: Custom WordPress theme (Veldrin)
- **Styling**: SCSS with Gulp build system
- **Database**: MySQL 8.0
- **Development**: Docker containerization
- **Package Manager**: pnpm

### Key Features
- 🛍️ **E-commerce**: Full WooCommerce integration
- 🎨 **Custom Theme**: Modern, responsive design
- 🔧 **Admin Customization**: Custom dashboard and settings
- 📱 **Mobile-First**: Responsive design for all devices
- 🚀 **Performance**: Optimized assets and caching
- 🔒 **Security**: WordPress security hardening

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- Node.js 18+ and pnpm
- Git

### 1. Clone and Setup
```bash
# Clone the repository
git clone <repository-url>
cd store

# Start the development environment
docker compose up -d
```

### 2. Install Theme Dependencies
```bash
# Navigate to theme directory
cd wp-content/themes/veldrin

# Install dependencies
pnpm install

# Build assets
npx gulp development
```

### 3. Access Your Site
- **Frontend**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/wp-admin
- **Database**: localhost:3306 (user: `store`, password: `store`)

## 🛠️ Development Workflow

### Starting Development
```bash
# 1. Start containers
docker compose up -d

# 2. Start theme build process (in new terminal)
cd wp-content/themes/veldrin
npx gulp  # Watches for changes and auto-compiles
```

### Making Changes
- **SCSS/CSS**: Edit files in `sass/` → auto-compiles to `css/`
- **JavaScript**: Edit files in `js/` → auto-bundles to `js/main.min.js`
- **PHP**: Edit theme files → changes immediately visible
- **Refresh browser** to see changes

### Testing Your Setup
```bash
# Run automated tests
./scripts/test-local-setup.sh

# Check for PHP errors
find wp-content/themes/veldrin -name "*.php" -exec php -l {} \;

# Verify SASS compilation
cd wp-content/themes/veldrin && npx gulp development
```

## 📁 Project Structure

```
store/
├── docker-compose.yml          # Docker configuration
├── sync_scripts/              # Product import automation scripts
│   ├── main_import.sh         # Main orchestration script
│   ├── 01_fetch_products.sh   # Step 1: Fetch product data
│   ├── 02_analyze_products.sh # Step 2: Analyze product structure
│   ├── 03_prepare_categories_attributes.php # Step 3: Create categories & brands ✅
│   ├── 04_split_products.php  # Step 4: Split products (simple/variable) ✅
│   ├── 05_import_simple_products.php # Step 5: Import simple products (TODO)
│   ├── 06_import_variable_products.php # Step 6: Import variable products (TODO)
│   └── 07_import_all_products.php # Step 7: Complete import orchestration (TODO)
├── wp-content/
│   └── themes/
│       └── veldrin/           # Custom WordPress theme
│           ├── sass/          # SCSS source files
│           │   ├── base/      # Base styles
│           │   ├── components/ # Component styles
│           │   └── helpers/   # Variables and mixins
│           ├── css/           # Compiled CSS (auto-generated)
│           ├── js/            # JavaScript files
│           ├── img/           # Images and icons
│           ├── partials/      # PHP template parts
│           ├── admin/         # Admin customizations
│           └── functions.php  # Theme functions
├── scripts/                   # Development scripts and tools
│   ├── import-production.sh   # Production database import script
│   ├── quick-import.sh       # Quick import one-liner
│   ├── test-local-setup.sh   # Automated testing script
│   ├── update-urls.sql       # URL update script
│   ├── update-urls-final.sql # Final URL cleanup
│   ├── veldrin_store.sql     # Production database dump
│   ├── LOCAL_DEVELOPMENT.md  # Detailed setup guide
│   ├── DEVELOPMENT_WORKFLOW.md # Development workflow
│   ├── DATABASE_IMPORT_GUIDE.md # Database import guide
│   ├── DEBUG_MODE_GUIDE.md   # Debug mode management
│   └── IMPORT_SUMMARY.md     # Quick import reference
├── sync_scripts/              # Production-ready import scripts
│   ├── main_import.sh        # Main orchestration script
│   ├── 01_fetch_products.sh  # Step 1: Fetch fresh product data
│   ├── 02_analyze_products.sh # Step 2: Analyze product structure
│   ├── 03_prepare_categories_attributes.sh # Step 3: Prepare categories, attributes, brands
│   └── README.md             # Import scripts documentation
└── tmp/                      # Temporary files (protected)
```

## 🎨 Theme Customization

### Color Scheme
Edit `sass/helpers/_variables.scss`:
```scss
$white: #fdf6e3;
$black: #2e1f17;
$gold: #ffcc00;
$vine: #8b0000;
$yellow: #ffd700;  // Ukrainian flag
$blue: #0057b8;    // Ukrainian flag
```

### Adding New Components
1. Create SCSS file in `sass/components/`
2. Import in `sass/components/_index.scss`
3. Add JavaScript in `js/` directory
4. Create PHP templates in `partials/`

### Admin Customizations
- **Theme Settings**: Custom admin panel for site configuration
- **Social Media**: Manage social media links
- **Dashboard Widgets**: Custom admin dashboard widgets

## 🗄️ Database Management

### Fresh Installation
1. Visit http://localhost:8000
2. Follow WordPress installation wizard
3. Use database settings:
   - Database Name: `store`
   - Username: `store`
   - Password: `store`
   - Database Host: `db`

### Import Existing Database
```bash
# Export from live site using WP Migrate DB plugin
# Import using database tool (phpMyAdmin, TablePlus, etc.)
```

### Database Operations
```bash
# Quick production import (recommended)
./scripts/import-production.sh

# Or use the quick one-liner
./scripts/quick-import.sh

# Export database
docker compose exec db mysqldump -u store -pstore store > backup.sql

# Import database
docker compose exec -T db mysql -u store -pstore store < backup.sql

# Reset database (fresh start)
docker compose down
docker volume rm store_store_db_data
docker compose up -d
```

### Product Import & Management
```bash
# 🚀 RECOMMENDED: Run complete import process
./sync_scripts/main_import.sh

# Or run steps individually:
./sync_scripts/01_fetch_products.sh                 # Step 1: Fetch fresh data
./sync_scripts/02_analyze_products.sh               # Step 2: Analyze product structure

# View comprehensive import solution guide
cat scripts/COMPREHENSIVE_IMPORT_SOLUTION.md
```

## 🔧 Useful Commands

### Docker Operations
```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# View logs
docker compose logs wordpress
docker compose logs db

# Restart containers
docker compose restart

# Rebuild everything
docker compose down --rmi all
docker compose up -d
```

### Theme Development
```bash
# Install dependencies
cd wp-content/themes/veldrin && pnpm install

# Build assets
npx gulp development

# Watch for changes
npx gulp

# Update dependencies
pnpm update
```

### Database Access
```bash
# Access MySQL directly
docker compose exec db mysql -u store -pstore store

# Backup database
docker compose exec db mysqldump -u store -pstore store > backup.sql
```

## 🐛 Troubleshooting

### Common Issues

#### Assets Not Loading
```bash
# Rebuild assets
cd wp-content/themes/veldrin && npx gulp development

# Check file permissions
ls -la css/ js/
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

#### Database Connection Issues
```bash
# Restart containers
docker compose restart

# Check database status
docker compose exec db mysql -u store -pstore -e "SHOW DATABASES;"
```

### Debug Mode
- Debug mode is enabled by default for development
- Check bottom-right corner for debug indicator
- Disable for production by setting `WP_DEBUG: false` in docker-compose.yml

## 🚀 Production Deployment

### Pre-deployment Checklist
- [ ] Disable debug mode
- [ ] Update database credentials
- [ ] Set up SSL certificate
- [ ] Configure caching
- [ ] Set up backups
- [ ] Test all functionality

### Building for Production
```bash
# Build optimized assets
cd wp-content/themes/veldrin && npx gulp development

# Test locally
docker compose up -d
```

## 📚 Additional Resources

- [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md) - Detailed local development guide
- [DEVELOPMENT_WORKFLOW.md](DEVELOPMENT_WORKFLOW.md) - Daily development workflow
- [WordPress Documentation](https://wordpress.org/documentation/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)

## 🤝 Contributing

1. Follow the development workflow in `DEVELOPMENT_WORKFLOW.md`
2. Test your changes with `./test-local-setup.sh`
3. Build assets before committing: `npx gulp development`
4. Follow WordPress coding standards

## 📄 License

This project is licensed under the GNU General Public License v2 or later.

---

**Built with ❤️ by [FEDIRKO.PRO](https://fedirko.pro)**