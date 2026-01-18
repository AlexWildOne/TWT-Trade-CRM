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
    // DB
    TWT_TCRM_DB::maybe_upgrade();

    // CPTs e Roles
    TWT_TCRM_CPT::register();
    TWT_TCRM_CPT_Locations::register();
    TWT_TCRM_Roles::maybe_register_runtime_caps();

    // Permitir HTML rico em templates (apenas twt_email_template)
    if (class_exists('TWT_TCRM_Email_Kses')) {
      TWT_TCRM_Email_Kses::boot();
    }

    // Boot modules que registam hooks
    TWT_TCRM_Public::boot();
    TWT_TCRM_Forms::boot();
    TWT_TCRM_Form_Builder::boot();

    // Email shortcodes (front) + export CSV handler
    if (class_exists('TWT_TCRM_Email_Shortcodes')) {
      TWT_TCRM_Email_Shortcodes::boot();
    }
    if (class_exists('TWT_TCRM_Submissions_Shortcodes')) {
      TWT_TCRM_Submissions_Shortcodes::boot();
    }

    // Dashboards/Reports/Insights runtime
    TWT_TCRM_Dashboards::boot();
    TWT_TCRM_Reports::boot();
    TWT_TCRM_Insights::boot();

    // Shortcodes
    $this->register_shortcodes();

    // Admin boot
    if (is_admin()) {
      TWT_TCRM_Admin::boot();
      TWT_TCRM_Settings_Admin::boot();
      TWT_TCRM_Admin_Layouts::boot();
      TWT_TCRM_Locations_Admin::boot();

      // Insights UI (metaboxes)
      if (class_exists('TWT_TCRM_Insights_Admin')) {
        TWT_TCRM_Insights_Admin::boot();
      }

      // Submissions + Emails (menu/página)
      if (class_exists('TWT_TCRM_Submissions_Admin')) {
        TWT_TCRM_Submissions_Admin::boot();
      }
      if (class_exists('TWT_TCRM_Emails_Admin')) {
        TWT_TCRM_Emails_Admin::boot();
      }

      // Templates + Regras (CPT metaboxes)
      if (class_exists('TWT_TCRM_Email_Templates_Admin')) {
        TWT_TCRM_Email_Templates_Admin::boot();
      }
      if (class_exists('TWT_TCRM_Email_Rules_Admin')) {
        TWT_TCRM_Email_Rules_Admin::boot();
      }
    }
  }

  private function load_dependencies() {
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-db.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-security.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-roles.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-cpt-locations.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-email-kses.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'public/class-public.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'modules/reports/class-reports.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-form-renderer.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-forms.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/forms/class-form-builder.php';

    require_once TWT_TCRM_PLUGIN_DIR . 'modules/dashboards/class-dashboards.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/insights/class-insights.php';

    // Email engine (carregar sempre)
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/emails/class-email-service.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/emails/class-email-automations.php';

    // Shortcodes (front)
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/emails/class-email-shortcodes.php';
    require_once TWT_TCRM_PLUGIN_DIR . 'modules/submissions/class-submissions-shortcodes.php';

    if (is_admin()) {
      require_once TWT_TCRM_PLUGIN_DIR . 'includes/class-settings.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-admin.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-layouts.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-locations-admin.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-insights-admin.php';

      // menus Submissões + Emails
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-submissions-admin.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-emails-admin.php';

      // CPT metaboxes para templates e regras
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-email-templates-admin.php';
      require_once TWT_TCRM_PLUGIN_DIR . 'admin/class-email-rules-admin.php';
    }
  }

  private function register_shortcodes() {
    add_shortcode('twt_form', ['TWT_TCRM_Forms', 'shortcode_form']);
    add_shortcode('twt_assigned_forms', ['TWT_TCRM_Forms', 'shortcode_assigned_forms']);

    add_shortcode('twt_brand_dashboard', ['TWT_TCRM_Dashboards', 'shortcode_brand_dashboard']);
    add_shortcode('twt_user_dashboard', ['TWT_TCRM_Dashboards', 'shortcode_user_dashboard']);

    // envio manual de emails no front
    add_shortcode('twt_email_send', ['TWT_TCRM_Email_Shortcodes', 'shortcode_email_send']);

    // tabela + export CSV de submissões
    add_shortcode('twt_submissions_table', ['TWT_TCRM_Submissions_Shortcodes', 'shortcode_submissions_table']);
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
    // Base CSS (o teu existente)
    wp_enqueue_style(
      'twt-tcrm-public',
      TWT_TCRM_PLUGIN_URL . 'public/assets/public.css',
      [],
      TWT_TCRM_VERSION
    );

    // NEW: theme layer (tokens + components) por cima do public.css
    wp_enqueue_style(
      'twt-tcrm-theme',
      TWT_TCRM_PLUGIN_URL . 'public/assets/theme.css',
      ['twt-tcrm-public'],
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