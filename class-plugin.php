<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Plugin {

  public function run() {
    $this->load_dependencies();

    add_action('plugins_loaded', [$this, 'load_textdomain']);
    add_action('init', [$this, 'init']);

    if (is_admin()) {
      add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    } else {
      add_action('wp_enqueue_scripts', [$this, 'public_assets']);
    }
  }

  public function load_textdomain() {
    load_plugin_textdomain(
      'twt-trade-crm',
      false,
      dirname(plugin_basename(TWT_TCRM_PLUGIN_FILE)) . '/languages/'
    );
  }

  public function init() {

    // Core
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-roles.php';

    // Locations CPT (com Google Maps BO)
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt-locations.php';

    // DB
    TWT_TCRM_DB::maybe_upgrade();

    // CPTs e Roles
    TWT_TCRM_CPT::register();
    TWT_TCRM_Roles::maybe_register_runtime_caps();

    // Shortcodes
    $this->register_shortcodes();

    // Admin bootstrap
    if (is_admin()) {
      TWT_TCRM_Admin::boot();
    }
  }

  private function load_dependencies() {

    /**
     * CORE
     */
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-db.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-security.php';
    

    /**
     * PUBLIC
     */
    require_once TWT_TCRM_PLUGIN_DIR . 'public/class-public.php';

    /**
     * MODULES
     */
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/reports/class-reports.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-form-renderer.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-forms.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-form-builder.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'modules/dashboards/class-dashboards.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/insights/class-insights.php';

    /**
     * ADMIN
     */
    if (is_admin()) {

      // Menu pai
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-admin.php';

      // Definições globais (Google Maps keys, etc)
      require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-settings.php';

      // Layouts (forms)
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-layouts.php';

      // Lojas / Locais + atribuições
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-locations-admin.php';
      
    }
  }

  private function register_shortcodes() {
    add_shortcode('twt_form', ['TWT_TCRM_Forms', 'shortcode_form']);
    add_shortcode('twt_assigned_forms', ['TWT_TCRM_Forms', 'shortcode_assigned_forms']);

    add_shortcode('twt_brand_dashboard', ['TWT_TCRM_Dashboards', 'shortcode_brand_dashboard']);
    add_shortcode('twt_user_dashboard', ['TWT_TCRM_Dashboards', 'shortcode_user_dashboard']);
  }

  public function admin_assets() {
    wp_enqueue_style(
      'twt-tcrm-admin',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/admin.css',
      [],
      TWT_TCRM_VERSION
    );

    wp_enqueue_script(
      'twt-tcrm-admin',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/admin.js',
      ['jquery'],
      TWT_TCRM_VERSION,
      true
    );
  }

  public function public_assets() {
    wp_enqueue_style(
      'twt-tcrm-public',
      TWT_TCRM_PLUGIN_URL . 'public/assets/public.css',
      [],
      TWT_TCRM_VERSION
    );

    wp_enqueue_script(
      'twt-tcrm-public',
      TWT_TCRM_PLUGIN_URL . 'public/assets/public.js',
      ['jquery'],
      TWT_TCRM_VERSION,
      true
    );
  }
}
