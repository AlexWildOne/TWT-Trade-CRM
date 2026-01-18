<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Admin_Layouts {

  const MENU_SLUG = 'twt-tcrm';
  const PAGE_SLUG = 'twt-tcrm-layouts';

  const OPT_PREFIX = 'twt_tcrm_layout_';

  public static function boot() {
    add_action('admin_menu', [__CLASS__, 'register_submenu'], 30);
    add_action('admin_init', [__CLASS__, 'handle_post']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function register_submenu() {
    add_submenu_page(
      self::MENU_SLUG,
      'Layouts',
      'Layouts',
      'twt_tcrm_manage_brands',
      self::PAGE_SLUG,
      [__CLASS__, 'page_layouts']
    );
  }

  public static function enqueue_assets($hook) {
    if (!is_admin()) return;

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($page !== self::PAGE_SLUG) return;

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    wp_enqueue_style(
      'twt-tcrm-layouts',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/layouts.css',
      [],
      TWT_TCRM_VERSION
    );

    wp_enqueue_script(
      'twt-tcrm-layouts',
      TWT_TCRM_PLUGIN_URL . 'admin/assets/layouts.js',
      ['jquery', 'wp-color-picker'],
      TWT_TCRM_VERSION,
      true
    );
  }

  public static function page_layouts() {
    if (!current_user_can('twt_tcrm_manage_brands')) {
      wp_die('Sem permissões.');
    }

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

    $brand_id = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : 0;
    $campaign_id = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;

    $msg = isset($_GET['twt_tcrm_msg']) ? sanitize_text_field(wp_unslash($_GET['twt_tcrm_msg'])) : '';

    echo '<div class="wrap twt-tcrm-admin twt-tcrm-layouts">';
    echo '<h1>Layouts</h1>';
    echo '<p>Define estilos por Marca e Campanha. Se escolheres “Sem campanha”, aplicas um layout base à Marca.</p>';

    if ($msg === 'saved') {
      echo '<div class="notice notice-success"><p>Layout guardado.</p></div>';
    } elseif ($msg === 'error') {
      echo '<div class="notice notice-error"><p>Erro ao guardar. Verifica os dados.</p></div>';
    }

    echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="twt-layouts-filter">';
    echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';

    echo '<div class="twt-layouts-row">';
    echo '<div class="twt-layouts-col">';
    echo '<label><strong>Marca</strong></label><br>';
    echo '<select name="brand_id" required style="min-width:320px;">';
    echo '<option value="">Seleccionar</option>';
    foreach ($brands as $b) {
      echo '<option value="' . esc_attr($b->ID) . '"' . selected($brand_id, (int) $b->ID, false) . '>' . esc_html($b->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="twt-layouts-col">';
    echo '<label><strong>Campanha</strong></label><br>';
    echo '<select name="campaign_id" style="min-width:320px;">';
    echo '<option value="0"' . selected($campaign_id, 0, false) . '>Sem campanha</option>';
    foreach ($campaigns as $c) {
      echo '<option value="' . esc_attr($c->ID) . '"' . selected($campaign_id, (int) $c->ID, false) . '>' . esc_html($c->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="twt-layouts-col twt-layouts-col-btn">';
    echo '<label>&nbsp;</label><br>';
    echo '<button type="submit" class="button button-primary">Carregar</button>';
    echo '</div>';
    echo '</div>';
    echo '</form>';

    if (!$brand_id) {
      echo '<hr>';
      echo '<p>Escolhe uma marca para começares.</p>';
      echo '</div>';
      return;
    }

    $layout = self::get_layout($brand_id, $campaign_id);

    echo '<hr>';

    echo '<h2>Editor</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&brand_id=' . (int) $brand_id . '&campaign_id=' . (int) $campaign_id)) . '">';
    wp_nonce_field('twt_tcrm_layout_save', 'twt_tcrm_layout_nonce');

    echo '<input type="hidden" name="brand_id" value="' . esc_attr($brand_id) . '">';
    echo '<input type="hidden" name="campaign_id" value="' . esc_attr($campaign_id) . '">';
    echo '<input type="hidden" name="twt_tcrm_do" value="save_layout">';

    echo '<div class="twt-layouts-editor">';

    echo '<div class="twt-layouts-card">';
    echo '<h3>Cores</h3>';

    self::color_field('Cor primária', 'primary', $layout);
    self::color_field('Cor de fundo', 'background', $layout);
    self::color_field('Cor do card', 'card', $layout);
    self::color_field('Cor do texto', 'text', $layout);
    self::color_field('Cor de borda', 'border', $layout);

    echo '</div>';

    echo '<div class="twt-layouts-card">';
    echo '<h3>Tipografia</h3>';

    self::text_field('Fonte (CSS font-family)', 'font_family', $layout, 'Ex, Inter, system-ui, sans-serif');
    self::number_field('Tamanho base (px)', 'font_size', $layout, 12, 22, 1);
    self::number_field('Título Secção (px)', 'h3_size', $layout, 14, 28, 1);

    echo '</div>';

    echo '<div class="twt-layouts-card">';
    echo '<h3>Componentes</h3>';

    self::number_field('Radius (px)', 'radius', $layout, 0, 28, 1);
    self::number_field('Padding card (px)', 'card_padding', $layout, 8, 28, 1);
    self::number_field('Espaçamento (px)', 'gap', $layout, 6, 24, 1);
    self::number_field('Botão, tamanho (px)', 'btn_font_size', $layout, 12, 20, 1);

    echo '</div>';

    echo '<div class="twt-layouts-card twt-layouts-preview">';
    echo '<h3>Preview</h3>';
    echo self::render_preview();
    echo '<p class="description">Isto é um preview simples. No front, o layout aplica-se aos shortcodes automaticamente no próximo passo.</p>';
    echo '</div>';

    echo '</div>';

    echo '<p style="margin-top:14px;">';
    echo '<button type="submit" class="button button-primary">Guardar layout</button> ';
    echo '<a class="button" href="' . esc_url(self::reset_url($brand_id, $campaign_id)) . '" onclick="return confirm(\'Repor para defaults?\');">Repor defaults</a>';
    echo '</p>';

    echo '</form>';

    echo '</div>';
  }

  public static function handle_post() {
    if (!is_admin()) return;

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($page !== self::PAGE_SLUG) return;

    if (!current_user_can('twt_tcrm_manage_brands')) return;

    // Reset
    if (isset($_GET['twt_tcrm_reset']) && sanitize_text_field(wp_unslash($_GET['twt_tcrm_reset'])) === '1') {
      $brand_id = isset($_GET['brand_id']) ? (int) $_GET['brand_id'] : 0;
      $campaign_id = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 0;
      $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

      if (!$brand_id || !wp_verify_nonce($nonce, 'twt_tcrm_layout_reset_' . $brand_id . '_' . $campaign_id)) {
        wp_safe_redirect(self::url(['twt_tcrm_msg' => 'error', 'brand_id' => $brand_id, 'campaign_id' => $campaign_id]));
        exit;
      }

      delete_option(self::opt_key($brand_id, $campaign_id));

      wp_safe_redirect(self::url(['twt_tcrm_msg' => 'saved', 'brand_id' => $brand_id, 'campaign_id' => $campaign_id]));
      exit;
    }

    // Save
    if (!isset($_POST['twt_tcrm_do']) || sanitize_text_field(wp_unslash($_POST['twt_tcrm_do'])) !== 'save_layout') return;

    $nonce = isset($_POST['twt_tcrm_layout_nonce']) ? sanitize_text_field(wp_unslash($_POST['twt_tcrm_layout_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'twt_tcrm_layout_save')) {
      wp_safe_redirect(self::url(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $brand_id = isset($_POST['brand_id']) ? (int) $_POST['brand_id'] : 0;
    $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
    if (!$brand_id) {
      wp_safe_redirect(self::url(['twt_tcrm_msg' => 'error']));
      exit;
    }

    $layout = self::sanitize_layout($_POST);

    update_option(self::opt_key($brand_id, $campaign_id), wp_json_encode($layout, JSON_UNESCAPED_UNICODE), false);

    wp_safe_redirect(self::url(['twt_tcrm_msg' => 'saved', 'brand_id' => $brand_id, 'campaign_id' => $campaign_id]));
    exit;
  }

  public static function get_layout($brand_id, $campaign_id = 0) {
    $brand_id = (int) $brand_id;
    $campaign_id = (int) $campaign_id;

    $raw = get_option(self::opt_key($brand_id, $campaign_id), '');
    $arr = [];
    if ($raw) {
      $tmp = json_decode($raw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $arr = $tmp;
      }
    }

    return array_merge(self::defaults(), $arr);
  }

  private static function sanitize_layout($src) {
    $d = self::defaults();

    $out = [];

    $out['primary'] = self::sanitize_color($src['primary'] ?? $d['primary'], $d['primary']);
    $out['background'] = self::sanitize_color($src['background'] ?? $d['background'], $d['background']);
    $out['card'] = self::sanitize_color($src['card'] ?? $d['card'], $d['card']);
    $out['text'] = self::sanitize_color($src['text'] ?? $d['text'], $d['text']);
    $out['border'] = self::sanitize_color($src['border'] ?? $d['border'], $d['border']);

    $out['font_family'] = isset($src['font_family']) ? sanitize_text_field(wp_unslash($src['font_family'])) : $d['font_family'];

    $out['font_size'] = self::sanitize_int($src['font_size'] ?? $d['font_size'], 12, 22, $d['font_size']);
    $out['h3_size'] = self::sanitize_int($src['h3_size'] ?? $d['h3_size'], 14, 28, $d['h3_size']);

    $out['radius'] = self::sanitize_int($src['radius'] ?? $d['radius'], 0, 28, $d['radius']);
    $out['card_padding'] = self::sanitize_int($src['card_padding'] ?? $d['card_padding'], 8, 28, $d['card_padding']);
    $out['gap'] = self::sanitize_int($src['gap'] ?? $d['gap'], 6, 24, $d['gap']);
    $out['btn_font_size'] = self::sanitize_int($src['btn_font_size'] ?? $d['btn_font_size'], 12, 20, $d['btn_font_size']);

    return $out;
  }

  private static function defaults() {
    return [
      'primary' => '#111111',
      'background' => '#ffffff',
      'card' => '#ffffff',
      'text' => '#111111',
      'border' => '#e6e6e6',
      'font_family' => 'inherit',
      'font_size' => 16,
      'h3_size' => 16,
      'radius' => 12,
      'card_padding' => 14,
      'gap' => 14,
      'btn_font_size' => 15,
    ];
  }

  private static function sanitize_color($value, $fallback) {
    $value = is_string($value) ? trim($value) : '';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) return $value;
    if (preg_match('/^#[0-9a-fA-F]{3}$/', $value)) return $value;
    return $fallback;
  }

  private static function sanitize_int($value, $min, $max, $fallback) {
    $v = (int) $value;
    if ($v < (int) $min || $v > (int) $max) return (int) $fallback;
    return $v;
  }

  private static function color_field($label, $key, $layout) {
    $val = isset($layout[$key]) ? $layout[$key] : '';
    echo '<p>';
    echo '<label><strong>' . esc_html($label) . '</strong></label><br>';
    echo '<input type="text" class="twt-color" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '" data-default-color="' . esc_attr($val) . '">';
    echo '</p>';
  }

  private static function text_field($label, $key, $layout, $placeholder = '') {
    $val = isset($layout[$key]) ? $layout[$key] : '';
    echo '<p>';
    echo '<label><strong>' . esc_html($label) . '</strong></label><br>';
    echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($placeholder) . '" style="width:100%;">';
    echo '</p>';
  }

  private static function number_field($label, $key, $layout, $min, $max, $step) {
    $val = isset($layout[$key]) ? (int) $layout[$key] : 0;

    echo '<p>';
    echo '<label><strong>' . esc_html($label) . '</strong></label><br>';
    echo '<input type="number" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '" min="' . esc_attr((int)$min) . '" max="' . esc_attr((int)$max) . '" step="' . esc_attr((int)$step) . '" style="width:120px;">';
    echo '</p>';
  }

  private static function render_preview() {
    $html = '';
    $html .= '<div class="twt-layout-preview" id="twt-layout-preview">';
    $html .= '<div class="twt-p-card">';
    $html .= '<div class="twt-p-kpi">';
    $html .= '<div class="twt-p-kpi-label">Reports, 7 dias</div>';
    $html .= '<div class="twt-p-kpi-val">12</div>';
    $html .= '</div>';
    $html .= '<div class="twt-p-kpi">';
    $html .= '<div class="twt-p-kpi-label">Marcas, 30 dias</div>';
    $html .= '<div class="twt-p-kpi-val">3</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="twt-p-card">';
    $html .= '<h4>Exemplo de secção</h4>';
    $html .= '<p>Texto de exemplo para validar tipografia, espaçamento e cores.</p>';
    $html .= '<a href="#" class="twt-p-btn" onclick="return false;">Botão</a>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
  }

  private static function opt_key($brand_id, $campaign_id) {
    return self::OPT_PREFIX . (int) $brand_id . '_' . (int) $campaign_id;
  }

  private static function url($args = []) {
    $base = admin_url('admin.php?page=' . self::PAGE_SLUG);
    return add_query_arg($args, $base);
  }

  private static function reset_url($brand_id, $campaign_id) {
    $url = self::url([
      'brand_id' => (int) $brand_id,
      'campaign_id' => (int) $campaign_id,
      'twt_tcrm_reset' => '1',
    ]);

    return wp_nonce_url($url, 'twt_tcrm_layout_reset_' . (int) $brand_id . '_' . (int) $campaign_id);
  }
}
