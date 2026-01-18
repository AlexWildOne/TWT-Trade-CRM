<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_CPT {

  const NONCE_ACTION = 'twt_tcrm_save_meta';
  const NONCE_FIELD  = 'twt_tcrm_nonce';

  public static function register() {
    self::register_brand();
    self::register_campaign();
    self::register_form();
    self::register_insight();

    // NEW: Emails (CPT)
    self::register_email_template_cpt();
    self::register_email_rule_cpt();

    /**
     * IMPORTANTE:
     * - Locations (twt_location) estão em includes/class-cpt-locations.php
     * - Não registamos aqui para evitar duplicação/confusão de args.
     */

    add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
    add_action('save_post', [__CLASS__, 'save_metaboxes'], 10, 2);

    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_brand_admin_assets']);

    add_filter('manage_twt_brand_posts_columns', [__CLASS__, 'brand_columns']);
    add_action('manage_twt_brand_posts_custom_column', [__CLASS__, 'brand_column_content'], 10, 2);

    add_filter('manage_twt_campaign_posts_columns', [__CLASS__, 'campaign_columns']);
    add_action('manage_twt_campaign_posts_custom_column', [__CLASS__, 'campaign_column_content'], 10, 2);

    add_filter('manage_twt_form_posts_columns', [__CLASS__, 'form_columns']);
    add_action('manage_twt_form_posts_custom_column', [__CLASS__, 'form_column_content'], 10, 2);

    add_filter('manage_twt_insight_posts_columns', [__CLASS__, 'insight_columns']);
    add_action('manage_twt_insight_posts_custom_column', [__CLASS__, 'insight_column_content'], 10, 2);
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
  private static function register_email_template_cpt() {
    $labels = [
      'name' => 'Templates de Email',
      'singular_name' => 'Template de Email',
      'add_new' => 'Adicionar',
      'add_new_item' => 'Adicionar Template',
      'edit_item' => 'Editar Template',
      'new_item' => 'Novo Template',
      'view_item' => 'Ver Template',
      'search_items' => 'Pesquisar Templates',
      'not_found' => 'Nenhum template encontrado',
    ];

    register_post_type('twt_email_template', [
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_position' => 60,
      'supports' => ['title', 'editor'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  private static function register_email_rule_cpt() {
    $labels = [
      'name' => 'Regras de Email',
      'singular_name' => 'Regra de Email',
      'add_new' => 'Adicionar',
      'add_new_item' => 'Adicionar Regra',
      'edit_item' => 'Editar Regra',
      'new_item' => 'Nova Regra',
      'view_item' => 'Ver Regra',
      'search_items' => 'Pesquisar Regras',
      'not_found' => 'Nenhuma regra encontrada',
    ];

    register_post_type('twt_email_rule', [
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => 'edit.php?post_type=twt_brand',
      'menu_position' => 61,
      'supports' => ['title'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
    ]);
  }

  /**
   * Assets só para o editor do CPT twt_brand:
   * - admin/assets/brands.js
   * - Google Maps JS API (Places)
   */
  public static function enqueue_brand_admin_assets($hook) {
    if (!is_admin()) return;
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_brand = false;

    if ($screen && !empty($screen->post_type)) {
      $is_brand = ($screen->post_type === 'twt_brand');
    } else {
      if ($hook === 'post-new.php') {
        $pt = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
        $is_brand = ($pt === 'twt_brand');
      } else {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$post_id && isset($_POST['post_ID'])) $post_id = (int) $_POST['post_ID'];
        if ($post_id) $is_brand = (get_post_type($post_id) === 'twt_brand');
      }
    }

    if (!$is_brand) return;

    // Browser key (Settings Admin)
    $browser_key = '';
    if (class_exists('TWT_TCRM_Settings_Admin') && method_exists('TWT_TCRM_Settings_Admin', 'get_gmaps_browser_key')) {
      $browser_key = (string) TWT_TCRM_Settings_Admin::get_gmaps_browser_key();
    }
    $browser_key = is_string($browser_key) ? trim($browser_key) : '';

    $deps = ['jquery'];

    // Carrega Google Maps JS API com Places (SEM callback)
    if ($browser_key) {
      $src = add_query_arg([
        'key' => $browser_key,
        'libraries' => 'places',
      ], 'https://maps.googleapis.com/maps/api/js');

      wp_enqueue_script(
        'twt-tcrm-google-maps',
        $src,
        [],
        null,
        true
      );

      $deps[] = 'twt-tcrm-google-maps';
    }

    wp_enqueue_script(
      'twt-tcrm-brands-admin',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/brands.js',
      $deps,
      TWT_TCRM_VERSION,
      true
    );

    wp_localize_script('twt-tcrm-brands-admin', 'TWT_TCRM_BRAND', [
      'hasKey' => $browser_key ? 1 : 0,
      'i18n' => [
        'missingKey' => 'Falta a Browser key do Google Maps. Vai a TWT CRM → Definições.',
      ],
      'selectors' => [
        'address' => '#twt_brand_address',
        'lat' => '#twt_brand_lat',
        'lng' => '#twt_brand_lng',
        'placeId' => '#twt_brand_place_id',
        'latRead' => '#twt-brand-lat-read',
        'lngRead' => '#twt-brand-lng-read',
        'placeRead' => '#twt-brand-place-read',
      ],
    ]);
  }

  public static function add_metaboxes() {
    add_meta_box(
      'twt_tcrm_brand_meta',
      'Dados da Marca/Cliente',
      [__CLASS__, 'render_brand_metabox'],
      'twt_brand',
      'normal',
      'high'
    );

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

    // Insight: o metabox será gerido por admin/class-insights-admin.php
    // (não adicionamos aqui para evitar duplicação e para ter UI mais rica)
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
      $sel = selected((int) $selected, (int) $b->ID, false);
      echo '<option value="' . esc_attr($b->ID) . '" ' . $sel . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
  }

  private static function users_multi_select($selected_ids = [], $name = 'twt_brand_user_ids') {
    $selected_ids = is_array($selected_ids) ? array_map('intval', $selected_ids) : [];
    $selected_ids = array_values(array_unique(array_filter($selected_ids)));

    $users = get_users([
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 500,
      'fields' => ['ID', 'display_name', 'user_login', 'user_email'],
    ]);

    echo '<select name="' . esc_attr($name) . '[]" multiple size="10" style="min-width:360px;max-width:700px;width:100%;">';
    foreach ($users as $u) {
      $label = $u->display_name . ' (' . $u->user_login . ') — ' . $u->user_email;
      $sel = in_array((int) $u->ID, $selected_ids, true) ? ' selected' : '';
      echo '<option value="' . esc_attr((int) $u->ID) . '"' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Ctrl/Cmd para multi-seleção.</p>';
  }

  public static function render_brand_metabox($post) {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $nif = (string) get_post_meta($post->ID, 'twt_brand_nif', true);

    $user_ids = get_post_meta($post->ID, 'twt_brand_user_ids', true);
    if (!is_array($user_ids)) $user_ids = [];

    // emails extra (não users)
    $email_lines = (string) get_post_meta($post->ID, 'twt_brand_user_emails', true);

    $address = (string) get_post_meta($post->ID, 'twt_brand_address', true);
    $lat = (string) get_post_meta($post->ID, 'twt_brand_lat', true);
    $lng = (string) get_post_meta($post->ID, 'twt_brand_lng', true);
    $place_id = (string) get_post_meta($post->ID, 'twt_brand_place_id', true);

    $phone = (string) get_post_meta($post->ID, 'twt_brand_phone', true);
    $contact_name = (string) get_post_meta($post->ID, 'twt_brand_contact_name', true);
    $notes = (string) get_post_meta($post->ID, 'twt_brand_notes', true);

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_nif">NIF</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_brand_nif" name="twt_brand_nif" value="' . esc_attr($nif) . '" class="regular-text" autocomplete="off">';
    echo '<p class="description">Apenas dígitos (PT: 9 dígitos).</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_phone">Telefone</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_brand_phone" name="twt_brand_phone" value="' . esc_attr($phone) . '" class="regular-text" autocomplete="off">';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_contact_name">Contacto</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_brand_contact_name" name="twt_brand_contact_name" value="' . esc_attr($contact_name) . '" class="regular-text" autocomplete="off">';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row">Utilizadores associados</th>';
    echo '<td>';
    self::users_multi_select($user_ids, 'twt_brand_user_ids');
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_user_emails">Emails extra (opcional)</label></th>';
    echo '<td>';
    echo '<textarea id="twt_brand_user_emails" name="twt_brand_user_emails" rows="4" style="width:100%;max-width:700px;" placeholder="um email por linha (opcional)">' . esc_textarea($email_lines) . '</textarea>';
    echo '<p class="description">Opcional. Útil se quiseres guardar contactos que não são users do WordPress.</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_address">Morada</label></th>';
    echo '<td>';
    echo '<input type="text" id="twt_brand_address" name="twt_brand_address" value="' . esc_attr($address) . '" style="width:100%;max-width:800px;" placeholder="Começa a escrever e escolhe no dropdown (Autocomplete)">';
    echo '<p class="description">Quando escolheres no dropdown, guardamos lat/lng e place_id.</p>';

    echo '<input type="hidden" id="twt_brand_lat" name="twt_brand_lat" value="' . esc_attr($lat) . '">';
    echo '<input type="hidden" id="twt_brand_lng" name="twt_brand_lng" value="' . esc_attr($lng) . '">';
    echo '<input type="hidden" id="twt_brand_place_id" name="twt_brand_place_id" value="' . esc_attr($place_id) . '">';

    echo '<div style="margin-top:8px;font-size:12px;opacity:.8;">';
    echo 'Lat: <code id="twt-brand-lat-read">' . esc_html($lat ? $lat : '-') . '</code> &nbsp; ';
    echo 'Lng: <code id="twt-brand-lng-read">' . esc_html($lng ? $lng : '-') . '</code> &nbsp; ';
    echo 'Place ID: <code id="twt-brand-place-read">' . esc_html($place_id ? $place_id : '-') . '</code>';
    echo '</div>';

    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_brand_notes">Notas</label></th>';
    echo '<td>';
    echo '<textarea id="twt_brand_notes" name="twt_brand_notes" rows="4" style="width:100%;max-width:800px;" placeholder="Notas internas...">' . esc_textarea($notes) . '</textarea>';
    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';

    echo '<p class="description"><strong>Nota:</strong> para o Autocomplete funcionar, precisamos da Google Maps Browser Key nas Definições do plugin.</p>';
  }

  public static function render_campaign_metabox($post) {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $start = (string) get_post_meta($post->ID, 'twt_campaign_start', true);
    $end = (string) get_post_meta($post->ID, 'twt_campaign_end', true);
    $status = (string) get_post_meta($post->ID, 'twt_campaign_status', true);
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

  public static function save_metaboxes($post_id, $post) {
    if (!$post || empty($post->post_type)) return;

    // IMPORTANT: twt_insight não guarda aqui (é no admin/class-insights-admin.php)
    if (!in_array($post->post_type, ['twt_brand', 'twt_campaign'], true)) {
      // Form e Location não guardam aqui:
      // - twt_form é guardado pelo Form Builder
      // - twt_location é guardado pelo Locations Admin
      // - twt_insight é guardado pelo Insights Admin
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) return;

    if (!current_user_can('edit_post', $post_id)) return;

    // Campos comuns (brand_id em campaign)
    if (isset($_POST['twt_brand_id']) && $post->post_type !== 'twt_brand') {
      update_post_meta($post_id, 'twt_brand_id', (int) wp_unslash($_POST['twt_brand_id']));
    }

    if ($post->post_type === 'twt_brand') {
      if (isset($_POST['twt_brand_nif'])) {
        $nif = (string) wp_unslash($_POST['twt_brand_nif']);
        $nif = preg_replace('/[^0-9]/', '', $nif);
        $nif = substr($nif, 0, 20);
        update_post_meta($post_id, 'twt_brand_nif', $nif);
      }

      if (isset($_POST['twt_brand_phone'])) {
        $phone = sanitize_text_field(wp_unslash($_POST['twt_brand_phone']));
        update_post_meta($post_id, 'twt_brand_phone', $phone);
      }

      if (isset($_POST['twt_brand_contact_name'])) {
        $contact_name = sanitize_text_field(wp_unslash($_POST['twt_brand_contact_name']));
        update_post_meta($post_id, 'twt_brand_contact_name', $contact_name);
      }

      $user_ids = isset($_POST['twt_brand_user_ids']) && is_array($_POST['twt_brand_user_ids'])
        ? array_map('intval', wp_unslash($_POST['twt_brand_user_ids']))
        : [];
      $user_ids = array_values(array_unique(array_filter($user_ids)));

      $valid = [];
      foreach ($user_ids as $uid) {
        if ($uid && get_user_by('id', $uid)) $valid[] = $uid;
      }
      update_post_meta($post_id, 'twt_brand_user_ids', $valid);

      if (isset($_POST['twt_brand_user_emails'])) {
        $raw = (string) wp_unslash($_POST['twt_brand_user_emails']);
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        $out = [];

        if (is_array($lines)) {
          foreach ($lines as $ln) {
            $ln = trim((string) $ln);
            if ($ln === '') continue;
            $email = sanitize_email($ln);
            if ($email && is_email($email)) {
              $out[] = strtolower($email);
            }
          }
        }

        $out = array_values(array_unique($out));
        update_post_meta($post_id, 'twt_brand_user_emails', implode("\n", $out));
      }

      if (isset($_POST['twt_brand_address'])) {
        $address = sanitize_text_field(wp_unslash($_POST['twt_brand_address']));
        update_post_meta($post_id, 'twt_brand_address', $address);
      }

      if (isset($_POST['twt_brand_lat'])) {
        $lat = sanitize_text_field(wp_unslash($_POST['twt_brand_lat']));
        update_post_meta($post_id, 'twt_brand_lat', $lat);
      }

      if (isset($_POST['twt_brand_lng'])) {
        $lng = sanitize_text_field(wp_unslash($_POST['twt_brand_lng']));
        update_post_meta($post_id, 'twt_brand_lng', $lng);
      }

      if (isset($_POST['twt_brand_place_id'])) {
        $place_id = sanitize_text_field(wp_unslash($_POST['twt_brand_place_id']));
        update_post_meta($post_id, 'twt_brand_place_id', $place_id);
      }

      if (isset($_POST['twt_brand_notes'])) {
        $notes = sanitize_textarea_field(wp_unslash($_POST['twt_brand_notes']));
        update_post_meta($post_id, 'twt_brand_notes', $notes);
      }
    }

    if ($post->post_type === 'twt_campaign') {
      if (isset($_POST['twt_campaign_status'])) {
        update_post_meta($post_id, 'twt_campaign_status', sanitize_key(wp_unslash($_POST['twt_campaign_status'])));
      }
      if (isset($_POST['twt_campaign_start'])) {
        update_post_meta($post_id, 'twt_campaign_start', sanitize_text_field(wp_unslash($_POST['twt_campaign_start'])));
      }
      if (isset($_POST['twt_campaign_end'])) {
        update_post_meta($post_id, 'twt_campaign_end', sanitize_text_field(wp_unslash($_POST['twt_campaign_end'])));
      }
    }
  }

  public static function brand_columns($cols) {
    $cols['twt_nif'] = 'NIF';
    $cols['twt_address'] = 'Morada';
    $cols['twt_users'] = 'Utilizadores';
    return $cols;
  }

  public static function brand_column_content($col, $post_id) {
    if ($col === 'twt_nif') {
      $nif = (string) get_post_meta($post_id, 'twt_brand_nif', true);
      echo $nif ? esc_html($nif) : '-';
      return;
    }
    if ($col === 'twt_address') {
      $a = (string) get_post_meta($post_id, 'twt_brand_address', true);
      echo $a ? esc_html($a) : '-';
      return;
    }
    if ($col === 'twt_users') {
      $ids = get_post_meta($post_id, 'twt_brand_user_ids', true);
      $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];

      $labels = [];
      foreach ($ids as $uid) {
        $u = get_user_by('id', $uid);
        if (!$u) continue;
        $labels[] = $u->display_name;
      }

      if ($labels) {
        echo esc_html(implode(', ', array_slice($labels, 0, 3)));
        if (count($labels) > 3) echo esc_html(' …');
        return;
      }

      $emails = (string) get_post_meta($post_id, 'twt_brand_user_emails', true);
      $emails = trim($emails);
      if (!$emails) {
        echo '-';
        return;
      }
      $lines = preg_split("/\r\n|\n|\r/", $emails);
      $lines = is_array($lines) ? array_filter(array_map('trim', $lines)) : [];
      echo $lines ? esc_html(implode(', ', array_slice($lines, 0, 3))) : '-';
      return;
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
      $status = (string) get_post_meta($post_id, 'twt_campaign_status', true);
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
      $status = (string) get_post_meta($post_id, 'twt_form_status', true);
      echo $status ? esc_html($status) : 'active';
    }
  }

  public static function insight_columns($cols) {
    // UI completa do insight fica no Insights Admin; aqui mantemos colunas mínimas
    $cols['twt_active'] = 'Ativo';
    $cols['twt_priority'] = 'Prioridade';
    $cols['twt_scope'] = 'Scope';
    return $cols;
  }

  public static function insight_column_content($col, $post_id) {
    if ($col === 'twt_active') {
      $active = (string) get_post_meta($post_id, 'twt_insight_active', true);
      echo ($active === '1') ? 'Sim' : 'Não';
      return;
    }
    if ($col === 'twt_priority') {
      $p = (string) get_post_meta($post_id, 'twt_insight_priority', true);
      echo $p !== '' ? esc_html($p) : '-';
      return;
    }
    if ($col === 'twt_scope') {
      // Para já, mostrar resumo rápido do target
      $bid = (int) get_post_meta($post_id, 'twt_brand_id', true);
      $cid = (int) get_post_meta($post_id, 'twt_campaign_id', true);
      $uid = (int) get_post_meta($post_id, 'twt_user_id', true);
      $fid = (int) get_post_meta($post_id, 'twt_form_id', true);

      $parts = [];
      if ($bid) $parts[] = 'Marca';
      if ($cid) $parts[] = 'Campanha';
      if ($uid) $parts[] = 'User';
      if ($fid) $parts[] = 'Form';

      echo $parts ? esc_html(implode(', ', $parts)) : 'Global';
      return;
    }
  }
}