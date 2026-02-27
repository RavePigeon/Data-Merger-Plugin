<?php
/**
 * Importer Class
 * Handles CSV imports for NetSuite, PUP, and Discontinued SKUs
 */

if (!defined('ABSPATH')) {
    exit;
}

class NPM_Importer {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Import NetSuite CSV - Simplified version
     */
    public function import_netsuite_csv($file_path) {
        $table = $this->wpdb->prefix . 'npm_netsuite_dump';
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Cannot open file'];
        }
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => false, 'error' => 'Empty CSV file'];
        }
        
        // Remove BOM if present
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map('trim', $headers);
        
        // Map headers to database columns
        $column_map = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(str_replace([' ', '&'], ['_', 'and'], $header));
            
            $mappings = [
                'internal_id' => 'internal_id',
                'name' => 'name',
                'disabled_in_magento' => 'disabled_in_magento',
                'delivery_and_install' => 'delivery_install',
                'delivery_install' => 'delivery_install',
                'manufacturer' => 'manufacturer',
                'class' => 'class',
                'parent' => 'parent',
                'purchase_price' => 'purchase_price',
                'online_customer_price' => 'online_customer_price',
                'rrp' => 'rrp',
                'matrix_item' => 'matrix_item',
                'store_display_name' => 'store_display_name',
                'store_description' => 'store_description',
                'detailed_description' => 'detailed_description',
            ];
            
            if (isset($mappings[$normalized])) {
                $column_map[$index] = $mappings[$normalized];
            }
        }
        
        if (!in_array('internal_id', $column_map) || !in_array('name', $column_map)) {
            fclose($handle);
            return ['success' => false, 'error' => 'Missing required columns: Internal ID and Name'];
        }
        
        // Truncate table
        $this->wpdb->query("TRUNCATE TABLE {$table}");
        
        // Import rows
        $imported = 0;
        $errors = [];
        $line_number = 1;
        
        while (($row = fgetcsv($handle)) !== false) {
            $line_number++;
            
            $data = [];
            foreach ($column_map as $csv_index => $db_column) {
                $value = isset($row[$csv_index]) ? trim($row[$csv_index]) : '';
                
                // Convert scientific notation
                if (in_array($db_column, ['internal_id', 'name', 'parent']) && preg_match('/^[0-9.]+[eE][+-]?[0-9]+$/', $value)) {
                    $value = sprintf('%.0f', floatval($value));
                }
                
                // Convert empty strings to NULL for numeric columns
                if (in_array($db_column, ['purchase_price', 'online_customer_price', 'rrp']) && $value === '') {
                    $value = null;
                }
                
                $data[$db_column] = $value;
            }
            
            if (empty($data['internal_id'])) {
                continue;
            }
            
            $result = $this->wpdb->insert($table, $data);
            
            if ($result) {
                $imported++;
            } else {
                if (count($errors) < 10) {
                    $errors[] = "Line {$line_number}: " . $this->wpdb->last_error;
                }
            }
        }
        
        fclose($handle);
        
        // Extract SKU suffixes
        $this->wpdb->query("
            UPDATE {$table}
            SET sku_suffix = CASE
                WHEN name LIKE '%:%' THEN TRIM(SUBSTRING_INDEX(name, ':', -1))
                ELSE TRIM(name)
            END
            WHERE sku_suffix IS NULL OR sku_suffix = ''
        ");
        
        return [
            'success' => true,
            'inserted' => $imported,
            'updated' => 0,
            'skipped' => 0,
            'errors' => $errors
        ];
    }
    
    /**
     * Universal CSV import handler
     */
    public function import_csv($file_path, $table_name, $options = []) {
        $defaults = [
            'truncate' => true,
            'unique_key' => null,
            'skip_duplicates' => false,
            'batch_size' => 500,
        ];
        
        $options = array_merge($defaults, $options);
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Unable to open CSV file'];
        }
        
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'error' => 'CSV file is empty'];
        }
        
        // Clean BOM and normalize headers
        $header = array_map(function($h) {
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $h), '_'));
        }, $header);
        
        // Get existing table columns
        $existing_cols = $this->wpdb->get_col("SHOW COLUMNS FROM {$table_name}");
        $existing_cols_lc = array_map('strtolower', $existing_cols);
        
        // Map CSV columns to DB columns
        $col_map = [];
        foreach ($header as $i => $col) {
            if ($col === '' || in_array($col, ['id', 'primary'])) {
                continue;
            }
            
            if (in_array($col, $existing_cols_lc)) {
                $col_map[$i] = $existing_cols[array_search($col, $existing_cols_lc)];
            }
        }
        
        // Truncate if requested
        if ($options['truncate']) {
            $this->wpdb->query("TRUNCATE TABLE {$table_name}");
        }
        
        // Import data
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            $has_data = false;
            
            foreach ($col_map as $i => $dbcol) {
                $value = isset($row[$i]) ? trim($row[$i]) : null;
                
                // Convert scientific notation for SKU fields
                if (in_array($dbcol, ['sku', 'discontinued_sku']) && !empty($value) && preg_match('/^[0-9.]+[eE][+-]?[0-9]+$/', $value)) {
                    $value = sprintf('%.0f', floatval($value));
                }
                
                $data[$dbcol] = $value;
                
                if ($value !== null && $value !== '') {
                    $has_data = true;
                }
            }
            
            if (!$has_data) {
                continue;
            }
            
            // Handle duplicates
            if ($options['unique_key'] && isset($data[$options['unique_key']])) {
                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE `{$options['unique_key']}` = %s",
                    $data[$options['unique_key']]
                ));
                
                if ($exists > 0) {
                    if ($options['skip_duplicates']) {
                        $skipped++;
                        continue;
                    } else {
                        $result = $this->wpdb->update(
                            $table_name,
                            $data,
                            [$options['unique_key'] => $data[$options['unique_key']]]
                        );
                        if ($result !== false) {
                            $updated++;
                        }
                        continue;
                    }
                }
            }
            
            $result = $this->wpdb->insert($table_name, $data);
            
            if ($result) {
                $inserted++;
            } else {
                if (count($errors) < 10) {
                    $errors[] = [
                        'row' => $inserted + $updated + $skipped + 1,
                        'error' => $this->wpdb->last_error
                    ];
                }
            }
        }
        
        fclose($handle);
        
        return [
            'success' => true,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
    
/**
 * Import PUP CSV with custom column mapping
 */
public function import_pup_csv($file_path) {
    $table = $this->wpdb->prefix . 'npm_pup_updates';
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Cannot open file'];
    }
    
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['success' => false, 'error' => 'Empty CSV file'];
    }
    
    // Remove BOM
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $headers = array_map('trim', $headers);
    
    // Custom column mapping for PUP CSV
    $column_map = [];
    foreach ($headers as $index => $header) {
        $normalized = strtolower($header);
        $normalized = str_replace([' ', '-', '(', ')', '+'], '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);
        $normalized = trim($normalized, '_');
        
        // Map CSV columns to database columns
        $mappings = [
            'sku' => 'sku',
            'catalogue_code' => 'catalogue_code',
            'category' => 'category',
            'sub_category' => 'sub_category',
            'product_name' => 'product_name',
            'rrp' => 'rrp',
            'standard_invoice_cost' => 'standard_invoice_cost',
            'invoice_cost_4_items' => 'invoice_cost_4plus',
            'next_day_cost_not_available_if_blank' => 'next_day_cost',
            '7_10_day_install_cost' => 'install_cost_7_10',
            '3_5_day_prebuild_cost' => 'prebuild_cost_3_5',
            'next_day_prebuild_cost' => 'prebuild_cost_next_day',
            'new' => 'new_status',
            'status' => 'status',
            'promotion_available' => 'promotion_available',
            'promo_code' => 'promo_code',
            'promo_cost' => 'promo_cost',
            'promo_minimum_order_spend' => 'promo_min_order_spend',
            'promo_surcharge_orders_under_minimum' => 'promo_surcharge',
            'promo_cost_plus_surcharge' => 'promo_cost_plus_surcharge',
        ];
        
        if (isset($mappings[$normalized])) {
            $column_map[$index] = $mappings[$normalized];
        }
    }
    
    if (!in_array('sku', $column_map)) {
        fclose($handle);
        return ['success' => false, 'error' => 'Missing required column: SKU'];
    }
    
    // Truncate table
    $this->wpdb->query("TRUNCATE TABLE {$table}");
    
    // Import rows
    $imported = 0;
    $errors = [];
    $line_number = 1;
    
    while (($row = fgetcsv($handle)) !== false) {
        $line_number++;
        
        $data = [];
        foreach ($column_map as $csv_index => $db_column) {
            $value = isset($row[$csv_index]) ? trim($row[$csv_index]) : '';
            
            // Convert empty strings to NULL for numeric columns
            if (in_array($db_column, ['rrp', 'standard_invoice_cost', 'invoice_cost_4plus', 'next_day_cost', 'install_cost_7_10', 'prebuild_cost_3_5', 'prebuild_cost_next_day', 'promo_cost', 'promo_cost_plus_surcharge']) && $value === '') {
                $value = null;
            }
            
            // Convert promotion_available to boolean
            if ($db_column === 'promotion_available') {
                $value = in_array(strtolower($value), ['1', 'yes', 'true', 'y']) ? 1 : 0;
            }
            
            $data[$db_column] = $value;
        }
        
        if (empty($data['sku'])) {
            continue;
        }
        
        $result = $this->wpdb->insert($table, $data);
        
        if ($result) {
            $imported++;
        } else {
            if (count($errors) < 10) {
                $errors[] = "Line {$line_number}: " . $this->wpdb->last_error;
            }
        }
    }
    
    fclose($handle);
    
    return [
        'success' => true,
        'inserted' => $imported,
        'updated' => 0,
        'skipped' => 0,
        'errors' => $errors
    ];
}
/**
 * Import Discontinued SKUs CSV
 * Handles parent SKUs and creates entries for all child SKU combinations
 */
