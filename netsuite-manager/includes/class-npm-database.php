<?php
/**
 * Database Management Class
 * Handles table creation and schema management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Database {
    
    /**
     * Create all plugin tables
     */
    public static function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_table_schemas($charset_collate);
        
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
        
        // Log table creation
        error_log('NPM: Database tables created successfully');
    }
    
    /**
     * Get all table schemas
     */
    private static function get_table_schemas($charset_collate) {
        global $wpdb;
        
        return [
            // NetSuite product dump
            "CREATE TABLE {$wpdb->prefix}npm_netsuite_dump (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                internal_id VARCHAR(32) UNIQUE,
                name VARCHAR(255),
                sku_suffix VARCHAR(128),
                display_name VARCHAR(255),
                preferred_supplier VARCHAR(255),
                online_price DECIMAL(10,2),
                supplier_price DECIMAL(10,2),
                parent VARCHAR(128),
                disabled_in_magento VARCHAR(8),
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_internal_id (internal_id),
                INDEX idx_sku_suffix (sku_suffix)
            ) $charset_collate;",
            
            // PUP updates (monthly pricing)
            "CREATE TABLE {$wpdb->prefix}npm_pup_updates (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                sku VARCHAR(128) UNIQUE,
                catalogue_code VARCHAR(64),
                category VARCHAR(64),
                sub_category VARCHAR(64),
                product_name VARCHAR(255),
                rrp DECIMAL(10,2),
                standard_invoice_cost DECIMAL(10,2),
                invoice_cost_4plus DECIMAL(10,2),
                next_day_cost DECIMAL(10,2),
                install_cost_7_10 DECIMAL(10,2),
                prebuild_cost_3_5 DECIMAL(10,2),
                prebuild_cost_next_day DECIMAL(10,2),
                new_status VARCHAR(16),
                status VARCHAR(16),
                promotion_available TINYINT(1) DEFAULT 0,
                promo_code VARCHAR(32),
                promo_cost DECIMAL(10,2),
                promo_min_order_spend VARCHAR(32),
                promo_surcharge VARCHAR(32),
                promo_cost_plus_surcharge DECIMAL(10,2),
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_sku (sku),
                INDEX idx_promo (promotion_available)
            ) $charset_collate;",
            
            // Child products (all SKU variations)
            "CREATE TABLE {$wpdb->prefix}npm_child_products (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                parent_sku VARCHAR(128),
                child_sku VARCHAR(128) NOT NULL,
                netsuite_internal_id VARCHAR(32),
                delivery_option VARCHAR(128),
                delivery_price DECIMAL(10,2) DEFAULT 0,
                rrp DECIMAL(10,2),
                standard_invoice_cost DECIMAL(10,2),
                cost DECIMAL(10,2),
                promo_cost DECIMAL(10,2),
                promo_code VARCHAR(32),
                has_promo TINYINT(1) DEFAULT 0,
                new_status VARCHAR(16),
                status VARCHAR(16),
                discontinued TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_child_sku (child_sku),
                INDEX idx_parent_sku (parent_sku),
                INDEX idx_child_sku (child_sku),
                INDEX idx_netsuite_id (netsuite_internal_id),
                INDEX idx_discontinued (discontinued)
            ) $charset_collate;",
            
            // Export queue (ready for NetSuite import)
            "CREATE TABLE {$wpdb->prefix}npm_export_queue (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                netsuite_internal_id VARCHAR(32),
                product_code VARCHAR(128),
                parent VARCHAR(128),
                brand_name VARCHAR(255),
                discontinued TINYINT(1) DEFAULT 0,
                rrp DECIMAL(10,2),
                cost_price DECIMAL(10,2),
                online_price DECIMAL(10,2),
                promo_price DECIMAL(10,2),
                delivery_method VARCHAR(128),
                status VARCHAR(16),
                new_status VARCHAR(16),
                promo_code VARCHAR(32),
                has_promo TINYINT(1) DEFAULT 0,
                export_status VARCHAR(16) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                exported_at DATETIME,
                PRIMARY KEY (id),
                INDEX idx_product_code (product_code),
                INDEX idx_netsuite_id (netsuite_internal_id),
                INDEX idx_discontinued (discontinued),
                INDEX idx_export_status (export_status)
            ) $charset_collate;",
            
            // Discontinued SKUs
            "CREATE TABLE {$wpdb->prefix}npm_discontinued_skus (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                discontinued_sku VARCHAR(128) NOT NULL UNIQUE,
                internal_id VARCHAR(32),
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_sku (discontinued_sku)
            ) $charset_collate;",
            
            // Unmatched SKUs (in PUP but not in NetSuite)
            "CREATE TABLE {$wpdb->prefix}npm_unmatched_skus (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                sku VARCHAR(128) NOT NULL,
                product_name VARCHAR(255),
                source VARCHAR(32),
                detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_sku (sku),
                INDEX idx_source (source)
            ) $charset_collate;",
            
            // Parent SKU manager
            "CREATE TABLE {$wpdb->prefix}npm_parent_sku_manager (
                id BIGINT(20) UNSIGNED AUTO_INCREMENT,
                parent_sku VARCHAR(128) NOT NULL UNIQUE,
                netsuite_parent_id VARCHAR(32),
                product_name VARCHAR(255),
                category VARCHAR(64),
                child_count INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_parent_sku (parent_sku),
                INDEX idx_netsuite_id (netsuite_parent_id)
            ) $charset_collate;",
        ];
    }
    
    /**
     * Drop all plugin tables (for clean uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            'npm_netsuite_dump',
            'npm_pup_updates',
            'npm_child_products',
            'npm_export_queue',
            'npm_discontinued_skus',
            'npm_unmatched_skus',
            'npm_parent_sku_manager',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
    }
    
    /**
     * Get table statistics
     */
    public static function get_table_stats() {
        global $wpdb;
        
        return [
            'netsuite_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_netsuite_dump"),
            'pup_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_pup_updates"),
            'child_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_child_products"),
            'export_queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_export_queue"),
            'discontinued' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_discontinued_skus"),
            'unmatched' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_unmatched_skus"),
            'parent_skus' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}npm_parent_sku_manager"),
        ];
    }
}