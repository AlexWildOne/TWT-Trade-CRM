<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Locations_GMaps {

  public static function boot() {
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
  }

  public static function enqueue($hook) {
    if (!is_admin()) return;

    // Só em ecrãs de edição
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || empty($screen->post_type) || $screen->post_type !== 'twt_location') return;

    if (!class_exists('TWT_TCRM_Settings_Admin')) return;

    $key = TWT_TCRM_Settings_Admin::get_gmaps_browser_key();
    $key = is_string($key) ? trim($key) : '';
    if ($key === '') return;

    // Carrega Google Maps JS com Places
    $gmaps_url = add_query_arg([
      'key' => $key,
      'libraries' => 'places',
      'v' => 'weekly',
    ], 'https://maps.googleapis.com/maps/api/js');

    // Google, sem dependências, no footer
    wp_enqueue_script('twt-tcrm-gmaps', $gmaps_url, [], null, true);

    // O teu JS que inicializa o Autocomplete
    wp_enqueue_script(
      'twt-tcrm-locations-gmaps',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/locations-gmaps.js',
      ['twt-tcrm-gmaps'],
      TWT_TCRM_VERSION,
      true
    );
  }
}
