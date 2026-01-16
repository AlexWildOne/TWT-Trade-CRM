<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Locations_Admin {

  const PARENT_SLUG = 'twt-tcrm';
  const PAGE_LIST = 'twt-tcrm-locations';
  const PAGE_ASSIGN = 'twt-tcrm-location-assignments';

  // Se criares a cap nova nas roles, muda esta constante para 'twt_tcrm_manage_locations'
  const CAP_LOCATIONS_FALLBACK = 'twt_tcrm_manage_brands';
  const CAP_LOCATIONS_NEW = 'twt_tcrm_manage_locations';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
    add_action('admin_init', [__CLASS__, 'handle_actions']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_location_editor_assets']);
  }

  /**
   * Cap usada para gerir locations
   */
  private static function cap_locations() {
    // Se já criaste a cap nova e atribuístes aos roles, usa-a
    // Se não, mantém compatibilidade
    // Nota: não dá para validar "existência" da cap de forma perfeita, isto é o mais prático.
    $u = wp_get_current_user();
    if ($u && $u->exists() && user_can($u, self::CAP_LOCATIONS_NEW)) {
      return self::CAP_LOCATIONS_NEW;
    }
    return self::CAP_LOCATIONS_FALLBACK;
  }

  public static function register_menu() {
    $cap_locations = self::cap_locations();

    add_submenu_page(
      self::PARENT_SLUG,
      'Lojas/Locais',
      'Lojas/Locais',
      $cap_locations,
      self::PAGE_LIST,
      [__CLASS__, 'page_locations']
    );

    add_submenu_page(
      self::PARENT_SLUG,
      'Atribuições de Lojas',
      'Atribuições de Lojas',
      'twt_tcrm_manage_assignments',
      self::PAGE_ASSIGN,
      [__CLASS__, 'page_assignments']
    );
  }

  /**
   * Carrega Google Maps Places Autocomplete e o teu JS só no editor do CPT twt_location.
   * Isto é o que normalmente está a faltar quando "o autocomplete não funciona".
   */
  public static function enqueue_location_editor_assets($hook) {
    if (!is_admin()) return;

    // Só em ecrãs de edição de posts
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_location_screen = false;

    if ($screen && !empty($screen->post_type) && $screen->post_type === 'twt_location') {
      $is_location_screen = true;
    } else {
      // fallback, se o screen falhar
      if ($hook === 'post-new.php') {
        $pt = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
        if ($pt === 'twt_location') $is_location_screen = true;
      } else {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if ($post_id && get_post_type($post_id) === 'twt_location') $is_location_screen = true;
      }
    }

    if (!$is_location_screen) return;

    $cap_locations = self::cap_locations();
    if (!current_user_can($cap_locations)) return;

    // Browser key
    $browser_key = '';
    if (class_exists('TWT_TCRM_Settings_Admin') && method_exists('TWT_TCRM_Settings_Admin', 'get_gmaps_browser_key')) {
      $browser_key = (string) TWT_TCRM_Settings_Admin::get_gmaps_browser_key();
    }

    // Se não houver key, não carrega Maps e mostra warning no console, mas não rebenta o admin.
    if (!$browser_key) {
      wp_enqueue_script(
        'twt-tcrm-locations-gmaps',
        TWT_TCRM_PLUGIN_URL . 'admin/assets/locations-gmaps.js',
        ['jquery'],
        TWT_TCRM_VERSION,
        true
      );

      wp_localize_script('twt-tcrm-locations-gmaps', 'TWT_TCRM_LOC', [
        'hasKey' => 0,
        'error' => 'Sem Google Maps Browser Key nas Definições do plugin.',
        'selectors' => [
          'address' => '#twt_location_address',
          'lat' => '#twt_location_lat',
          'lng' => '#twt_location_lng',
          'placeId' => '#twt_location_place_id',
        ],
      ]);

      return;
    }

    // Google Maps JS API com Places
    $gmaps_src = add_query_arg([
      'key' => rawurlencode($browser_key),
      'libraries' => 'places',
    ], 'https://maps.googleapis.com/maps/api/js');

    wp_register_script('twt-tcrm-google-maps', $gmaps_src, [], null, true);
    wp_enqueue_script('twt-tcrm-google-maps');

    // O teu JS que inicializa o autocomplete
    wp_enqueue_script(
      'twt-tcrm-locations-gmaps',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/locations-gmaps.js',
      ['jquery', 'twt-tcrm-google-maps'],
      TWT_TCRM_VERSION,
      true
    );

    wp_localize_script('twt-tcrm-locations-gmaps', 'TWT_TCRM_LOC', [
      'hasKey' => 1,
      'selectors' => [
        'address' => '#twt_location_address',
        'lat' => '#twt_location_lat',
        'lng' => '#twt_location_lng',
        'placeId' => '#twt_location_place_id',
      ],
      'strings' => [
        'noAddress' => 'Morada não encontrada.',
      ],
    ]);
  }

  public static function page_locations() {
    $cap_locations = self::cap_locations();
    if (!current_user_can($cap_locations)) {
      wp_die('Sem permissões.');
    }

    $msg = isset($_GET['twt_tcrm_msg']) ? sanitize_text_field($_GET['twt_tcrm_msg']) : '';

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Lojas/Locais</h1>';
    echo '<p>Gere locais por Marca e, opcionalmente, Campanha. Cada local terá um link NFC para check-in e check-out.</p>';

    if ($msg === 'saved') {
      echo '<div class="notice notice-success"><p>Alterações guardadas.</p></div>';
    } elseif ($msg === 'error') {
      echo '<div class="notice notice-error"><p>Ocorreu um erro. Verifica os dados.</p></div>';
    }

    echo '<p class="twt-inline">';
    echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=twt_location')) . '">Adicionar local</a> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_ASSIGN)) . '">Atribuições de Lojas</a>';
    echo '</p>';

    $locations = get_posts([
      'post_type' => 'twt_location',
      'numberposts' => 200,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    if (!$locations) {
      echo '<p>Sem locais. Cria o primeiro em "Adicionar local".</p>';
      echo '</div>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Local</th>';
    echo '<th>Marca</th>';
    echo '<th>Campanha</th>';
    echo '<th>Morada</th>';
    echo '<th>Raio</th>';
    echo '<th>Estado</th>';
    echo '<th>Link NFC</th>';
    echo '</tr></thead><tbody>';

    foreach ($locations as $loc) {
      $brand_id = (int) get_post_meta($loc->ID, 'twt_brand_id', true);
      $campaign_id = (int) get_post_meta($loc->ID, 'twt_campaign_id', true);

      $address = (string) get_post_meta($loc->ID, 'twt_location_address', true);
      $radius = (int) get_post_meta($loc->ID, 'twt_location_radius_m', true);
      if ($radius <= 0) $radius = 80;

      $status = (string) get_post_meta($loc->ID, 'twt_location_status', true);
      if (!$status) $status = 'active';

      $brand_title = $brand_id ? get_the_title($brand_id) : 'Sem';
      $campaign_title = $campaign_id ? get_the_title($campaign_id) : 'Sem';

      $edit_link = get_edit_post_link($loc->ID, '');
      $public_pick_url = home_url('/twt-pick/' . (int)$loc->ID . '/');

      $status_html = ($status === 'active')
        ? '<span class="status-active">Activo</span>'
        : '<span class="status-inactive">Inactivo</span>';

      echo '<tr>';
      echo '<td><strong><a href="' . esc_url($edit_link) . '">' . esc_html($loc->post_title) . '</a></strong><div class="twt-tcrm-muted">ID: ' . esc_html((int)$loc->ID) . '</div></td>';
      echo '<td>' . esc_html($brand_title) . '</td>';
      echo '<td>' . esc_html($campaign_title) . '</td>';
      echo '<td>' . esc_html($address ? $address : '-') . '</td>';
      echo '<td>' . esc_html($radius) . ' m</td>';
      echo '<td>' . $status_html . '</td>';
      echo '<td><code style="font-size:12px;">' . esc_html($public_pick_url) . '</code></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<p class="twt-tcrm-hint">Dica: este link é o que vais gravar no NFC por loja. O check-in vai validar geolocalização quando criarmos o endpoint no front.</p>';
    echo '</div>';
  }

  public static function page_assignments() {
    if (!current_user_can('twt_tcrm_manage_assignments')) {
      wp_die('Sem permissões.');
    }

    $msg = isset($_GET['twt_tcrm_msg']) ? sanitize_text_field($_GET['twt_tcrm_msg']) : '';

    $locations = get_posts([
      'post_type' => 'twt_location',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $field_users = get_users([
      'role__in' => ['twt_field_user'],
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 500,
    ]);

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Atribuições de Lojas</h1>';
    echo '<p>Atribui locais/lojas a utilizadores de terreno. Isto controla o picking e, mais tarde, a selecção de loja nos formulários.</p>';

    if ($msg === 'assigned') {
      echo '<div class="notice notice-success"><p>Atribuições guardadas.</p></div>';
    } elseif ($msg === 'toggled') {
      echo '<div class="notice notice-success"><p>Atribuição actualizada.</p></div>';
    } elseif ($msg === 'error') {
      echo '<div class="notice notice-error"><p>Ocorreu um erro. Verifica os dados.</p></div>';
    } elseif ($msg === 'missing_table') {
      echo '<div class="notice notice-warning"><p>Falta a tabela de atribuições de lojas na DB. Atualiza o class-db.php para criar table_location_assignments.</p></div>';
    }

    if (!$locations) {
      echo '<p>Sem locais. Cria primeiro pelo menu "Lojas/Locais".</p>';
      echo '</div>';
      return;
    }

    echo '<h2>Adicionar atribuições</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::PAGE_ASSIGN)) . '">';
    wp_nonce_field('twt_tcrm_loc_assign', 'twt_tcrm_loc_assign_nonce');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Local/Loja</th><td>';
    echo '<select name="location_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($locations as $loc) {
      $brand_id = (int) get_post_meta($loc->ID, 'twt_brand_id', true);
      $brand = $brand_id ? get_the_title($brand_id) : 'Sem marca';
      echo '<option value="' . esc_attr($loc->ID) . '">' . esc_html($loc->post_title . ' , ' . $brand) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Utilizadores</th><td>';
    echo '<select name="user_ids[]" multiple size="12" required style="min-width:320px;">';
    foreach ($field_users as $u) {
      echo '<option value="' . esc_attr($u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Multi-selecção.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';

    echo '<p><button type="submit" class="button button-primary" name="twt_tcrm_do" value="assign_locations">Atribuir</button></p>';
    echo '</form>';

    echo '<hr>';
    echo '<h2>Atribuições existentes</h2>';
    self::render_assignments_table();

    echo '</div>';
  }

  public static function handle_actions() {
    if (!is_admin()) return;

    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($page !== self::PAGE_ASSIGN) return;
    if (!current_user_can('twt_tcrm_manage_assignments')) return;

    if (!method_exists('TWT_TCRM_DB', 'table_location_assignments')) {
      if (isset($_POST['twt_tcrm_do']) || isset($_GET['twt_tcrm_toggle'])) {
        wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'missing_table']));
        exit;
      }
      return;
    }

    if (isset($_GET['twt_tcrm_toggle']) && isset($_GET['id'])) {
      $id = (int) $_GET['id'];
      $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

      if (!$id || !wp_verify_nonce($nonce, 'twt_tcrm_loc_toggle_' . $id)) {
        wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
        exit;
      }

      self::toggle_assignment($id);

      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'toggled']));
      exit;
    }

    if (!isset($_POST['twt_tcrm_do']) || sanitize_text_field(wp_unslash($_POST['twt_tcrm_do'])) !== 'assign_locations') return;

    $nonce = isset($_POST['twt_tcrm_loc_assign_nonce']) ? sanitize_text_field(wp_unslash($_POST['twt_tcrm_loc_assign_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'twt_tcrm_loc_assign')) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $location_id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;
    $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];

    if (!$location_id || !$user_ids) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $loc = get_post($location_id);
    if (!$loc || $loc->post_type !== 'twt_location') {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $ok = self::create_assignments($location_id, $user_ids);

    wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => $ok ? 'assigned' : 'error']));
    exit;
  }

  private static function create_assignments($location_id, $user_ids) {
    global $wpdb;

    $t = TWT_TCRM_DB::table_location_assignments();
    $created_any = false;

    foreach ($user_ids as $uid) {
      if (!$uid) continue;

      $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t WHERE user_id = %d AND location_id = %d LIMIT 1",
        (int)$uid,
        (int)$location_id
      ));

      if ($existing_id) {
        $wpdb->update(
          $t,
          [
            'active' => 1,
            'updated_at' => current_time('mysql'),
          ],
          ['id' => (int)$existing_id],
          ['%d','%s'],
          ['%d']
        );
        $created_any = true;
        continue;
      }

      $ins = $wpdb->insert(
        $t,
        [
          'user_id' => (int)$uid,
          'location_id' => (int)$location_id,
          'active' => 1,
          'created_at' => current_time('mysql'),
          'updated_at' => current_time('mysql'),
        ],
        ['%d','%d','%d','%s','%s']
      );

      if ($ins) $created_any = true;
    }

    return $created_any;
  }

  private static function toggle_assignment($id) {
    global $wpdb;

    $t = TWT_TCRM_DB::table_location_assignments();

    $row = $wpdb->get_row($wpdb->prepare("SELECT id, active FROM $t WHERE id = %d", (int)$id));
    if (!$row) return false;

    $new = ((int)$row->active === 1) ? 0 : 1;

    return (bool) $wpdb->update(
      $t,
      [
        'active' => $new,
        'updated_at' => current_time('mysql'),
      ],
      ['id' => (int)$id],
      ['%d','%s'],
      ['%d']
    );
  }

  private static function render_assignments_table() {
    global $wpdb;

    if (!method_exists('TWT_TCRM_DB', 'table_location_assignments')) {
      echo '<p>Falta implementar a tabela de atribuições de lojas na DB.</p>';
      return;
    }

    $t = TWT_TCRM_DB::table_location_assignments();

    $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC LIMIT 300");

    if (!$rows) {
      echo '<p>Sem atribuições.</p>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Utilizador</th>';
    echo '<th>Local</th>';
    echo '<th>Activo</th>';
    echo '<th>Ações</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $toggle_url = wp_nonce_url(
        self::url_assignments(['twt_tcrm_toggle' => '1', 'id' => (int)$r->id]),
        'twt_tcrm_loc_toggle_' . (int)$r->id
      );

      $user = get_userdata((int)$r->user_id);
      $user_label = $user ? ($user->display_name . ' (' . $user->user_login . ')') : ('User #' . (int)$r->user_id);

      $loc_title = $r->location_id ? get_the_title((int)$r->location_id) : ('Local #' . (int)$r->location_id);

      $active_html = ((int)$r->active === 1)
        ? '<span class="status-active">Sim</span>'
        : '<span class="status-inactive">Não</span>';

      echo '<tr>';
      echo '<td>' . esc_html((int)$r->id) . '</td>';
      echo '<td>' . esc_html($user_label) . '</td>';
      echo '<td>' . esc_html($loc_title) . '</td>';
      echo '<td>' . $active_html . '</td>';
      echo '<td><a class="button" href="' . esc_url($toggle_url) . '">Alternar</a></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  private static function url_assignments($args = []) {
    $base = admin_url('admin.php?page=' . self::PAGE_ASSIGN);
    return add_query_arg($args, $base);
  }
}

TWT_TCRM_Locations_Admin::boot();
