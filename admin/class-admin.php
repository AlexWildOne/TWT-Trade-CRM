<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Admin {

  const MENU_SLUG = 'twt-tcrm';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_menu']);
    add_action('admin_init', [__CLASS__, 'handle_post_actions']);
  }

  public static function register_menu() {
    add_menu_page(
      'TWT Trade CRM',
      'TWT CRM',
      'twt_tcrm_view_all_reports',
      self::MENU_SLUG,
      [__CLASS__, 'page_dashboard'],
      'dashicons-chart-bar',
      26
    );

    add_submenu_page(
      self::MENU_SLUG,
      'Dashboard',
      'Dashboard',
      'twt_tcrm_view_all_reports',
      self::MENU_SLUG,
      [__CLASS__, 'page_dashboard']
    );

    add_submenu_page(
      self::MENU_SLUG,
      'Atribuições',
      'Atribuições',
      'twt_tcrm_manage_assignments',
      self::MENU_SLUG . '-assignments',
      [__CLASS__, 'page_assignments']
    );

    add_submenu_page(
      self::MENU_SLUG,
      'Utilizadores de Marca',
      'Utilizadores de Marca',
      'twt_tcrm_manage_brands',
      self::MENU_SLUG . '-brand-users',
      [__CLASS__, 'page_brand_users']
    );

    add_submenu_page(
      self::MENU_SLUG,
      'Reports',
      'Reports',
      'twt_tcrm_view_all_reports',
      self::MENU_SLUG . '-reports',
      [__CLASS__, 'page_reports']
    );

    add_submenu_page(
      self::MENU_SLUG,
      'Sugestões/Insights',
      'Insights',
      'twt_tcrm_manage_insights',
      'edit.php?post_type=twt_insight'
    );
  }

  /* ======================================================
     DASHBOARD
     ====================================================== */

  public static function page_dashboard() {
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    $kpis = self::get_admin_kpis();

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>TWT Trade CRM, Dashboard</h1>';

    echo '<div class="twt-kpis">';
    echo '<div class="twt-kpi"><div class="twt-kpi-label">Reports, 7 dias</div><div class="twt-kpi-value">' . esc_html((string)$kpis['reports_7d']) . '</div></div>';
    echo '<div class="twt-kpi"><div class="twt-kpi-label">Reports, 30 dias</div><div class="twt-kpi-value">' . esc_html((string)$kpis['reports_30d']) . '</div></div>';
    echo '<div class="twt-kpi"><div class="twt-kpi-label">Utilizadores activos, 30 dias</div><div class="twt-kpi-value">' . esc_html((string)$kpis['active_users_30d']) . '</div></div>';
    echo '<div class="twt-kpi"><div class="twt-kpi-label">Marcas activas, 30 dias</div><div class="twt-kpi-value">' . esc_html((string)$kpis['active_brands_30d']) . '</div></div>';
    echo '</div>';

    echo '<div class="twt-row twt-row-2">';

    echo '<div class="twt-card">';
    echo '<h2>Qualidade de dados</h2>';
    echo '<p class="twt-muted">Campanhas com dados (30 dias): <strong>' . esc_html((string)$kpis['active_campaigns_30d']) . '</strong></p>';
    echo '<p class="twt-muted">Último report: <strong>' . esc_html($kpis['last_report_label']) . '</strong></p>';
    echo '</div>';

    echo '<div class="twt-card">';
    echo '<h2>Atalhos</h2>';
    echo '<p class="twt-inline">';
    echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-assignments')) . '">Gerir atribuições</a>';
    echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-brand-users')) . '">Utilizadores de Marca</a>';
    echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=twt_form')) . '">Formulários</a>';
    echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=twt_brand')) . '">Marcas</a>';
    echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=twt_campaign')) . '">Campanhas</a>';
    echo '</p>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
  }

  private static function get_admin_kpis() {
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $now = current_time('timestamp');
    $since_7d = date('Y-m-d H:i:s', $now - (7 * 86400));
    $since_30d = date('Y-m-d H:i:s', $now - (30 * 86400));

    $reports_7d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE submitted_at >= %s",
      $since_7d
    ));

    $reports_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE submitted_at >= %s",
      $since_30d
    ));

    $active_users_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT user_id) FROM $t_sub WHERE submitted_at >= %s",
      $since_30d
    ));

    $active_brands_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT brand_id) FROM $t_sub WHERE submitted_at >= %s",
      $since_30d
    ));

    $active_campaigns_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT campaign_id) FROM $t_sub WHERE submitted_at >= %s AND campaign_id IS NOT NULL",
      $since_30d
    ));

    $last = $wpdb->get_row("SELECT user_id, submitted_at FROM $t_sub ORDER BY submitted_at DESC LIMIT 1");

    $last_label = 'Sem dados';
    if ($last && !empty($last->submitted_at)) {
      $u = get_userdata((int) $last->user_id);
      $who = $u ? $u->display_name : ('User #' . (int)$last->user_id);
      $when = mysql2date('Y-m-d H:i', $last->submitted_at);
      $last_label = $when . ', ' . $who;
    }

    return [
      'reports_7d' => $reports_7d,
      'reports_30d' => $reports_30d,
      'active_users_30d' => $active_users_30d,
      'active_brands_30d' => $active_brands_30d,
      'active_campaigns_30d' => $active_campaigns_30d,
      'last_report_label' => $last_label,
    ];
  }

  /* ======================================================
     REPORTS
     ====================================================== */

  public static function page_reports() {
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Reports</h1>';
    echo '<p>Esta página vai evoluir para filtros, tabela e exportações.</p>';
    echo '</div>';
  }

  /* ======================================================
     ATRIBUIÇÕES
     ====================================================== */

  public static function page_assignments() {
    if (!current_user_can('twt_tcrm_manage_assignments')) {
      wp_die('Sem permissões.');
    }

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

    $forms = get_posts([
      'post_type' => 'twt_form',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $field_users = get_users([
      'role__in' => ['twt_field_user'],
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 300,
    ]);

    $msg = isset($_GET['twt_tcrm_msg']) ? sanitize_text_field($_GET['twt_tcrm_msg']) : '';

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Atribuições</h1>';

    if ($msg === 'assigned') {
      echo '<div class="notice notice-success"><p>Atribuições criadas com sucesso.</p></div>';
    } elseif ($msg === 'toggled') {
      echo '<div class="notice notice-success"><p>Atribuição actualizada.</p></div>';
    } elseif ($msg === 'error') {
      echo '<div class="notice notice-error"><p>Ocorreu um erro. Verifica os dados.</p></div>';
    }

    echo '<h2>Criar atribuições</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-assignments')) . '">';
    wp_nonce_field('twt_tcrm_assign', 'twt_tcrm_assign_nonce');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Marca/Cliente</th><td>';
    echo '<select name="brand_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '">' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Atribuições ficam sempre associadas à marca.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Campanha</th><td>';
    echo '<select name="campaign_id" style="min-width:320px;">';
    echo '<option value="0">Sem campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr($c->ID) . '">' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Opcional, usa quando o formulário pertence a uma campanha.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Formulário</th><td>';
    echo '<select name="form_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($forms as $f) {
      echo '<option value="' . esc_attr($f->ID) . '">' . esc_html($f->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">O user só verá formulários atribuídos.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Utilizadores</th><td>';
    echo '<select name="user_ids[]" multiple size="10" required style="min-width:320px;">';
    foreach ($field_users as $u) {
      echo '<option value="' . esc_attr($u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Multi-selecção, atribui a vários de uma vez.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';

    echo '<p><button type="submit" class="button button-primary" name="twt_tcrm_do" value="assign">Atribuir</button></p>';
    echo '</form>';

    echo '<hr>';
    echo '<h2>Atribuições existentes</h2>';
    self::render_assignments_table();
    echo '</div>';
  }

  /* ======================================================
     UTILIZADORES DE MARCA
     ====================================================== */

  public static function page_brand_users() {
    if (!current_user_can('twt_tcrm_manage_brands')) {
      wp_die('Sem permissões.');
    }

    $brands = get_posts([
      'post_type' => 'twt_brand',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    $brand_users = get_users([
      'role__in' => ['twt_brand', 'twt_trade_manager', 'administrator'],
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 300,
    ]);

    $msg = isset($_GET['twt_tcrm_msg']) ? sanitize_text_field($_GET['twt_tcrm_msg']) : '';

    echo '<div class="wrap twt-tcrm-admin">';
    echo '<h1>Utilizadores de Marca</h1>';
    echo '<p>Associa cada utilizador a uma Marca/Cliente (user meta <code>twt_brand_id</code>).</p>';

    if ($msg === 'saved') {
      echo '<div class="notice notice-success"><p>Associação guardada.</p></div>';
    } elseif ($msg === 'error') {
      echo '<div class="notice notice-error"><p>Ocorreu um erro. Verifica os dados.</p></div>';
    }

    echo '<h2>Associar utilizador</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-brand-users')) . '">';
    wp_nonce_field('twt_tcrm_brand_user_link', 'twt_tcrm_brand_user_nonce');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Utilizador</th><td>';
    echo '<select name="user_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($brand_users as $u) {
      echo '<option value="' . esc_attr($u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Idealmente usa o role <code>twt_brand</code> para estes utilizadores.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Marca/Cliente</th><td>';
    echo '<select name="brand_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '">' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Isto controla o que o utilizador vê no dashboard de marca.</p>';
    echo '</td></tr>';

    echo '</tbody></table>';

    echo '<p><button type="submit" class="button button-primary" name="twt_tcrm_do" value="link_brand_user">Guardar</button></p>';
    echo '</form>';

    echo '<hr>';
    echo '<h2>Mapa actual</h2>';
    self::render_brand_users_table($brand_users);
    echo '</div>';
  }

  /* ======================================================
     HANDLERS
     ====================================================== */

  public static function handle_post_actions() {
    if (!is_admin()) return;

    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if (!$page) return;

    if ($page === self::MENU_SLUG . '-brand-users') {
      if (!current_user_can('twt_tcrm_manage_brands')) return;

      if (isset($_POST['twt_tcrm_do']) && $_POST['twt_tcrm_do'] === 'link_brand_user') {
        $nonce = isset($_POST['twt_tcrm_brand_user_nonce']) ? sanitize_text_field($_POST['twt_tcrm_brand_user_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'twt_tcrm_brand_user_link')) {
          wp_safe_redirect(self::url_brand_users(['twt_tcrm_msg' => 'error']));
          exit;
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $brand_id = isset($_POST['brand_id']) ? (int) $_POST['brand_id'] : 0;

        if (!$user_id || !$brand_id) {
          wp_safe_redirect(self::url_brand_users(['twt_tcrm_msg' => 'error']));
          exit;
        }

        $brand_post = get_post($brand_id);
        if (!$brand_post || $brand_post->post_type !== 'twt_brand') {
          wp_safe_redirect(self::url_brand_users(['twt_tcrm_msg' => 'error']));
          exit;
        }

        update_user_meta($user_id, 'twt_brand_id', $brand_id);

        wp_safe_redirect(self::url_brand_users(['twt_tcrm_msg' => 'saved']));
        exit;
      }

      return;
    }

    if ($page !== self::MENU_SLUG . '-assignments') return;
    if (!current_user_can('twt_tcrm_manage_assignments')) return;

    if (isset($_GET['twt_tcrm_toggle']) && isset($_GET['id'])) {
      $id = (int) $_GET['id'];
      $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

      if (!$id || !wp_verify_nonce($nonce, 'twt_tcrm_toggle_' . $id)) {
        wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
        exit;
      }

      self::toggle_assignment($id);

      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'toggled']));
      exit;
    }

    if (!isset($_POST['twt_tcrm_do']) || $_POST['twt_tcrm_do'] !== 'assign') return;

    $nonce = isset($_POST['twt_tcrm_assign_nonce']) ? sanitize_text_field($_POST['twt_tcrm_assign_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'twt_tcrm_assign')) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $brand_id = isset($_POST['brand_id']) ? (int) $_POST['brand_id'] : 0;
    $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
    $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
    $user_ids = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];

    if (!$brand_id || !$form_id || !$user_ids) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $form_brand = (int) get_post_meta($form_id, 'twt_brand_id', true);
    if ($form_brand && $form_brand !== $brand_id) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $form_campaign = (int) get_post_meta($form_id, 'twt_campaign_id', true);
    if ($form_campaign && $campaign_id && $form_campaign !== $campaign_id) {
      wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $ok = self::create_assignments($user_ids, $brand_id, $campaign_id, $form_id);

    wp_safe_redirect(self::url_assignments(['twt_tcrm_msg' => $ok ? 'assigned' : 'error']));
    exit;
  }

  /* ======================================================
     DB HELPERS
     ====================================================== */

  private static function create_assignments($user_ids, $brand_id, $campaign_id, $form_id) {
    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $created_any = false;

    foreach ($user_ids as $uid) {
      if (!$uid) continue;

      $existing_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t_assign
         WHERE user_id = %d AND brand_id = %d AND form_id = %d
           AND ( (campaign_id IS NULL AND %d = 0) OR (campaign_id = %d) )
         LIMIT 1",
        $uid, $brand_id, $form_id, $campaign_id, $campaign_id
      ));

      if ($existing_id) {
        $wpdb->update(
          $t_assign,
          [
            'active' => 1,
            'updated_at' => current_time('mysql'),
          ],
          ['id' => (int) $existing_id],
          ['%d', '%s'],
          ['%d']
        );
        $created_any = true;
        continue;
      }

      $ins = $wpdb->insert(
        $t_assign,
        [
          'user_id' => $uid,
          'brand_id' => $brand_id,
          'campaign_id' => $campaign_id ? $campaign_id : null,
          'form_id' => $form_id,
          'active' => 1,
          'created_at' => current_time('mysql'),
          'updated_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%d', '%d', '%s', '%s']
      );

      if ($ins) $created_any = true;
    }

    return $created_any;
  }

  private static function toggle_assignment($id) {
    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $row = $wpdb->get_row($wpdb->prepare("SELECT id, active FROM $t_assign WHERE id = %d", $id));
    if (!$row) return false;

    $new = ((int)$row->active === 1) ? 0 : 1;

    return $wpdb->update(
      $t_assign,
      [
        'active' => $new,
        'updated_at' => current_time('mysql'),
      ],
      ['id' => (int) $id],
      ['%d', '%s'],
      ['%d']
    );
  }

  /* ======================================================
     TABLE RENDER
     ====================================================== */

  private static function render_assignments_table() {
    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $rows = $wpdb->get_results("SELECT * FROM $t_assign ORDER BY id DESC LIMIT 200");

    if (!$rows) {
      echo '<p>Sem atribuições.</p>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Utilizador</th>';
    echo '<th>Marca</th>';
    echo '<th>Campanha</th>';
    echo '<th>Formulário</th>';
    echo '<th>Activo</th>';
    echo '<th>Ações</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $toggle_url = wp_nonce_url(
        self::url_assignments(['twt_tcrm_toggle' => '1', 'id' => (int) $r->id]),
        'twt_tcrm_toggle_' . (int) $r->id
      );

      $user = get_userdata((int) $r->user_id);
      $user_label = $user ? $user->display_name . ' (' . $user->user_login . ')' : 'User #' . (int)$r->user_id;

      $brand_title = $r->brand_id ? get_the_title((int) $r->brand_id) : '';
      $campaign_title = $r->campaign_id ? get_the_title((int) $r->campaign_id) : 'Sem';
      $form_title = $r->form_id ? get_the_title((int) $r->form_id) : '';

      echo '<tr>';
      echo '<td>' . esc_html((int) $r->id) . '</td>';
      echo '<td>' . esc_html($user_label) . '</td>';
      echo '<td>' . esc_html($brand_title) . '</td>';
      echo '<td>' . esc_html($campaign_title) . '</td>';
      echo '<td>' . esc_html($form_title) . '</td>';

      if ((int)$r->active === 1) {
        echo '<td><span class="status-active">Sim</span></td>';
      } else {
        echo '<td><span class="status-inactive">Não</span></td>';
      }

      echo '<td><a class="button" href="' . esc_url($toggle_url) . '">Alternar</a></td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  private static function render_brand_users_table($users) {
    if (!$users) {
      echo '<p>Sem utilizadores.</p>';
      return;
    }

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Utilizador</th>';
    echo '<th>Role</th>';
    echo '<th>Marca associada</th>';
    echo '</tr></thead><tbody>';

    foreach ($users as $u) {
      $brand_id = (int) get_user_meta($u->ID, 'twt_brand_id', true);
      $brand_title = $brand_id ? get_the_title($brand_id) : 'Sem';

      $roles = !empty($u->roles) ? implode(', ', $u->roles) : '';

      echo '<tr>';
      echo '<td>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</td>';
      echo '<td>' . esc_html($roles) . '</td>';
      echo '<td>' . esc_html($brand_title) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  /* ======================================================
     URL HELPERS
     ====================================================== */

  private static function url_assignments($args = []) {
    $base = admin_url('admin.php?page=' . self::MENU_SLUG . '-assignments');
    return add_query_arg($args, $base);
  }

  private static function url_brand_users($args = []) {
    $base = admin_url('admin.php?page=' . self::MENU_SLUG . '-brand-users');
    return add_query_arg($args, $base);
  }
}
