# Debug Mode Management Guide

## Overview

This guide explains how to manage WordPress debug modes for different environments in your Veldrin store project.

## Current Configuration

### Production Mode (Default)
- **Debug Mode**: Disabled
- **Debug Log**: Disabled  
- **Debug Display**: Disabled
- **File**: `wp-config.php` (lines 109-111)

```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

## Switching to Development Mode

### Option 1: Using Docker Compose Override
```bash
# Start with debug mode enabled
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Or create a development override
cp docker-compose.dev.yml docker-compose.override.yml
docker compose up -d
```

### Option 2: Manual Configuration
Edit `wp-config.php` and change the debug settings:

```php
// Development mode
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );

// Production mode
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
```

## WordPress 6.7+ Compatibility Fixes

### WooCommerce Translation Issues
The project includes fixes for WordPress 6.7+ compatibility issues:

1. **Must-Use Plugin**: `wp-content/mu-plugins/wp67-woocommerce-fix.php`
   - Fixes translation loading for WooCommerce Services and Payments
   - Suppresses specific WordPress 6.7+ notices

2. **Theme Compatibility**: `wp-content/themes/veldrin/functions.php`
   - Additional compatibility layer in the theme

### Common Issues Resolved
- ✅ Translation loading warnings for WooCommerce plugins
- ✅ Header modification warnings
- ✅ Early plugin loading issues

## Troubleshooting

### If You Still See Warnings
1. **Check if debug mode is enabled**:
   ```bash
   curl -s http://localhost:8000/wp-admin/ | grep -i "notice\|warning"
   ```

2. **Restart containers**:
   ```bash
   docker compose restart
   ```

3. **Check WordPress logs**:
   ```bash
   docker compose logs wordpress
   ```

### Development Workflow
```bash
# Start development with debug mode
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Make changes and test
cd wp-content/themes/veldrin && npx gulp

# Switch back to production mode
docker compose -f docker-compose.yml up -d
```

## Best Practices

### For Development
- Enable debug mode to catch issues early
- Use debug log for detailed error tracking
- Monitor logs regularly

### For Production
- Always disable debug mode
- Disable debug display to prevent information leakage
- Keep debug logs disabled unless troubleshooting

### For Testing
- Use production mode for final testing
- Test both debug and production configurations
- Verify no warnings appear in production mode

## Files Modified for WordPress 6.7+ Compatibility

1. **wp-config.php**: Debug mode configuration
2. **wp-content/mu-plugins/wp67-woocommerce-fix.php**: WooCommerce compatibility fixes
3. **wp-content/themes/veldrin/functions.php**: Theme-level compatibility
4. **docker-compose.dev.yml**: Development environment configuration

## Quick Commands

```bash
# Production mode (no debug)
docker compose up -d

# Development mode (with debug)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Check current mode
curl -s http://localhost:8000/wp-admin/ | grep -i "debug"

# View logs
docker compose logs wordpress
``` 