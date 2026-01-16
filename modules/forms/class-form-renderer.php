<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Form_Renderer {

  public static function render_questions(array $schema, $layout_arr = null) {
    $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
    if (!$questions) return '<p>Formulário sem perguntas.</p>';

    $layout = is_array($layout_arr) ? $layout_arr : null;

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

    // Layout por secções
    if ($layout && isset($layout['layout']['sections']) && is_array($layout['layout']['sections'])) {
      $cols = 1;
      if (isset($layout['layout']['columns'])) {
        $cols = max(1, (int)$layout['layout']['columns']);
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

    // Sem layout: lista simples
    $out .= '<div class="twt-tcrm-section"><div class="twt-tcrm-grid twt-tcrm-cols-1">';
    foreach ($questions as $q) {
      $out .= self::render_field($q);
    }
    $out .= '</div></div>';

    return $out;
  }

  public static function schema_needs_multipart(array $schema) {
    $questions = isset($schema['questions']) && is_array($schema['questions']) ? $schema['questions'] : [];
    foreach ($questions as $q) {
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

    // Inputs "normais" usam twt_q[key], uploads usam twt_upload[key]
    $name_text = 'twt_q[' . $key . ']';
    $name_upload = 'twt_upload[' . $key . ']';

    $req_attr = $required ? ' required' : '';
    $aria_help = $help ? ' aria-describedby="' . esc_attr($help_id) . '"' : '';

    $out = '<div class="twt-tcrm-field twt-tcrm-type-' . esc_attr($type) . '">';

    // Label
    // Checkbox/radio têm UI própria, mas mantemos um cabeçalho consistente
    $out .= '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong>';
    if ($required) $out .= ' <span class="twt-tcrm-req">*</span>';
    $out .= '</label>';

    if ($help) {
      $out .= '<div class="twt-tcrm-help" id="' . esc_attr($help_id) . '">' . esc_html($help) . '</div>';
    }

    // Render por tipo
    if ($type === 'textarea') {
      $out .= '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name_text) . '" rows="4"' . $req_attr . $aria_help . '></textarea>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'date') {
      // type="date" dá calendário em browsers modernos
      $out .= '<input id="' . esc_attr($id) . '" type="date" name="' . esc_attr($name_text) . '" inputmode="numeric"' . $req_attr . $aria_help . '>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'time') {
      // type="time" dá selector de horas em browsers modernos
      $out .= '<input id="' . esc_attr($id) . '" type="time" name="' . esc_attr($name_text) . '" inputmode="numeric"' . $req_attr . $aria_help . '>';
      $out .= '</div>';
      return $out;
    }

    if ($type === 'checkbox') {
      // Truque essencial: garantir que o servidor recebe sempre algo
      // Se não estiver marcado, o hidden "0" é enviado
      $out .= '<div class="twt-tcrm-checkwrap">';
      $out .= '<input type="hidden" name="' . esc_attr($name_text) . '" value="0">';
      $out .= '<label class="twt-tcrm-check">';
      $out .= '<input id="' . esc_attr($id) . '" type="checkbox" name="' . esc_attr($name_text) . '" value="1"' . $aria_help . '>';
      $out .= '<span>' . esc_html('Sim') . '</span>';
      $out .= '</label>';
      $out .= '</div>';

      $out .= '</div>';
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
        $out .= '</div>';
        return $out;
      }

      $out .= '<div class="twt-tcrm-radio"' . ($help ? ' aria-describedby="' . esc_attr($help_id) . '"' : '') . '>';
      foreach ($opts as $i => $o) {
        $o = sanitize_text_field($o);
        if ($o === '') continue;
        $rid = $id . '_' . (int)$i;

        // required: só precisa de ir num dos radios do grupo, mas não faz mal repetir
        $out .= '<label class="twt-tcrm-radio-item" for="' . esc_attr($rid) . '">';
        $out .= '<input id="' . esc_attr($rid) . '" type="radio" name="' . esc_attr($name_text) . '" value="' . esc_attr($o) . '"' . $req_attr . '>';
        $out .= '<span>' . esc_html($o) . '</span>';
        $out .= '</label>';
      }
      $out .= '</div>';

      $out .= '</div>';
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

    // Números, euro, percentagem
    if (in_array($type, ['number', 'currency', 'percent'], true)) {
      $step = ' step="0.01"';
      $min_attr = ($min !== null && $min !== '') ? ' min="' . esc_attr((float)$min) . '"' : '';
      $max_attr = ($max !== null && $max !== '') ? ' max="' . esc_attr((float)$max) . '"' : '';
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

    // default text
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
}
