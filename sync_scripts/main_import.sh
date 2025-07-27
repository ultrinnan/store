#!/bin/bash
# Main Import Script for Bearpaw Products
# Orchestrates the entire import process

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TMP_DIR="$(cd "$SCRIPT_DIR/../tmp" && pwd)"
CURRENT_DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${TMP_DIR}/import_log_${CURRENT_DATE}.log"

# Function to log messages
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$LOG_FILE"
}

log_info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO:${NC} $1" | tee -a "$LOG_FILE"
}

# Function to check if script exists and is executable
check_script() {
    local script="$1"
    if [[ ! -f "$script" ]]; then
        log_error "Script not found: $script"
        return 1
    fi
    if [[ ! -x "$script" ]]; then
        log_error "Script not executable: $script"
        return 1
    fi
    return 0
}

       # Function to run a script and check its exit status
       run_script() {
           local script="$1"
           local description="$2"
           
           log "Starting: $description"
           log_info "Running: $script"
           
           if check_script "$script"; then
               if "$script"; then
                   log "✅ Completed: $description"
               else
                   log_error "❌ Failed: $description"
                   return 1
               fi
           else
               return 1
           fi
       }

       # Function to cleanup old files at the end of the process
       cleanup_final() {
           log_info "Performing final cleanup..."
           
           # Clean old product files (keep 3 latest)
           local dealer_files=($(ls -t "${TMP_DIR}"/dealers_products_*.json 2>/dev/null | grep -v latest))
           if [[ ${#dealer_files[@]} -gt 3 ]]; then
               local files_to_remove=("${dealer_files[@]:3}")
               for file in "${files_to_remove[@]}"; do
                   rm -f "$file"
                   log_info "Removed old dealer file: $(basename "$file")"
               done
           fi
           
           local retail_files=($(ls -t "${TMP_DIR}"/retail_products_*.json 2>/dev/null | grep -v latest))
           if [[ ${#retail_files[@]} -gt 3 ]]; then
               local files_to_remove=("${retail_files[@]:3}")
               for file in "${files_to_remove[@]}"; do
                   rm -f "$file"
                   log_info "Removed old retail file: $(basename "$file")"
               done
           fi
           
           # Clean old log files (keep 1 latest)
           local log_files=($(ls -t "${TMP_DIR}"/import_log_*.log 2>/dev/null))
           if [[ ${#log_files[@]} -gt 1 ]]; then
               local files_to_remove=("${log_files[@]:1}")
               for file in "${files_to_remove[@]}"; do
                   rm -f "$file"
                   log_info "Removed old log file: $(basename "$file")"
               done
           fi
           
           log "✅ Final cleanup completed"
       }

# Main function
main() {
    log "🚀 Starting Bearpaw Products Import Process"
    log_info "Date: $CURRENT_DATE"
    log_info "Working directory: $SCRIPT_DIR"
    log_info "Temporary directory: $TMP_DIR"
    log_info "Log file: $LOG_FILE"
    
    # Create tmp directory if it doesn't exist
    mkdir -p "$TMP_DIR"
    
    # Change to script directory
    cd "$SCRIPT_DIR"
    
               # Step 1: Fetch fresh product data
           log_info "Step 1: Fetching fresh product data..."
           if run_script "./01_fetch_products.sh" "Fetch fresh product data from dealer and retail sites"; then
               log "✅ Step 1 completed successfully"
           else
               log_error "❌ Step 1 failed. Stopping import process."
               exit 1
           fi
           
           # Step 2: Analyze product data structure
           log_info "Step 2: Analyzing product data structure..."
           if run_script "./02_analyze_products.sh" "Analyze product data structure"; then
               log "✅ Step 2 completed successfully"
           else
               log_error "❌ Step 2 failed. Stopping import process."
               exit 1
           fi
    
    # TODO: Add more steps as we create them
    # Step 2: Analyze product data
    # if run_script "./02_analyze_products.sh" "Analyze product data structure"; then
    #     log "✅ Step 2 completed successfully"
    # else
    #     log_error "❌ Step 2 failed. Stopping import process."
    #     exit 1
    # fi
    
    # Step 3: Generate import CSV
    # if run_script "./03_generate_import.sh" "Generate WooCommerce import CSV"; then
    #     log "✅ Step 3 completed successfully"
    # else
    #     log_error "❌ Step 3 failed. Stopping import process."
    #     exit 1
    # fi
    
    # Step 4: Import to WooCommerce
    # if run_script "./04_import_to_woo.sh" "Import products to WooCommerce"; then
    #     log "✅ Step 4 completed successfully"
    # else
    #     log_error "❌ Step 4 failed. Stopping import process."
    #     exit 1
               # fi
           
           # Final cleanup
           cleanup_final
           
           log "🎉 Import process completed successfully!"
           log_info "Check log file for details: $LOG_FILE"
}

# Run main function
main "$@" 