<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Templates_Admin {

  const NONCE_ACTION = 'twt_tcrm_email_template_save';
  const NONCE_FIELD  = 'twt_tcrm_email_template_nonce';

  public static function boot() {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_twt_email_template', [__CLASS__, 'save_meta'], 10, 2);
  }

  public static function add_meta_boxes() {
    add_meta_box(
      'twt_email_template_settings',
      'Definições do Template',
      [__CLASS__, 'render_metabox'],
      'twt_email_template',
      'side',
      'default'
    );

    add_meta_box(
      'twt_email_template_placeholders',
      'Placeholders disponíveis',
      [__CLASS__, 'render_placeholders_box'],
      'twt_email_template',
      'normal',
      'low'
    );
  }

  public static function render_metabox($post) {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $subject = (string) get_post_meta($post->ID, 'twt_email_subject', true);
    if ($subject === '') {
      $subject = '[TWT] Submissão #{submission_id} — {brand_name}';
    }

    echo '<p><label><strong>Subject</strong></label><br>';
    echo '<input type="text" name="twt_email_subject" value="' . esc_attr($subject) . '" style="width:100%;">';
    echo '</p>';

    echo '<p class="description">O HTML do email é o conteúdo do template (editor). Usa layout em tabelas e estilos inline para Outlook.</p>';
  }

  public static function render_placeholders_box($post) {
    echo '<p class="description">Podes usar estes placeholders no <strong>Subject</strong> e no <strong>HTML</strong>:</p>';
    echo '<ul style="columns:2;max-width:900px;">';
    $items = [
      '{submission_id}',
      '{submitted_at}',
      '{brand_name}',
      '{campaign_name}',
      '{form_name}',
      '{location_name}',
      '{user_name}',
      '{answers_rows_html}',
      '{answers_text}',
    ];
    foreach ($items as $it) {
      echo '<li><code>' . esc_html($it) . '</code></li>';
    }
    echo '</ul>';

    echo '<hr>';
    echo '<p><strong>Template base (Outlook-safe)</strong></p>';
    echo '<p class="description">Se quiseres um ponto de partida, copia este HTML para o editor do template:</p>';

    $base_html = '';
    if (class_exists('TWT_TCRM_Email_Service') && method_exists('TWT_TCRM_Email_Service', 'default_outlook_template_html')) {
      $base_html = (string) TWT_TCRM_Email_Service::default_outlook_template_html();
    } else {
      $base_html = '/* Email service não carregado. Confirma modules/emails/class-email-service.php e includes/class-plugin.php */';
    }

    echo '<textarea readonly style="width:100%;min-height:220px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;">' . esc_textarea($base_html) . '</textarea>';
  }

  public static function save_meta($post_id, $post) {
    if (!$post || $post->post_type !== 'twt_email_template') return;

    if (
      !isset($_POST[self::NONCE_FIELD]) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
    ) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $subject = isset($_POST['twt_email_subject']) ? sanitize_text_field(wp_unslash($_POST['twt_email_subject'])) : '';
    update_post_meta($post_id, 'twt_email_subject', $subject);
  }
}