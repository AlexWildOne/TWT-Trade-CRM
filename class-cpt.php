<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_CPT {

  public static function register() {
    self::register_brand();
    self::register_campaign();
    self::register_form();
    self::register_insight();
    self::register_location();

    // Metaboxes:
    // - Campanha e Insight continuam aqui (simples e estáveis)
    // - Formulário sai daqui: é o Form Builder (admin/class-form-builder.php) que manda
    add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
    add_action('save_post', [__CLASS__, 'save_metaboxes'], 10, 2);

    // Colunas
    add_filter('manage_twt_campaign_posts_columns', [__CLASS__, 'campaign_columns']);
    add_action('manage_twt_campaign_posts_custom_column', [__CLASS__, 'campaign_column_content'], 10, 2);

    add_filter('manage_twt_form_posts_columns', [__CLASS__, 'form_columns']);
    add_action('manage_twt_form_posts_custom_column', [__CLASS__, 'form_column_content'], 10, 2);

    add_filter('manage_twt_insight_posts_columns', [__CLASS__, 'insight_columns']);
    add_action('manage_twt_insight_posts_custom_column', [__CLASS__, 'insight_column_content'], 10, 2);

    add_filter('manage_twt_location_posts_columns', [__CLASS__, 'location_columns']);
    add_action('manage_twt_location_posts_custom_column', [__CLASS__, 'location_column_content'], 10, 2);
  }

  private static function register_brand() {
    register_post_type('twt_brand', [
      'labels' => [
        'name' => 'Marcas/Clientes',
        'singular_name' => 'Marca/Cliente',
        'add_new_item' => 'Adicionar Marca/Cliente',
        'edit_item' => 'Editar Marca/Cliente',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'menu_icon' => 'dashicons-store',
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  private static function register_campaign() {
    register_post_type('twt_campaign', [
      'labels' => [
        'name' => 'Campanhas',
        'singular_name' => 'Campanha',
        'add_new_item' => 'Adicionar Campanha',
        'edit_item' => 'Editar Campanha',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_icon' => 'dashicons-megaphone',
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  private static function register_form() {
    register_post_type('twt_form', [
      'labels' => [
        'name' => 'Formulários',
        'singular_name' => 'Formulário',
        'add_new_item' => 'Adicionar Formulário',
        'edit_item' => 'Editar Formulário',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_icon' => 'dashicons-forms',
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  private static function register_insight() {
    register_post_type('twt_insight', [
      'labels' => [
        'name' => 'Sugestões/Insights',
        'singular_name' => 'Sugestão/Insight',
        'add_new_item' => 'Adicionar Sugestão/Insight',
        'edit_item' => 'Editar Sugestão/Insight',
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_icon' => 'dashicons-lightbulb',
      'supports' => ['title', 'editor'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
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

  public static function add_metaboxes() {
    add_meta_box(
      'twt_tcrm_campaign_meta',
      'Configuração da Campanha',
      [__CLASS__, 'render_campaign_metabox'],
      'twt_campaign',
      'normal',
      'default'
    );

    // IMPORTANTE:
    // Formulário: metaboxes saíram daqui, ficam no TWT_TCRM_Form_Builder.
    // Isto evita o JSON aparecer e evita duplicações.

    add_meta_box(
      'twt_tcrm_insight_meta',
      'Scope e Regras',
      [__CLASS__, 'render_insight_metabox'],
      'twt_insight',
      'normal',
      'default'
    );

    // Locations: metaboxes ficam no admin/class-locations-admin.php
    // (para incluir morada, lat/lng, raio, google maps, etc)
  }

  private static function brands_dropdown($selected = 0, $name = 'twt_brand_id', $placeholder = 'Selecionar Marca/Cliente') {
    $brands = get_posts([
      'post_type' => 'twt_brand',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<select name="' . esc_attr($name) . '" style="min-width:280px;">';
    echo '<option value="0">' . esc_html($placeholder) . '</option>';
    foreach ($brands as $b) {
      $sel = selected((int)$selected, (int)$b->ID, false);
      echo '<option value="' . esc_attr($b->ID) . '" ' . $sel . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
  }

  public static function render_campaign_metabox($post) {
    wp_nonce_field('twt_tcrm_save_meta', 'twt_tcrm_nonce');

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $start = get_post_meta($post->ID, 'twt_campaign_start', true);
    $end = get_post_meta($post->ID, 'twt_campaign_end', true);
    $status = get_post_meta($post->ID, 'twt_campaign_status', true);
    if (!$status) $status = 'active';

    echo '<p><strong>Marca/Cliente</strong><br>';
    self::brands_dropdown($brand_id);
    echo '</p>';

    echo '<p><strong>Estado</strong><br>';
    echo '<select name="twt_campaign_status" style="min-width:200px;">';
    echo '<option value="active" ' . selected($status, 'active', false) . '>Activa</option>';
    echo '<option value="paused" ' . selected($status, 'paused', false) . '>Pausada</option>';
    echo '<option value="archived" ' . selected($status, 'archived', false) . '>Arquivada</option>';
    echo '</select></p>';

    echo '<p><strong>Início</strong><br><input type="date" name="twt_campaign_start" value="' . esc_attr($start) . '"></p>';
    echo '<p><strong>Fim</strong><br><input type="date" name="twt_campaign_end" value="' . esc_attr($end) . '"></p>';
  }

  public static function render_insight_metabox($post) {
    wp_nonce_field('twt_tcrm_save_meta', 'twt_tcrm_nonce');

    $scope = get_post_meta($post->ID, 'twt_insight_scope', true);
    if (!$scope) $scope = 'brand';

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($post->ID, 'twt_campaign_id', true);
    $rules = get_post_meta($post->ID, 'twt_insight_rules_json', true);

    echo '<p><strong>Scope</strong><br>';
    echo '<select name="twt_insight_scope" style="min-width:220px;">';
    echo '<option value="global" ' . selected($scope, 'global', false) . '>Global</option>';
    echo '<option value="brand" ' . selected($scope, 'brand', false) . '>Marca</option>';
    echo '<option value="campaign" ' . selected($scope, 'campaign', false) . '>Campanha</option>';
    echo '<option value="user" ' . selected($scope, 'user', false) . '>Utilizador</option>';
    echo '</select></p>';

    echo '<p><strong>Marca/Cliente</strong><br>';
    self::brands_dropdown($brand_id);
    echo '</p>';

    $campaigns = get_posts([
      'post_type' => 'twt_campaign',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<p><strong>Campanha</strong><br>';
    echo '<select name="twt_campaign_id" style="min-width:280px;">';
    echo '<option value="0">Sem campanha</option>';
    foreach ($campaigns as $c) {
      $sel = selected((int)$campaign_id, (int)$c->ID, false);
      echo '<option value="' . esc_attr($c->ID) . '" ' . $sel . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><strong>Regras (JSON)</strong><br>';
    echo '<textarea name="twt_insight_rules_json" rows="10" style="width:100%; font-family:monospace;" placeholder=\'{"when":[...],"then":[...]}\'>' . esc_textarea($rules) . '</textarea>';
    echo '</p>';

    echo '<p><em>Nota:</em> o texto do insight é o conteúdo normal do editor. As regras dizem quando aparece.</p>';
  }

  public static function save_metaboxes($post_id, $post) {
    if (!$post || empty($post->post_type)) return;

    if (!in_array($post->post_type, ['twt_campaign', 'twt_insight'], true)) {
      // Form e Location não guardam aqui:
      // - twt_form é guardado pelo Form Builder
      // - twt_location é guardado pelo Locations Admin
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['twt_tcrm_nonce']) || !wp_verify_nonce($_POST['twt_tcrm_nonce'], 'twt_tcrm_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['twt_brand_id'])) {
      update_post_meta($post_id, 'twt_brand_id', (int) $_POST['twt_brand_id']);
    }

    if ($post->post_type === 'twt_campaign') {
      if (isset($_POST['twt_campaign_status'])) {
        update_post_meta($post_id, 'twt_campaign_status', sanitize_key($_POST['twt_campaign_status']));
      }
      if (isset($_POST['twt_campaign_start'])) {
        update_post_meta($post_id, 'twt_campaign_start', sanitize_text_field($_POST['twt_campaign_start']));
      }
      if (isset($_POST['twt_campaign_end'])) {
        update_post_meta($post_id, 'twt_campaign_end', sanitize_text_field($_POST['twt_campaign_end']));
      }
    }

    if ($post->post_type === 'twt_insight') {
      if (isset($_POST['twt_insight_scope'])) {
        update_post_meta($post_id, 'twt_insight_scope', sanitize_key($_POST['twt_insight_scope']));
      }
      if (isset($_POST['twt_campaign_id'])) {
        update_post_meta($post_id, 'twt_campaign_id', (int) $_POST['twt_campaign_id']);
      }
      if (isset($_POST['twt_insight_rules_json'])) {
        update_post_meta($post_id, 'twt_insight_rules_json', wp_kses_post($_POST['twt_insight_rules_json']));
      }
    }
  }

  public static function campaign_columns($cols) {
    $cols['twt_brand'] = 'Marca';
    $cols['twt_status'] = 'Estado';
    return $cols;
  }

  public static function campaign_column_content($col, $post_id) {
    if ($col === 'twt_brand') {
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      echo $bid ? esc_html(get_the_title($bid)) : 'Sem marca';
    }
    if ($col === 'twt_status') {
      $status = get_post_meta($post_id, 'twt_campaign_status', true);
      echo $status ? esc_html($status) : 'active';
    }
  }

  public static function form_columns($cols) {
    $cols['twt_brand'] = 'Marca';
    $cols['twt_campaign'] = 'Campanha';
    $cols['twt_status'] = 'Estado';
    return $cols;
  }

  public static function form_column_content($col, $post_id) {
    if ($col === 'twt_brand') {
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      echo $bid ? esc_html(get_the_title($bid)) : 'Sem marca';
    }
    if ($col === 'twt_campaign') {
      $cid = (int) get_post_meta($post_id, 'twt_campaign_id', true);
      echo $cid ? esc_html(get_the_title($cid)) : 'Sem campanha';
    }
    if ($col === 'twt_status') {
      $status = get_post_meta($post_id, 'twt_form_status', true);
      echo $status ? esc_html($status) : 'active';
    }
  }

  public static function insight_columns($cols) {
    $cols['twt_scope'] = 'Scope';
    $cols['twt_brand'] = 'Marca';
    $cols['twt_campaign'] = 'Campanha';
    return $cols;
  }

  public static function insight_column_content($col, $post_id) {
    if ($col === 'twt_scope') {
      $scope = get_post_meta($post_id, 'twt_insight_scope', true);
      echo $scope ? esc_html($scope) : 'brand';
    }
    if ($col === 'twt_brand') {
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      echo $bid ? esc_html(get_the_title($bid)) : 'Sem marca';
    }
    if ($col === 'twt_campaign') {
      $cid = (int) get_post_meta($post_id, 'twt_campaign_id', true);
      echo $cid ? esc_html(get_the_title($cid)) : 'Sem campanha';
    }
  }

  public static function location_columns($cols) {
    $cols['twt_brand'] = 'Marca';
    $cols['twt_campaign'] = 'Campanha';
    $cols['twt_address'] = 'Morada';
    $cols['twt_geo'] = 'Geo';
    return $cols;
  }

  public static function location_column_content($col, $post_id) {
    if ($col === 'twt_brand') {
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      echo $bid ? esc_html(get_the_title($bid)) : 'Sem marca';
    }
    if ($col === 'twt_campaign') {
      $cid = (int) get_post_meta($post_id, 'twt_campaign_id', true);
      echo $cid ? esc_html(get_the_title($cid)) : 'Sem campanha';
    }
    if ($col === 'twt_address') {
      $addr = get_post_meta($post_id, 'twt_location_address', true);
      echo $addr ? esc_html($addr) : '';
    }
    if ($col === 'twt_geo') {
      $lat = get_post_meta($post_id, 'twt_location_lat', true);
      $lng = get_post_meta($post_id, 'twt_location_lng', true);
      if ($lat !== '' && $lng !== '') {
        echo esc_html($lat . ', ' . $lng);
      } else {
        echo esc_html('Sem geo');
      }
    }
  }
}
