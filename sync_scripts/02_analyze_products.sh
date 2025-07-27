#!/bin/bash
# Step 2: Analyze product data structure
# Analyzes the fetched product data to understand structure, categories, attributes, etc.

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
ANALYSIS_OUTPUT="${TMP_DIR}/product_analysis_${CURRENT_DATE}.json"
SUMMARY_OUTPUT="${TMP_DIR}/analysis_summary_${CURRENT_DATE}.txt"

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
    
    if ! command -v jq &> /dev/null; then
        log_error "jq is not installed"
        exit 1
    fi
    
    log "✅ Dependencies check passed"
}

# Function to find latest product files
find_latest_files() {
    log_info "Finding latest product files..."
    
    # Find latest dealer file
    local dealer_file=$(ls -t "${TMP_DIR}"/dealers_products_*.json 2>/dev/null | head -1)
    if [[ -z "$dealer_file" ]]; then
        log_error "No dealer products file found"
        exit 1
    fi
    
    # Find latest retail file
    local retail_file=$(ls -t "${TMP_DIR}"/retail_products_*.json 2>/dev/null | head -1)
    if [[ -z "$retail_file" ]]; then
        log_error "No retail products file found"
        exit 1
    fi
    
    log "✅ Found dealer file: $(basename "$dealer_file")"
    log "✅ Found retail file: $(basename "$retail_file")"
    
    DEALER_FILE="$dealer_file"
    RETAIL_FILE="$retail_file"
}

# Function to analyze dealer products structure
analyze_dealer_products() {
    log_info "Analyzing dealer products structure..."
    
    local dealer_analysis=$(cat <<EOF
{
  "source": "dealer",
  "total_products": $(jq '.products | length' "$DEALER_FILE"),
  "product_types": $(jq '.products | group_by(.product_type) | map({type: .[0].product_type, count: length})' "$DEALER_FILE"),
  "tags": $(jq '.products | map(.tags // []) | flatten | group_by(.) | map({tag: .[0], count: length}) | sort_by(.count) | reverse | .[0:20]' "$DEALER_FILE"),
  "vendors": $(jq '.products | group_by(.vendor) | map({vendor: .[0].vendor, count: length}) | sort_by(.count) | reverse' "$DEALER_FILE"),
  "price_ranges": {
    "min_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | min' "$DEALER_FILE"),
    "max_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | max' "$DEALER_FILE"),
    "avg_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | add / length' "$DEALER_FILE")
  },
  "variants_per_product": {
    "min": $(jq '.products | map(.variants | length) | min' "$DEALER_FILE"),
    "max": $(jq '.products | map(.variants | length) | max' "$DEALER_FILE"),
    "avg": $(jq '.products | map(.variants | length) | add / length' "$DEALER_FILE")
  },
  "products_with_images": $(jq '.products | map(select(.images and (.images | length > 0))) | length' "$DEALER_FILE"),
  "products_with_options": $(jq '.products | map(select(.variants[0].option1 or .variants[0].option2 or .variants[0].option3)) | length' "$DEALER_FILE"),
  "sample_products": $(jq '.products | .[0:3] | map({id: .id, title: .title, product_type: .product_type, tags: .tags, variants_count: (.variants | length), has_images: (.images and (.images | length > 0))})' "$DEALER_FILE")
}
EOF
)
    
    echo "$dealer_analysis" > "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json"
    log "✅ Dealer analysis saved: dealer_analysis_${CURRENT_DATE}.json"
}

