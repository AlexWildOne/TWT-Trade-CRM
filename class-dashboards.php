<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Dashboards {

  public static function boot() {
    // reservado para futuras actions
  }

  /**
   * Shortcode: [twt_user_dashboard]
   */
  public static function shortcode_user_dashboard($atts) {
    if (!is_user_logged_in()) {
      return '<p>Precisas de login.</p>';
    }

    $user_id = get_current_user_id();

    $kpis = self::get_user_kpis($user_id);
    $reports = self::get_recent_reports(['user_id' => $user_id], 15);

    $out = '';
    $out .= '<div class="twt-tcrm twt-tcrm-dashboard twt-tcrm-user-dashboard">';
    $out .= '<h2>Dashboard</h2>';

    $out .= self::render_kpis([
      ['label' => 'Reports, 7 dias', 'value' => $kpis['reports_7d']],
      ['label' => 'Reports, 30 dias', 'value' => $kpis['reports_30d']],
      ['label' => 'Marcas reportadas, 30 dias', 'value' => $kpis['brands_30d']],
      ['label' => 'Campanhas reportadas, 30 dias', 'value' => $kpis['campaigns_30d']],
    ]);

    $out .= '<div class="twt-tcrm-grid-cards">';
    $out .= '<div class="twt-tcrm-card">';
    $out .= '<h3>Os teus últimos reports</h3>';
    $out .= self::render_reports_table($reports, 'user');
    $out .= '</div>';

    $out .= '<div class="twt-tcrm-card">';
    $out .= '<h3>Sugestões</h3>';
    $out .= self::render_insights_for_user($user_id);
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';

    return $out;
  }

  /**
   * Shortcode: [twt_brand_dashboard]
   * Mostra dados da marca associada ao utilizador via user_meta twt_brand_id
   */
  public static function shortcode_brand_dashboard($atts) {
    if (!is_user_logged_in()) {
      return '<p>Precisas de login.</p>';
    }

    $viewer_id = get_current_user_id();

    // Admin-like pode forçar brand_id via shortcode: [twt_brand_dashboard brand_id="123"]
    $atts = shortcode_atts([
      'brand_id' => 0,
    ], $atts);

    $brand_id = (int) $atts['brand_id'];

    if (!$brand_id) {
      $brand_id = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
    }

    if (!$brand_id) {
      return '<p>Não tens marca associada. Pede ao admin para ligar a tua conta a uma marca.</p>';
    }

    $brand_post = get_post($brand_id);
    if (!$brand_post || $brand_post->post_type !== 'twt_brand') {
      return '<p>Marca inválida.</p>';
    }

    // Se não for admin-like, só pode ver a sua própria marca
    if (!TWT_TCRM_Roles::is_admin_like($viewer_id)) {
      $own = (int) get_user_meta($viewer_id, 'twt_brand_id', true);
      if ((int)$own !== (int)$brand_id) {
        return '<p>Sem acesso a esta marca.</p>';
      }
    }

    $kpis = self::get_brand_kpis($brand_id);
    $reports = self::get_recent_reports(['brand_id' => $brand_id], 25);

    $out = '';
    $out .= '<div class="twt-tcrm twt-tcrm-dashboard twt-tcrm-brand-dashboard">';
    $out .= '<h2>Dashboard da Marca, ' . esc_html(get_the_title($brand_id)) . '</h2>';

    $out .= self::render_kpis([
      ['label' => 'Reports, 7 dias', 'value' => $kpis['reports_7d']],
      ['label' => 'Reports, 30 dias', 'value' => $kpis['reports_30d']],
      ['label' => 'Utilizadores activos, 30 dias', 'value' => $kpis['users_30d']],
      ['label' => 'Campanhas com dados, 30 dias', 'value' => $kpis['campaigns_30d']],
    ]);

    $out .= '<div class="twt-tcrm-grid-cards">';
    $out .= '<div class="twt-tcrm-card">';
    $out .= '<h3>Últimos reports</h3>';
    $out .= self::render_reports_table($reports, 'brand');
    $out .= '</div>';

    $out .= '<div class="twt-tcrm-card">';
    $out .= '<h3>Sugestões para a marca</h3>';
    $out .= self::render_insights_for_brand($brand_id);
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';

    return $out;
  }

  /* =========================================================
     KPIs
     ========================================================= */

  private static function get_user_kpis($user_id) {
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $now = current_time('timestamp');
    $since_7d = date('Y-m-d H:i:s', $now - (7 * 86400));
    $since_30d = date('Y-m-d H:i:s', $now - (30 * 86400));

    $reports_7d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE user_id = %d AND submitted_at >= %s",
      $user_id, $since_7d
    ));

    $reports_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE user_id = %d AND submitted_at >= %s",
      $user_id, $since_30d
    ));

    $brands_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT brand_id) FROM $t_sub WHERE user_id = %d AND submitted_at >= %s",
      $user_id, $since_30d
    ));

    $campaigns_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT campaign_id) FROM $t_sub WHERE user_id = %d AND submitted_at >= %s AND campaign_id IS NOT NULL",
      $user_id, $since_30d
    ));

    return [
      'reports_7d' => $reports_7d,
      'reports_30d' => $reports_30d,
      'brands_30d' => $brands_30d,
      'campaigns_30d' => $campaigns_30d,
    ];
  }

  private static function get_brand_kpis($brand_id) {
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $now = current_time('timestamp');
    $since_7d = date('Y-m-d H:i:s', $now - (7 * 86400));
    $since_30d = date('Y-m-d H:i:s', $now - (30 * 86400));

    $reports_7d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE brand_id = %d AND submitted_at >= %s",
      $brand_id, $since_7d
    ));

    $reports_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $t_sub WHERE brand_id = %d AND submitted_at >= %s",
      $brand_id, $since_30d
    ));

    $users_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT user_id) FROM $t_sub WHERE brand_id = %d AND submitted_at >= %s",
      $brand_id, $since_30d
    ));

    $campaigns_30d = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT campaign_id) FROM $t_sub WHERE brand_id = %d AND submitted_at >= %s AND campaign_id IS NOT NULL",
      $brand_id, $since_30d
    ));

    return [
      'reports_7d' => $reports_7d,
      'reports_30d' => $reports_30d,
      'users_30d' => $users_30d,
      'campaigns_30d' => $campaigns_30d,
    ];
  }

  /* =========================================================
     REPORT LISTS
     ========================================================= */

  private static function get_recent_reports($filters = [], $limit = 20) {
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['user_id'])) {
      $where .= " AND user_id = %d";
      $params[] = (int) $filters['user_id'];
    }

    if (!empty($filters['brand_id'])) {
      $where .= " AND brand_id = %d";
      $params[] = (int) $filters['brand_id'];
    }

    if (!empty($filters['campaign_id'])) {
      $where .= " AND campaign_id = %d";
      $params[] = (int) $filters['campaign_id'];
    }

    $limit = max(1, min(200, (int) $limit));

    $sql = "SELECT id, form_id, brand_id, campaign_id, user_id, submitted_at, status
            FROM $t_sub
            $where
            ORDER BY submitted_at DESC
            LIMIT $limit";

    if ($params) {
      $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);
    return $rows ? $rows : [];
  }

  private static function render_reports_table($rows, $mode = 'user') {
    if (!$rows) {
      return '<p class="twt-tcrm-muted">Ainda não há reports.</p>';
    }

    $out = '';
    $out .= '<table class="twt-tcrm-table">';
    $out .= '<thead><tr>';
    $out .= '<th>Data</th>';
    $out .= '<th>Formulário</th>';
    $out .= '<th>Marca</th>';
    $out .= '<th>Campanha</th>';
    if ($mode === 'brand') $out .= '<th>Utilizador</th>';
    $out .= '</tr></thead><tbody>';

    foreach ($rows as $r) {
      $when = $r->submitted_at ? mysql2date('Y-m-d H:i', $r->submitted_at) : '';
      $form_title = $r->form_id ? get_the_title((int)$r->form_id) : '';
      $brand_title = $r->brand_id ? get_the_title((int)$r->brand_id) : '';
      $camp_title = $r->campaign_id ? get_the_title((int)$r->campaign_id) : 'Sem';

      $out .= '<tr>';
      $out .= '<td>' . esc_html($when) . '</td>';
      $out .= '<td>' . esc_html($form_title) . '</td>';
      $out .= '<td>' . esc_html($brand_title) . '</td>';
      $out .= '<td>' . esc_html($camp_title) . '</td>';

      if ($mode === 'brand') {
        $u = get_userdata((int)$r->user_id);
        $u_label = $u ? $u->display_name : ('User #' . (int)$r->user_id);
        $out .= '<td>' . esc_html($u_label) . '</td>';
      }

      $out .= '</tr>';
    }

    $out .= '</tbody></table>';

    return $out;
  }

  /* =========================================================
     INSIGHTS (placeholder compatível)
     ========================================================= */

  private static function render_insights_for_user($user_id) {
    // Se existir um módulo de insights mais avançado, usamos
    if (class_exists('TWT_TCRM_Insights') && method_exists('TWT_TCRM_Insights', 'get_for_user')) {
      $items = TWT_TCRM_Insights::get_for_user($user_id);
      return self::render_insights_list($items);
    }

    // Placeholder mínimo
    return '<p class="twt-tcrm-muted">Ainda não há sugestões configuradas. Em breve.</p>';
  }

  private static function render_insights_for_brand($brand_id) {
    if (class_exists('TWT_TCRM_Insights') && method_exists('TWT_TCRM_Insights', 'get_for_brand')) {
      $items = TWT_TCRM_Insights::get_for_brand($brand_id);
      return self::render_insights_list($items);
    }

    return '<p class="twt-tcrm-muted">Ainda não há sugestões configuradas. Em breve.</p>';
  }

  private static function render_insights_list($items) {
    if (!$items || !is_array($items)) {
      return '<p class="twt-tcrm-muted">Sem sugestões.</p>';
    }

    $out = '<ul class="twt-tcrm-insights">';
    foreach ($items as $it) {
      $title = isset($it['title']) ? $it['title'] : '';
      $body = isset($it['body']) ? $it['body'] : '';
      if (!$title && !$body) continue;

      $out .= '<li class="twt-tcrm-insight">';
      if ($title) $out .= '<strong>' . esc_html($title) . '</strong><br>';
      if ($body) $out .= '<span>' . esc_html($body) . '</span>';
      $out .= '</li>';
    }
    $out .= '</ul>';

    return $out;
  }

  /* =========================================================
     UI HELPERS
     ========================================================= */

  private static function render_kpis($items) {
    if (!$items || !is_array($items)) return '';

    $out = '<div class="twt-tcrm-kpis">';
    foreach ($items as $it) {
      $label = isset($it['label']) ? $it['label'] : '';
      $value = isset($it['value']) ? $it['value'] : '';
      $out .= '<div class="twt-tcrm-kpi">';
      $out .= '<div class="twt-tcrm-kpi-label">' . esc_html($label) . '</div>';
      $out .= '<div class="twt-tcrm-kpi-value">' . esc_html((string)$value) . '</div>';
      $out .= '</div>';
    }
    $out .= '</div>';

    return $out;
  }
}

TWT_TCRM_Dashboards::boot();
