<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Shortcodes {

  const ACTION_SEND_FRONT = 'twt_tcrm_send_email_front';
  const NONCE_ACTION = 'twt_tcrm_send_email_front';
  const NONCE_FIELD = 'twt_tcrm_send_email_front_nonce';

  public static function boot() {
    add_action('admin_post_' . self::ACTION_SEND_FRONT, [__CLASS__, 'handle_send_front']);
  }

  /**
   * Shortcode: [twt_email_send]
   *
   * Attributes:
   * - brand_id (optional)
   * - campaign_id (optional)
   * - form_id (optional)
   */
  public static function shortcode_email_send($atts) {
    if (!is_user_logged_in()) {
      return '<p>Precisas de login.</p>';
    }

    $atts = shortcode_atts([
      'brand_id' => 0,
      'campaign_id' => 0,
      'form_id' => 0,
    ], $atts);

    $filters = [
      'brand_id' => (int) $atts['brand_id'],
      'campaign_id' => (int) $atts['campaign_id'],
      'form_id' => (int) $atts['form_id'],
      'user_id' => 0,
      'date_from' => '',
      'date_to' => '',
      'submission_id' => 0,
      'template_id' => 0,
    ];

    // templates
    $templates = get_posts([
      'post_type' => 'twt_email_template',
      'numberposts' => -1,
      'post_status' => ['publish', 'draft'],
      'orderby' => 'title',
      'order' => 'ASC',
    ]);
    $templates = is_array($templates) ? $templates : [];

    // default template (primeiro)
    $selected_template_id = isset($_GET['template_id']) ? (int) $_GET['template_id'] : 0;
    if (!$selected_template_id && !empty($templates[0])) {
      $selected_template_id = (int) $templates[0]->ID;
    }

    // submission id manual
    $submission_id = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0;

    $sent = isset($_GET['sent']) ? sanitize_text_field(wp_unslash($_GET['sent'])) : '';
    $err = isset($_GET['err']) ? sanitize_text_field(wp_unslash($_GET['err'])) : '';

    $action_url = admin_url('admin-post.php');

    $out = '';
    $out .= '<div class="twt-tcrm twt-tcrm-email-send">';

    if ($sent === '1') {
      $out .= '<div class="twt-tcrm-notice twt-tcrm-success">Email enviado.</div>';
    } elseif ($sent === '0') {
      $out .= '<div class="twt-tcrm-notice twt-tcrm-error">Falhou o envio do email.</div>';
    }

    if ($err) {
      $out .= '<div class="twt-tcrm-notice twt-tcrm-error">Erro: ' . esc_html($err) . '</div>';
    }

    $out .= '<form method="post" action="' . esc_url($action_url) . '" enctype="multipart/form-data">';
    $out .= '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SEND_FRONT) . '">';
    $out .= wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD, true, false);

    // filtros fixos vindos dos atts
    foreach ($filters as $k => $v) {
      $out .= '<input type="hidden" name="filters[' . esc_attr($k) . ']" value="' . esc_attr((string) $v) . '">';
    }

    $out .= '<p><label><strong>Submission ID</strong></label><br>';
    $out .= '<input type="number" name="submission_id" value="' . esc_attr((string) $submission_id) . '" style="width:180px;" placeholder="ex: 123" required>';
    $out .= '</p>';

    $out .= '<p><label><strong>Template</strong></label><br>';
    $out .= '<select name="template_id" style="min-width:320px;" required>';
    if (!$templates) {
      $out .= '<option value="0">Sem templates (cria em Templates de Email)</option>';
    } else {
      foreach ($templates as $t) {
        $label = $t->post_title . ($t->post_status !== 'publish' ? ' (draft)' : '');
        $out .= '<option value="' . esc_attr((int) $t->ID) . '"' . selected($selected_template_id, (int) $t->ID, false) . '>' . esc_html($label) . '</option>';
      }
    }
    $out .= '</select></p>';

    $out .= '<fieldset style="border:1px solid #e5e7eb;padding:12px;margin:14px 0;">';
    $out .= '<legend><strong>Destinatários</strong></legend>';

    $out .= '<p>';
    $out .= '<label style="margin-right:14px;"><input type="radio" name="recipient_mode" value="brand" checked> Marca</label>';
    $out .= '<label style="margin-right:14px;"><input type="radio" name="recipient_mode" value="assigned"> Users atribuídos</label>';
    $out .= '<label><input type="radio" name="recipient_mode" value="user"> Utilizador específico</label>';
    $out .= '</p>';

    $out .= '<p><label><strong>User ID (se “Utilizador específico”)</strong></label><br>';
    $out .= '<input type="number" name="recipient_user_id" value="0" style="width:180px;" placeholder="ex: 12">';
    $out .= '</p>';

    $out .= '<p><label><strong>Emails extra (opcional)</strong></label><br>';
    $out .= '<textarea name="extra_emails" rows="3" style="width:100%;max-width:640px;" placeholder="um email por linha"></textarea>';
    $out .= '</p>';

    $out .= '</fieldset>';

    $out .= '<p><label><strong>Anexos (opcional)</strong></label><br>';
    $out .= '<input type="file" name="attachments[]" multiple></p>';

    $out .= '<p><button type="submit" class="twt-tcrm-btn">Enviar email</button></p>';
    $out .= '</form>';

    $out .= '</div>';

    return $out;
  }

  public static function handle_send_front() {
    if (!is_user_logged_in()) {
      wp_die('Sem login.');
    }

    $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
      wp_die('Nonce inválido.');
    }

    $submission_id = isset($_POST['submission_id']) ? (int) wp_unslash($_POST['submission_id']) : 0;
    $template_id = isset($_POST['template_id']) ? (int) wp_unslash($_POST['template_id']) : 0;

    $recipient_mode = isset($_POST['recipient_mode']) ? sanitize_key(wp_unslash($_POST['recipient_mode'])) : 'brand';
    if (!in_array($recipient_mode, ['brand', 'assigned', 'user'], true)) $recipient_mode = 'brand';

    $recipient_user_id = isset($_POST['recipient_user_id']) ? (int) wp_unslash($_POST['recipient_user_id']) : 0;

    $extra_raw = isset($_POST['extra_emails']) ? wp_unslash($_POST['extra_emails']) : '';
    $extra = self::parse_extra_emails($extra_raw);

    $back = isset($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : home_url('/');

    if (!$submission_id) {
      wp_safe_redirect(add_query_arg(['sent' => '0', 'err' => 'submission_id'], $back));
      exit;
    }
    if (!$template_id) {
      wp_safe_redirect(add_query_arg(['sent' => '0', 'err' => 'template_id'], $back));
      exit;
    }

    // Carregar submissão + answers (reutiliza DB)
    global $wpdb;
    $t_sub = TWT_TCRM_DB::table_submissions();
    $t_ans = TWT_TCRM_DB::table_answers();

    $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_sub} WHERE id = %d", $submission_id), ARRAY_A);
    if (!$sub) {
      wp_safe_redirect(add_query_arg(['sent' => '0', 'err' => 'submission_not_found'], $back));
      exit;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT question_key, value_text, value_number, value_currency, value_percent, value_json
       FROM {$t_ans}
       WHERE submission_id = %d
       ORDER BY question_key ASC",
      $submission_id
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

    // Recipients
    $to = [];
    if ($recipient_mode === 'brand') {
      $to = array_merge($to, self::get_brand_recipients((int) ($sub['brand_id'] ?? 0)));
    } elseif ($recipient_mode === 'assigned') {
      $to = array_merge($to, self::get_assigned_recipients((int) ($sub['brand_id'] ?? 0), (int) ($sub['campaign_id'] ?? 0), (int) ($sub['form_id'] ?? 0)));
    } else {
      if ($recipient_user_id) {
        $u = get_user_by('id', $recipient_user_id);
        if ($u && $u->user_email && is_email($u->user_email)) {
          $to[] = strtolower($u->user_email);
        }
      }
    }

    $to = array_merge($to, $extra);
    $to = array_values(array_unique(array_filter($to)));

    if (!$to) {
      wp_safe_redirect(add_query_arg(['sent' => '0', 'err' => 'no_recipients'], $back));
      exit;
    }

    $attachments = self::handle_upload_attachments();

    if (!class_exists('TWT_TCRM_Email_Service')) {
      wp_safe_redirect(add_query_arg(['sent' => '0', 'err' => 'email_service_missing'], $back));
      exit;
    }

    $ok = TWT_TCRM_Email_Service::send_template_for_submission($template_id, $sub, $answers, $to, $attachments, 0);

    wp_safe_redirect(add_query_arg(['sent' => $ok ? '1' : '0'], $back));
    exit;
  }

  private static function parse_extra_emails($raw) {
    $raw = (string) $raw;
    $raw = trim($raw);
    if ($raw === '') return [];

    $lines = preg_split("/\r\n|\n|\r/", $raw);
    $out = [];
    if (is_array($lines)) {
      foreach ($lines as $ln) {
        $ln = trim((string) $ln);
        if (!$ln) continue;
        $e = sanitize_email($ln);
        if ($e && is_email($e)) $out[] = strtolower($e);
      }
    }
    return array_values(array_unique($out));
  }

  private static function get_brand_recipients($brand_id) {
    $brand_id = (int) $brand_id;
    if ($brand_id <= 0) return [];

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
    $emails = array_merge($emails, self::parse_extra_emails($raw));

    return array_values(array_unique(array_filter($emails)));
  }

  private static function get_assigned_recipients($brand_id, $campaign_id, $form_id) {
    $brand_id = (int) $brand_id;
    $campaign_id = (int) $campaign_id;
    $form_id = (int) $form_id;

    if (!$brand_id || !$form_id) return [];

    global $wpdb;
    $t_assign = TWT_TCRM_DB::table_assignments();

    if ($campaign_id > 0) {
      $sql = "SELECT DISTINCT user_id FROM {$t_assign}
              WHERE active = 1
                AND brand_id = %d
                AND form_id = %d
                AND (campaign_id = %d OR campaign_id = 0)";
      $ids = $wpdb->get_col($wpdb->prepare($sql, $brand_id, $form_id, $campaign_id));
    } else {
      $sql = "SELECT DISTINCT user_id FROM {$t_assign}
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

  private static function handle_upload_attachments() {
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments'])) return [];

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $allowed_mimes = [
      'pdf' => 'application/pdf',
      'csv' => 'text/csv',
      'txt' => 'text/plain',
      'jpg|jpeg|jpe' => 'image/jpeg',
      'png' => 'image/png',
      'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'xls' => 'application/vnd.ms-excel',
    ];

    $files = $_FILES['attachments'];

    $count = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;
    if ($count <= 0) return [];

    $paths = [];

    for ($i = 0; $i < $count; $i++) {
      if (empty($files['name'][$i])) continue;
      if (!empty($files['error'][$i])) continue;

      $single = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i],
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i],
      ];

      if (!empty($single['size']) && (int) $single['size'] > 10 * 1024 * 1024) {
        continue;
      }

      $overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
      $moved = wp_handle_upload($single, $overrides);

      if (!empty($moved['file']) && empty($moved['error'])) {
        $paths[] = $moved['file'];
      }
    }

    return $paths;
  }
}