<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Reports {

  public static function boot() {
    // reservado
  }

  public static function get_submissions($args = [], $limit = 50, $offset = 0) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();

    $limit = max(1, min(200, (int)$limit));
    $offset = max(0, (int)$offset);

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($args['brand_id'])) {
      $where .= " AND brand_id = %d";
      $params[] = (int)$args['brand_id'];
    }

    if (!empty($args['campaign_id'])) {
      $where .= " AND campaign_id = %d";
      $params[] = (int)$args['campaign_id'];
    }

    if (!empty($args['form_id'])) {
      $where .= " AND form_id = %d";
      $params[] = (int)$args['form_id'];
    }

    if (!empty($args['user_id'])) {
      $where .= " AND user_id = %d";
      $params[] = (int)$args['user_id'];
    }

    if (!empty($args['date_from'])) {
      $where .= " AND submitted_at >= %s";
      $params[] = sanitize_text_field($args['date_from']);
    }

    if (!empty($args['date_to'])) {
      $where .= " AND submitted_at <= %s";
      $params[] = sanitize_text_field($args['date_to']);
    }

    if (!empty($args['status'])) {
      $where .= " AND status = %s";
      $params[] = sanitize_text_field($args['status']);
    }

    $sql = "SELECT id, form_id, brand_id, campaign_id, user_id, submitted_at, status
            FROM $t_sub
            $where
            ORDER BY submitted_at DESC
            LIMIT $limit OFFSET $offset";

    if ($params) {
      $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);
    return $rows ? $rows : [];
  }

  public static function count_submissions($args = []) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($args['brand_id'])) {
      $where .= " AND brand_id = %d";
      $params[] = (int)$args['brand_id'];
    }
    if (!empty($args['campaign_id'])) {
      $where .= " AND campaign_id = %d";
      $params[] = (int)$args['campaign_id'];
    }
    if (!empty($args['form_id'])) {
      $where .= " AND form_id = %d";
      $params[] = (int)$args['form_id'];
    }
    if (!empty($args['user_id'])) {
      $where .= " AND user_id = %d";
      $params[] = (int)$args['user_id'];
    }
    if (!empty($args['date_from'])) {
      $where .= " AND submitted_at >= %s";
      $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
      $where .= " AND submitted_at <= %s";
      $params[] = sanitize_text_field($args['date_to']);
    }
    if (!empty($args['status'])) {
      $where .= " AND status = %s";
      $params[] = sanitize_text_field($args['status']);
    }

    $sql = "SELECT COUNT(*) FROM $t_sub $where";
    if ($params) {
      $sql = $wpdb->prepare($sql, $params);
    }

    return (int)$wpdb->get_var($sql);
  }

  public static function get_submission($submission_id) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM $t_sub WHERE id = %d",
      (int)$submission_id
    ));

    return $row ? $row : null;
  }

  public static function get_submission_answers($submission_id) {
    global $wpdb;

    $t_ans = TWT_TCRM_DB::table_answers();

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $t_ans WHERE submission_id = %d ORDER BY id ASC",
      (int)$submission_id
    ));

    return $rows ? $rows : [];
  }

  /**
   * Agrega valores por question_key para um conjunto de filtros.
   * Resultado:
   * [
   *   'key' => [
   *      'count' => 10,
   *      'text_count' => 6,
   *      'num_count' => 4,
   *      'sum' => 123.4,
   *      'avg' => 30.85,
   *      'min' => 10,
   *      'max' => 60
   *   ],
   * ]
   */
  public static function aggregate_by_question($filters = []) {
    global $wpdb;

    $t_sub = TWT_TCRM_DB::table_submissions();
    $t_ans = TWT_TCRM_DB::table_answers();

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($filters['brand_id'])) {
      $where .= " AND s.brand_id = %d";
      $params[] = (int)$filters['brand_id'];
    }
    if (!empty($filters['campaign_id'])) {
      $where .= " AND s.campaign_id = %d";
      $params[] = (int)$filters['campaign_id'];
    }
    if (!empty($filters['form_id'])) {
      $where .= " AND s.form_id = %d";
      $params[] = (int)$filters['form_id'];
    }
    if (!empty($filters['user_id'])) {
      $where .= " AND s.user_id = %d";
      $params[] = (int)$filters['user_id'];
    }
    if (!empty($filters['date_from'])) {
      $where .= " AND s.submitted_at >= %s";
      $params[] = sanitize_text_field($filters['date_from']);
    }
    if (!empty($filters['date_to'])) {
      $where .= " AND s.submitted_at <= %s";
      $params[] = sanitize_text_field($filters['date_to']);
    }

    $sql = "
      SELECT
        a.question_key AS qk,
        COUNT(*) AS total_count,
        SUM(CASE WHEN a.value_text IS NOT NULL AND a.value_text <> '' THEN 1 ELSE 0 END) AS text_count,
        SUM(CASE WHEN a.value_number IS NOT NULL THEN 1 ELSE 0 END) AS number_count,
        SUM(CASE WHEN a.value_currency IS NOT NULL THEN 1 ELSE 0 END) AS currency_count,
        SUM(CASE WHEN a.value_percent IS NOT NULL THEN 1 ELSE 0 END) AS percent_count,

        SUM(COALESCE(a.value_number, 0) + COALESCE(a.value_currency, 0) + COALESCE(a.value_percent, 0)) AS sum_any,
        AVG(NULLIF(COALESCE(a.value_number, a.value_currency, a.value_percent), 0)) AS avg_any,
        MIN(COALESCE(a.value_number, a.value_currency, a.value_percent)) AS min_any,
        MAX(COALESCE(a.value_number, a.value_currency, a.value_percent)) AS max_any

      FROM $t_ans a
      INNER JOIN $t_sub s ON s.id = a.submission_id
      $where
      GROUP BY a.question_key
      ORDER BY total_count DESC
    ";

    if ($params) {
      $sql = $wpdb->prepare($sql, $params);
    }

    $rows = $wpdb->get_results($sql);
    if (!$rows) return [];

    $out = [];
    foreach ($rows as $r) {
      $key = (string)$r->qk;
      $out[$key] = [
        'count' => (int)$r->total_count,
        'text_count' => (int)$r->text_count,
        'number_count' => (int)$r->number_count,
        'currency_count' => (int)$r->currency_count,
        'percent_count' => (int)$r->percent_count,
        'sum' => (float)$r->sum_any,
        'avg' => $r->avg_any !== null ? (float)$r->avg_any : null,
        'min' => $r->min_any !== null ? (float)$r->min_any : null,
        'max' => $r->max_any !== null ? (float)$r->max_any : null,
      ];
    }

    return $out;
  }
}

TWT_TCRM_Reports::boot();
