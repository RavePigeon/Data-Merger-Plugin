<?php
/**
 * Exporter Class
 * Handles CSV exports for NetSuite updates and Magento specials
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Exporter {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Populate export queue from child products
     */
    public function populate_export_queue() {
        $export_table = $this->wpdb->prefix . 'npm_export_queue';
        
        // Clear existing queue
        $this->wpdb->query("TRUNCATE TABLE {$export_table}");
        
        // Populate from child products
        $inserted = $this->wpdb->query("
            INSERT INTO {$export_table} (
                netsuite_internal_id,
                product_code,
                parent,
                brand_name,
                discontinued,
                rrp,
                cost_price,
                online_price,
                promo_price,
                delivery_method,
                status,
                new_status,
                promo_code,
                has_promo,
                export_status
            )
            SELECT 
                cp.netsuite_internal_id,
                cp.child_sku AS product_code,
                cp.parent_sku AS parent,
                ns.preferred_supplier AS brand_name,
                cp.discontinued,
                cp.rrp,
                cp.cost AS cost_price,
                ROUND(cp.cost * 1.25, 2) AS online_price,
                cp.promo_cost AS promo_price,
                cp.delivery_option AS delivery_method,
                cp.status,
                cp.new_status,
                cp.promo_code,
                cp.has_promo,
                'pending' AS export_status
            FROM {$this->wpdb->prefix}npm_child_products cp
            LEFT JOIN {$this->wpdb->prefix}npm_netsuite_dump ns 
                ON cp.netsuite_internal_id = ns.internal_id
            WHERE cp.child_sku IS NOT NULL
        ");
        
        return $inserted !== false ? $inserted : 0;
    }
    
    /**
     * Export NetSuite update CSV
     */
    public function export_netsuite_update_csv() {
        set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        $table = $this->wpdb->prefix . 'npm_export_queue';
        $filename = 'netsuite_update_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'Internal ID',
            'SKU',
            'Parent SKU',
            'Brand',
            'RRP',
            'Cost Price',
            'Online Price',
            'Promo Price',
            'Discontinued',
            'Delivery Method',
            'Status',
            'New Status',
            'Promo Code',
            'Has Promo'
        ]);
        
        // Stream data in batches
        $batch_size = 500;
        $offset = 0;
        
        while (true) {
            $rows = $this->wpdb->get_results(
                "SELECT * FROM {$table} 
                 WHERE export_status = 'pending'
                 ORDER BY parent, product_code
                 LIMIT {$batch_size} OFFSET {$offset}",
                ARRAY_A
            );
            
            if (empty($rows)) {
                break;
            }
            
            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['netsuite_internal_id'],
                    $row['product_code'],
                    $row['parent'],
                    $row['brand_name'],
                    $row['rrp'],
                    $row['cost_price'],
                    $row['online_price'],
                    $row['promo_price'],
                    $row['discontinued'],
                    $row['delivery_method'],
                    $row['status'],
                    $row['new_status'],
                    $row['promo_code'],
                    $row['has_promo']
                ]);
            }
            
            $offset += $batch_size;
            unset($rows);
            
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
        
        fclose($output);
        
        // Mark as exported
        $this->wpdb->query("
            UPDATE {$table} 
            SET export_status = 'exported', exported_at = NOW() 
            WHERE export_status = 'pending'
        ");
        
        exit;
    }
    
    /**
     * Export Magento specials CSV
     */
    public function export_magento_specials_csv($multipliers = []) {
        $defaults = [
            'delivery_multiplier' => 1.10,
            'invoice_multiplier' => 1.25,
        ];
        
        $multipliers = array_merge($defaults, $multipliers);
        
        set_time_limit(600);
        
        $from_date = date('Y-m-d');
        $to_date = date('Y-m-d', strtotime('+90 days'));
        $filename = 'magento_special_prices_' . date('Ymd_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'sku',
            'special_price',
            'special_price_from_date',
            'special_price_to_date'
        ]);
        
        // Get products with promos
        $rows = $this->wpdb->get_results("
            SELECT 
                child_sku AS sku,
                promo_cost,
                delivery_price
            FROM {$this->wpdb->prefix}npm_child_products
            WHERE has_promo = 1
              AND promo_cost IS NOT NULL
              AND promo_cost > 0
        ", ARRAY_A);
        
        foreach ($rows as $row) {
            $promo_cost = floatval($row['promo_cost']);
            $delivery = floatval($row['delivery_price']);
            
            $special_price = round(
                ($promo_cost * $multipliers['invoice_multiplier']) + 
                ($delivery * $multipliers['delivery_multiplier']), 
                2
            );
            
            fputcsv($output, [
                $row['sku'],
                $special_price,
                $from_date,
                $to_date
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get export queue statistics
     */
    public function get_export_stats() {
        $table = $this->wpdb->prefix . 'npm_export_queue';
        
        return [
            'total' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'pending' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE export_status = 'pending'"),
            'exported' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE export_status = 'exported'"),
            'discontinued' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE discontinued = 1"),
            'with_netsuite_id' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE netsuite_internal_id IS NOT NULL"),
            'last_export' => $this->wpdb->get_var("SELECT MAX(exported_at) FROM {$table}"),
        ];
    }
    
    /**
     * Validation before export
     */
    public function validate_export_queue() {
        $errors = [];
        $warnings = [];
        
        $stats = $this->get_export_stats();
        
        // Check if queue is empty
        if ($stats['total'] == 0) {
            $errors[] = 'Export queue is empty. Run "Generate Export" first.';
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }
        
        // Check for missing NetSuite IDs
        $missing_percentage = (($stats['total'] - $stats['with_netsuite_id']) / $stats['total']) * 100;
        if ($missing_percentage > 50) {
            $errors[] = sprintf(
                '%d%% of products are missing NetSuite internal IDs. Cannot export.',
                round($missing_percentage)
            );
        } elseif ($missing_percentage > 10) {
            $warnings[] = sprintf(
                '%d%% of products are missing NetSuite internal IDs.',
                round($missing_percentage)
            );
        }
        
        // Check for zero prices
        $zero_price_count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}npm_export_queue 
             WHERE cost_price = 0 OR cost_price IS NULL"
        );
        
        if ($zero_price_count > 0) {
            $zero_percentage = ($zero_price_count / $stats['total']) * 100;
            if ($zero_percentage > 10) {
                $errors[] = sprintf(
                    '%d products have zero or null cost price.',
                    $zero_price_count
                );
            } else {
                $warnings[] = sprintf(
                    '%d products have zero or null cost price.',
                    $zero_price_count
                );
            }
        }
        
        // Check discontinued count
        if ($stats['discontinued'] > 100) {
            $warnings[] = sprintf(
                '%d products are marked as discontinued. Please verify this is correct.',
                $stats['discontinued']
            );
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}