public function import_discontinued_csv($file_path) {
    $discontinued_table = $this->wpdb->prefix . 'npm_discontinued_skus';
    $netsuite_table = $this->wpdb->prefix . 'npm_netsuite_dump';
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Cannot open file'];
    }
    
    $first_line = fgetcsv($handle);
    if (!$first_line) {
        fclose($handle);
        return ['success' => false, 'error' => 'Empty CSV file'];
    }
    
    // Remove BOM
    $first_line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $first_line[0]);
    $first_value = trim($first_line[0]);
    
    // Check if first row is a header
    $is_header = in_array(strtolower($first_value), ['sku', 'discontinued', 'discontinued_sku', 'id', 'parent']);
    
    // Collect all parent SKUs from CSV
    $parent_skus = [];
    
    // If first row is NOT a header, add it
    if (!$is_header && !empty($first_value)) {
        $parent_skus[] = $first_value;
    }
    
    // Read all remaining rows
    while (($row = fgetcsv($handle)) !== false) {
        $sku = isset($row[0]) ? trim($row[0]) : '';
        if (!empty($sku)) {
            $parent_skus[] = $sku;
        }
    }
    
    fclose($handle);
    
    if (empty($parent_skus)) {
        return ['success' => false, 'error' => 'No SKUs found in CSV'];
    }
    
    // Truncate discontinued table
    $this->wpdb->query("TRUNCATE TABLE {$discontinued_table}");
    
    // For each parent SKU, find all child SKUs and mark them as discontinued
    $imported = 0;
    $errors = [];
    $parent_count = 0;
    
    foreach ($parent_skus as $parent_sku) {
        $parent_count++;
        
        // First, add the parent SKU itself (in case it's a standalone product)
        $result = $this->wpdb->insert($discontinued_table, [
            'discontinued_sku' => $parent_sku,
            'internal_id' => $parent_sku
        ]);
        
        if ($result) {
            $imported++;
        }
        
        // Find all child products with this parent
        $children = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT internal_id, sku_suffix 
             FROM {$netsuite_table} 
             WHERE parent = %s 
             AND sku_suffix IS NOT NULL 
             AND sku_suffix != ''",
            $parent_sku
        ), ARRAY_A);
        
        // Add each child SKU to discontinued table
        foreach ($children as $child) {
            $child_sku = $child['internal_id'] . $child['sku_suffix'];
            
            $result = $this->wpdb->insert($discontinued_table, [
                'discontinued_sku' => $child_sku,
                'internal_id' => $child['internal_id']
            ]);
            
            if ($result) {
                $imported++;
            } else {
                if (count($errors) < 10) {
                    $errors[] = "Failed to insert child SKU: {$child_sku} - " . $this->wpdb->last_error;
                }
            }
        }
    }
    
    return [
        'success' => true,
        'inserted' => $imported,
        'parent_skus' => $parent_count,
        'updated' => 0,
        'skipped' => 0,
        'errors' => $errors
    ];
}
    /**
     * Get import statistics
     */
    public function get_last_import_stats($type) {
        $stats = [
            'count' => 0,
            'last_import' => null,
        ];
        
        switch ($type) {
            case 'netsuite':
                $table = $this->wpdb->prefix . 'npm_netsuite_dump';
                $date_col = 'imported_at';
                break;
            case 'pup':
                $table = $this->wpdb->prefix . 'npm_pup_updates';
                $date_col = 'imported_at';
                break;
            case 'discontinued':
                $table = $this->wpdb->prefix . 'npm_discontinued_skus';
                $date_col = 'uploaded_at';
                break;
            default:
                return $stats;
        }
        
        $stats['count'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $stats['last_import'] = $this->wpdb->get_var("SELECT MAX({$date_col}) FROM {$table}");
        
        return $stats;
    }
}