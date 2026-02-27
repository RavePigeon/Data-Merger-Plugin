<?php
/**
 * Admin Class
 * Handles admin interface, menus, and page rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Admin {
    
    private $importer;
    private $exporter;
    private $generator;
    
    public function __construct() {
        $this->importer = new NPM_Importer();
        $this->exporter = new NPM_Exporter();
        $this->generator = new NPM_Product_Generator();
        
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_downloads']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            'NetSuite Manager',
            'NetSuite Manager',
            'manage_options',
            'npm-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-update',
            26
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'npm-dashboard',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Import NetSuite',
            'Import NetSuite',
            'manage_options',
            'npm-import-netsuite',
            [$this, 'render_import_netsuite']
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Import PUP',
            'Import PUP',
            'manage_options',
            'npm-import-pup',
            [$this, 'render_import_pup']
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Discontinued SKUs',
            'Discontinued SKUs',
            'manage_options',
            'npm-discontinued',
            [$this, 'render_discontinued']
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Export to NetSuite',
            'Export to NetSuite',
            'manage_options',
            'npm-export',
            [$this, 'render_export']
        );
        
        add_submenu_page(
            'npm-dashboard',
            'Parent SKU Manager',
            'Parent SKU Manager',
            'manage_options',
            'npm-parent-manager',
            [$this, 'render_parent_manager']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'npm-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'npm-admin-css',
            NPM_PLUGIN_URL . 'assets/css/npm-admin.css',
            [],
            NPM_VERSION
        );
        
        wp_enqueue_script(
            'npm-admin-js',
            NPM_PLUGIN_URL . 'assets/js/npm-admin.js',
            ['jquery'],
            NPM_VERSION,
            true
        );
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Import NetSuite CSV
        if (isset($_POST['npm_import_netsuite']) && isset($_FILES['netsuite_csv'])) {
            check_admin_referer('npm_import_netsuite');
            
            $file = $_FILES['netsuite_csv']['tmp_name'];
            $result = $this->importer->import_netsuite_csv($file);
            
            if ($result['success']) {
                set_transient('npm_admin_notice', [
                    'type' => 'success',
                    'message' => sprintf(
                        'NetSuite import complete! Inserted: %d, Updated: %d',
                        $result['inserted'],
                        $result['updated']
                    )
                ], 30);
            } else {
                set_transient('npm_admin_notice', [
                    'type' => 'error',
                    'message' => 'Import failed: ' . $result['error']
                ], 30);
            }
            
            wp_redirect(admin_url('admin.php?page=npm-import-netsuite'));
            exit;
        }
        
        // Import PUP CSV
        if (isset($_POST['npm_import_pup']) && isset($_FILES['pup_csv'])) {
            check_admin_referer('npm_import_pup');
            
            $file = $_FILES['pup_csv']['tmp_name'];
            $result = $this->importer->import_pup_csv($file);
            
            if ($result['success']) {
                set_transient('npm_admin_notice', [
                    'type' => 'success',
                    'message' => sprintf(
                        'PUP import complete! Inserted: %d',
                        $result['inserted']
                    )
                ], 30);
            } else {
                set_transient('npm_admin_notice', [
                    'type' => 'error',
                    'message' => 'Import failed: ' . $result['error']
                ], 30);
            }
            
            wp_redirect(admin_url('admin.php?page=npm-import-pup'));
            exit;
        }
        
        // Import Discontinued SKUs
        if (isset($_POST['npm_import_discontinued']) && isset($_FILES['discontinued_csv'])) {
            check_admin_referer('npm_import_discontinued');
            
            $file = $_FILES['discontinued_csv']['tmp_name'];
            $result = $this->importer->import_discontinued_csv($file);
            
            if ($result['success']) {
                set_transient('npm_admin_notice', [
                    'type' => 'success',
                    'message' => sprintf(
                        'Discontinued SKUs imported! New: %d, Skipped: %d',
                        $result['inserted'],
                        $result['skipped']
                    )
                ], 30);
            } else {
                set_transient('npm_admin_notice', [
                    'type' => 'error',
                    'message' => 'Import failed: ' . $result['error']
                ], 30);
            }
            
            wp_redirect(admin_url('admin.php?page=npm-discontinued'));
            exit;
        }
        
        // Generate Export
        if (isset($_POST['npm_generate_export'])) {
            check_admin_referer('npm_generate_export');
            
            set_time_limit(600);
            
            // Step 1: Generate child products
            $gen_result = $this->generator->generate_all_child_products();
            
            // Step 2: Identify unmatched
            $unmatched = $this->generator->identify_unmatched_skus();
            
            // Step 3: Populate export queue
            $export_count = $this->exporter->populate_export_queue();
            
            set_transient('npm_admin_notice', [
                'type' => 'success',
                'message' => sprintf(
                    'Export generated! Base SKUs: %d, Child SKUs: %d, Export queue: %d, Unmatched: %d (Time: %ss)',
                    $gen_result['base_skus'],
                    $gen_result['child_skus'],
                    $export_count,
                    $unmatched,
                    $gen_result['time_taken']
                )
            ], 30);
            
            wp_redirect(admin_url('admin.php?page=npm-export'));
            exit;
        }
    }
    
    /**
     * Handle CSV downloads
     */
    public function handle_downloads() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Download NetSuite update CSV
        if (isset($_GET['npm_download']) && $_GET['npm_download'] === 'netsuite_update') {
            check_admin_referer('npm_download_netsuite_update');
            $this->exporter->export_netsuite_update_csv();
        }
        
        // Download Magento specials CSV
        if (isset($_GET['npm_download']) && $_GET['npm_download'] === 'magento_specials') {
            check_admin_referer('npm_download_magento_specials');
            
            $multipliers = [
                'delivery_multiplier' => isset($_GET['delivery_mult']) ? floatval($_GET['delivery_mult']) : 1.10,
                'invoice_multiplier' => isset($_GET['invoice_mult']) ? floatval($_GET['invoice_mult']) : 1.25,
            ];
            
            $this->exporter->export_magento_specials_csv($multipliers);
        }
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $this->show_admin_notice();
        
        $stats = NPM_Database::get_table_stats();
        $export_stats = $this->exporter->get_export_stats();
        
        include NPM_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    /**
     * Render import NetSuite page
     */
    public function render_import_netsuite() {
        $this->show_admin_notice();
        $stats = $this->importer->get_last_import_stats('netsuite');
        
        include NPM_PLUGIN_DIR . 'admin/import-netsuite.php';
    }
    
    /**
     * Render import PUP page
     */
    public function render_import_pup() {
        $this->show_admin_notice();
        $stats = $this->importer->get_last_import_stats('pup');
        
        include NPM_PLUGIN_DIR . 'admin/import-pup.php';
    }
    
    /**
     * Render discontinued page
     */
    public function render_discontinued() {
        $this->show_admin_notice();
        
        global $wpdb;
        $stats = $this->importer->get_last_import_stats('discontinued');
        $recent = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}npm_discontinued_skus ORDER BY uploaded_at DESC LIMIT 50",
            ARRAY_A
        );
        
        include NPM_PLUGIN_DIR . 'admin/discontinued.php';
    }
    
    /**
     * Render export page
     */
    public function render_export() {
        $this->show_admin_notice();
        
        $export_stats = $this->exporter->get_export_stats();
        $validation = $this->exporter->validate_export_queue();
        
        include NPM_PLUGIN_DIR . 'admin/export.php';
    }
    
    /**
     * Render parent SKU manager
     */
    public function render_parent_manager() {
        $this->show_admin_notice();
        
        global $wpdb;
        $parents = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}npm_parent_sku_manager ORDER BY parent_sku LIMIT 100",
            ARRAY_A
        );
        
        include NPM_PLUGIN_DIR . 'admin/parent-manager.php';
    }
    
    /**
     * Show admin notices
     */
    private function show_admin_notice() {
        $notice = get_transient('npm_admin_notice');
        if ($notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
            delete_transient('npm_admin_notice');
        }
    }
}