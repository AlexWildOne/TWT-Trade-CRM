<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Activator {

  public static function activate() {
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-db.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-roles.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt.php';

    TWT_TCRM_DB::create_tables();
    TWT_TCRM_Roles::add_roles_caps();

    // Registar CPTs e flush rules
    TWT_TCRM_CPT::register();
    flush_rewrite_rules();
  }
}
