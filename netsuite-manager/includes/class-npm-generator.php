<?php
/**
 * Product Generator for NetSuite Product Manager
 * Combines NetSuite and PUP data to create child products
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Generator {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Generate child products by combining NetSuite and PUP data
     * 
     * @param int $offset Starting position for batch processing
     * @param int $limit Number of records to process in this batch
     * @return array Result with success status and counts
     */
    public function generate_child_products($offset = 0, $limit = 100) {
        $netsuite_table = $this->wpdb->prefix . 'npm_netsuite_dump';
        $pup_table = $this->wpdb->prefix . 'npm_pup_updates';
        $discontinued_table = $this->wpdb->prefix . 'npm_discontinued_skus';
        $child_table = $this->wpdb->prefix . 'npm_child_products';

        // Get batch of NetSuite records with SKU suffixes
        $netsuite_records = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$netsuite_table} 
             WHERE sku_suffix IS NOT NULL 
             AND sku_suffix != ''
             ORDER BY internal_id, sku_suffix
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        if (empty($netsuite_records)) {
            return [
                'success' => true,
                'generated' => 0,
                'skipped' => 0,
                'errors' => []
            ];
        }

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($netsuite_records as $ns_record) {
            // Create full child SKU
            $child_sku = $ns_record['internal_id'] . $ns_record['sku_suffix'];
            
            // Check if discontinued
            $is_discontinued = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$discontinued_table} 
                 WHERE discontinued_sku = %s",
                $child_sku
            ));

            if ($is_discontinued) {
                $skipped++;
                continue;
            }

            // Get matching PUP data
            $pup_record = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$pup_table} 
                 WHERE sku = %s 
                 LIMIT 1",
                $child_sku
            ), ARRAY_A);

            // Prepare child product data
            $child_data = [
                'sku' => $child_sku,
                'parent_sku' => $ns_record['internal_id'],
                'sku_suffix' => $ns_record['sku_suffix'],
                'internal_id' => $ns_record['internal_id'],
                'name' => $ns_record['name'],
                'display_name' => $ns_record['display_name'],
                'product_name' => $pup_record['product_name'] ?? $ns_record['display_name'],
                'category' => $pup_record['category'] ?? null,
                'sub_category' => $pup_record['sub_category'] ?? null,
                'rrp' => $pup_record['rrp'] ?? $ns_record['rrp'],
                'online_price' => $ns_record['online_price'],
                'online_customer_price' => $ns_record['online_customer_price'],
                'purchase_price' => $ns_record['purchase_price'],
                'standard_invoice_cost' => $pup_record['standard_invoice_cost'] ?? null,
                'invoice_cost_4plus' => $pup_record['invoice_cost_4plus'] ?? null,
                'next_day_cost' => $pup_record['next_day_cost'] ?? null,
                'install_cost_7_10' => $pup_record['install_cost_7_10'] ?? null,
                'prebuild_cost_3_5' => $pup_record['prebuild_cost_3_5'] ?? null,
                'prebuild_cost_next_day' => $pup_record['prebuild_cost_next_day'] ?? null,
                'status' => $pup_record['status'] ?? 'Live',
                'delivery_install' => $ns_record['delivery_install'],
                'manufacturer' => $ns_record['manufacturer'],
                'class' => $ns_record['class'],
                'store_display_name' => $ns_record['store_display_name'],
                'store_description' => $ns_record['store_description'],
                'detailed_description' => $ns_record['detailed_description'],
                'is_discontinued' => 0,
                'has_pup_data' => !empty($pup_record) ? 1 : 0
            ];

            // Insert child product
            $result = $this->wpdb->insert($child_table, $child_data);

            if ($result) {
                $generated++;
            } else {
                if (count($errors) < 10) {
                    $errors[] = "Failed to insert SKU {$child_sku}: " . $this->wpdb->last_error;
                }
            }
        }

        return [
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Get generation statistics
     */
    public function get_stats() {
        $child_table = $this->wpdb->prefix . 'npm_child_products';
        $netsuite_table = $this->wpdb->prefix . 'npm_netsuite_dump';

        return [
            'total_child_products' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$child_table}"),
            'with_pup_data' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$child_table} WHERE has_pup_data = 1"),
            'without_pup_data' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$child_table} WHERE has_pup_data = 0"),
            'discontinued_count' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$child_table} WHERE is_discontinued = 1"),
            'source_records' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$netsuite_table} WHERE sku_suffix IS NOT NULL AND sku_suffix != ''"),
            'last_generated' => $this->wpdb->get_var("SELECT MAX(created_at) FROM {$child_table}")
        ];
    }

    /**
     * Clear all generated child products
     */
    public function clear_child_products() {
        $child_table = $this->wpdb->prefix . 'npm_child_products';
        $this->wpdb->query("TRUNCATE TABLE {$child_table}");
        
        return [
            'success' => true,
            'message' => 'All child products cleared'
        ];
    }
}