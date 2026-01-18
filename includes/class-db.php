<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_DB {

  const DB_VERSION_OPTION = 'twt_tcrm_db_version';
  const DB_VERSION = '0.3.2';

  public static function table_assignments() {
    global $wpdb;
    return $wpdb->prefix . 'twt_assignments';
  }

  public static function table_submissions() {
    global $wpdb;
    return $wpdb->prefix . 'twt_submissions';
  }

  public static function table_answers() {
    global $wpdb;
    return $wpdb->prefix . 'twt_submission_answers';
  }

  public static function table_location_assignments() {
    global $wpdb;
    return $wpdb->prefix . 'twt_location_assignments';
  }

  public static function table_location_picks() {
    global $wpdb;
    return $wpdb->prefix . 'twt_location_picks';
  }

  public static function table_email_log() {
    global $wpdb;
    return $wpdb->prefix . 'twt_email_log';
  }

  // NEW: pivot form <-> location
  public static function table_form_locations() {
    global $wpdb;
    return $wpdb->prefix . 'twt_form_locations';
  }

  // NEW: pivot form <-> campaign (campanhas disponíveis para o form)
  public static function table_form_campaigns() {
    global $wpdb;
    return $wpdb->prefix . 'twt_form_campaigns';
  }

  public static function get_assigned_user_ids($brand_id, $campaign_id = null, $form_id = null, $only_active = true) {
    global $wpdb;

    $brand_id = (int) $brand_id;
    if ($brand_id <= 0) return [];

    $t = self::table_assignments();

    $where = "WHERE brand_id = %d";
    $params = [$brand_id];

    if ($only_active) {
      $where .= " AND active = 1";
    }

    if ($campaign_id !== null) {
      $campaign_id = (int) $campaign_id;

      if ($campaign_id > 0) {
        $where .= " AND (campaign_id = %d OR campaign_id = 0)";
        $params[] = $campaign_id;
      } else {
        $where .= " AND campaign_id = 0";
      }
    }

    if ($form_id !== null) {
      $form_id = (int) $form_id;
      if ($form_id > 0) {
        $where .= " AND form_id = %d";
        $params[] = $form_id;
      }
    }

    $sql = "SELECT DISTINCT user_id FROM {$t} {$where} ORDER BY user_id ASC";
    $ids = $wpdb->get_col($wpdb->prepare($sql, $params));

    if (!is_array($ids)) return [];

    $ids = array_map('intval', $ids);
    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
  }

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $t_assign = self::table_assignments();
    $t_sub = self::table_submissions();
    $t_ans = self::table_answers();
    $t_loc_assign = self::table_location_assignments();
    $t_picks = self::table_location_picks();
    $t_email = self::table_email_log();

    $t_form_locs = self::table_form_locations();
    $t_form_camps = self::table_form_campaigns();

    $sql_assign = "CREATE TABLE $t_assign (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      brand_id BIGINT UNSIGNED NOT NULL,
      campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      form_id BIGINT UNSIGNED NOT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_assignment (user_id, form_id, brand_id, campaign_id),
      KEY idx_user (user_id),
      KEY idx_brand (brand_id),
      KEY idx_campaign (campaign_id),
      KEY idx_form (form_id),
      KEY idx_active (active)
    ) $charset_collate;";

    // NEW: adiciona location_id (default 0)
    $sql_sub = "CREATE TABLE $t_sub (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      form_id BIGINT UNSIGNED NOT NULL,
      brand_id BIGINT UNSIGNED NOT NULL,
      campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      user_id BIGINT UNSIGNED NOT NULL,
      submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      status VARCHAR(30) NOT NULL DEFAULT 'submitted',
      meta_json LONGTEXT NULL,
      PRIMARY KEY (id),
      KEY idx_form (form_id),
      KEY idx_brand (brand_id),
      KEY idx_campaign (campaign_id),
      KEY idx_location (location_id),
      KEY idx_user (user_id),
      KEY idx_submitted_at (submitted_at),
      KEY idx_status (status)
    ) $charset_collate;";

    $sql_ans = "CREATE TABLE $t_ans (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      submission_id BIGINT UNSIGNED NOT NULL,
      question_key VARCHAR(190) NOT NULL,
      value_text LONGTEXT NULL,
      value_number DECIMAL(18,4) NULL,
      value_currency DECIMAL(18,4) NULL,
      value_percent DECIMAL(9,4) NULL,
      value_json LONGTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_submission (submission_id),
      KEY idx_question (question_key),
      KEY idx_value_number (value_number),
      KEY idx_value_currency (value_currency),
      KEY idx_value_percent (value_percent)
    ) $charset_collate;";

    $sql_loc_assign = "CREATE TABLE $t_loc_assign (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      location_id BIGINT UNSIGNED NOT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_loc_assignment (user_id, location_id),
      KEY idx_user (user_id),
      KEY idx_location (location_id),
      KEY idx_active (active)
    ) $charset_collate;";

    $sql_picks = "CREATE TABLE $t_picks (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      location_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NULL,
      user_email VARCHAR(190) NULL,
      brand_id BIGINT UNSIGNED NULL,
      campaign_id BIGINT UNSIGNED NULL,

      pick_action VARCHAR(20) NOT NULL DEFAULT 'checkin',
      pick_source VARCHAR(30) NOT NULL DEFAULT 'web',
      picked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

      lat DECIMAL(10,7) NULL,
      lng DECIMAL(10,7) NULL,
      accuracy_m INT UNSIGNED NULL,
      within_radius TINYINT(1) NULL,
      distance_m INT UNSIGNED NULL,

      token_hash VARCHAR(190) NULL,
      meta_json LONGTEXT NULL,

      PRIMARY KEY (id),
      KEY idx_location (location_id),
      KEY idx_user (user_id),
      KEY idx_email (user_email),
      KEY idx_brand (brand_id),
      KEY idx_campaign (campaign_id),
      KEY idx_action (pick_action),
      KEY idx_picked_at (picked_at)
    ) $charset_collate;";

    $sql_email = "CREATE TABLE $t_email (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

      submission_id BIGINT UNSIGNED NULL,
      brand_id BIGINT UNSIGNED NULL,
      campaign_id BIGINT UNSIGNED NULL,
      form_id BIGINT UNSIGNED NULL,
      location_id BIGINT UNSIGNED NULL,
      user_id BIGINT UNSIGNED NULL,

      template_key VARCHAR(100) NOT NULL DEFAULT '',
      recipients_json LONGTEXT NULL,

      subject TEXT NULL,
      body LONGTEXT NULL,

      status VARCHAR(30) NOT NULL DEFAULT 'sent',
      error_text LONGTEXT NULL,

      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

      PRIMARY KEY (id),
      KEY idx_submission (submission_id),
      KEY idx_brand (brand_id),
      KEY idx_campaign (campaign_id),
      KEY idx_form (form_id),
      KEY idx_location (location_id),
      KEY idx_user (user_id),
      KEY idx_created_at (created_at),
      KEY idx_status (status)
    ) $charset_collate;";

    // NEW: N:N form-locations
    $sql_form_locs = "CREATE TABLE $t_form_locs (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      form_id BIGINT UNSIGNED NOT NULL,
      location_id BIGINT UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_form_location (form_id, location_id),
      KEY idx_form (form_id),
      KEY idx_location (location_id)
    ) $charset_collate;";

    // NEW: N:N form-campaigns
    $sql_form_camps = "CREATE TABLE $t_form_camps (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      form_id BIGINT UNSIGNED NOT NULL,
      campaign_id BIGINT UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_form_campaign (form_id, campaign_id),
      KEY idx_form (form_id),
      KEY idx_campaign (campaign_id)
    ) $charset_collate;";

    dbDelta($sql_assign);
    dbDelta($sql_sub);
    dbDelta($sql_ans);
    dbDelta($sql_loc_assign);
    dbDelta($sql_picks);
    dbDelta($sql_email);
    dbDelta($sql_form_locs);
    dbDelta($sql_form_camps);

    update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
  }

  public static function maybe_upgrade() {
    global $wpdb;

    $installed = get_option(self::DB_VERSION_OPTION);
    if ($installed === self::DB_VERSION) return;

    self::create_tables();

    $t_assign = self::table_assignments();
    $t_sub = self::table_submissions();
    $t_picks = self::table_location_picks();

    // campaign_id: NULL -> 0 (dados)
    $wpdb->query("UPDATE $t_assign SET campaign_id = 0 WHERE campaign_id IS NULL");
    $wpdb->query("UPDATE $t_sub SET campaign_id = 0 WHERE campaign_id IS NULL");

    // campaign_id NOT NULL DEFAULT 0 (estrutura)
    $wpdb->query("ALTER TABLE $t_assign MODIFY campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
    $wpdb->query("ALTER TABLE $t_sub MODIFY campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0");

    // within_radius permitir NULL
    $wpdb->query("ALTER TABLE $t_picks MODIFY within_radius TINYINT(1) NULL");

    // NEW: location_id na submissions (para installs antigos que já tinham a tabela sem esta coluna)
    // dbDelta normalmente cria, mas garantimos com ALTER (idempotente em muitos MySQL; se falhar, ignora)
    // Nota: alguns MySQL lançam erro se coluna já existir; se quiseres, posso envolver com "SHOW COLUMNS".
    $wpdb->query("ALTER TABLE $t_sub ADD COLUMN location_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER campaign_id");
    $wpdb->query("ALTER TABLE $t_sub ADD KEY idx_location (location_id)");

    update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
  }
}