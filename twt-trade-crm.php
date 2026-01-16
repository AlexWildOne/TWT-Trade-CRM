<?php
/**
 * Plugin Name: TWT Trade CRM Reports
 * Description: CRM dinâmico de reports para equipas comerciais e trade marketing, com formulários, atribuições, dashboards e insights.
 * Version: 0.1.0
 * Author: The Wild Theory
 * Text Domain: twt-trade-crm
 */

if (!defined('ABSPATH')) {
  exit;
}

define('TWT_TCRM_VERSION', '0.1.0');
define('TWT_TCRM_PLUGIN_FILE', __FILE__);
define('TWT_TCRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWT_TCRM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-plugin.php';
require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-activator.php';
require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-deactivator.php';

register_activation_hook(__FILE__, ['TWT_TCRM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['TWT_TCRM_Deactivator', 'deactivate']);

function twt_tcrm_run() {
  $plugin = new TWT_TCRM_Plugin();
  $plugin->run();
}

twt_tcrm_run();
