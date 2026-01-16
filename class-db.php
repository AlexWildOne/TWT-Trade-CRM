<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_DB {

  const DB_VERSION_OPTION = 'twt_tcrm_db_version';
  const DB_VERSION = '0.2.0';

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

  public static function create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $t_assign = self::table_assignments();
    $t_sub = self::table_submissions();
    $t_ans = self::table_answers();
    $t_loc_assign = self::table_location_assignments();
    $t_picks = self::table_location_picks();

    // Atribuições: que user vê que form, em que marca e campanha
    $sql_assign = "CREATE TABLE $t_assign (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      brand_id BIGINT UNSIGNED NOT NULL,
      campaign_id BIGINT UNSIGNED NULL,
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

    // Submissões: um report submetido pelo user para um form, numa marca/campanha
    $sql_sub = "CREATE TABLE $t_sub (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      form_id BIGINT UNSIGNED NOT NULL,
      brand_id BIGINT UNSIGNED NOT NULL,
      campaign_id BIGINT UNSIGNED NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      status VARCHAR(30) NOT NULL DEFAULT 'submitted',
      meta_json LONGTEXT NULL,
      PRIMARY KEY (id),
      KEY idx_form (form_id),
      KEY idx_brand (brand_id),
      KEY idx_campaign (campaign_id),
      KEY idx_user (user_id),
      KEY idx_submitted_at (submitted_at),
      KEY idx_status (status)
    ) $charset_collate;";

    // Respostas: cada submissão tem várias respostas, guardamos por tipo para analytics
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

    // Atribuições de lojas/locais a utilizadores (picking)
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

    // Registo de picks (check-in e check-out), pronto para NFC e geofence
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
      within_radius TINYINT(1) NOT NULL DEFAULT 0,
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

    dbDelta($sql_assign);
    dbDelta($sql_sub);
    dbDelta($sql_ans);
    dbDelta($sql_loc_assign);
    dbDelta($sql_picks);

    update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
  }

  public static function maybe_upgrade() {
    $installed = get_option(self::DB_VERSION_OPTION);
    if ($installed !== self::DB_VERSION) {
      self::create_tables();
    }
  }
}