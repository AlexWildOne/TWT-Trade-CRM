<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Service {

  public static function default_outlook_template_html() {
    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8;padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;background:#ffffff;border:1px solid #e6e8eb;">
        <tr>
          <td style="padding:18px 20px;background:#111827;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:bold;">
            TWT CRM — Submissão
          </td>
        </tr>
        <tr>
          <td style="padding:18px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111827;">
            <p style="margin:0 0 12px 0;">Olá,</p>

            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin:0 0 14px 0;">
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Marca</td><td style="padding:6px 0;"><strong>{brand_name}</strong></td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Campanha</td><td style="padding:6px 0;">{campaign_name}</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Local</td><td style="padding:6px 0;">{location_name}</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Formulário</td><td style="padding:6px 0;">{form_name}</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Utilizador</td><td style="padding:6px 0;">{user_name}</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">Data</td><td style="padding:6px 0;">{submitted_at}</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;width:160px;">ID</td><td style="padding:6px 0;">#{submission_id}</td></tr>
            </table>

            <h3 style="margin:16px 0 10px 0;font-size:14px;color:#111827;">Respostas</h3>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;border:1px solid #e6e8eb;">
              <tr>
                <td style="padding:10px 12px;background:#f9fafb;border-bottom:1px solid #e6e8eb;color:#6b7280;font-size:12px;font-weight:bold;">Pergunta</td>
                <td style="padding:10px 12px;background:#f9fafb;border-bottom:1px solid #e6e8eb;color:#6b7280;font-size:12px;font-weight:bold;">Resposta</td>
              </tr>
              {answers_rows_html}
            </table>

            <p style="margin:16px 0 0 0;color:#6b7280;font-size:12px;">Enviado pelo TWT CRM.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>';
  }

  public static function send_template_for_submission($template_id, array $submission, array $answers, array $to, array $attachments = [], $rule_id = 0) {
    $template_id = (int) $template_id;
    $to = array_values(array_unique(array_filter($to)));

    if (!$template_id || !$to) return false;

    $tpl = get_post($template_id);
    if (!$tpl || $tpl->post_type !== 'twt_email_template') return false;

    $subject_tpl = (string) get_post_meta($template_id, 'twt_email_subject', true);
    if ($subject_tpl === '') $subject_tpl = '[TWT] Submissão #{submission_id} — {brand_name}';

    $html_tpl = (string) $tpl->post_content;
    if (trim($html_tpl) === '') {
      $html_tpl = self::default_outlook_template_html();
    }

    $vars = self::build_vars($submission, $answers);
    $subject = strtr($subject_tpl, $vars);
    $html = strtr($html_tpl, $vars);

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $ok = wp_mail($to, $subject, $html, $headers, $attachments);

    self::log_send([
      'submission_id' => (int) ($submission['id'] ?? 0),
      'brand_id' => (int) ($submission['brand_id'] ?? 0),
      'campaign_id' => (int) ($submission['campaign_id'] ?? 0),
      'form_id' => (int) ($submission['form_id'] ?? 0),
      'location_id' => (int) ($submission['location_id'] ?? 0),
      'user_id' => (int) ($submission['user_id'] ?? 0),
      'template_key' => 'template:' . $template_id,
      'recipients' => $to,
      'subject' => $subject,
      'body' => $html,
      'status' => $ok ? 'sent' : 'failed',
      'error_text' => $ok ? '' : 'wp_mail_failed',
      'meta' => [
        'rule_id' => (int) $rule_id,
      ],
    ]);

    return (bool) $ok;
  }

  public static function build_vars(array $submission, array $answers) {
    $brand_name = !empty($submission['brand_id']) ? (string) get_the_title((int) $submission['brand_id']) : '';
    $campaign_name = !empty($submission['campaign_id']) ? (string) get_the_title((int) $submission['campaign_id']) : 'Sem campanha';
    $form_name = !empty($submission['form_id']) ? (string) get_the_title((int) $submission['form_id']) : '';
    $location_name = !empty($submission['location_id']) ? (string) get_the_title((int) $submission['location_id']) : 'Sem local';

    $u = !empty($submission['user_id']) ? get_user_by('id', (int) $submission['user_id']) : null;
    $user_name = $u ? $u->display_name : '';

    $submitted_at = isset($submission['submitted_at']) ? (string) $submission['submitted_at'] : '';
    $submission_id = isset($submission['id']) ? (int) $submission['id'] : 0;

    $answers_rows_html = self::answers_rows_html($answers);
    $answers_text = self::answers_text($answers);

    return [
      '{submission_id}' => (string) $submission_id,
      '{submitted_at}' => (string) $submitted_at,
      '{brand_name}' => (string) $brand_name,
      '{campaign_name}' => (string) $campaign_name,
      '{form_name}' => (string) $form_name,
      '{location_name}' => (string) $location_name,
      '{user_name}' => (string) $user_name,
      '{answers_rows_html}' => (string) $answers_rows_html,
      '{answers_text}' => (string) $answers_text,
    ];
  }

  private static function answers_rows_html(array $answers) {
    // $answers: [['question_key' => 'x', 'value' => 'y'], ...] ou map key=>value
    $rows = '';

    // Normaliza para map key=>value
    $map = [];
    foreach ($answers as $k => $v) {
      if (is_array($v) && isset($v['question_key'])) {
        $map[(string) $v['question_key']] = (string) ($v['value'] ?? '');
      } else {
        $map[(string) $k] = (string) $v;
      }
    }

    foreach ($map as $k => $v) {
      $rows .= '<tr>';
      $rows .= '<td style="padding:10px 12px;border-top:1px solid #e6e8eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111827;">' . esc_html($k) . '</td>';
      $rows .= '<td style="padding:10px 12px;border-top:1px solid #e6e8eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111827;">' . esc_html($v) . '</td>';
      $rows .= '</tr>';
    }

    if ($rows === '') {
      $rows = '<tr><td colspan="2" style="padding:10px 12px;border-top:1px solid #e6e8eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">Sem respostas.</td></tr>';
    }

    return $rows;
  }

  private static function answers_text(array $answers) {
    $lines = [];

    foreach ($answers as $k => $v) {
      if (is_array($v) && isset($v['question_key'])) {
        $k2 = (string) $v['question_key'];
        $v2 = (string) ($v['value'] ?? '');
        $lines[] = "- {$k2}: {$v2}";
      } else {
        $lines[] = "- " . (string) $k . ': ' . (string) $v;
      }
    }

    return trim(implode("\n", $lines));
  }

  private static function log_send(array $data) {
    if (!class_exists('TWT_TCRM_DB') || !method_exists('TWT_TCRM_DB', 'table_email_log')) return;

    global $wpdb;
    $t = TWT_TCRM_DB::table_email_log();

    // Se a tabela não existir, não faz nada
    // (podia verificar via SHOW TABLES, mas mantemos simples)
    $recipients_json = isset($data['recipients']) ? wp_json_encode(array_values((array) $data['recipients']), JSON_UNESCAPED_UNICODE) : null;

    $meta = isset($data['meta']) ? wp_json_encode((array) $data['meta'], JSON_UNESCAPED_UNICODE) : null;

    $wpdb->insert(
      $t,
      [
        'submission_id' => (int) ($data['submission_id'] ?? 0),
        'brand_id' => (int) ($data['brand_id'] ?? 0),
        'campaign_id' => (int) ($data['campaign_id'] ?? 0),
        'form_id' => (int) ($data['form_id'] ?? 0),
        'location_id' => (int) ($data['location_id'] ?? 0),
        'user_id' => (int) ($data['user_id'] ?? 0),
        'template_key' => (string) ($data['template_key'] ?? ''),
        'recipients_json' => $recipients_json,
        'subject' => (string) ($data['subject'] ?? ''),
        'body' => (string) ($data['body'] ?? ''),
        'status' => (string) ($data['status'] ?? 'sent'),
        'error_text' => (string) ($data['error_text'] ?? ''),
        'created_at' => current_time('mysql'),
      ],
      ['%d','%d','%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s']
    );
  }
}