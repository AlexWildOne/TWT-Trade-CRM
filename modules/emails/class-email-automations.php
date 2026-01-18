    <?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Automations {

  public static function handle_submission($submission_id) {
    $submission_id = (int) $submission_id;
    if (!$submission_id) return;

    $submission = self::get_submission($submission_id);
    if (!$submission) return;

    $answers = self::get_submission_answers_map($submission_id);

    $rules = get_posts([
      'post_type' => 'twt_email_rule',
      'numberposts' => -1,
      'post_status' => 'publish',
      'orderby' => 'date',
      'order' => 'DESC',
      'meta_query' => [
        [
          'key' => 'twt_rule_active',
          'value' => '1',
          'compare' => '=',
        ]
      ],
    ]);

    if (!$rules) return;

    foreach ($rules as $rule) {
      $rule_id = (int) $rule->ID;

      $brand_id = (int) get_post_meta($rule_id, 'twt_rule_brand_id', true);
      $campaign_id = (int) get_post_meta($rule_id, 'twt_rule_campaign_id', true);
      $form_id = (int) get_post_meta($rule_id, 'twt_rule_form_id', true);
      $template_id = (int) get_post_meta($rule_id, 'twt_rule_template_id', true);

      if (!$form_id || !$template_id) continue;

      // match obrigatório: form
      if ((int) $submission['form_id'] !== $form_id) continue;

      // match opcional: brand/campaign (0 = qualquer)
      if ($brand_id && (int) $submission['brand_id'] !== $brand_id) continue;

      if ($campaign_id) {
        // regra especifica campanha -> submissão tem de ser essa campanha (0 não bate)
        if ((int) $submission['campaign_id'] !== $campaign_id) continue;
      }

      $to = [];

      // Destinatários: marca
      $to_brand = (string) get_post_meta($rule_id, 'twt_rule_send_to_brand', true) === '1';
      if ($to_brand && !empty($submission['brand_id'])) {
        $to = array_merge($to, self::get_brand_recipients((int) $submission['brand_id']));
      }

      // Destinatários: submitter
      $to_submitter = (string) get_post_meta($rule_id, 'twt_rule_send_to_submitter', true) === '1';
      if ($to_submitter && !empty($submission['user_id'])) {
        $u = get_user_by('id', (int) $submission['user_id']);
        if ($u && $u->user_email && is_email($u->user_email)) {
          $to[] = strtolower($u->user_email);
        }
      }

      // Destinatários: users atribuídos (brand + campaign OR 0 + form)
      $to_assigned = (string) get_post_meta($rule_id, 'twt_rule_send_to_assigned_users', true) === '1';
      if ($to_assigned) {
        $to = array_merge($to, self::get_assigned_recipients_for_submission($submission));
      }

      // Emails extra
      $extra_raw = (string) get_post_meta($rule_id, 'twt_rule_extra_emails', true);
      $to = array_merge($to, self::parse_emails_lines($extra_raw));

      $to = array_values(array_unique(array_filter($to)));
      if (!$to) continue;

      TWT_TCRM_Email_Service::send_template_for_submission(
        $template_id,
        $submission,
        $answers,
        $to,
        [],
        $rule_id
      );
    }
  }

  private static function get_submission($submission_id) {
    global $wpdb;
    $t = TWT_TCRM_DB::table_submissions();

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", (int) $submission_id), ARRAY_A);
    return $row ?: null;
  }

  private static function get_submission_answers_map($submission_id) {
    global $wpdb;
    $t = TWT_TCRM_DB::table_answers();

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT question_key, value_text, value_number, value_currency, value_percent, value_json
       FROM {$t}
       WHERE submission_id = %d
       ORDER BY question_key ASC",
      (int) $submission_id
    ), ARRAY_A);

    $map = [];
    if (!$rows) return $map;

    foreach ($rows as $r) {
      $k = (string) $r['question_key'];
      $val = '';

      if ($r['value_text'] !== null && $r['value_text'] !== '') $val = (string) $r['value_text'];
      elseif ($r['value_number'] !== null && $r['value_number'] !== '') $val = (string) $r['value_number'];
      elseif ($r['value_currency'] !== null && $r['value_currency'] !== '') $val = (string) $r['value_currency'];
      elseif ($r['value_percent'] !== null && $r['value_percent'] !== '') $val = (string) $r['value_percent'];
      elseif ($r['value_json'] !== null && $r['value_json'] !== '') $val = (string) $r['value_json'];

      $map[$k] = $val;
    }

    return $map;
  }

  private static function get_brand_recipients($brand_id) {
    $emails = [];

    $user_ids = get_post_meta($brand_id, 'twt_brand_user_ids', true);
    if (is_array($user_ids)) {
      foreach ($user_ids as $uid) {
        $u = get_user_by('id', (int) $uid);
        if ($u && $u->user_email && is_email($u->user_email)) {
          $emails[] = strtolower($u->user_email);
        }
      }
    }

    $raw = (string) get_post_meta($brand_id, 'twt_brand_user_emails', true);
    $emails = array_merge($emails, self::parse_emails_lines($raw));

    return array_values(array_unique(array_filter($emails)));
  }

  private static function get_assigned_recipients_for_submission(array $submission) {
    global $wpdb;

    $brand_id = (int) ($submission['brand_id'] ?? 0);
    $campaign_id = (int) ($submission['campaign_id'] ?? 0);
    $form_id = (int) ($submission['form_id'] ?? 0);

    if (!$brand_id || !$form_id) return [];

    $t = TWT_TCRM_DB::table_assignments();

    // campaign: inclui 0 (geral) e a campanha específica (se campaign_id > 0)
    if ($campaign_id > 0) {
      $sql = "SELECT DISTINCT user_id FROM {$t}
              WHERE active = 1
                AND brand_id = %d
                AND form_id = %d
                AND (campaign_id = %d OR campaign_id = 0)";
      $ids = $wpdb->get_col($wpdb->prepare($sql, $brand_id, $form_id, $campaign_id));
    } else {
      $sql = "SELECT DISTINCT user_id FROM {$t}
              WHERE active = 1
                AND brand_id = %d
                AND form_id = %d
                AND campaign_id = 0";
      $ids = $wpdb->get_col($wpdb->prepare($sql, $brand_id, $form_id));
    }

    if (!is_array($ids)) return [];

    $emails = [];
    foreach ($ids as $uid) {
      $u = get_user_by('id', (int) $uid);
      if ($u && $u->user_email && is_email($u->user_email)) {
        $emails[] = strtolower($u->user_email);
      }
    }

    return array_values(array_unique(array_filter($emails)));
  }

  private static function parse_emails_lines($raw) {
    $raw = trim((string) $raw);
    if (!$raw) return [];

    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];

    if (!is_array($lines)) return [];

    foreach ($lines as $ln) {
      $ln = trim((string) $ln);
      if (!$ln) continue;
      $e = sanitize_email($ln);
      if ($e && is_email($e)) $out[] = strtolower($e);
    }

    return array_values(array_unique($out));
  }
}
