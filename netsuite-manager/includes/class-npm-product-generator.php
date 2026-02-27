<?php
/**
 * Product Generator Class
 * Generates child SKU variations with delivery options
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Product_Generator {
    
    private $wpdb;
    
    // Supplier dataset tables (keeping old names for compatibility)
    private $dataset_tables = [
        'seat' => 'mi4w_dos_datasets_seat',
        'desk' => 'mi4w_dos_datasets_desk',
        'bund' => 'mi4w_dos_datasets_bund',
        'soft' => 'mi4w_dos_datasets_soft',
    ];
    
    // Delivery option suffixes
    private $delivery_options = [
        ['suffix' => 'ND', 'name' => 'Next Day (Self Assembly)', 'cost_col' => 'Next_Day_Cost'],
        ['suffix' => 'DI', 'name' => '7-10 Days (Delivered & Installed)', 'cost_col' => 'Seven_Ten_Day_Install_Cost'],
        ['suffix' => 'B', 'name' => '3-5 Days (Pre-Assembled)', 'cost_col' => 'Three_Five_Day_Prebuild_Cost'],
        ['suffix' => 'BE', 'name' => 'Next Day (Pre-Assembled)', 'cost_col' => 'Next_Day_Prebuild_Cost'],
    ];
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Main generation method - creates all child SKUs
     */
    public function generate_all_child_products() {
        $results = [
            'base_skus' => 0,
            'child_skus' => 0,
            'errors' => [],
            'time_taken' => 0,
        ];
        
        $start_time = microtime(true);
        
        // Step 1: Clear existing child products
        $this->wpdb->query("TRUNCATE TABLE {$this->wpdb->prefix}npm_child_products");
        
        // Step 2: Insert base SKUs
        $base_count = $this->insert_base_skus();
        $results['base_skus'] = $base_count;
        
        // Step 3: Insert child SKU variations
        $child_count = $this->insert_child_sku_variations();
        $results['child_skus'] = $child_count;
        
        // Step 4: Link NetSuite internal IDs
        $this->link_netsuite_ids();
        
        // Step 5: Mark discontinued products
        $this->mark_discontinued_products();
        
        // Step 6: Update parent SKU manager
        $this->update_parent_manager();
        
        $results['time_taken'] = round(microtime(true) - $start_time, 2);
        
        return $results;
    }
    
    /**
     * Insert base/parent SKUs with delivery method from dos_delivery_standard
     */
    private function insert_base_skus() {
        $delivery_std_table = 'mi4w_dos_delivery_standard';
        
        $inserted = $this->wpdb->query("
            INSERT INTO {$this->wpdb->prefix}npm_child_products
            (parent_sku, child_sku, delivery_option, delivery_price, rrp, standard_invoice_cost, cost, 
             promo_cost, promo_code, has_promo, new_status, status, discontinued)
            SELECT
                pup.sku AS parent_sku,
                pup.sku AS child_sku,
                COALESCE(dds.delivery_method, '3 - 5 Days Economy (Self Assembly)') AS delivery_option,
                0 AS delivery_price,
                pup.rrp,
                pup.standard_invoice_cost,
                CASE
                    WHEN pup.promotion_available = 1 AND pup.promo_cost IS NOT NULL AND pup.promo_cost > 0 
                    THEN pup.promo_cost
                    ELSE pup.standard_invoice_cost
                END AS cost,
                CASE
                    WHEN pup.promotion_available = 1 AND pup.promo_cost IS NOT NULL AND pup.promo_cost > 0 
                    THEN pup.promo_cost
                    ELSE NULL
                END AS promo_cost,
                CASE
                    WHEN pup.promotion_available = 1 THEN pup.promo_code
                    ELSE NULL
                END AS promo_code,
                pup.promotion_available AS has_promo,
                pup.new_status,
                pup.status,
                0 AS discontinued
            FROM {$this->wpdb->prefix}npm_pup_updates pup
            LEFT JOIN {$delivery_std_table} dds ON pup.sku = dds.sku
            WHERE pup.standard_invoice_cost IS NOT NULL AND pup.standard_invoice_cost > 0
            ON DUPLICATE KEY UPDATE
                delivery_option=VALUES(delivery_option),
                rrp=VALUES(rrp),
                standard_invoice_cost=VALUES(standard_invoice_cost),
                cost=VALUES(cost),
                promo_cost=VALUES(promo_cost),
                promo_code=VALUES(promo_code),
                has_promo=VALUES(has_promo),
                new_status=VALUES(new_status),
                status=VALUES(status)
        ");
        
        return $inserted !== false ? $inserted : 0;
    }
    
    /**
     * Insert child SKU variations from supplier dataset tables
     */
    private function insert_child_sku_variations() {
        $total_inserted = 0;
        
        foreach ($this->dataset_tables as $category => $table) {
            // Check if table exists
            $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$table_exists) {
                error_log("NPM: Dataset table {$table} not found, skipping {$category}");
                continue;
            }
            
            foreach ($this->delivery_options as $delivery) {
                $inserted = $this->wpdb->query("
                    INSERT INTO {$this->wpdb->prefix}npm_child_products
                    (parent_sku, child_sku, delivery_option, delivery_price, rrp, standard_invoice_cost, cost,
                     promo_cost, promo_code, has_promo, new_status, status, discontinued)
                    SELECT
                        pup.sku AS parent_sku,
                        CONCAT(pup.sku, '{$delivery['suffix']}') AS child_sku,
                        '{$delivery['name']}' AS delivery_option,
                        ds.{$delivery['cost_col']} AS delivery_price,
                        pup.rrp,
                        pup.standard_invoice_cost,
                        CASE
                            WHEN pup.promotion_available = 1 AND pup.promo_cost IS NOT NULL AND pup.promo_cost > 0 
                            THEN pup.promo_cost + ds.{$delivery['cost_col']}
                            ELSE pup.standard_invoice_cost + ds.{$delivery['cost_col']}
                        END AS cost,
                        CASE
                            WHEN pup.promotion_available = 1 AND pup.promo_cost IS NOT NULL AND pup.promo_cost > 0 
                            THEN pup.promo_cost
                            ELSE NULL
                        END AS promo_cost,
                        CASE
                            WHEN pup.promotion_available = 1 THEN pup.promo_code
                            ELSE NULL
                        END AS promo_code,
                        pup.promotion_available AS has_promo,
                        pup.new_status,
                        pup.status,
                        0 AS discontinued
                    FROM {$this->wpdb->prefix}npm_pup_updates pup
                    INNER JOIN {$table} ds ON pup.sku = ds.SKU
                    WHERE ds.{$delivery['cost_col']} IS NOT NULL 
                      AND ds.{$delivery['cost_col']} > 0
                    ON DUPLICATE KEY UPDATE
                        delivery_price=VALUES(delivery_price),
                        rrp=VALUES(rrp),
                        standard_invoice_cost=VALUES(standard_invoice_cost),
                        cost=VALUES(cost),
                        promo_cost=VALUES(promo_cost),
                        promo_code=VALUES(promo_code),
                        has_promo=VALUES(has_promo),
                        new_status=VALUES(new_status),
                        status=VALUES(status)
                ");
                
                if ($inserted !== false && $inserted > 0) {
                    $total_inserted += $inserted;
                    error_log("NPM: Inserted {$inserted} {$delivery['suffix']} variants for {$category}");
                }
            }
        }
        
        return $total_inserted;
    }
    
    /**
     * Link NetSuite internal IDs to child products
     */
    private function link_netsuite_ids() {
        $updated = $this->wpdb->query("
            UPDATE {$this->wpdb->prefix}npm_child_products cp
            INNER JOIN {$this->wpdb->prefix}npm_netsuite_dump ns
                ON TRIM(LOWER(cp.child_sku)) = TRIM(LOWER(SUBSTRING_INDEX(ns.name, ':', -1)))
            SET cp.netsuite_internal_id = ns.internal_id
            WHERE cp.netsuite_internal_id IS NULL
        ");
        
        error_log("NPM: Linked {$updated} NetSuite internal IDs");
        return $updated;
    }
    
    /**
     * Mark discontinued products (base SKU + all variants)
     */
    private function mark_discontinued_products() {
        // Mark exact matches
        $exact = $this->wpdb->query("
            UPDATE {$this->wpdb->prefix}npm_child_products cp
            INNER JOIN {$this->wpdb->prefix}npm_discontinued_skus ds
                ON cp.child_sku = ds.discontinued_sku
            SET cp.discontinued = 1
        ");
        
        // Mark suffix variations (e.g., BS0003 → BS0003ND, BS0003DI, etc.)
        $variants = $this->wpdb->query("
            UPDATE {$this->wpdb->prefix}npm_child_products cp
            INNER JOIN {$this->wpdb->prefix}npm_discontinued_skus ds
                ON cp.child_sku LIKE CONCAT(ds.discontinued_sku, '%')
                AND cp.child_sku != ds.discontinued_sku
            SET cp.discontinued = 1
        ");
        
        $total = $exact + $variants;
        error_log("NPM: Marked {$total} products as discontinued (exact: {$exact}, variants: {$variants})");
        
        return $total;
    }
    
    /**
     * Update parent SKU manager with child counts
     */
    private function update_parent_manager() {
        // Insert/update parent SKUs
        $this->wpdb->query("
            INSERT INTO {$this->wpdb->prefix}npm_parent_sku_manager 
            (parent_sku, product_name, category, child_count)
            SELECT 
                cp.parent_sku,
                pup.product_name,
                pup.category,
                COUNT(*) as child_count
            FROM {$this->wpdb->prefix}npm_child_products cp
            LEFT JOIN {$this->wpdb->prefix}npm_pup_updates pup ON cp.parent_sku = pup.sku
            GROUP BY cp.parent_sku
            ON DUPLICATE KEY UPDATE
                child_count = VALUES(child_count),
                product_name = VALUES(product_name),
                category = VALUES(category)
        ");
    }
    
    /**
     * Identify unmatched SKUs (in PUP but not in NetSuite)
     */
    public function identify_unmatched_skus() {
        // Clear existing
        $this->wpdb->query("TRUNCATE TABLE {$this->wpdb->prefix}npm_unmatched_skus");
        
        // Find PUP SKUs without NetSuite match
        $inserted = $this->wpdb->query("
            INSERT INTO {$this->wpdb->prefix}npm_unmatched_skus (sku, product_name, source)
            SELECT 
                pup.sku,
                pup.product_name,
                'pup' as source
            FROM {$this->wpdb->prefix}npm_pup_updates pup
            LEFT JOIN {$this->wpdb->prefix}npm_netsuite_dump ns 
                ON pup.sku = ns.sku_suffix
            WHERE ns.id IS NULL
        ");
        
        return $inserted !== false ? $inserted : 0;
    }
}