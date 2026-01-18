<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Submissions_Shortcodes {

  const ACTION_EXPORT_CSV = 'twt_tcrm_export_submissions_csv';
  const NONCE_ACTION = 'twt_tcrm_export_submissions_csv';
  const NONCE_FIELD = 'twt_tcrm_export_submissions_csv_nonce';

  public static function boot() {
    add_action('admin_post_' . self::ACTION_EXPORT_CSV, [__CLASS__, 'handle_export_csv']);
  }

  /**
   * Shortcode: [twt_submissions_table]
   *
   * Attributes:
   * - scope: "user" | "brand" (default "user")
   * - brand_id: number (only for scope=brand; default 0 = current user's brand if not admin_like)
   * - per_page: 1..200 (default 25)
   * - show_answers: 0|1 (default 1) -> shows expandable answers
   * - show_export: 0|1 (default 1) -> shows "Export CSV" button
   * - show_filters: 0|1 (default 1) -> shows filter bar (dropdowns)
   * - limit_latest: 0|N (default 0) -> if >0, shows only latest N, no pagination, and ignores GET filters (except scope permissions)
   * - view_all_url: string (optional) -> prints a "Ver todos" link
   */
  public static function shortcode_submissions_table($atts) {
    if (!is_user_logged_in()) {
      return '<p>Precisas de login.</p>';
    }

    $viewer_id = get_current_user_id();
    $is_admin_like = class_exists('TWT_TCRM_Roles') && TWT_TCRM_Roles::is_admin_like($viewer_id);

    $atts = shortcode_atts([
      'scope' => 'user',
      'brand_id' => 0,
      'per_page' => 25,
      'show_answers' => 1,
      'show_export' => 1,
      'show_filters' => 1,
      'limit_latest' => 0,
      'view_all_url' => '',
    ], $atts);

    $scope = sanitize_key($atts['scope']);
    if (!in_array($scope, ['user', 'brand'], true)) $scope = 'user';

    $per_page = max(1, min(200, (int) $atts['per_page']));
    $show_answers = ((int) $atts['show_answers']) === 1;
    $show_export = ((int) $atts['show_export']) === 1;
    $show_filters = ((int) $atts['show_filters']) === 1;

    $limit_latest = max(0, (int) $atts['limit_latest']);
    if ($limit_latest > 0) {
      $limit_latest = min(200, $limit_latest);
    }

    $view_all_url = trim((string) $atts['view_all_url']);
    if ($view_all_url !== '') {
      $view_all_url = esc_url($view_all_url);
    }

    // Resolve brand_id se for scope=brand
    $brand_id = (int) $atts['brand_id'];
    if ($scope === 'brand') {
      if (!$brand_id) {
        $brand_id = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
      }

      if (!$brand_id) {
        return '<p>Não tens marca associada.</p>';
      }

      // Permissões: não-admin só pode ver a própria marca
      if (!$is_admin_like) {
        $own = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
        if ((int) $own !== (int) $brand_id) {
          return '<p>Sem acesso a esta marca.</p>';
        }
      }
    }

    // Filtros via GET (só usados se limit_latest = 0)
    $filters = [
      'form_id' => 0,
      'campaign_id' => 0,
      'location_id' => 0,
      'date_from' => '',
      'date_to' => '',
      'q' => '',
    ];

    if ($limit_latest <= 0) {
      $filters = [
        'form_id' => isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0,
        'campaign_id' => isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0,
        'location_id' => isset($_GET['location_id']) ? (int) $_GET['location_id'] : 0,
        'date_from' => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
        'date_to' => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
        'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '',
      ];

      if ($filters['date_from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) $filters['date_from'] = '';
      if ($filters['date_to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) $filters['date_to'] = '';
    }

    // Dropdown data (ligado ao BO via Form_Renderer quando existe) — só precisamos se vamos mostrar filtros
    $dropdowns = ['forms' => [], 'forms_map' => [], 'campaigns' => [], 'locations' => []];

    if ($show_filters && $limit_latest <= 0) {
      $dropdowns = self::get_dropdown_data($viewer_id, $is_admin_like, $filters['form_id']);

      // Se form_id selecionado não está na lista (user sem acesso), reset
      if ($filters['form_id'] && !isset($dropdowns['forms_map'][(int) $filters['form_id']])) {
        $filters['form_id'] = 0;
        $filters['campaign_id'] = 0;
        $filters['location_id'] = 0;
      }

      // Se campaign_id selecionado não é permitido no form selecionado, reset
      if ($filters['campaign_id'] && $filters['form_id']) {
        $allowed_campaigns = self::get_allowed_campaign_ids_for_form($filters['form_id']);
        if ($allowed_campaigns && !in_array((int) $filters['campaign_id'], $allowed_campaigns, true)) {
          $filters['campaign_id'] = 0;
        }
      }

      // Se location_id selecionado não é permitido, reset
      if ($filters['location_id'] && $filters['form_id']) {
        $allowed_locations = self::get_allowed_location_ids_for_form_and_user($filters['form_id'], $viewer_id, $is_admin_like);
        if ($allowed_locations && !in_array((int) $filters['location_id'], $allowed_locations, true)) {
          $filters['location_id'] = 0;
        }
      }
    }

    // Paginação (só se limit_latest=0)
    $page = 1;
    $offset = 0;
    $pages = 1;

    if ($limit_latest <= 0) {
      $page = isset($_GET['twt_page']) ? max(1, (int) $_GET['twt_page']) : 1;
      $offset = ($page - 1) * $per_page;
    }

    // Query
    if ($limit_latest > 0) {
      $result = self::query_submissions($scope, $viewer_id, $brand_id, [], $limit_latest, 0);
    } else {
      $result = self::query_submissions($scope, $viewer_id, $brand_id, $filters, $per_page, $offset);
    }

    $rows = $result['rows'];
    $total = (int) $result['total'];

    if ($limit_latest <= 0) {
      $pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
    }

    $out = '';
    $out .= '<div class="twt-tcrm twt-tcrm-submissions-table">';

    // Top actions (Ver todos)
    if ($view_all_url !== '') {
      $out .= '<div style="margin: 0 0 10px 0;">';
      $out .= '<a href="' . esc_url($view_all_url) . '">Ver todos</a>';
      $out .= '</div>';
    }

    if ($show_filters && $limit_latest <= 0) {
      $out .= self::render_filters_form($filters, $dropdowns);
    }

    if ($show_export && $limit_latest <= 0) {
      $out .= self::render_export_button($scope, $brand_id, $filters);
    }

    if (!$rows) {
      $out .= '<p class="twt-tcrm-muted">Sem submissões.</p>';
      $out .= '</div>';
      return $out;
    }

    $out .= '<table class="twt-tcrm-table">';
    $out .= '<thead><tr>';
    $out .= '<th>ID</th><th>Data</th><th>Form</th><th>Marca</th><th>Campanha</th><th>Local</th>';
    if ($scope === 'brand') $out .= '<th>Utilizador</th>';
    $out .= '<th>Status</th>';
    if ($show_answers) $out .= '<th>Respostas</th>';
    $out .= '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $sub_id = (int) $r['id'];
      $when = $r['submitted_at'] ? mysql2date('Y-m-d H:i', $r['submitted_at']) : '';
      $form_title = $r['form_id'] ? get_the_title((int) $r['form_id']) : '';
      $brand_title = $r['brand_id'] ? get_the_title((int) $r['brand_id']) : '';
      $camp_title = ((int) $r['campaign_id']) ? get_the_title((int) $r['campaign_id']) : 'Sem';
      $loc_title = ((int) $r['location_id']) ? get_the_title((int) $r['location_id']) : 'Sem';
      $status = (string) ($r['status'] ?? '');

      $out .= '<tr>';
      $out .= '<td>' . esc_html((string) $sub_id) . '</td>';
      $out .= '<td>' . esc_html($when) . '</td>';
      $out .= '<td>' . esc_html($form_title) . '</td>';
      $out .= '<td>' . esc_html($brand_title) . '</td>';
      $out .= '<td>' . esc_html($camp_title) . '</td>';
      $out .= '<td>' . esc_html($loc_title) . '</td>';

      if ($scope === 'brand') {
        $u = $r['user_id'] ? get_userdata((int) $r['user_id']) : null;
        $u_label = $u ? $u->display_name : ('User #' . (int) $r['user_id']);
        $out .= '<td>' . esc_html($u_label) . '</td>';
      }

      $out .= '<td>' . esc_html($status) . '</td>';

      if ($show_answers) {
        $answers = self::get_answers_map($sub_id);
        $out .= '<td><details><summary>Ver (' . esc_html((string) count($answers)) . ')</summary>';
        $out .= self::render_answers_table($answers);
        $out .= '</details></td>';
      }

      $out .= '</tr>';
    }

    $out .= '</tbody></table>';

    if ($limit_latest <= 0 && $pages > 1) {
      $out .= self::render_pagination($page, $pages);
    }

    $out .= '</div>';
    return $out;
  }

  private static function get_dropdown_data($viewer_id, $is_admin_like, $selected_form_id) {
    $forms = self::get_forms_for_viewer($viewer_id, $is_admin_like);

    $forms_map = [];
    foreach ($forms as $f) {
      $forms_map[(int) $f->ID] = $f;
    }

    $campaigns = self::get_campaigns_for_form((int) $selected_form_id);
    $locations = self::get_locations_for_form_and_viewer((int) $selected_form_id, (int) $viewer_id, (bool) $is_admin_like);

    return [
      'forms' => $forms,
      'forms_map' => $forms_map,
      'campaigns' => $campaigns,
      'locations' => $locations,
    ];
  }

  private static function render_filters_form(array $filters, array $dropdowns) {
    $out = '';
    $out .= '<form method="get" style="margin: 8px 0 14px 0;">';

    foreach ($_GET as $k => $v) {
      if (in_array($k, ['form_id','campaign_id','location_id','date_from','date_to','q','twt_page'], true)) continue;
      if (is_array($v)) continue;
      $out .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr(wp_unslash($v)) . '">';
    }

    $out .= '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">';

    $out .= '<div><label><strong>Formulário</strong></label><br>';
    $out .= '<select name="form_id" style="min-width:240px;">';
    $out .= '<option value="0">Todos</option>';
    foreach ($dropdowns['forms'] as $f) {
      $out .= '<option value="' . esc_attr((int) $f->ID) . '"' . selected((int) $filters['form_id'], (int) $f->ID, false) . '>' . esc_html($f->post_title) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div><label><strong>Campanha</strong></label><br>';
    $out .= '<select name="campaign_id" style="min-width:240px;">';
    $out .= '<option value="0">Sem campanha / Todas</option>';
    foreach ($dropdowns['campaigns'] as $c) {
      $out .= '<option value="' . esc_attr((int) $c->ID) . '"' . selected((int) $filters['campaign_id'], (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div><label><strong>Local</strong></label><br>';
    $out .= '<select name="location_id" style="min-width:240px;">';
    $out .= '<option value="0">Sem local / Todos</option>';
    foreach ($dropdowns['locations'] as $l) {
      $out .= '<option value="' . esc_attr((int) $l->ID) . '"' . selected((int) $filters['location_id'], (int) $l->ID, false) . '>' . esc_html($l->post_title) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div><label><strong>De</strong></label><br><input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '"></div>';
    $out .= '<div><label><strong>Até</strong></label><br><input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '"></div>';

    $out .= '<div><label><strong>Status</strong></label><br><input type="text" name="q" value="' . esc_attr($filters['q']) . '" placeholder="submitted..." style="width:160px;"></div>';

    $out .= '<div><button class="twt-tcrm-btn" type="submit">Filtrar</button></div>';

    $out .= '</div>';
    $out .= '<p class="twt-tcrm-muted" style="margin:6px 0 0 0;">Tip: muda o Formulário para atualizar Campanha/Local automaticamente.</p>';
    $out .= '</form>';

    return $out;
  }

  private static function render_export_button($scope, $brand_id, array $filters) {
    $action_url = admin_url('admin-post.php');

    $out = '';
    $out .= '<form method="post" action="' . esc_url($action_url) . '" style="margin: 0 0 14px 0;">';
    $out .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_EXPORT_CSV) . '">';
    $out .= wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD, true, false);

    $out .= '<input type="hidden" name="scope" value="' . esc_attr($scope) . '">';
    $out .= '<input type="hidden" name="brand_id" value="' . esc_attr((string) (int) $brand_id) . '">';

    foreach ($filters as $k => $v) {
      $out .= '<input type="hidden" name="filters[' . esc_attr($k) . ']" value="' . esc_attr((string) $v) . '">';
    }

    $out .= '<button class="twt-tcrm-btn" type="submit">Exportar CSV</button>';
    $out .= '</form>';

    return $out;
  }

  private static function render_pagination($page, $pages) {
    $out = '<div class="twt-tcrm-pagination" style="margin-top:12px;">';

    for ($p = 1; $p <= $pages; $p++) {
      $url = add_query_arg(['twt_page' => $p]);
      if ($p === (int) $page) {
        $out .= '<strong style="margin-right:8px;">' . esc_html((string) $p) . '</strong>';
      } else {
        $out .= '<a style="margin-right:8px;" href="' . esc_url($url) . '">' . esc_html((string) $p) . '</a>';
      }
    }

    $out .= '</div>';
    return $out;
  }

  private static function query_submissions($scope, $viewer_id, $brand_id, array $filters, $limit, $offset) {
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $where = "WHERE 1=1";
    $params = [];

    if ($scope === 'user') {
      $where .= " AND user_id = %d";
      $params[] = (int) $viewer_id;
    } else {
      $where .= " AND brand_id = %d";
      $params[] = (int) $brand_id;
    }

    if (!empty($filters['form_id'])) {
      $where .= " AND form_id = %d";
      $params[] = (int) $filters['form_id'];
    }
    if (!empty($filters['campaign_id'])) {
      $where .= " AND campaign_id = %d";
      $params[] = (int) $filters['campaign_id'];
    }
    if (!empty($filters['location_id'])) {
      $where .= " AND location_id = %d";
      $params[] = (int) $filters['location_id'];
    }
    if (!empty($filters['date_from'])) {
      $where .= " AND submitted_at >= %s";
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND submitted_at <= %s";
      $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['q'])) {
      $where .= " AND status LIKE %s";
      $params[] = '%' . $wpdb->esc_like($filters['q']) . '%';
    }

    $limit = max(1, min(5000, (int) $limit));
    $offset = max(0, (int) $offset);

    $sql_total = "SELECT COUNT(*) FROM {$t_sub} {$where}";
    $total = (int) $wpdb->get_var($wpdb->prepare($sql_total, $params));

    $sql = "SELECT id, form_id, brand_id, campaign_id, location_id, user_id, submitted_at, status
            FROM {$t_sub}
            {$where}
            ORDER BY submitted_at DESC
            LIMIT {$limit} OFFSET {$offset}";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    $rows = is_array($rows) ? $rows : [];

    return ['rows' => $rows, 'total' => $total];
  }

  private static function get_answers_map($submission_id) {
    global $wpdb;
    $t_ans = TWT_TCRM_DB::table_answers();

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT question_key, value_text, value_number, value_currency, value_percent, value_json
       FROM {$t_ans}
       WHERE submission_id = %d
       ORDER BY question_key ASC",
      (int) $submission_id
    ), ARRAY_A);

    $rows = is_array($rows) ? $rows : [];

    $answers = [];
    foreach ($rows as $a) {
      $k = (string) $a['question_key'];
      $val = '';
      if ($a['value_text'] !== null && $a['value_text'] !== '') $val = (string) $a['value_text'];
      elseif ($a['value_number'] !== null && $a['value_number'] !== '') $val = (string) $a['value_number'];
      elseif ($a['value_currency'] !== null && $a['value_currency'] !== '') $val = (string) $a['value_currency'];
      elseif ($a['value_percent'] !== null && $a['value_percent'] !== '') $val = (string) $a['value_percent'];
      elseif ($a['value_json'] !== null && $a['value_json'] !== '') $val = (string) $a['value_json'];

      $answers[$k] = $val;
    }

    return $answers;
  }

  private static function render_answers_table(array $answers) {
    if (!$answers) return '<p class="twt-tcrm-muted">Sem respostas.</p>';

    $out = '';
    $out .= '<table class="twt-tcrm-table" style="margin-top:10px;">';
    $out .= '<thead><tr><th>Pergunta</th><th>Resposta</th></tr></thead><tbody>';

    foreach ($answers as $k => $v) {
      $out .= '<tr>';
      $out .= '<td>' . esc_html((string) $k) . '</td>';
      $out .= '<td>' . esc_html((string) $v) . '</td>';
      $out .= '</tr>';
    }

    $out .= '</tbody></table>';
    return $out;
  }

  public static function handle_export_csv() {
    if (!is_user_logged_in()) {
      wp_die('Sem login.');
    }

    $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
      wp_die('Nonce inválido.');
    }

    $viewer_id = get_current_user_id();
    $is_admin_like = class_exists('TWT_TCRM_Roles') && TWT_TCRM_Roles::is_admin_like($viewer_id);

    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'user';
    if (!in_array($scope, ['user', 'brand'], true)) $scope = 'user';

    $brand_id = isset($_POST['brand_id']) ? (int) wp_unslash($_POST['brand_id']) : 0;

    if ($scope === 'brand') {
      if (!$brand_id) {
        $brand_id = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
      }
      if (!$brand_id) wp_die('Sem marca.');

      if (!$is_admin_like) {
        $own = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
        if ((int) $own !== (int) $brand_id) {
          wp_die('Sem acesso.');
        }
      }
    }

    $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? (array) wp_unslash($_POST['filters']) : [];
    $filters = array_merge([
      'form_id' => 0,
      'campaign_id' => 0,
      'location_id' => 0,
      'date_from' => '',
      'date_to' => '',
      'q' => '',
    ], $filters);

    $filters['form_id'] = (int) $filters['form_id'];
    $filters['campaign_id'] = (int) $filters['campaign_id'];
    $filters['location_id'] = (int) $filters['location_id'];
    $filters['date_from'] = sanitize_text_field((string) $filters['date_from']);
    $filters['date_to'] = sanitize_text_field((string) $filters['date_to']);
    $filters['q'] = sanitize_text_field((string) $filters['q']);

    $result = self::query_submissions($scope, $viewer_id, $brand_id, $filters, 5000, 0);
    $rows = $result['rows'];

    $filename = 'twt-submissions-' . $scope . '-' . gmdate('Ymd-His') . '.csv';

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $fh = fopen('php://output', 'w');

    // BOM para Excel
    fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $header = [
      'submission_id',
      'submitted_at',
      'status',
      'form_id',
      'form_name',
      'brand_id',
      'brand_name',
      'campaign_id',
      'campaign_name',
      'location_id',
      'location_name',
      'user_id',
      'user_name',
      'answers_json',
    ];
    fputcsv($fh, $header);

    foreach ($rows as $r) {
      $sub_id = (int) $r['id'];
      $answers = self::get_answers_map($sub_id);

      $user_name = '';
      if (!empty($r['user_id'])) {
        $u = get_userdata((int) $r['user_id']);
        $user_name = $u ? $u->display_name : '';
      }

      $line = [
        (int) $r['id'],
        (string) ($r['submitted_at'] ?? ''),
        (string) ($r['status'] ?? ''),
        (int) ($r['form_id'] ?? 0),
        (string) (($r['form_id'] ?? 0) ? get_the_title((int) $r['form_id']) : ''),
        (int) ($r['brand_id'] ?? 0),
        (string) (($r['brand_id'] ?? 0) ? get_the_title((int) $r['brand_id']) : ''),
        (int) ($r['campaign_id'] ?? 0),
        (string) (((int) ($r['campaign_id'] ?? 0)) ? get_the_title((int) $r['campaign_id']) : 'Sem'),
        (int) ($r['location_id'] ?? 0),
        (string) (((int) ($r['location_id'] ?? 0)) ? get_the_title((int) $r['location_id']) : 'Sem'),
        (int) ($r['user_id'] ?? 0),
        (string) $user_name,
        wp_json_encode($answers, JSON_UNESCAPED_UNICODE),
      ];

      fputcsv($fh, $line);
    }

    fclose($fh);
    exit;
  }

  /* =========================================================
     Dropdown helpers (ligação ao BO)
     ========================================================= */

  private static function get_forms_for_viewer($viewer_id, $is_admin_like) {
    if ($is_admin_like) {
      $forms = get_posts([
        'post_type' => 'twt_form',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
          [
            'key' => 'twt_form_status',
            'value' => 'active',
            'compare' => '=',
          ]
        ],
      ]);
      return is_array($forms) ? $forms : [];
    }

    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT form_id FROM {$t_assign} WHERE user_id = %d AND active = 1",
      (int) $viewer_id
    ));
    $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];

    if (!$ids) return [];

    $forms = get_posts([
      'post_type' => 'twt_form',
      'post__in' => $ids,
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    return is_array($forms) ? $forms : [];
  }

  private static function get_campaigns_for_form($form_id) {
    $form_id = (int) $form_id;

    if ($form_id > 0 && class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'get_form_campaign_ids')) {
      $ids = TWT_TCRM_Form_Renderer::get_form_campaign_ids($form_id);
      $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
      if ($ids) {
        $posts = get_posts([
          'post_type' => 'twt_campaign',
          'post__in' => $ids,
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        ]);
        return is_array($posts) ? $posts : [];
      }
      return [];
    }

    $posts = get_posts([
      'post_type' => 'twt_campaign',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);
    return is_array($posts) ? $posts : [];
  }

  private static function get_locations_for_form_and_viewer($form_id, $viewer_id, $is_admin_like) {
    $form_id = (int) $form_id;
    $viewer_id = (int) $viewer_id;

    if ($form_id <= 0) {
      if ($is_admin_like) {
        $posts = get_posts([
          'post_type' => 'twt_location',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        ]);
        return is_array($posts) ? $posts : [];
      }

      if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'get_user_location_ids')) {
        $ids = TWT_TCRM_Form_Renderer::get_user_location_ids($viewer_id);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
        if (!$ids) return [];
        $posts = get_posts([
          'post_type' => 'twt_location',
          'post__in' => $ids,
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        ]);
        return is_array($posts) ? $posts : [];
      }

      return [];
    }

    if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'get_form_location_ids')) {
      $form_ids = TWT_TCRM_Form_Renderer::get_form_location_ids($form_id);
      $form_ids = is_array($form_ids) ? array_values(array_unique(array_filter(array_map('intval', $form_ids)))) : [];
    } else {
      $form_ids = [];
    }

    if ($is_admin_like) {
      if (!$form_ids) {
        $posts = get_posts([
          'post_type' => 'twt_location',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        ]);
        return is_array($posts) ? $posts : [];
      }

      $posts = get_posts([
        'post_type' => 'twt_location',
        'post__in' => $form_ids,
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
      ]);
      return is_array($posts) ? $posts : [];
    }

    if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'get_user_location_ids')) {
      $user_ids = TWT_TCRM_Form_Renderer::get_user_location_ids($viewer_id);
      $user_ids = is_array($user_ids) ? array_values(array_unique(array_filter(array_map('intval', $user_ids)))) : [];
    } else {
      $user_ids = [];
    }

    $ids = !$form_ids ? $user_ids : array_values(array_intersect($form_ids, $user_ids));
    if (!$ids) return [];

    $posts = get_posts([
      'post_type' => 'twt_location',
      'post__in' => $ids,
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);
    return is_array($posts) ? $posts : [];
  }

  private static function get_allowed_campaign_ids_for_form($form_id) {
    $form_id = (int) $form_id;
    if ($form_id <= 0) return [];

    if (class_exists('TWT_TCRM_Form_Renderer') && method_exists('TWT_TCRM_Form_Renderer', 'get_form_campaign_ids')) {
      $ids = TWT_TCRM_Form_Renderer::get_form_campaign_ids($form_id);
      $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];
      return $ids;
    }

    return [];
  }

  private static function get_allowed_location_ids_for_form_and_user($form_id, $user_id, $is_admin_like) {
    $posts = self::get_locations_for_form_and_viewer((int) $form_id, (int) $user_id, (bool) $is_admin_like);
    $ids = [];
    foreach ($posts as $p) $ids[] = (int) $p->ID;
    $ids = array_values(array_unique(array_filter($ids)));
    return $ids;
  }
}
