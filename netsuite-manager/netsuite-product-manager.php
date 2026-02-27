<?php
/**
 * Plugin Name: NetSuite Product Manager
 * Plugin URI: https://yoursite.com
 * Description: Unified NetSuite product management with PUP pricing updates, child SKU generation, and discontinued product tracking
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: netsuite-product-manager
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('NPM_VERSION', '2.0.0');
define('NPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NPM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NPM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once NPM_PLUGIN_DIR . 'includes/class-npm-database.php';
require_once NPM_PLUGIN_DIR . 'includes/class-npm-importer.php';
require_once NPM_PLUGIN_DIR . 'includes/class-npm-exporter.php';
require_once NPM_PLUGIN_DIR . 'includes/class-npm-product-generator.php';
require_once NPM_PLUGIN_DIR . 'includes/class-npm-admin.php';

/**
 * Main Plugin Class
 */
class NetSuite_Product_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
    }
    
    public function init() {
        // Initialize admin interface
        if (is_admin()) {
            new NPM_Admin();
        }
    }
    
    public function activate() {
        // Create database tables
        NPM_Database::create_tables();
        
        // Set default options
        add_option('npm_version', NPM_VERSION);
        add_option('npm_activated_at', current_time('mysql'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up temporary data
        delete_transient('npm_import_progress');
        flush_rewrite_rules();
    }
}

// Initialize plugin
function npm_init() {
    return NetSuite_Product_Manager::get_instance();
}

npm_init();