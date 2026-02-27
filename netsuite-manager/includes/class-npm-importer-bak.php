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
        
        // Parse header
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
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
            $this->wpdb->query("TRUNCATE TABLE {$table_name}");
            $this->wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
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
            
            // Insert new record
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
     * Import NetSuite CSV
     */
    public function import_netsuite_csv($file_path) {
        $table = $this->wpdb->prefix . 'npm_netsuite_dump';
        
        $result = $this->import_csv($file_path, $table, [
            'truncate' => true,
            'unique_key' => 'internal_id',
        ]);
        
        if ($result['success']) {
            // Auto-extract SKU suffixes
            $this->extract_sku_suffixes();
        }
        
        return $result;
    }
    
    /**
     * Extract SKU suffixes from name field
     */
    private function extract_sku_suffixes() {
        $updated = $this->wpdb->query("
            UPDATE {$this->wpdb->prefix}npm_netsuite_dump
            SET sku_suffix = CASE
                WHEN name LIKE '%:%' THEN TRIM(SUBSTRING_INDEX(name, ':', -1))
                ELSE TRIM(name)
            END
            WHERE sku_suffix IS NULL OR sku_suffix = ''
        ");
        
        error_log("NPM: Extracted {$updated} SKU suffixes");
        return $updated;
    }
    
    /**
     * Import PUP CSV
     */
    public function import_pup_csv($file_path) {
        $table = $this->wpdb->prefix . 'npm_pup_updates';
        
        return $this->import_csv($file_path, $table, [
            'truncate' => true,
            'unique_key' => 'sku',
        ]);
    }
    
    /**
     * Import discontinued SKUs CSV
     */
    public function import_discontinued_csv($file_path) {
        $table = $this->wpdb->prefix . 'npm_discontinued_skus';
        
        return $this->import_csv($file_path, $table, [
            'truncate' => false,
            'unique_key' => 'discontinued_sku',
            'skip_duplicates' => true,
        ]);
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