<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Submissions_Admin {

  const PAGE_SLUG = 'twt-tcrm-submissions';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
    add_action('admin_post_twt_tcrm_export_submissions_csv', [__CLASS__, 'handle_export_csv']);
  }

  public static function register_menu() {
    add_submenu_page(
      'edit.php?post_type=twt_brand',
      'Submissões',
      'Submissões',
      'twt_tcrm_view_all_reports',
      self::PAGE_SLUG,
      [__CLASS__, 'render_page']
    );
  }

  private static function get_filters_from_request() {
    $brand_id = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : 0;
    $campaign_id = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
    $form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

    if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = '';
    if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) $date_to = '';

    $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
    if ($status && !in_array($status, ['submitted', 'draft', 'rejected', 'approved'], true)) {
      $status = '';
    }

    return [
      'brand_id' => $brand_id,
      'campaign_id' => $campaign_id,
      'form_id' => $form_id,
      'user_id' => $user_id,
      'date_from' => $date_from,
      'date_to' => $date_to,
      'status' => $status,
    ];
  }

  private static function get_dropdown_data($filters) {
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

    // Users filtrados por assignments (se brand_id definido)
    $users = [];
    if (!empty($filters['brand_id'])) {
      $ids = TWT_TCRM_DB::get_assigned_user_ids(
        (int) $filters['brand_id'],
        (int) $filters['campaign_id'], // inclui 0 (geral) quando campaign > 0
        (int) $filters['form_id'],
        true
      );

      foreach ($ids as $uid) {
        $u = get_user_by('id', (int) $uid);
        if (!$u) continue;
        $users[] = $u;
      }
    }

    return compact('brands', 'campaigns', 'forms', 'users');
  }

  private static function build_where_sql($filters, &$params) {
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['brand_id'])) {
      $where .= " AND s.brand_id = %d";
      $params[] = (int) $filters['brand_id'];
    }
    if (!empty($filters['campaign_id'])) {
      $where .= " AND s.campaign_id = %d";
      $params[] = (int) $filters['campaign_id'];
    }
    if (!empty($filters['form_id'])) {
      $where .= " AND s.form_id = %d";
      $params[] = (int) $filters['form_id'];
    }
    if (!empty($filters['user_id'])) {
      $where .= " AND s.user_id = %d";
      $params[] = (int) $filters['user_id'];
    }
    if (!empty($filters['status'])) {
      $where .= " AND s.status = %s";
      $params[] = (string) $filters['status'];
    }
    if (!empty($filters['date_from'])) {
      $where .= " AND s.submitted_at >= %s";
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND s.submitted_at <= %s";
      $params[] = $filters['date_to'] . ' 23:59:59';
    }

    return $where;
  }

  private static function list_submissions($filters, $page, $per_page, &$total) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();

    $params = [];
    $where = self::build_where_sql($filters, $params);

    $total_sql = "SELECT COUNT(*) FROM {$t_sub} s {$where}";
    $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $params));

    $offset = max(0, ($page - 1) * $per_page);

    $sql = "SELECT s.*
            FROM {$t_sub} s
            {$where}
            ORDER BY s.submitted_at DESC
            LIMIT %d OFFSET %d";

    $params2 = array_merge($params, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);

    return is_array($rows) ? $rows : [];
  }

  public static function render_page() {
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    $filters = self::get_filters_from_request();
    $data = self::get_dropdown_data($filters);

    $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $per_page = 30;

    $total = 0;
    $rows = self::list_submissions($filters, $page, $per_page, $total);

    $export_url = admin_url('admin-post.php?action=twt_tcrm_export_submissions_csv');
    $export_url = add_query_arg($filters, $export_url);
    $export_url = wp_nonce_url($export_url, 'twt_tcrm_export_submissions_csv', 'nonce');

    echo '<div class="wrap">';
    echo '<h1>Submissões</h1>';

    echo '<form method="get" style="margin: 12px 0 16px 0;">';
    echo '<input type="hidden" name="post_type" value="twt_brand">';
    echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';

    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">';

    // Marca
    echo '<div>';
    echo '<label><strong>Marca</strong></label><br>';
    echo '<select name="brand_id" style="min-width:240px;">';
    echo '<option value="0">Todas</option>';
    foreach ($data['brands'] as $b) {
      echo '<option value="' . esc_attr((int) $b->ID) . '"' . selected($filters['brand_id'], (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Campanha
    echo '<div>';
    echo '<label><strong>Campanha</strong></label><br>';
    echo '<select name="campaign_id" style="min-width:240px;">';
    echo '<option value="0">Todas</option>';
    foreach ($data['campaigns'] as $c) {
      echo '<option value="' . esc_attr((int) $c->ID) . '"' . selected($filters['campaign_id'], (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Form
    echo '<div>';
    echo '<label><strong>Formulário</strong></label><br>';
    echo '<select name="form_id" style="min-width:240px;">';
    echo '<option value="0">Todos</option>';
    foreach ($data['forms'] as $f) {
      echo '<option value="' . esc_attr((int) $f->ID) . '"' . selected($filters['form_id'], (int) $f->ID, false) . '>' . esc_html($f->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // User (filtrado)
    echo '<div>';
    echo '<label><strong>Utilizador</strong></label><br>';
    echo '<select name="user_id" style="min-width:320px;">';
    echo '<option value="0">' . ($filters['brand_id'] ? 'Todos (da marca)' : 'Escolhe uma marca') . '</option>';

    if ($filters['brand_id']) {
      foreach ($data['users'] as $u) {
        $label = $u->display_name . ' (' . $u->user_login . ')';
        echo '<option value="' . esc_attr((int) $u->ID) . '"' . selected($filters['user_id'], (int) $u->ID, false) . '>' . esc_html($label) . '</option>';
      }
    }

    echo '</select>';
    echo '</div>';

    // Datas
    echo '<div>';
    echo '<label><strong>De</strong></label><br>';
    echo '<input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '">';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>Até</strong></label><br>';
    echo '<input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '">';
    echo '</div>';

    // Status
    echo '<div>';
    echo '<label><strong>Status</strong></label><br>';
    echo '<select name="status" style="min-width:160px;">';
    echo '<option value="">Todos</option>';
    $statuses = ['submitted' => 'submitted', 'draft' => 'draft', 'approved' => 'approved', 'rejected' => 'rejected'];
    foreach ($statuses as $k => $lbl) {
      echo '<option value="' . esc_attr($k) . '"' . selected($filters['status'], $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<button class="button button-primary" type="submit">Filtrar</button> ';
    echo '<a class="button" href="' . esc_url($export_url) . '">Exportar CSV</a>';
    echo '</div>';

    echo '</div>';
    echo '</form>';

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Data</th>';
    echo '<th>Marca</th>';
    echo '<th>Campanha</th>';
    echo '<th>Form</th>';
    echo '<th>User</th>';
    echo '<th>Status</th>';
    echo '</tr></thead><tbody>';

    if (!$rows) {
      echo '<tr><td colspan="7">Sem resultados.</td></tr>';
    } else {
      foreach ($rows as $r) {
        $brand = $r['brand_id'] ? get_the_title((int) $r['brand_id']) : '';
        $campaign = $r['campaign_id'] ? get_the_title((int) $r['campaign_id']) : '';
        $form = $r['form_id'] ? get_the_title((int) $r['form_id']) : '';
        $u = $r['user_id'] ? get_user_by('id', (int) $r['user_id']) : null;
        $uname = $u ? $u->display_name : '';

        echo '<tr>';
        echo '<td>' . esc_html($r['id']) . '</td>';
        echo '<td>' . esc_html($r['submitted_at']) . '</td>';
        echo '<td>' . esc_html($brand ?: '-') . '</td>';
        echo '<td>' . esc_html($campaign ?: '-') . '</td>';
        echo '<td>' . esc_html($form ?: '-') . '</td>';
        echo '<td>' . esc_html($uname ?: '-') . '</td>';
        echo '<td>' . esc_html($r['status']) . '</td>';
        echo '</tr>';
      }
    }

    echo '</tbody></table>';

    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages > 1) {
      $args = array_merge(['post_type' => 'twt_brand', 'page' => self::PAGE_SLUG], $filters);
      echo '<div style="margin-top:14px;">';
      echo paginate_links([
        'base' => add_query_arg($args + ['paged' => '%#%'], admin_url('edit.php')),
        'format' => '',
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
        'total' => $total_pages,
        'current' => $page,
      ]);
      echo '</div>';
    }

    echo '</div>';
  }

  // handle_export_csv() mantém como já tinhas (não precisa mudar)
  public static function handle_export_csv() {
    // mantém o teu método existente (da versão anterior)
    // (se quiseres, eu devolvo-o aqui completo também — mas é igual)
    if (!current_user_can('twt_tcrm_view_all_reports')) {
      wp_die('Sem permissões.');
    }

    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'twt_tcrm_export_submissions_csv')) {
      wp_die('Nonce inválido.');
    }

    $filters = self::get_filters_from_request();

    $page = 1;
    $per_page = 500;

    $filename = 'twt-submissions-' . gmdate('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();
    $t_ans = TWT_TCRM_DB::table_answers();

    $params = [];
    $where = self::build_where_sql($filters, $params);

    $keys_sql = "SELECT DISTINCT a.question_key
                 FROM {$t_ans} a
                 INNER JOIN {$t_sub} s ON s.id = a.submission_id
                 {$where}
                 ORDER BY a.question_key ASC";
    $keys = $wpdb->get_col($wpdb->prepare($keys_sql, $params));
    $keys = is_array($keys) ? $keys : [];

    $header = ['submission_id', 'submitted_at', 'status', 'brand_id', 'brand', 'campaign_id', 'campaign', 'form_id', 'form', 'user_id', 'user'];
    foreach ($keys as $k) $header[] = $k;
    fputcsv($out, $header);

    while (true) {
      $total = 0;
      $subs = self::list_submissions($filters, $page, $per_page, $total);
      if (!$subs) break;

      $ids = array_map(static function ($r) { return (int) $r['id']; }, $subs);
      $ids = array_values(array_filter($ids));
      if (!$ids) break;

      $placeholders = implode(',', array_fill(0, count($ids), '%d'));
      $ans_sql = "SELECT submission_id, question_key, value_text, value_number, value_currency, value_percent, value_json
                  FROM {$t_ans}
                  WHERE submission_id IN ($placeholders)";
      $ans_rows = $wpdb->get_results($wpdb->prepare($ans_sql, $ids), ARRAY_A);
      $ans_rows = is_array($ans_rows) ? $ans_rows : [];

      $by_sub = [];
      foreach ($ans_rows as $a) {
        $sid = (int) $a['submission_id'];
        $k = (string) $a['question_key'];

        $val = '';
        if ($a['value_text'] !== null && $a['value_text'] !== '') $val = (string) $a['value_text'];
        elseif ($a['value_number'] !== null && $a['value_number'] !== '') $val = (string) $a['value_number'];
        elseif ($a['value_currency'] !== null && $a['value_currency'] !== '') $val = (string) $a['value_currency'];
        elseif ($a['value_percent'] !== null && $a['value_percent'] !== '') $val = (string) $a['value_percent'];
        elseif ($a['value_json'] !== null && $a['value_json'] !== '') $val = (string) $a['value_json'];

        if (!isset($by_sub[$sid])) $by_sub[$sid] = [];
        if (isset($by_sub[$sid][$k]) && $by_sub[$sid][$k] !== '') $by_sub[$sid][$k] .= ' | ' . $val;
        else $by_sub[$sid][$k] = $val;
      }

      foreach ($subs as $s) {
        $sid = (int) $s['id'];
        $brand = $s['brand_id'] ? get_the_title((int) $s['brand_id']) : '';
        $campaign = $s['campaign_id'] ? get_the_title((int) $s['campaign_id']) : '';
        $form = $s['form_id'] ? get_the_title((int) $s['form_id']) : '';
        $u = $s['user_id'] ? get_user_by('id', (int) $s['user_id']) : null;
        $uname = $u ? $u->display_name : '';

        $row = [
          $sid,
          $s['submitted_at'],
          $s['status'],
          (int) $s['brand_id'],
          $brand,
          (int) $s['campaign_id'],
          $campaign,
          (int) $s['form_id'],
          $form,
          (int) $s['user_id'],
          $uname,
        ];

        $answers = isset($by_sub[$sid]) ? $by_sub[$sid] : [];
        foreach ($keys as $k) $row[] = isset($answers[$k]) ? $answers[$k] : '';

        fputcsv($out, $row);
      }

      $page++;
      if ($page > 2000) break;
    }

    fclose($out);
    exit;
  }
}