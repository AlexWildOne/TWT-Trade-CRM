<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Email_Kses {

  public static function boot() {
    // Permitir HTML "rico" só quando estamos a guardar twt_email_template
    add_filter('wp_kses_allowed_html', [__CLASS__, 'allow_email_template_html'], 10, 2);
  }

  public static function allow_email_template_html($tags, $context) {
    if (!is_admin()) return $tags;
    if ($context !== 'post') return $tags;

    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    if ($post_type !== 'twt_email_template') return $tags;

    // Base: tags normais permitidas em posts
    $allowed = $tags;

    // Acrescentar tags/atributos necessários para email HTML (Outlook-safe)
    $allowed['table'] = [
      'role' => true, 'width' => true, 'cellspacing' => true, 'cellpadding' => true, 'border' => true,
      'style' => true, 'align' => true, 'bgcolor' => true,
    ];
    $allowed['tr'] = ['style' => true, 'bgcolor' => true, 'align' => true, 'valign' => true];
    $allowed['td'] = ['style' => true, 'width' => true, 'align' => true, 'valign' => true, 'colspan' => true, 'rowspan' => true, 'bgcolor' => true];
    $allowed['th'] = ['style' => true, 'width' => true, 'align' => true, 'valign' => true, 'colspan' => true, 'rowspan' => true, 'bgcolor' => true];
    $allowed['tbody'] = ['style' => true];
    $allowed['thead'] = ['style' => true];
    $allowed['tfoot'] = ['style' => true];

    $allowed['div'] = ['style' => true, 'align' => true];
    $allowed['span'] = ['style' => true];
    $allowed['p'] = ['style' => true];
    $allowed['h1'] = ['style' => true];
    $allowed['h2'] = ['style' => true];
    $allowed['h3'] = ['style' => true];
    $allowed['h4'] = ['style' => true];

    $allowed['a'] = ['href' => true, 'style' => true, 'target' => true, 'rel' => true, 'title' => true];
    $allowed['img'] = ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'border' => true];

    $allowed['br'] = [];
    $allowed['hr'] = ['style' => true];

    $allowed['strong'] = ['style' => true];
    $allowed['b'] = ['style' => true];
    $allowed['em'] = ['style' => true];
    $allowed['i'] = ['style' => true];
    $allowed['u'] = ['style' => true];

    $allowed['ul'] = ['style' => true];
    $allowed['ol'] = ['style' => true];
    $allowed['li'] = ['style' => true];

    return $allowed;
  }
}