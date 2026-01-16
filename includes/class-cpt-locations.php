<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_CPT_Locations {

  public static function register() {
    self::register_location();

    add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
    add_action('save_post_twt_location', [__CLASS__, 'save_metaboxes'], 10, 2);

    add_filter('manage_twt_location_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_twt_location_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);

    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
  }

  private static function register_location() {
    register_post_type('twt_location', [
      'labels' => [
        'name' => 'Lojas/Locais',
        'singular_name' => 'Loja/Local',
        'add_new_item' => 'Adicionar Loja/Local',
        'edit_item' => 'Editar Loja/Local',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_icon' => 'dashicons-location',
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  public static function enqueue_admin_assets($hook) {
    if (!is_admin()) return;
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || empty($screen->post_type) || $screen->post_type !== 'twt_location') return;

    wp_enqueue_style(
      'twt-tcrm-locations-admin',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/locations.css',
      [],
      TWT_TCRM_VERSION
    );

    wp_enqueue_script(
      'twt-tcrm-locations-admin',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/locations.js',
      ['jquery'],
      TWT_TCRM_VERSION,
      true
    );

    $browser_key = class_exists('TWT_TCRM_Settings')
      ? get_option(TWT_TCRM_Settings::OPTION_GMAPS_BROWSER_KEY, '')
      : get_option('twt_tcrm_gmaps_browser_key', '');

    wp_localize_script('twt-tcrm-locations-admin', 'TWT_TCRM_LOC', [
      'browserKey' => (string) $browser_key,
      'hasKey' => $browser_key ? 1 : 0,
      'i18n' => [
        'missingKey' => 'Falta a Browser key do Google Maps. Vai a TWT CRM, Definições, Google Maps.',
      ],
    ]);

    if ($browser_key) {
      $src = add_query_arg([
        'key' => rawurlencode($browser_key),
        'libraries' => 'places',
        'callback' => 'initTwtTcrmLocationMap',
      ], 'https://maps.googleapis.com/maps/api/js');

      wp_enqueue_script(
        'twt-tcrm-google-maps',
        $src,
        [],
        null,
        true
      );
    }
  }

  public static function add_metaboxes() {
    add_meta_box(
      'twt_tcrm_location_meta',
      'TWT CRM, Dados do Local',
      [__CLASS__, 'render_location_metabox'],
      'twt_location',
      'normal',
      'high'
    );

    add_meta_box(
      'twt_tcrm_location_map',
      'TWT CRM, Mapa e Autocomplete',
      [__CLASS__, 'render_map_metabox'],
      'twt_location',
      'normal',
      'default'
    );
  }

  public static function render_location_metabox($post) {
    wp_nonce_field('twt_tcrm_save_location', 'twt_tcrm_location_nonce');

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($post->ID, 'twt_campaign_id', true);

    $address = (string) get_post_meta($post->ID, 'twt_location_address', true);

    $radius = (int) get_post_meta($post->ID, 'twt_location_radius_m', true);
    if ($radius <= 0) $radius = 80;

    $status = (string) get_post_meta($post->ID, 'twt_location_status', true);
    if (!$status) $status = 'active';

    $brands = get_posts([
      'post_type' => 'twt_brand',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $campaigns = get_posts([
      'post_type' => 'twt_campaign',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Estado</th><td>';
    echo '<select name="twt_location_status" style="min-width:220px;">';
    echo '<option value="active"' . selected($status, 'active', false) . '>Activo</option>';
    echo '<option value="inactive"' . selected($status, 'inactive', false) . '>Inactivo</option>';
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Marca</th><td>';
    echo '<select name="twt_brand_id" style="min-width:320px;">';
    echo '<option value="0">Sem marca</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '"' . selected($brand_id, (int)$b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Campanha</th><td>';
    echo '<select name="twt_campaign_id" style="min-width:320px;">';
    echo '<option value="0">Sem campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr($c->ID) . '"' . selected($campaign_id, (int)$c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Morada</th><td>';
    echo '<input id="twt_location_address" type="text" name="twt_location_address" value="' . esc_attr($address) . '" style="width:100%;" placeholder="Começa a escrever e escolhe no dropdown">';
    echo '<p class="description">Com Autocomplete activo, escolher uma morada preenche latitude, longitude e place_id.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Raio (metros)</th><td>';
    echo '<input id="twt_location_radius_m" type="number" name="twt_location_radius_m" value="' . esc_attr($radius) . '" min="10" max="2000" step="1" style="width:140px;">';
    echo '<p class="description">Isto desenha um círculo no mapa e vai ser usado na validação do check-in.</p>';
    echo '</td></tr>';

    $public_pick_url = home_url('/twt-pick/' . (int) $post->ID . '/');

    echo '<tr><th scope="row">Link NFC</th><td>';
    echo '<code style="font-size:12px;">' . esc_html($public_pick_url) . '</code>';
    echo '<p class="description">Este é o URL para gravar no NFC. No front, vai abrir o ecrã de picking.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';
  }

  public static function render_map_metabox($post) {
    wp_nonce_field('twt_tcrm_save_location', 'twt_tcrm_location_nonce');

    $lat = (string) get_post_meta($post->ID, 'twt_location_lat', true);
    $lng = (string) get_post_meta($post->ID, 'twt_location_lng', true);
    $place_id = (string) get_post_meta($post->ID, 'twt_location_place_id', true);

    echo '<div class="twt-loc-map-wrap">';
    echo '<div class="twt-loc-map-hint">';
    echo '<strong>Dica:</strong> escolhe uma morada no Autocomplete, ou arrasta o pin.';
    echo '</div>';

    echo '<input type="hidden" id="twt_location_lat" name="twt_location_lat" value="' . esc_attr($lat) . '">';
    echo '<input type="hidden" id="twt_location_lng" name="twt_location_lng" value="' . esc_attr($lng) . '">';
    echo '<input type="hidden" id="twt_location_place_id" name="twt_location_place_id" value="' . esc_attr($place_id) . '">';

    echo '<div id="twt-location-map" class="twt-loc-map"></div>';

    echo '<div class="twt-loc-meta">';
    echo '<div><span class="label">Lat:</span> <code id="twt-loc-lat-read">-</code></div>';
    echo '<div><span class="label">Lng:</span> <code id="twt-loc-lng-read">-</code></div>';
    echo '<div><span class="label">Place ID:</span> <code id="twt-loc-place-read">-</code></div>';
    echo '</div>';

    echo '</div>';
  }

  public static function save_metaboxes($post_id, $post) {
    if (!$post || $post->post_type !== 'twt_location') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $nonce = isset($_POST['twt_tcrm_location_nonce']) ? sanitize_text_field(wp_unslash($_POST['twt_tcrm_location_nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'twt_tcrm_save_location')) return;

    if (!current_user_can('edit_post', $post_id)) return;

    $status = isset($_POST['twt_location_status']) ? sanitize_key(wp_unslash($_POST['twt_location_status'])) : 'active';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    $brand_id = isset($_POST['twt_brand_id']) ? (int) $_POST['twt_brand_id'] : 0;
    $campaign_id = isset($_POST['twt_campaign_id']) ? (int) $_POST['twt_campaign_id'] : 0;

    $address = isset($_POST['twt_location_address']) ? sanitize_text_field(wp_unslash($_POST['twt_location_address'])) : '';

    $radius = isset($_POST['twt_location_radius_m']) ? (int) $_POST['twt_location_radius_m'] : 80;
    if ($radius <= 0) $radius = 80;

    $lat = isset($_POST['twt_location_lat']) ? sanitize_text_field(wp_unslash($_POST['twt_location_lat'])) : '';
    $lng = isset($_POST['twt_location_lng']) ? sanitize_text_field(wp_unslash($_POST['twt_location_lng'])) : '';
    $place_id = isset($_POST['twt_location_place_id']) ? sanitize_text_field(wp_unslash($_POST['twt_location_place_id'])) : '';

    update_post_meta($post_id, 'twt_location_status', $status);
    update_post_meta($post_id, 'twt_brand_id', $brand_id);
    update_post_meta($post_id, 'twt_campaign_id', $campaign_id);

    update_post_meta($post_id, 'twt_location_address', $address);
    update_post_meta($post_id, 'twt_location_radius_m', $radius);

    update_post_meta($post_id, 'twt_location_lat', $lat);
    update_post_meta($post_id, 'twt_location_lng', $lng);
    update_post_meta($post_id, 'twt_location_place_id', $place_id);
  }

  public static function columns($cols) {
    $cols['twt_brand'] = 'Marca';
    $cols['twt_campaign'] = 'Campanha';
    $cols['twt_address'] = 'Morada';
    $cols['twt_radius'] = 'Raio';
    $cols['twt_status'] = 'Estado';
    return $cols;
  }

  public static function column_content($col, $post_id) {
    if ($col === 'twt_brand') {
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      echo $bid ? esc_html(get_the_title($bid)) : 'Sem';
      return;
    }
    if ($col === 'twt_campaign') {
      $cid = (int) get_post_meta($post_id, 'twt_campaign_id', true);
      echo $cid ? esc_html(get_the_title($cid)) : 'Sem';
      return;
    }
    if ($col === 'twt_address') {
      $a = (string) get_post_meta($post_id, 'twt_location_address', true);
      echo $a ? esc_html($a) : '-';
      return;
    }
    if ($col === 'twt_radius') {
      $r = (int) get_post_meta($post_id, 'twt_location_radius_m', true);
      if ($r <= 0) $r = 80;
      echo esc_html($r) . ' m';
      return;
    }
    if ($col === 'twt_status') {
      $s = (string) get_post_meta($post_id, 'twt_location_status', true);
      if (!$s) $s = 'active';
      echo $s === 'active' ? 'Activo' : 'Inactivo';
      return;
    }
  }
}

TWT_TCRM_CPT_Locations::register();
