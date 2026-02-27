<?php
/**
 * Admin functionality for NetSuite Product Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Admin {
    private $importer;
    private $generator;

    public function __construct() {
        // Initialize components
        require_once NPM_PLUGIN_DIR . 'includes/class-npm-importer.php';
        require_once NPM_PLUGIN_DIR . 'includes/class-npm-generator.php';
        
        $this->importer = new NPM_Importer();
        $this->generator = new NPM_Generator();

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        
        // AJAX handlers for batched export
        add_action('wp_ajax_npm_initialize_export', [$this, 'ajax_initialize_export']);
        add_action('wp_ajax_npm_export_batch', [$this, 'ajax_export_batch']);
        
        // AJAX handlers for batched generation
        add_action('wp_ajax_npm_initialize_generation', [$this, 'ajax_initialize_generation']);
        add_action('wp_ajax_npm_generate_batch', [$this, 'ajax_generate_batch']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'NetSuite Manager',
            'NetSuite Manager',
            'manage_options',
            'netsuite-manager',
            [$this, 'render_dashboard'],
            'dashicons-database-export',
            30
        );

        // Dashboard (same as main)
        add_submenu_page(
            'netsuite-manager',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'netsuite-manager',
            [$this, 'render_dashboard']
        );

        // Import NetSuite
        add_submenu_page(
            'netsuite-manager',
            'Import NetSuite',
            'Import NetSuite',
            'manage_options',
            'npm-import-netsuite',
            [$this, 'render_import_netsuite']
        );

        // Import PUP
        add_submenu_page(
            'netsuite-manager',
            'Import PUP',
            'Import PUP',
            'manage_options',
            'npm-import-pup',
            [$this, 'render_import_pup']
        );

        // Import Discontinued
        add_submenu_page(
            'netsuite-manager',
            'Import Discontinued',
            'Import Discontinued',
            'manage_options',
            'npm-discontinued',
            [$this, 'render_discontinued']
        );

        // Generate Products
        add_submenu_page(
            'netsuite-manager',
            'Generate Child Products',
            'Generate Products',
            'manage_options',
            'npm-generate',
            [$this, 'render_generate']
        );

        // Export
        add_submenu_page(
            'netsuite-manager',
            'Export to NetSuite',
            'Export to NetSuite',
            'manage_options',
            'npm-export',
            [$this, 'render_export']
        );
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'netsuite-manager') === false && strpos($hook, 'npm-') === false) {
            return;
        }

        wp_enqueue_style(
            'npm-admin-styles',
            NPM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            NPM_VERSION
        );
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        include NPM_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render import NetSuite page
     */
    public function render_import_netsuite() {
        include NPM_PLUGIN_DIR . 'admin/import-netsuite.php';
    }

    /**
     * Render import PUP page
     */
    public function render_import_pup() {
        include NPM_PLUGIN_DIR . 'admin/import-pup.php';
    }

    /**
     * Render discontinued page
     */
    public function render_discontinued() {
        include NPM_PLUGIN_DIR . 'admin/import-discontinued.php';
    }

    /**
     * Render generate page
     */
    public function render_generate() {
        include NPM_PLUGIN_DIR . 'admin/generate.php';
    }

    /**
     * Render export page
     */
    public function render_export() {
        include NPM_PLUGIN_DIR . 'admin/export.php';
    }

    /**
     * AJAX: Initialize export (create empty CSV with headers)
     */
    public function ajax_initialize_export() {
        check_ajax_referer('npm_export', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $upload_dir = wp_upload_dir();
        $filename = 'netsuite-export-' . date('Y-m-d-His') . '.csv';
        $file_path = $upload_dir['basedir'] . '/' . $filename;
        
        // Create CSV with headers
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            wp_send_json_error(['message' => 'Cannot create export file']);
        }
        
        // Write BOM for UTF-8
        fwrite($handle, "\xEF\xBB\xBF");
        
        // Write headers
        $headers = [
            'SKU',
            'Parent SKU',
            'Product Name',
            'Category',
            'Sub-Category',
            'RRP',
            'Standard Invoice Cost',
            'Invoice Cost (4+ Items)',
            'Next Day Cost',
            '7-10 Day Install Cost',
            '3-5 Day Prebuild Cost',
            'Next Day Prebuild Cost',
            'Status',
            'Discontinued'
        ];
        
        fputcsv($handle, $headers);
        fclose($handle);
        
        wp_send_json_success([
            'filename' => $filename,
            'file_path' => $file_path
        ]);
    }

    /**
     * AJAX: Export batch of products
     */
    public function ajax_export_batch() {
        check_ajax_referer('npm_export', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 100);
        $filename = sanitize_file_name($_POST['filename'] ?? '');
        
        if (empty($filename)) {
            wp_send_json_error(['message' => 'Missing filename']);
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;
        
        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Export file not found']);
        }
        
        // Get batch of products
        global $wpdb;
        $table = $wpdb->prefix . 'npm_child_products';
        
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
             ORDER BY sku 
             LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ), ARRAY_A);
        
        if (empty($products)) {
            // No more products - export complete
            wp_send_json_success([
                'processed' => 0,
                'complete' => true,
                'file_url' => $upload_dir['baseurl'] . '/' . $filename,
                'file_path' => $file_path
            ]);
        }
        
        // Append to CSV
        $handle = fopen($file_path, 'a');
        if (!$handle) {
            wp_send_json_error(['message' => 'Cannot open export file']);
        }
        
        foreach ($products as $product) {
            $row = [
                $product['sku'],
                $product['parent_sku'],
                $product['product_name'],
                $product['category'],
                $product['sub_category'],
                $product['rrp'],
                $product['standard_invoice_cost'],
                $product['invoice_cost_4plus'],
                $product['next_day_cost'],
                $product['install_cost_7_10'],
                $product['prebuild_cost_3_5'],
                $product['prebuild_cost_next_day'],
                $product['status'],
                $product['is_discontinued'] ? 'Yes' : 'No'
            ];
            
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        wp_send_json_success([
            'processed' => count($products),
            'complete' => false
        ]);
    }

    /**
     * AJAX: Initialize generation (clear table and prepare)
     */
    public function ajax_initialize_generation() {
        check_ajax_referer('npm_generate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        global $wpdb;
        $child_table = $wpdb->prefix . 'npm_child_products';
        
        // Truncate child products table
        $wpdb->query("TRUNCATE TABLE {$child_table}");
        
        // Get total count of NetSuite records with suffixes
        $netsuite_table = $wpdb->prefix . 'npm_netsuite_dump';
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$netsuite_table} 
             WHERE sku_suffix IS NOT NULL AND sku_suffix != ''"
        );
        
        wp_send_json_success([
            'total_records' => intval($total)
        ]);
    }

    /**
     * AJAX: Generate batch of child products
     */
    public function ajax_generate_batch() {
        check_ajax_referer('npm_generate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 100);
        
        // Call generator with batch parameters
        $result = $this->generator->generate_child_products($offset, $batch_size);
        
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error'] ?? 'Generation failed']);
        }
        
        wp_send_json_success([
            'processed' => $result['generated'],
            'skipped' => $result['skipped'],
            'complete' => ($result['generated'] < $batch_size)
        ]);
    }
}