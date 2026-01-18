<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Rules_Admin {

  const NONCE_ACTION = 'twt_tcrm_email_rule_save';
  const NONCE_FIELD  = 'twt_tcrm_email_rule_nonce';

  public static function boot() {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_twt_email_rule', [__CLASS__, 'save_meta'], 10, 2);
  }

  public static function add_meta_boxes() {
    add_meta_box(
      'twt_email_rule_settings',
      'Regra: Disparo e Destinatários',
      [__CLASS__, 'render_metabox'],
      'twt_email_rule',
      'normal',
      'high'
    );
  }

  public static function render_metabox($post) {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $active = (string) get_post_meta($post->ID, 'twt_rule_active', true);
    if ($active === '') $active = '1';

    $brand_id = (int) get_post_meta($post->ID, 'twt_rule_brand_id', true);
    $campaign_id = (int) get_post_meta($post->ID, 'twt_rule_campaign_id', true);
    $form_id = (int) get_post_meta($post->ID, 'twt_rule_form_id', true);
    $template_id = (int) get_post_meta($post->ID, 'twt_rule_template_id', true);

    $to_brand = (string) get_post_meta($post->ID, 'twt_rule_send_to_brand', true);
    if ($to_brand === '') $to_brand = '1';

    $to_submitter = (string) get_post_meta($post->ID, 'twt_rule_send_to_submitter', true);
    if ($to_submitter === '') $to_submitter = '0';

    $to_assigned = (string) get_post_meta($post->ID, 'twt_rule_send_to_assigned_users', true);
    if ($to_assigned === '') $to_assigned = '1';

    $extra = (string) get_post_meta($post->ID, 'twt_rule_extra_emails', true);

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

    $templates = get_posts([
      'post_type' => 'twt_email_template',
      'numberposts' => -1,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">Ativa</th><td>';
    echo '<label><input type="checkbox" name="twt_rule_active" value="1" ' . checked($active, '1', false) . '> Sim</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Quando</th><td>';
    echo '<p class="description">Nesta fase: dispara <strong>sempre que houver submissão</strong> que combine com Form + (opcional) Marca/Campanha.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>Marca</label></th><td>';
    echo '<select name="twt_rule_brand_id" style="min-width:320px;">';
    echo '<option value="0">Qualquer marca</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr((int) $b->ID) . '"' . selected($brand_id, (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>Campanha</label></th><td>';
    echo '<select name="twt_rule_campaign_id" style="min-width:320px;">';
    echo '<option value="0">Qualquer campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr((int) $c->ID) . '"' . selected($campaign_id, (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Se a submissão vier sem campanha (0), só dispara se aqui estiver “Qualquer campanha”.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>Formulário</label></th><td>';
    echo '<select name="twt_rule_form_id" required style="min-width:320px;">';
    echo '<option value="">Selecionar</option>';
    foreach ($forms as $f) {
      echo '<option value="' . esc_attr((int) $f->ID) . '"' . selected($form_id, (int) $f->ID, false) . '>' . esc_html($f->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>Template</label></th><td>';
    echo '<select name="twt_rule_template_id" required style="min-width:320px;">';
    echo '<option value="">Selecionar</option>';
    foreach ($templates as $t) {
      echo '<option value="' . esc_attr((int) $t->ID) . '"' . selected($template_id, (int) $t->ID, false) . '>' . esc_html($t->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Enviar para</th><td>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="twt_rule_send_to_brand" value="1" ' . checked($to_brand, '1', false) . '> Marca (users + emails extra)</label>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="twt_rule_send_to_assigned_users" value="1" ' . checked($to_assigned, '1', false) . '> Users atribuídos (assignments) (brand + campaign (inclui 0) + form)</label>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="twt_rule_send_to_submitter" value="1" ' . checked($to_submitter, '1', false) . '> Utilizador que submeteu</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label>Emails extra</label></th><td>';
    echo '<textarea name="twt_rule_extra_emails" rows="4" style="width:100%;max-width:620px;" placeholder="um email por linha">' . esc_textarea($extra) . '</textarea>';
    echo '</td></tr>';

    echo '</tbody></table>';
  }

  public static function save_meta($post_id, $post) {
    if (!$post || $post->post_type !== 'twt_email_rule') return;

    if (
      !isset($_POST[self::NONCE_FIELD]) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
    ) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $active = isset($_POST['twt_rule_active']) && (string) wp_unslash($_POST['twt_rule_active']) === '1' ? '1' : '0';

    $brand_id = isset($_POST['twt_rule_brand_id']) ? (int) wp_unslash($_POST['twt_rule_brand_id']) : 0;
    $campaign_id = isset($_POST['twt_rule_campaign_id']) ? (int) wp_unslash($_POST['twt_rule_campaign_id']) : 0;
    $form_id = isset($_POST['twt_rule_form_id']) ? (int) wp_unslash($_POST['twt_rule_form_id']) : 0;
    $template_id = isset($_POST['twt_rule_template_id']) ? (int) wp_unslash($_POST['twt_rule_template_id']) : 0;

    $to_brand = isset($_POST['twt_rule_send_to_brand']) && (string) wp_unslash($_POST['twt_rule_send_to_brand']) === '1' ? '1' : '0';
    $to_submitter = isset($_POST['twt_rule_send_to_submitter']) && (string) wp_unslash($_POST['twt_rule_send_to_submitter']) === '1' ? '1' : '0';
    $to_assigned = isset($_POST['twt_rule_send_to_assigned_users']) && (string) wp_unslash($_POST['twt_rule_send_to_assigned_users']) === '1' ? '1' : '0';

    $extra = isset($_POST['twt_rule_extra_emails']) ? sanitize_textarea_field(wp_unslash($_POST['twt_rule_extra_emails'])) : '';

    update_post_meta($post_id, 'twt_rule_active', $active);
    update_post_meta($post_id, 'twt_rule_brand_id', $brand_id);
    update_post_meta($post_id, 'twt_rule_campaign_id', $campaign_id);
    update_post_meta($post_id, 'twt_rule_form_id', $form_id);
    update_post_meta($post_id, 'twt_rule_template_id', $template_id);

    update_post_meta($post_id, 'twt_rule_send_to_brand', $to_brand);
    update_post_meta($post_id, 'twt_rule_send_to_submitter', $to_submitter);
    update_post_meta($post_id, 'twt_rule_send_to_assigned_users', $to_assigned);
    update_post_meta($post_id, 'twt_rule_extra_emails', $extra);
  }
}