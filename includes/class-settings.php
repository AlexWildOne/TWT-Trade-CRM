<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Settings_Admin {

  const PARENT_SLUG = 'twt-tcrm';
  const PAGE_SLUG = 'twt-tcrm-settings';

  const OPT_GMAPS_SERVER_KEY = 'twt_tcrm_gmaps_server_key';
  const OPT_GMAPS_BROWSER_KEY = 'twt_tcrm_gmaps_browser_key';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_menu'], 40);
    add_action('admin_init', [__CLASS__, 'register_settings']);
  }

  private static function cap_settings() {
    // Evita “menu invisível” quando estás logado com um user sem manage_brands
    if (current_user_can('twt_tcrm_manage_brands')) return 'twt_tcrm_manage_brands';
    return 'twt_tcrm_view_all_reports';
  }

  public static function register_menu() {
    add_submenu_page(
      self::PARENT_SLUG,
      'Definições',
      'Definições',
      self::cap_settings(),
      self::PAGE_SLUG,
      [__CLASS__, 'page_settings']
    );
  }

  public static function register_settings() {
    register_setting('twt_tcrm_settings', self::OPT_GMAPS_SERVER_KEY, [
      'type' => 'string',
      'sanitize_callback' => [__CLASS__, 'sanitize_key_field'],
      'default' => '',
    ]);

    register_setting('twt_tcrm_settings', self::OPT_GMAPS_BROWSER_KEY, [
      'type' => 'string',
      'sanitize_callback' => [__CLASS__, 'sanitize_key_field'],
      'default' => '',
    ]);
  }

  public static function sanitize_key_field($value) {
    $value = is_string($value) ? trim($value) : '';
    // API keys podem ter hífen, underscore, letras e números.
    $value = preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
    return $value;
  }

  public static function page_settings() {
    $cap = self::cap_settings();
    if (!current_user_can($cap)) {
      wp_die('Sem permissões.');
    }

    $server_key = (string) get_option(self::OPT_GMAPS_SERVER_KEY, '');
    $browser_key = (string) get_option(self::OPT_GMAPS_BROWSER_KEY, '');

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Definições</h1>';
    echo '<p>Configura as chaves do Google Maps. Mantemos duas por segurança: uma para o servidor (Geocoding) e outra para o browser (Maps JS e Places).</p>';

    echo '<form method="post" action="options.php">';
    settings_fields('twt_tcrm_settings');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_tcrm_gmaps_server_key">Google Maps, Server API Key</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_tcrm_gmaps_server_key" name="' . esc_attr(self::OPT_GMAPS_SERVER_KEY) . '" value="' . esc_attr($server_key) . '" class="regular-text" autocomplete="off" spellcheck="false">';
    echo '<p class="description">Usada no backend (PHP) para Geocoding API. Idealmente restrita por IP do servidor e com APIs limitadas.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_tcrm_gmaps_browser_key">Google Maps, Browser API Key</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_tcrm_gmaps_browser_key" name="' . esc_attr(self::OPT_GMAPS_BROWSER_KEY) . '" value="' . esc_attr($browser_key) . '" class="regular-text" autocomplete="off" spellcheck="false">';
    echo '<p class="description">Usada no BO (browser) para Maps JavaScript API e Places API. Restringe por HTTP referrers (domínio) e limita APIs.</p>';
    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';

    submit_button('Guardar alterações');

    echo '</form>';

    echo '<hr>';
    echo '<h2>Checklist Google Cloud</h2>';
    echo '<ol style="max-width:900px;">';
    echo '<li>Ativa as APIs: Geocoding API, Maps JavaScript API, Places API.</li>';
    echo '<li>Cria 2 API keys: 1 para server, 1 para browser.</li>';
    echo '<li>Restrições: browser key por HTTP referrers, server key por IP (se possível) e restringe as APIs permitidas.</li>';
    echo '</ol>';

    echo '</div>';
  }

  public static function get_gmaps_server_key() {
    return (string) get_option(self::OPT_GMAPS_SERVER_KEY, '');
  }

  public static function get_gmaps_browser_key() {
    return (string) get_option(self::OPT_GMAPS_BROWSER_KEY, '');
  }
}

TWT_TCRM_Settings_Admin::boot();
