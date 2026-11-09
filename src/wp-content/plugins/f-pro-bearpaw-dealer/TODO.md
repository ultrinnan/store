# F-PRO Bearpaw Dealer - Development Roadmap

## ✅ Completed Phases

### Phase 1: Quick Wins (Code Quality & Maintainability)
- [x] Extract `PricingCalculator` domain class with 13 unit tests
- [x] Move inline CSS/JS to separate asset files (`assets/css/admin.css`, `assets/js/admin.js`)
- [x] Remove security risk: `compare-prices.php` (direct `wp-load.php` inclusion)
- [x] Add PSR-4 autoloader for `src/` directory
- [x] Add i18n support (`__()`, `esc_html_e()` calls)
- [x] Add Composer + PHPUnit for testing

### Phase 2: API Layer Extraction
- [x] `BearpawClient` - Robust HTTP client for B2B/Retail APIs
  - Automatic 304 handling with no-cache retry
  - Configurable timeout, pagination, rate limiting
  - Connection testing via `test_connection()`
- [x] `CurlParser` - Tokenization-based cURL parser (replaces fragile regex)
  - Handles single/double quotes, escaped quotes
  - Supports `-b`, `--cookie`, `-H`, `--header` flags
  - 16 tests covering Chrome DevTools formats
- [x] Updated main plugin to use new classes with fallback

### Phase 3a: Product Matching & Indexing
- [x] `ProductVariant` - Value object for Bearpaw API variant data
- [x] `WooCommerceProduct` - Value object for WC product with Bearpaw meta
- [x] `SkuPriceIndex` - Combined price data per SKU
- [x] `ProductMatcher` - Main indexer with statistics & query methods
  - `get_missing_products()` - B2B products not in WC
  - `get_updatable_products()` - WC products with both B2B & Retail prices
  - `get_sample_products()` - For admin UI display
- [x] Interface-based design (`WooCommerceProductInterface`) for testability

### Phase 3b: Download Manager & Progress Tracker
- [x] `FileStorage` - Safe temp file handling
- [x] `DownloadProgress` - Value object for progress state
- [x] `ProgressTracker` - Persists progress via transients + temp files
- [x] `DownloadManager` - Orchestrates chunked downloads (5 pages/chunk)
- [x] Resume capability, auto-finalization, progress callbacks

---

## 📋 Pending Phases (Priority Order)

### Phase 4: Background Processing (High Priority)
- [ ] **Integrate Action Scheduler** (WooCommerce's background job queue)
  - Move price updates to scheduled actions
  - Avoid PHP timeouts on large catalogs (>500 products)
  - Add progress tracking UI for background jobs
- [ ] **Batch price updates** with configurable batch size
- [ ] **Retry logic** for failed price updates

### Phase 5: Developer Experience (Medium Priority)
- [ ] **WP-CLI command** for headless sync
  - `wp f-bearpaw sync --source=b2b`
  - `wp f-bearpaw prices update --dry-run`
- [ ] **Structured logging** (Monolog compatible)
- [ ] **Health check endpoint** for monitoring

### Phase 6: CI/CD & Quality Gates (Medium Priority)
- [ ] **GitHub Actions workflow**
  - Run PHPUnit tests on PR
  - PHPStan static analysis (level 5+)
  - Code style checks (PHPCS with WordPress rules)
- [ ] **PHPStan configuration** with baseline
- [ ] **Dependabot** for dependency updates

### Phase 7: Plugin Architecture (Low Priority)
- [ ] **Settings validation schema** (JSON Schema based)
- [ ] **Multi-dealer support** (extensible vendor system)
- [ ] **Webhook support** for real-time Bearpaw updates
- [ ] **Import/Export** for backup/migration

### Phase 8: Public Release Prep (When Ready)
- [ ] **WordPress.org submission checklist**
  - `readme.txt` compliance
  - Proper license headers
  - Screenshots & banner
- [ ] **Internationalization (i18n)**
  - Generate `.pot` file
  - Translate admin strings
- [ ] **Documentation** (README, CHANGELOG, UPGRADE guide)

---

## 🧪 Current Test Status

```
PHPUnit 9.6.38
......................................................  54 / 54 (100%)
OK (54 tests, 149 assertions)
```

### Test Coverage by Module:
| Module | Tests | Assertions |
|--------|-------|------------|
| PricingCalculator | 13 | 23 |
| CurlParser | 16 | 40 |
| ProductMatcher | 9 | 38 |
| Sync (ProgressTracker, DownloadManager) | 16 | 48 |
| **Total** | **54** | **149** |

---

## 🏗️ Architecture Summary

```
f-pro-bearpaw-dealer/
├── f-pro-bearpaw-dealer.php          # Main plugin (bootstrap + admin UI)
├── composer.json                      # Dependencies
├── assets/
│   ├── css/admin.css                 # Admin styles (~400 lines)
│   └── js/admin.js                   # Admin JavaScript (~400 lines)
├── src/
│   ├── Domain/
│   │   ├── PricingCalculator.php     # Pure price logic (testable)
│   │   └── ProductMatcher.php        # SKU indexing & matching
│   ├── API/
│   │   ├── BearpawClient.php         # HTTP client abstraction
│   │   └── CurlParser.php            # Robust cURL parsing
│   ├── Sync/
│   │   ├── ProgressTracker.php       # Progress state management
│   │   └── DownloadManager.php       # Chunked download orchestration
│   └── Utilities/
│       └── FileStorage.php           # Temp file operations
└── tests/
    ├── bootstrap.php                 # Test bootstrap with WP mocks
    └── Unit/
        ├── PricingCalculatorTest.php
        ├── CurlParserTest.php
        ├── ProductMatcherTest.php
        └── SyncTest.php
```

---

## 🚀 Quick Commands

```bash
# Run all tests
./vendor/bin/phpunit --bootstrap src/wp-content/plugins/f-pro-bearpaw-dealer/tests/bootstrap.php src/wp-content/plugins/f-pro-bearpaw-dealer/tests/Unit/

# Run specific test file
./vendor/bin/phpunit --bootstrap src/wp-content/plugins/f-pro-bearpaw-dealer/tests/bootstrap.php src/wp-content/plugins/f-pro-bearpaw-dealer/tests/Unit/PricingCalculatorTest.php

# Check syntax
php -l src/wp-content/plugins/f-pro-bearpaw-dealer/f-pro-bearpaw-dealer.php
php -l src/wp-content/plugins/f-pro-bearpaw-dealer/src/Domain/PricingCalculator.php
```

---

## 📝 Notes for Next Session

1. **Action Scheduler Integration**: Check if `ActionScheduler` class exists (WooCommerce 4.0+), then refactor `update_products_prices()` to use `ActionScheduler::add_action()` for batch processing.

2. **WP-CLI Command**: Extend `WP_CLI_Command` class, register via `WP_CLI::add_command()`.

3. **PHPStan**: Start with level 3, create baseline, gradually increase to level 5.

4. **Admin UI**: Consider React/Vue for Step 2 progress UI instead of vanilla jQuery.