# Function to analyze retail products structure
analyze_retail_products() {
    log_info "Analyzing retail products structure..."
    
    local retail_analysis=$(cat <<EOF
{
  "source": "retail",
  "total_products": $(jq '.products | length' "$RETAIL_FILE"),
  "product_types": $(jq '.products | group_by(.product_type) | map({type: .[0].product_type, count: length})' "$RETAIL_FILE"),
  "tags": $(jq '.products | map(.tags // []) | flatten | group_by(.) | map({tag: .[0], count: length}) | sort_by(.count) | reverse | .[0:20]' "$RETAIL_FILE"),
  "vendors": $(jq '.products | group_by(.vendor) | map({vendor: .[0].vendor, count: length}) | sort_by(.count) | reverse' "$RETAIL_FILE"),
  "price_ranges": {
    "min_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | min' "$RETAIL_FILE"),
    "max_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | max' "$RETAIL_FILE"),
    "avg_price": $(jq '.products | map(.variants[0].price // "0" | tonumber) | add / length' "$RETAIL_FILE")
  },
  "variants_per_product": {
    "min": $(jq '.products | map(.variants | length) | min' "$RETAIL_FILE"),
    "max": $(jq '.products | map(.variants | length) | max' "$RETAIL_FILE"),
    "avg": $(jq '.products | map(.variants | length) | add / length' "$RETAIL_FILE")
  },
  "products_with_images": $(jq '.products | map(select(.images and (.images | length > 0))) | length' "$RETAIL_FILE"),
  "products_with_options": $(jq '.products | map(select(.variants[0].option1 or .variants[0].option2 or .variants[0].option3)) | length' "$RETAIL_FILE"),
  "sample_products": $(jq '.products | .[0:3] | map({id: .id, title: .title, product_type: .product_type, tags: .tags, variants_count: (.variants | length), has_images: (.images and (.images | length > 0))})' "$RETAIL_FILE")
}
EOF
)
    
    echo "$retail_analysis" > "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json"
    log "✅ Retail analysis saved: retail_analysis_${CURRENT_DATE}.json"
}

