<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Form_Renderer {

  public static function render_context_fields($form_id, $current_user_id, $selected_location_id = 0, $selected_campaign_id = 0) {
    $form_id = (int) $form_id;
    $current_user_id = (int) $current_user_id;

    $is_admin_like = false;
    if (class_exists('TWT_TCRM_Roles') && method_exists('TWT_TCRM_Roles', 'is_admin_like')) {
      $is_admin_like = (bool) TWT_TCRM_Roles::is_admin_like($current_user_id);
    }

    $allowed_campaign_ids = self::get_form_campaign_ids($form_id);

    $allowed_location_ids = self::get_form_location_ids($form_id);
    $user_location_ids = self::get_user_location_ids($current_user_id);

    $effective_location_ids = [];
    $location_required = false;

    if (!empty($allowed_location_ids)) {
      $location_required = true;

      if ($is_admin_like) {
        $effective_location_ids = $allowed_location_ids;
      } else {
        $effective_location_ids = array_values(array_intersect($allowed_location_ids, $user_location_ids));
      }
    } else {
      if ($is_admin_like) {
        $all = get_posts([
          'post_type' => 'twt_location',
          'numberposts' => -1,
          'orderby' => 'title',
          'order' => 'ASC',
        ]);

        $effective_location_ids = [];
        if (is_array($all)) {
          foreach ($all as $p) $effective_location_ids[] = (int) $p->ID;
        }
      } else {
        $effective_location_ids = $user_location_ids;
      }
    }

    $out = '';

    $out .= '<div class="twt-tcrm-section twt-tcrm-context">';
    $out .= '<h3 class="twt-tcrm-section-title">Contexto</h3>';
    $out .= '<div class="twt-tcrm-grid twt-tcrm-cols-2">';

    $out .= '<div class="twt-tcrm-field twt-tcrm-type-select">';
    $out .= '<label for="twt_location_id"><strong>Local</strong>';
    if ($location_required) $out .= ' <span class="twt-tcrm-req">*</span>';
    $out .= '</label>';

    if ($location_required && empty($effective_location_ids)) {
      $out .= '<div class="twt-tcrm-help">Este formulário está limitado a locais específicos, mas não tens nenhum desses locais atribuído.</div>';
      $out .= '<select id="twt_location_id" name="twt_location_id" disabled>';
      $out .= '<option value="">Sem locais disponíveis</option>';
      $out .= '</select>';
    } else {
      $out .= '<select id="twt_location_id" name="twt_location_id"' . ($location_required ? ' required' : '') . '>';
      if (!$location_required) {
        $out .= '<option value="0">Sem local</option>';
      } else {
        $out .= '<option value="">Seleccionar</option>';
      }

      foreach ($effective_location_ids as $loc_id) {
        $loc_id = (int) $loc_id;
        $title = get_the_title($loc_id);
        if (!$title) continue;
        $out .= '<option value="' . esc_attr($loc_id) . '"' . selected((int) $selected_location_id, $loc_id, false) . '>' . esc_html($title) . '</option>';
      }

      $out .= '</select>';

      if ($location_required) {
        $out .= '<div class="twt-tcrm-help">Obrigatório: este formulário só pode ser submetido num dos locais definidos.</div>';
      } else {
        $out .= '<div class="twt-tcrm-help">Opcional: escolhe o local para filtrar relatórios e insights.</div>';
      }
    }

    $out .= '</div>'; // field

    $out .= '<div class="twt-tcrm-field twt-tcrm-type-select">';
    $out .= '<label for="twt_campaign_id"><strong>Campanha</strong></label>';

    if (empty($allowed_campaign_ids)) {
      $out .= '<div class="twt-tcrm-help">Este formulário não tem campanhas configuradas. Podes submeter sem campanha.</div>';
      $out .= '<select id="twt_campaign_id" name="twt_campaign_id">';
      $out .= '<option value="0">Sem campanha</option>';
      $out .= '</select>';
    } else {
      $out .= '<select id="twt_campaign_id" name="twt_campaign_id">';
      $out .= '<option value="0">Sem campanha</option>';

      foreach ($allowed_campaign_ids as $cid) {
        $cid = (int) $cid;
        $title = get_the_title($cid);
        if (!$title) continue;
        $out .= '<option value="' . esc_attr($cid) . '"' . selected((int) $selected_campaign_id, $cid, false) . '>' . esc_html($title) . '</option>';
      }

      $out .= '</select>';
      $out .= '<div class="twt-tcrm-help">Opcional: escolhe uma campanha aplicável para esta submissão.</div>';
    }

    $out .= '</div>'; // field

    $out .= '</div>'; // grid
    $out .= '</div>'; // section

    return $out;
  }

  /**
   * Render questions with optional layout:
   * - single (default): current behavior
   * - steps: wizard (steps independent from sections)
   *
   * Expected layout shape (from schema.layout):
   * - mode: single|steps
   * - show_progress: bool
   * - steps: [{title, description, fields:[key]}]
   * - field_layout: { key: { width: 25|33|50|66|75|100 } }
   */
  public static function render_questions(array $schema, $layout_arr = null) {
    $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
    if (!$questions) return '<p>Formulário sem perguntas.</p>';

    $layout = is_array($layout_arr) ? $layout_arr : (isset($schema['layout']) && is_array($schema['layout']) ? $schema['layout'] : []);
    $mode = isset($layout['mode']) ? sanitize_key($layout['mode']) : 'single';
    if (!in_array($mode, ['single', 'steps'], true)) $mode = 'single';

    $meta_title = isset($schema['meta']['title']) ? sanitize_text_field($schema['meta']['title']) : '';
    $meta_subtitle = isset($schema['meta']['subtitle']) ? sanitize_text_field($schema['meta']['subtitle']) : '';

    $out = '';

    if ($meta_title || $meta_subtitle) {
      $out .= '<div class="twt-tcrm-section">';
      if ($meta_title) $out .= '<h3 class="twt-tcrm-section-title">' . esc_html($meta_title) . '</h3>';
      if ($meta_subtitle) $out .= '<div class="twt-tcrm-help">' . esc_html($meta_subtitle) . '</div>';
      $out .= '</div>';
    }

    $idx = self::index_questions($questions);

    // Steps mode
    if ($mode === 'steps') {
      $steps = isset($layout['steps']) && is_array($layout['steps']) ? $layout['steps'] : [];
      $show_progress = !empty($layout['show_progress']);
      $field_layout = isset($layout['field_layout']) && is_array($layout['field_layout']) ? $layout['field_layout'] : [];

      // fallback: if no steps configured, render everything as one step
      if (!$steps) {
        $all_keys = array_keys($idx);
        $steps = [
          [
            'title' => '',
            'description' => '',
            'fields' => $all_keys,
          ]
        ];
      }

      $total = count($steps);

      $out .= '<div class="twt-tcrm-wizard" data-steps-total="' . esc_attr((string) (int) $total) . '">';

      if ($show_progress && $total > 1) {
        $out .= '<div class="twt-tcrm-progress" data-twt-progress>';
        $out .= '<div class="twt-tcrm-progress-bar"><span class="twt-tcrm-progress-fill" style="width:0%"></span></div>';
        $out .= '<div class="twt-tcrm-progress-label"><span data-twt-progress-label>0%</span></div>';
        $out .= '</div>';
      }

      foreach ($steps as $i => $st) {
        $title = isset($st['title']) ? sanitize_text_field($st['title']) : '';
        $desc = isset($st['description']) ? sanitize_text_field($st['description']) : '';
        $fields = isset($st['fields']) && is_array($st['fields']) ? $st['fields'] : [];

        $step_index = (int) $i + 1;
        $is_active = ($i === 0);

        $out .= '<div class="twt-tcrm-step" data-twt-step data-step-index="' . esc_attr((string) $step_index) . '"' . ($is_active ? '' : ' hidden') . '>';

        if ($title) $out .= '<h3 class="twt-tcrm-step-title">' . esc_html($title) . '</h3>';
        if ($desc) $out .= '<div class="twt-tcrm-help">' . esc_html($desc) . '</div>';

        $out .= '<div class="twt-tcrm-grid twt-tcrm-cols-2">';

        foreach ($fields as $k) {
          $key = sanitize_key($k);
          if (!isset($idx[$key])) continue;

          $width = 100;
          if (isset($field_layout[$key]) && is_array($field_layout[$key]) && isset($field_layout[$key]['width'])) {
            $width = (int) $field_layout[$key]['width'];
          }

          $out .= self::wrap_field_with_width(self::render_field($idx[$key]), $width);
        }

        $out .= '</div>'; // grid

        // Wizard actions (JS will show/hide final submit button in form actions)
        $out .= '<div class="twt-tcrm-wizard-actions" data-twt-wizard-actions>';
        if ($step_index > 1) {
          $out .= '<button type="button" class="twt-tcrm-btn twt-tcrm-btn-secondary" data-twt-prev>Anterior</button>';
        }
        if ($step_index < $total) {
          $out .= '<button type="button" class="twt-tcrm-btn" data-twt-next>Seguinte</button>';
        } else {
          $out .= '<button type="button" class="twt-tcrm-btn" data-twt-submit>Submeter</button>';
        }
        $out .= '</div>';

        $out .= '</div>'; // step
      }

      $out .= '</div>'; // wizard

      return $out;
    }

    // ===== Single mode (existing behavior) =====
    // Keep compatibility with any legacy layout that had sections/columns (if passed)
    if ($layout && isset($layout['layout']['sections']) && is_array($layout['layout']['sections'])) {
      $cols = 1;
      if (isset($layout['layout']['columns'])) {
        $cols = max(1, (int) $layout['layout']['columns']);
        if ($cols > 2) $cols = 2;
      }

      foreach ($layout['layout']['sections'] as $section) {
        $title = isset($section['title']) ? sanitize_text_field($section['title']) : '';
        $fields = isset($section['fields']) && is_array($section['fields']) ? $section['fields'] : [];

        $out .= '<div class="twt-tcrm-section">';
        if ($title) $out .= '<h3 class="twt-tcrm-section-title">' . esc_html($title) . '</h3>';

        $out .= '<div class="twt-tcrm-grid twt-tcrm-cols-' . esc_attr($cols) . '">';

        foreach ($fields as $k) {
          $key = sanitize_key($k);
          if (!isset($idx[$key])) continue;
          $out .= self::render_field($idx[$key]);
        }

        $out .= '</div></div>';
      }

      return $out;
    }

    $out .= '<div class="twt-tcrm-section"><div class="twt-tcrm-grid twt-tcrm-cols-1">';
    foreach ($questions as $q) {
      if (!is_array($q)) continue;
      $out .= self::render_field($q);
    }
    $out .= '</div></div>';

    return $out;
  }

  private static function wrap_field_with_width($field_html, $width) {
    $allowed = [25, 33, 50, 66, 75, 100];
    $w = in_array((int) $width, $allowed, true) ? (int) $width : 100;

    // Wrap to apply width without reworking render_field markup
    $cls = 'twt-tcrm-col twt-tcrm-w-' . $w;
    return '<div class="' . esc_attr($cls) . '">' . $field_html . '</div>';
  }

  public static function schema_needs_multipart(array $schema) {
    $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
    foreach ($questions as $q) {
      if (!is_array($q)) continue;
      $type = isset($q['type']) ? sanitize_key($q['type']) : '';
      if ($type === 'image_upload' || $type === 'file_upload') {
        return true;
      }
    }
    return false;
  }

  public static function render_field(array $q) {
    $key = isset($q['key']) ? sanitize_key($q['key']) : '';
    if (!$key) return '';

    $label = isset($q['label']) ? sanitize_text_field($q['label']) : $key;
    $type = isset($q['type']) ? sanitize_key($q['type']) : 'text';
    $required = !empty($q['required']);
    $help = isset($q['help_text']) ? sanitize_text_field($q['help_text']) : '';

    $min = isset($q['min']) ? $q['min'] : null;
    $max = isset($q['max']) ? $q['max'] : null;
    $unit = isset($q['unit']) ? sanitize_text_field($q['unit']) : '';

    $id = 'twt_q_' . $key;
    $help_id = $id . '_help';

    $name_text = 'twt_q[' . $key . ']';
    $name_upload = 'twt_upload[' . $key . ']';

    $req_attr = $required ? ' required' : '';
    $aria_help = $help ? ' aria-describedby="' . esc_attr($help_id) . '"' : '';

    $out = '<div class="twt-tcrm-field twt-tcrm-type-' . esc_attr($type) . '">';

    $is_choice_group = ($type === 'checkbox' || $type === 'radio');

    if ($is_choice_group) {
      $out .= '<fieldset class="twt-tcrm-fieldset">';
      $out .= '<legend><strong>' . esc_html($label) . '</strong>';
      if ($required) $out .= ' <span class="twt-tcrm-req">*</span>';
      $out .= '</legend>';

      if ($help) {
        $out .= '<div class="twt-tcrm-help" id="' . esc_attr($help_id) . '">' . esc_html($help) . '</div>';
      }
    } else {
      $out .= '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong>';
      if ($required) $out .= ' <span class="twt-tcrm-req">*</span>';
      $out .= '</label>';

      if ($help) {
        $out .= '<div class="twt-tcrm-help" id="' . esc_attr($help_id) . '">' . esc_html($help) . '</div>';
      }
    }

    if ($type === 'textarea') {
      $out .= '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name_text) . '" rows="4"' . $req_attr . $aria_help . '></textarea>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'date') {
      $out .= '<input id="' . esc_attr($id) . '" type="date" name="' . esc_attr($name_text) . '" inputmode="numeric"' . $req_attr . $aria_help . '>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'time') {
      $out .= '<input id="' . esc_attr($id) . '" type="time" name="' . esc_attr($name_text) . '" inputmode="numeric"' . $req_attr . $aria_help . '>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'checkbox') {
      $out .= '<div class="twt-tcrm-checkwrap">';
      $out .= '<input type="hidden" name="' . esc_attr($name_text) . '" value="0">';
      $out .= '<label class="twt-tcrm-check" for="' . esc_attr($id) . '">';
      $out .= '<input id="' . esc_attr($id) . '" type="checkbox" name="' . esc_attr($name_text) . '" value="1"' . $req_attr . $aria_help . '>';
      $out .= '<span>' . esc_html('Sim') . '</span>';
      $out .= '</label>';
      $out .= '</div>';

      $out .= '</fieldset></div>';
      return $out;
    }

    if ($type === 'select') {
      $opts = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];

      if (!$opts) {
        $out .= '<div class="twt-tcrm-help">Sem opções configuradas para este campo.</div>';
        $out .= '<input id="' . esc_attr($id) . '" type="text" name="' . esc_attr($name_text) . '"' . $req_attr . $aria_help . '>';
        $out .= '</div>';
        return $out;
      }

      $out .= '<select id="' . esc_attr($id) . '" name="' . esc_attr($name_text) . '"' . $req_attr . $aria_help . '>';
      $out .= '<option value="">' . esc_html('Seleccionar') . '</option>';
      foreach ($opts as $o) {
        $o = sanitize_text_field($o);
        if ($o === '') continue;
        $out .= '<option value="' . esc_attr($o) . '">' . esc_html($o) . '</option>';
      }
      $out .= '</select>';

      $out .= '</div>';
      return $out;
    }

    if ($type === 'radio') {
      $opts = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];

      if (!$opts) {
        $out .= '<div class="twt-tcrm-help">Sem opções configuradas para este campo.</div>';
        $out .= '<input id="' . esc_attr($id) . '" type="text" name="' . esc_attr($name_text) . '"' . $req_attr . $aria_help . '>';
        $out .= '</fieldset></div>';
        return $out;
      }

      $out .= '<div class="twt-tcrm-radio"' . ($help ? ' aria-describedby="' . esc_attr($help_id) . '"' : '') . '>';
      $printed_any = false;
      foreach ($opts as $i => $o) {
        $o = sanitize_text_field($o);
        if ($o === '') continue;

        $rid = $id . '_' . (int) $i;
        $req_this = (!$printed_any && $required) ? ' required' : '';

        $out .= '<label class="twt-tcrm-radio-item" for="' . esc_attr($rid) . '">';
        $out .= '<input id="' . esc_attr($rid) . '" type="radio" name="' . esc_attr($name_text) . '" value="' . esc_attr($o) . '"' . $req_this . '>';
        $out .= '<span>' . esc_html($o) . '</span>';
        $out .= '</label>';

        $printed_any = true;
      }
      $out .= '</div>';

      $out .= '</fieldset></div>';
      return $out;
    }

    if ($type === 'image_upload') {
      $out .= '<input id="' . esc_attr($id) . '" type="file" name="' . esc_attr($name_upload) . '" accept="image/*"' . $req_attr . $aria_help . '>';
      $out .= '<div class="twt-tcrm-help">Aceita imagem (JPG, PNG, GIF, WebP).</div>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'file_upload') {
      $out .= '<input id="' . esc_attr($id) . '" type="file" name="' . esc_attr($name_upload) . '"' . $req_attr . $aria_help . '>';
      $out .= '<div class="twt-tcrm-help">Aceita ficheiro.</div>';
      $out .= '</div>';
      return $out;
    }

    if (in_array($type, ['number', 'currency', 'percent'], true)) {
      $step = ' step="0.01"';
      $min_attr = ($min !== null && $min !== '') ? ' min="' . esc_attr((float) $min) . '"' : '';
      $max_attr = ($max !== null && $max !== '') ? ' max="' . esc_attr((float) $max) . '"' : '';
      $inputmode = ' inputmode="decimal"';

      $placeholder = '';
      $suffix = '';

      if ($type === 'currency') {
        $placeholder = ' placeholder="0,00"';
        $suffix = '€';
      } elseif ($type === 'percent') {
        $placeholder = ' placeholder="0"';
        $suffix = '%';
      } elseif ($unit) {
        $suffix = $unit;
      }

      $out .= '<div class="twt-tcrm-inline">';
      $out .= '<input id="' . esc_attr($id) . '" type="number" name="' . esc_attr($name_text) . '"' . $step . $min_attr . $max_attr . $placeholder . $inputmode . $req_attr . $aria_help . '>';
      if ($suffix) {
        $out .= '<span class="twt-tcrm-unit">' . esc_html($suffix) . '</span>';
      }
      $out .= '</div>';

      $out .= '</div>';
      return $out;
    }

    $out .= '<input id="' . esc_attr($id) . '" type="text" name="' . esc_attr($name_text) . '"' . $req_attr . $aria_help . '>';
    $out .= '</div>';

    return $out;
  }

  private static function index_questions(array $questions) {
    $idx = [];
    foreach ($questions as $q) {
      if (!is_array($q)) continue;
      $key = isset($q['key']) ? sanitize_key($q['key']) : '';
      if (!$key) continue;
      $idx[$key] = $q;
    }
    return $idx;
  }

  public static function get_form_location_ids($form_id) {
    $form_id = (int) $form_id;
    if (!$form_id || !class_exists('TWT_TCRM_DB') || !method_exists('TWT_TCRM_DB', 'table_form_locations')) {
      return [];
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_form_locations();

    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT location_id FROM {$t} WHERE form_id = %d ORDER BY location_id ASC",
      $form_id
    ));

    if (!is_array($ids)) return [];
    $ids = array_map('intval', $ids);
    return array_values(array_unique(array_filter($ids)));
  }

  public static function get_form_campaign_ids($form_id) {
    $form_id = (int) $form_id;
    if (!$form_id || !class_exists('TWT_TCRM_DB') || !method_exists('TWT_TCRM_DB', 'table_form_campaigns')) {
      return [];
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_form_campaigns();

    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT campaign_id FROM {$t} WHERE form_id = %d ORDER BY campaign_id ASC",
      $form_id
    ));

    if (!is_array($ids)) return [];
    $ids = array_map('intval', $ids);
    return array_values(array_unique(array_filter($ids)));
  }

  public static function get_user_location_ids($user_id) {
    $user_id = (int) $user_id;
    if (!$user_id || !class_exists('TWT_TCRM_DB') || !method_exists('TWT_TCRM_DB', 'table_location_assignments')) {
      return [];
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_location_assignments();

    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT location_id FROM {$t} WHERE user_id = %d AND active = 1 ORDER BY location_id ASC",
      $user_id
    ));

    if (!is_array($ids)) return [];
    $ids = array_map('intval', $ids);
    return array_values(array_unique(array_filter($ids)));
  }
}