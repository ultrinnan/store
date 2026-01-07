=== F-PRO Bearpaw Dealer ===
Contributors: fedirkopro
Tags: woocommerce, bearpaw, dealer, products, vendor, import, price-sync
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tools for managing Bearpaw dealer products in WooCommerce. Syncs products and prices from Bearpaw B2B portal with automatic price calculation.

== Description ==

F-PRO Bearpaw Dealer is a comprehensive plugin designed to help Bearpaw dealers manage products imported from the Bearpaw B2B portal (https://b2bportal.bearpaw-products.de) into their WooCommerce store. The plugin provides tools for product synchronization, price management, and identification of missing products.

= Key Features =

* Track total number of products imported from Bearpaw
* Display statistics for Bearpaw products (total, published, draft)
* Download all products from B2B and Retail Bearpaw stores via JSON API
* Automatic price calculation and update based on configurable rules
* Identify missing products from B2B portal
* Product matching by SKU
* Sortable product tables
* Pre-fill product form when adding new products

= Current Version Features =

* **Step 1: Configure cURL Commands** - Paste cURL commands from browser to authenticate with Bearpaw APIs
* **Step 2: Download All Products** - Download all products from both B2B and Retail stores with pagination support
* **Step 3: Process Products** - Analyze product matching and automatically update prices based on:
  * Configurable margin above B2B price (default: 20%)
  * Configurable discount below retail price (default: 10%)
  * Configurable price change threshold (default: 0.2 EUR)
* **Step 4: Missing Products** - View products available in B2B but not in your store, with:
  * Pre-calculated recommended prices
  * Direct links to B2B product pages
  * Quick "Add Product" button that opens product form and B2B page
  * Sortable columns (SKU, Title, Price)
  * Search functionality

= Price Calculation Logic =

The plugin automatically calculates optimal prices using the following formula:
1. Calculate desired price: B2B Price × (1 + Margin%)
2. Calculate maximum allowed price: Retail Price × (1 - Discount%)
3. Use the lower of the two prices
4. Only update if price difference exceeds the threshold

This ensures your prices are competitive while maintaining your desired margin.

= Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* WooCommerce 3.0 or higher

= How It Works =

Products are identified as Bearpaw products using a custom meta field `dealer_product_id` (Shopify product ID from Bearpaw B2B portal). The plugin uses SKU as the primary identifier for matching products between WooCommerce, B2B portal, and Retail store.

**Product Matching:**
- Products are matched by SKU across all three sources (WooCommerce, B2B, Retail)
- SKU must be unique and present in all systems for proper matching

**Price Updates:**
- Only products with matches in both B2B and Retail stores are eligible for automatic price updates
- Prices are updated only if the difference exceeds the configured threshold
- All prices are in EUR currency

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "F-PRO Bearpaw Dealer"
4. Click "Install Now"
5. Click "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Click "Activate"

= Via FTP =

1. Extract the ZIP file
2. Upload the `f-pro-bearpaw-dealer` folder to `/wp-content/plugins/`
3. Log in to your WordPress admin panel
4. Navigate to Plugins
5. Find "F-PRO Bearpaw Dealer" and click "Activate"

== Frequently Asked Questions ==

= How are products identified as Bearpaw products? =

Products are identified using a custom meta field `dealer_product_id` (Shopify product ID). Products with this meta field are considered Bearpaw products. The plugin uses SKU as the primary matching identifier.

= How do I get the cURL commands for authentication? =

1. Log in to the Bearpaw B2B portal (or retail site) in your browser
2. Open Developer Tools (F12)
3. Go to Network tab
4. Visit the products.json URL (https://www.b2bportal.bearpaw-products.de/products.json or https://bearpaw-products.com/products.json)
5. Right-click on the request → Copy → Copy as cURL
6. Paste the full cURL command into the plugin settings

= How does automatic price calculation work? =

The plugin calculates prices using this logic:
- Desired price = B2B Price × (1 + Margin%)
- Maximum price = Retail Price × (1 - Discount%)
- Final price = min(Desired price, Maximum price)
- Price is only updated if the difference exceeds the threshold

You can configure margin, discount, and threshold in the plugin settings.

= What if a product has multiple variants with the same SKU? =

The plugin uses the minimum price if multiple variants share the same SKU to ensure competitive pricing.

= Can I manually add products from the missing products list? =

Yes! Click "Add Product" button which will:
- Open WooCommerce product creation form (pre-filled with SKU, Title, and calculated Price)
- Open B2B product page in a new tab for reference
- You can then complete the product details and save

== Changelog ==

= 1.0.0 =
* Initial release
* Display total count of Bearpaw products
* Show published vs draft statistics
* Admin dashboard for Bearpaw products
* Product identification using custom meta field (dealer_product_id)
* Download all products from B2B and Retail Bearpaw stores via JSON API
* Automatic price calculation and update based on configurable rules
* Product matching by SKU across WooCommerce, B2B, and Retail stores
* Missing products identification and management
* Pre-fill product form when adding new products
* Sortable product tables
* Search functionality for missing products
* JSON API access status monitoring
* Support for Shopify pagination
* Automatic handling of HTTP 304 responses

== Upgrade Notice ==

= 1.0.0 =
Initial release of F-PRO Bearpaw Dealer plugin.