# Function to create comprehensive analysis
create_comprehensive_analysis() {
    log_info "Creating comprehensive analysis..."
    
    local comprehensive_analysis=$(cat <<EOF
{
  "analysis_date": "$CURRENT_DATE",
  "dealer_analysis": $(cat "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json"),
  "retail_analysis": $(cat "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json"),
  "comparison": {
    "total_products": {
      "dealer": $(jq '.total_products' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json"),
      "retail": $(jq '.total_products' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
    },
    "price_comparison": {
      "dealer_avg": $(jq '.price_ranges.avg_price' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json"),
      "retail_avg": $(jq '.price_ranges.avg_price' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
    },
    "variants_comparison": {
      "dealer_avg": $(jq '.variants_per_product.avg' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json"),
      "retail_avg": $(jq '.variants_per_product.avg' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
    }
  }
}
EOF
)
    
    echo "$comprehensive_analysis" > "$ANALYSIS_OUTPUT"
    log "✅ Comprehensive analysis saved: $ANALYSIS_OUTPUT"
}

# Function to generate summary report
generate_summary_report() {
    log_info "Generating summary report..."
    
    cat > "$SUMMARY_OUTPUT" <<EOF
===========================================
BEARPAW PRODUCTS ANALYSIS SUMMARY
===========================================
Analysis Date: $CURRENT_DATE
Generated: $(date)

DEALER PRODUCTS ANALYSIS:
------------------------
Total Products: $(jq '.total_products' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Product Types: $(jq '.product_types | length' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Unique Tags: $(jq '.tags | length' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Vendors: $(jq '.vendors | length' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Price Range: €$(jq '.price_ranges.min_price' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json") - €$(jq '.price_ranges.max_price' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Average Price: €$(jq '.price_ranges.avg_price' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Products with Images: $(jq '.products_with_images' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")
Products with Options: $(jq '.products_with_options' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")

All Tags (Dealer):
$(jq -r '.tags | .[] | "- \(.tag): \(.count) products"' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")

RETAIL PRODUCTS ANALYSIS:
------------------------
Total Products: $(jq '.total_products' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Product Types: $(jq '.product_types | length' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Unique Tags: $(jq '.tags | length' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Vendors: $(jq '.vendors | length' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Price Range: €$(jq '.price_ranges.min_price' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json") - €$(jq '.price_ranges.max_price' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Average Price: €$(jq '.price_ranges.avg_price' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Products with Images: $(jq '.products_with_images' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Products with Options: $(jq '.products_with_options' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")

All Tags (Retail):
$(jq -r '.tags | .[] | "- \(.tag): \(.count) products"' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")

COMPARISON SUMMARY:
------------------
Total Products: Dealer $(jq '.total_products' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json") vs Retail $(jq '.total_products' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Average Price: Dealer €$(jq '.price_ranges.avg_price' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json") vs Retail €$(jq '.price_ranges.avg_price' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")
Average Variants: Dealer $(jq '.variants_per_product.avg' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json") vs Retail $(jq '.variants_per_product.avg' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")

FILES GENERATED:
----------------
- dealer_analysis_${CURRENT_DATE}.json
- retail_analysis_${CURRENT_DATE}.json
- product_analysis_${CURRENT_DATE}.json
- analysis_summary_${CURRENT_DATE}.txt

===========================================
EOF
    
    log "✅ Summary report saved: $SUMMARY_OUTPUT"
}

# Function to cleanup old analysis files (keep only 1 latest version)
cleanup_old_analysis() {
    log_info "Cleaning up old analysis files (keeping 1 latest version)..."
    
    # Clean dealer analysis files
    local dealer_analysis_files=($(ls -t "${TMP_DIR}"/dealer_analysis_*.json 2>/dev/null))
    if [[ ${#dealer_analysis_files[@]} -gt 1 ]]; then
        local files_to_remove=("${dealer_analysis_files[@]:1}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old dealer analysis file: $(basename "$file")"
        done
        log "✅ Cleaned up dealer analysis files: removed ${#files_to_remove[@]} old files"
    fi
    
    # Clean retail analysis files
    local retail_analysis_files=($(ls -t "${TMP_DIR}"/retail_analysis_*.json 2>/dev/null))
    if [[ ${#retail_analysis_files[@]} -gt 1 ]]; then
        local files_to_remove=("${retail_analysis_files[@]:1}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old retail analysis file: $(basename "$file")"
        done
        log "✅ Cleaned up retail analysis files: removed ${#files_to_remove[@]} old files"
    fi
    
    # Clean comprehensive analysis files
    local comprehensive_analysis_files=($(ls -t "${TMP_DIR}"/product_analysis_*.json 2>/dev/null))
    if [[ ${#comprehensive_analysis_files[@]} -gt 1 ]]; then
        local files_to_remove=("${comprehensive_analysis_files[@]:1}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old comprehensive analysis file: $(basename "$file")"
        done
        log "✅ Cleaned up comprehensive analysis files: removed ${#files_to_remove[@]} old files"
    fi
    
    # Clean summary report files
    local summary_files=($(ls -t "${TMP_DIR}"/analysis_summary_*.txt 2>/dev/null))
    if [[ ${#summary_files[@]} -gt 1 ]]; then
        local files_to_remove=("${summary_files[@]:1}")
        for file in "${files_to_remove[@]}"; do
            rm -f "$file"
            log_info "Removed old summary file: $(basename "$file")"
        done
        log "✅ Cleaned up summary files: removed ${#files_to_remove[@]} old files"
    fi
}

# Function to show summary
show_summary() {
    log_info "Analysis Summary:"
    echo "  📊 Dealer products analyzed: $(jq '.total_products' "${TMP_DIR}/dealer_analysis_${CURRENT_DATE}.json")"
    echo "  📊 Retail products analyzed: $(jq '.total_products' "${TMP_DIR}/retail_analysis_${CURRENT_DATE}.json")"
    echo "  📁 Files created:"
    echo "    - dealer_analysis_${CURRENT_DATE}.json"
    echo "    - retail_analysis_${CURRENT_DATE}.json"
    echo "    - product_analysis_${CURRENT_DATE}.json"
    echo "    - analysis_summary_${CURRENT_DATE}.txt"
    echo ""
    echo "📋 Quick Summary:"
    cat "$SUMMARY_OUTPUT" | grep -A 20 "COMPARISON SUMMARY:"
}

# Main function
main() {
    log "🚀 Step 2: Analyzing product data structure"
    log_info "Date: $CURRENT_DATE"
    log_info "Temporary directory: $TMP_DIR"
    
    # Create tmp directory if it doesn't exist
    mkdir -p "$TMP_DIR"
    
    # Check dependencies
    check_dependencies
    
    # Find latest product files
    find_latest_files
    
    # Analyze dealer products
    analyze_dealer_products
    
    # Analyze retail products
    analyze_retail_products
    
    # Create comprehensive analysis
    create_comprehensive_analysis
    
    # Generate summary report
    generate_summary_report
    
    # Clean up old analysis files
    cleanup_old_analysis
    
    # Show summary
    show_summary
    
    log "✅ Step 2 completed successfully!"
}

# Run main function
main "$@" 