<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Insights_Admin {

  const NONCE_ACTION = 'twt_tcrm_insight_save';
  const NONCE_FIELD  = 'twt_tcrm_insight_nonce';

  public static function boot() {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_twt_insight', [__CLASS__, 'save_meta'], 10, 2);
  }

  public static function add_meta_boxes() {
    add_meta_box(
      'twt_tcrm_insight_config',
      'Configuração do Insight',
      [__CLASS__, 'render_metabox'],
      'twt_insight',
      'normal',
      'high'
    );
  }

  public static function render_metabox($post) {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $active = (string) get_post_meta($post->ID, 'twt_insight_active', true);
    if ($active === '') $active = '1';

    $priority = (string) get_post_meta($post->ID, 'twt_insight_priority', true);
    if ($priority === '') $priority = '100';

    $start = (string) get_post_meta($post->ID, 'twt_insight_start_date', true);
    $end = (string) get_post_meta($post->ID, 'twt_insight_end_date', true);

    $brand_id = (int) get_post_meta($post->ID, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($post->ID, 'twt_campaign_id', true);
    $user_id = (int) get_post_meta($post->ID, 'twt_user_id', true);
    $form_id = (int) get_post_meta($post->ID, 'twt_form_id', true);

    $when_q = (string) get_post_meta($post->ID, 'twt_insight_when_question_key', true);
    $when_op = (string) get_post_meta($post->ID, 'twt_insight_when_operator', true);
    if (!$when_op) $when_op = 'eq';
    $when_val = (string) get_post_meta($post->ID, 'twt_insight_when_value', true);

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

    $users = get_users([
      'orderby' => 'display_name',
      'order' => 'ASC',
      'number' => 500,
      'fields' => ['ID', 'display_name', 'user_login', 'user_email'],
    ]);

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>';
    echo '<th scope="row">Ativo</th>';
    echo '<td>';
    echo '<label style="display:inline-flex;align-items:center;gap:10px;">';
    echo '<input type="checkbox" name="twt_insight_active" value="1" ' . checked($active, '1', false) . '> ';
    echo '<strong>Sim</strong>';
    echo '</label>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_insight_priority">Prioridade</label></th>';
    echo '<td>';
    echo '<input type="number" id="twt_insight_priority" name="twt_insight_priority" value="' . esc_attr($priority) . '" min="0" step="1">';
    echo '<p class="description">Maior = aparece primeiro (ex.: 100).</p>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_insight_start_date">Data início</label></th>';
    echo '<td><input type="date" id="twt_insight_start_date" name="twt_insight_start_date" value="' . esc_attr($start) . '"></td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th scope="row"><label for="twt_insight_end_date">Data fim</label></th>';
    echo '<td><input type="date" id="twt_insight_end_date" name="twt_insight_end_date" value="' . esc_attr($end) . '"></td>';
    echo '</tr>';

    echo '<tr><th colspan="2"><hr></th></tr>';

    echo '<tr>';
    echo '<th scope="row">Aplicar a</th>';
    echo '<td>';
    echo '<p class="description">Podes definir 0..N alvos. Se deixares tudo vazio, é global.</p>';

    echo '<p><strong>Marca</strong><br>';
    echo '<select name="twt_brand_id" style="min-width:320px;">';
    echo '<option value="0">Global (qualquer marca)</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr((int) $b->ID) . '"' . selected($brand_id, (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><strong>Campanha</strong><br>';
    echo '<select name="twt_campaign_id" style="min-width:320px;">';
    echo '<option value="0">Qualquer campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr((int) $c->ID) . '"' . selected($campaign_id, (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><strong>Formulário</strong><br>';
    echo '<select name="twt_form_id" style="min-width:320px;">';
    echo '<option value="0">Qualquer formulário</option>';
    foreach ($forms as $f) {
      echo '<option value="' . esc_attr((int) $f->ID) . '"' . selected($form_id, (int) $f->ID, false) . '>' . esc_html($f->post_title) . '</option>';
    }
    echo '</select></p>';

    echo '<p><strong>Utilizador</strong><br>';
    echo '<select name="twt_user_id" style="min-width:520px;">';
    echo '<option value="0">Qualquer utilizador</option>';
    foreach ($users as $u) {
      $label = $u->display_name . ' (' . $u->user_login . ') — ' . $u->user_email;
      echo '<option value="' . esc_attr((int) $u->ID) . '"' . selected($user_id, (int) $u->ID, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '</td>';
    echo '</tr>';

    echo '<tr><th colspan="2"><hr></th></tr>';

    echo '<tr>';
    echo '<th scope="row">Condição (resposta)</th>';
    echo '<td>';

    echo '<p><label><strong>Question key</strong></label><br>';
    echo '<input type="text" name="twt_insight_when_question_key" value="' . esc_attr($when_q) . '" class="regular-text" placeholder="Ex: ruptura_stock">';
    echo '</p>';

    echo '<p><label><strong>Operador</strong></label><br>';
    echo '<select name="twt_insight_when_operator">';
    $ops = [
      'eq' => '== (igual)',
      'neq' => '!= (diferente)',
      'contains' => 'contém',
      'gt' => '> (maior)',
      'gte' => '≥ (maior/igual)',
      'lt' => '< (menor)',
      'lte' => '≤ (menor/igual)',
    ];
    foreach ($ops as $k => $lbl) {
      echo '<option value="' . esc_attr($k) . '"' . selected($when_op, $k, false) . '>' . esc_html($lbl) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label><strong>Valor</strong></label><br>';
    echo '<input type="text" name="twt_insight_when_value" value="' . esc_attr($when_val) . '" class="regular-text" placeholder="Ex: Sim">';
    echo '</p>';

    echo '<p class="description">Nota: por agora é condição simples (1 regra). A seguir podemos evoluir para múltiplas regras e condições analíticas (médias).</p>';

    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';
  }

  public static function save_meta($post_id, $post) {
    if (!$post || $post->post_type !== 'twt_insight') return;

    if (
      !isset($_POST[self::NONCE_FIELD]) ||
      !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
    ) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $active = isset($_POST['twt_insight_active']) && (string) wp_unslash($_POST['twt_insight_active']) === '1' ? '1' : '0';
    update_post_meta($post_id, 'twt_insight_active', $active);

    $priority = isset($_POST['twt_insight_priority']) ? (int) wp_unslash($_POST['twt_insight_priority']) : 100;
    if ($priority < 0) $priority = 0;
    update_post_meta($post_id, 'twt_insight_priority', $priority);

    $start = isset($_POST['twt_insight_start_date']) ? sanitize_text_field(wp_unslash($_POST['twt_insight_start_date'])) : '';
    $end = isset($_POST['twt_insight_end_date']) ? sanitize_text_field(wp_unslash($_POST['twt_insight_end_date'])) : '';

    if ($start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = '';
    if ($end && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = '';

    update_post_meta($post_id, 'twt_insight_start_date', $start);
    update_post_meta($post_id, 'twt_insight_end_date', $end);

    $brand_id = isset($_POST['twt_brand_id']) ? (int) wp_unslash($_POST['twt_brand_id']) : 0;
    $campaign_id = isset($_POST['twt_campaign_id']) ? (int) wp_unslash($_POST['twt_campaign_id']) : 0;
    $form_id = isset($_POST['twt_form_id']) ? (int) wp_unslash($_POST['twt_form_id']) : 0;
    $user_id = isset($_POST['twt_user_id']) ? (int) wp_unslash($_POST['twt_user_id']) : 0;

    update_post_meta($post_id, 'twt_brand_id', $brand_id);
    update_post_meta($post_id, 'twt_campaign_id', $campaign_id);
    update_post_meta($post_id, 'twt_form_id', $form_id);
    update_post_meta($post_id, 'twt_user_id', $user_id);

    $when_q = isset($_POST['twt_insight_when_question_key']) ? sanitize_text_field(wp_unslash($_POST['twt_insight_when_question_key'])) : '';
    $when_op = isset($_POST['twt_insight_when_operator']) ? sanitize_key(wp_unslash($_POST['twt_insight_when_operator'])) : 'eq';
    $when_val = isset($_POST['twt_insight_when_value']) ? sanitize_text_field(wp_unslash($_POST['twt_insight_when_value'])) : '';

    $allowed_ops = ['eq', 'neq', 'contains', 'gt', 'gte', 'lt', 'lte'];
    if (!in_array($when_op, $allowed_ops, true)) $when_op = 'eq';

    update_post_meta($post_id, 'twt_insight_when_question_key', $when_q);
    update_post_meta($post_id, 'twt_insight_when_operator', $when_op);
    update_post_meta($post_id, 'twt_insight_when_value', $when_val);
  }
}