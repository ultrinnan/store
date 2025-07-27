# Sync Scripts Directory

## Overview
This directory contains secure synchronization scripts for the Bearpaw Products import system.

## Security
- **Web Access**: Completely blocked via `.htaccess`
- **SSH Only**: Scripts can only be executed via SSH access
- **Protected**: All files are protected from external access

## Structure
```
sync_scripts/
├── main_import.sh          # Main orchestration script
       ├── 01_fetch_products.sh    # Step 1: Fetch fresh product data
       ├── 02_analyze_products.sh  # Step 2: Analyze product structure
       ├── 03_generate_import.sh   # Step 3: Generate WooCommerce CSV (TODO)
       ├── 04_import_to_woo.sh     # Step 4: Import to WooCommerce (TODO)
└── README.md              # This file
```

## Usage
```bash
# Run complete import process
./main_import.sh

       # Run individual steps
       ./01_fetch_products.sh
       ./02_analyze_products.sh
```

## Temporary Files
All temporary files are stored in the `../tmp/` directory, which is also protected from web access.

## File Management
- **Product files**: Only 3 latest versions are kept for each source (dealer/retail)
- **Log files**: Only 1 latest log file is kept
- **Automatic cleanup**: Old files are automatically removed after each run
- **Symlinks**: `dealers_products_latest.json` and `retail_products_latest.json` always point to the most recent files

## Logs
Log files are created with timestamps in the format: `import_log_YYYYMMDD_HHMMSS.log`

## Dependencies
- `curl` - for fetching data
- `jq` - for JSON processing
- `bash` - for script execution 