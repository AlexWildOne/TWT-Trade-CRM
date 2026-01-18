<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Activator {

  public static function activate() {
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-db.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-roles.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt-locations.php';

    // DB
    if (method_exists('TWT_TCRM_DB', 'create_tables')) {
      TWT_TCRM_DB::create_tables();
    }
    if (method_exists('TWT_TCRM_DB', 'maybe_upgrade')) {
      TWT_TCRM_DB::maybe_upgrade();
    }

    // Roles / caps
    TWT_TCRM_Roles::add_roles_caps();

    // Registar CPTs antes do flush
    TWT_TCRM_CPT::register();
    if (class_exists('TWT_TCRM_CPT_Locations') && method_exists('TWT_TCRM_CPT_Locations', 'register')) {
      TWT_TCRM_CPT_Locations::register();
    }

    flush_rewrite_rules();
  }
}