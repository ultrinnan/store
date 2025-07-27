#!/bin/bash
# Step 1: Fetch fresh product data from dealer and retail sites
# Downloads complete product lists with pagination support

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
DEALER_URL="https://b2bportal.bearpaw-products.de/en/products.json"
RETAIL_URL="https://bearpaw-products.com/products.json"
DEALER_OUTPUT="${TMP_DIR}/dealers_products_${CURRENT_DATE}.json"
RETAIL_OUTPUT="${TMP_DIR}/retail_products_${CURRENT_DATE}.json"

# Function to log messages
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

log_info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO:${NC} $1"
}

# Function to check if required tools are available
check_dependencies() {
    log_info "Checking dependencies..."
    
    if ! command -v curl &> /dev/null; then
        log_error "curl is not installed"
        exit 1
    fi
    
    if ! command -v jq &> /dev/null; then
        log_error "jq is not installed"
        exit 1
    fi
    
    log "✅ Dependencies check passed"
}

# Function to fetch paginated dealer data
fetch_dealer_products() {
    log_info "Fetching dealer products (English) with pagination..."
    
    local temp_file=$(mktemp)
    local page=1
    local total_products=0
    local has_more=true
    
    # Start with empty products array
    echo '{"products": []}' > "$temp_file"
    
    while [[ "$has_more" == "true" ]]; do
        log_info "Fetching page $page..."
        
        local url="${DEALER_URL}?page=${page}&limit=250"
        local response_file=$(mktemp)
        
        # Fetch page data
        if curl -s "$url" > "$response_file"; then
            # Check if response is valid JSON
            if jq empty "$response_file" 2>/dev/null; then
                local page_products=$(jq '.products | length' "$response_file")
                
                if [[ "$page_products" -gt 0 ]]; then
                    # Merge products from this page
                    jq -s '.[0].products + .[1].products | {products: .}' "$temp_file" "$response_file" > "${temp_file}.tmp"
                    mv "${temp_file}.tmp" "$temp_file"
                    
                    total_products=$((total_products + page_products))
                    log_info "Page $page: $page_products products (Total: $total_products)"
                    page=$((page + 1))
                else
                    log_info "No more products found on page $page"
                    has_more=false
                fi
            else
                log_error "Invalid JSON response on page $page"
                has_more=false
            fi
        else
            log_error "Failed to fetch page $page"
            has_more=false
        fi
        
        rm -f "$response_file"
        
        # Safety check to prevent infinite loop
        if [[ $page -gt 50 ]]; then
            log_warning "Reached maximum page limit (50). Stopping pagination."
            has_more=false
        fi
    done
    
    # Save final result
    mv "$temp_file" "$DEALER_OUTPUT"
    
    # Verify final result
    if [[ -f "$DEALER_OUTPUT" ]]; then
        local final_count=$(jq '.products | length' "$DEALER_OUTPUT")
        log "✅ Dealer products saved: $DEALER_OUTPUT ($final_count products)"
    else
        log_error "Failed to save dealer products"
        exit 1
    fi
}

# Function to fetch retail data (with pagination)
fetch_retail_products() {
    log_info "Fetching retail products (German) with pagination..."
    
    local temp_file=$(mktemp)
    local page=1
    local total_products=0
    local has_more=true
    
    # Start with empty products array
    echo '{"products": []}' > "$temp_file"
    
    while [[ "$has_more" == "true" ]]; do
        log_info "Fetching retail page $page..."
        
        local url="${RETAIL_URL}?page=${page}"
        local response_file=$(mktemp)
        
        # Fetch page data
        if curl -s "$url" > "$response_file"; then
            # Check if response is valid JSON
            if jq empty "$response_file" 2>/dev/null; then
                local page_products=$(jq '.products | length' "$response_file")
                
                if [[ "$page_products" -gt 0 ]]; then
                    # Merge products from this page
                    jq -s '.[0].products + .[1].products | {products: .}' "$temp_file" "$response_file" > "${temp_file}.tmp"
                    mv "${temp_file}.tmp" "$temp_file"
                    
                    total_products=$((total_products + page_products))
                    log_info "Retail page $page: $page_products products (Total: $total_products)"
                    page=$((page + 1))
                else
                    log_info "No more retail products found on page $page"
                    has_more=false
                fi
            else
                log_error "Invalid JSON response from retail site on page $page"
                has_more=false
            fi
        else
            log_error "Failed to fetch retail page $page"
            has_more=false
        fi
        
        rm -f "$response_file"
        
        # Safety check to prevent infinite loop
        if [[ $page -gt 100 ]]; then
            log_warning "Reached maximum retail page limit (100). Stopping pagination."
            has_more=false
        fi
    done
    
    # Save final result
    mv "$temp_file" "$RETAIL_OUTPUT"
    
    # Verify final result
    if [[ -f "$RETAIL_OUTPUT" ]]; then
        local final_count=$(jq '.products | length' "$RETAIL_OUTPUT")
        log "✅ Retail products saved: $RETAIL_OUTPUT ($final_count products)"
    else
        log_error "Failed to save retail products"
        exit 1
    fi
}

