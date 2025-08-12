<?php
/*
Plugin Name: !Data Merger Plugin
Description: Merges NetSuite and PUP data, manages delivery costs, supports discontinued SKUs, etc.
*/

require_once plugin_dir_path(__FILE__) . 'includes/class-data-merger-plugin.php';
require_once plugin_dir_path(__FILE__) . 'admin-delivery-costs.php';
require_once plugin_dir_path(__FILE__) . 'import-pup-child-sku-price.php';
// The export class is now handled in the main plugin class with Export Merged and Obsolete SKUs features.
// require_once plugin_dir_path(__FILE__) . 'class-data-merger-export.php';

register_activation_hook(__FILE__, ['Data_Merger_Plugin', 'activate']);

new Data_Merger_Plugin();