# Function to clean old files (keep only 3 latest versions)
cleanup_old_files() {
    log_info "Cleaning up old files (keeping 3 latest versions)..."
    
    # Clean dealer files
    local dealer_files=($(ls -t "${TMP_DIR}"/dealers_products_*.json 2>/dev/null | grep -v latest))
    if [[ ${#dealer_files[@]} -gt 3 ]]; then
        local files_to_remove=("${dealer_files[@]:3}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old dealer file: $(basename "$file")"
        done
        log "✅ Cleaned up dealer files: removed ${#files_to_remove[@]} old files"
    fi
    
    # Clean retail files
    local retail_files=($(ls -t "${TMP_DIR}"/retail_products_*.json 2>/dev/null | grep -v latest))
    if [[ ${#retail_files[@]} -gt 3 ]]; then
        local files_to_remove=("${retail_files[@]:3}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old retail file: $(basename "$file")"
        done
        log "✅ Cleaned up retail files: removed ${#files_to_remove[@]} old files"
    fi
    
    # Clean log files (keep only 1 latest)
    local log_files=($(ls -t "${TMP_DIR}"/import_log_*.log 2>/dev/null))
    if [[ ${#log_files[@]} -gt 1 ]]; then
        local files_to_remove=("${log_files[@]:1}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old log file: $(basename "$file")"
        done
        log "✅ Cleaned up log files: removed ${#files_to_remove[@]} old files"
    fi
}

# Function to create symlinks for easy access
create_symlinks() {
    log_info "Creating symlinks for easy access..."
    
    # Remove old symlinks if they exist
    rm -f "${TMP_DIR}/dealers_products_latest.json" "${TMP_DIR}/retail_products_latest.json"
    
    # Create new symlinks in tmp directory
    ln -sf "$(basename "$DEALER_OUTPUT")" "${TMP_DIR}/dealers_products_latest.json"
    ln -sf "$(basename "$RETAIL_OUTPUT")" "${TMP_DIR}/retail_products_latest.json"
    
    log "✅ Symlinks created: ${TMP_DIR}/dealers_products_latest.json -> $(basename "$DEALER_OUTPUT")"
    log "✅ Symlinks created: ${TMP_DIR}/retail_products_latest.json -> $(basename "$RETAIL_OUTPUT")"
}

# Function to show summary
show_summary() {
    log_info "Fetch Summary:"
    echo "  📊 Dealer products: $(jq '.products | length' "$DEALER_OUTPUT")"
    echo "  📊 Retail products: $(jq '.products | length' "$RETAIL_OUTPUT")"
    echo "  📁 Files created:"
    echo "    - $DEALER_OUTPUT"
    echo "    - $RETAIL_OUTPUT"
    echo "    - ${TMP_DIR}/dealers_products_latest.json (symlink)"
    echo "    - ${TMP_DIR}/retail_products_latest.json (symlink)"
}

# Main function
main() {
    log "🚀 Step 1: Fetching fresh product data"
    log_info "Date: $CURRENT_DATE"
    log_info "Dealer URL: $DEALER_URL"
    log_info "Retail URL: $RETAIL_URL"
    log_info "Temporary directory: $TMP_DIR"
    
    # Create tmp directory if it doesn't exist
    mkdir -p "$TMP_DIR"
    
    # Check dependencies
    check_dependencies
    
    # Fetch dealer products
    fetch_dealer_products
    
    # Fetch retail products
    fetch_retail_products
    
    # Create symlinks
    create_symlinks
    
    # Clean up old files
    cleanup_old_files
    
    # Show summary
    show_summary
    
    log "✅ Step 1 completed successfully!"
}

# Run main function
main "$@" 