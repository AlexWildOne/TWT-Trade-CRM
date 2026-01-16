<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Insights {

  const META_ACTIVE = 'twt_insight_active';
  const META_PRIORITY = 'twt_insight_priority';

  const META_USER_ID = 'twt_user_id';
  const META_BRAND_ID = 'twt_brand_id';
  const META_CAMPAIGN_ID = 'twt_campaign_id';
  const META_FORM_ID = 'twt_form_id';

  public static function boot() {
    // reservado, no futuro podemos registar REST endpoints, etc.
  }

  /**
   * Insights para um user.
   * Regra:
   * - inclui insights globais (sem filtros)
   * - inclui insights com user_id exacto
   * - inclui insights por brand_id do próprio user (se existir)
   */
  public static function get_for_user($user_id, $limit = 20) {
    $user_id = (int) $user_id;
    if (!$user_id) return [];

    $brand_id = (int) get_user_meta($user_id, 'twt_brand_id', true);

    $items = self::query_insights([
      'user_id' => $user_id,
      'brand_id' => $brand_id,
    ], $limit);

    return self::map_items($items);
  }

  /**
   * Insights para uma marca.
   * Regra:
   * - inclui insights globais
   * - inclui insights com brand_id exacto
   */
  public static function get_for_brand($brand_id, $limit = 20) {
    $brand_id = (int) $brand_id;
    if (!$brand_id) return [];

    $items = self::query_insights([
      'brand_id' => $brand_id,
    ], $limit);

    return self::map_items($items);
  }

  /**
   * Query central de insights com regras simples:
   * - insight activo
   * - faz match em qualquer filtro fornecido (user/brand/campaign/form)
   * - sempre inclui globais (sem filtros)
   *
   * Nota: aqui usamos OR em meta_query para combinar.
   */
  private static function query_insights($ctx = [], $limit = 20) {
    $limit = max(1, min(100, (int) $limit));

    $meta_or = [];

    // Globais: não têm nenhum dos metadados de filtro definidos (ou são 0/vazio)
    // Para não complicar, tratamos globais como:
    // user_id=0 AND brand_id=0 AND campaign_id=0 AND form_id=0
    // Se não existir meta, assumimos global, então incluímos também NOT EXISTS.
    $meta_or[] = [
      'relation' => 'AND',
      [
        'relation' => 'OR',
        ['key' => self::META_USER_ID, 'compare' => 'NOT EXISTS'],
        ['key' => self::META_USER_ID, 'value' => '0', 'compare' => '='],
        ['key' => self::META_USER_ID, 'value' => '', 'compare' => '='],
      ],
      [
        'relation' => 'OR',
        ['key' => self::META_BRAND_ID, 'compare' => 'NOT EXISTS'],
        ['key' => self::META_BRAND_ID, 'value' => '0', 'compare' => '='],
        ['key' => self::META_BRAND_ID, 'value' => '', 'compare' => '='],
      ],
      [
        'relation' => 'OR',
        ['key' => self::META_CAMPAIGN_ID, 'compare' => 'NOT EXISTS'],
        ['key' => self::META_CAMPAIGN_ID, 'value' => '0', 'compare' => '='],
        ['key' => self::META_CAMPAIGN_ID, 'value' => '', 'compare' => '='],
      ],
      [
        'relation' => 'OR',
        ['key' => self::META_FORM_ID, 'compare' => 'NOT EXISTS'],
        ['key' => self::META_FORM_ID, 'value' => '0', 'compare' => '='],
        ['key' => self::META_FORM_ID, 'value' => '', 'compare' => '='],
      ],
    ];

    // Match por user_id
    if (!empty($ctx['user_id'])) {
      $meta_or[] = [
        'key' => self::META_USER_ID,
        'value' => (string) (int) $ctx['user_id'],
        'compare' => '=',
      ];
    }

    // Match por brand_id
    if (!empty($ctx['brand_id'])) {
      $meta_or[] = [
        'key' => self::META_BRAND_ID,
        'value' => (string) (int) $ctx['brand_id'],
        'compare' => '=',
      ];
    }

    // Match por campaign_id
    if (!empty($ctx['campaign_id'])) {
      $meta_or[] = [
        'key' => self::META_CAMPAIGN_ID,
        'value' => (string) (int) $ctx['campaign_id'],
        'compare' => '=',
      ];
    }

    // Match por form_id
    if (!empty($ctx['form_id'])) {
      $meta_or[] = [
        'key' => self::META_FORM_ID,
        'value' => (string) (int) $ctx['form_id'],
        'compare' => '=',
      ];
    }

    $args = [
      'post_type' => 'twt_insight',
      'post_status' => 'publish',
      'numberposts' => $limit,
      'orderby' => 'meta_value_num',
      'order' => 'DESC',
      'meta_key' => self::META_PRIORITY,
      'meta_query' => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          ['key' => self::META_ACTIVE, 'compare' => 'NOT EXISTS'],
          ['key' => self::META_ACTIVE, 'value' => '1', 'compare' => '='],
          ['key' => self::META_ACTIVE, 'value' => 'yes', 'compare' => '='],
          ['key' => self::META_ACTIVE, 'value' => 'true', 'compare' => '='],
        ],
        array_merge(['relation' => 'OR'], $meta_or),
      ],
    ];

    $posts = get_posts($args);
    return $posts ? $posts : [];
  }

  private static function map_items($posts) {
    if (!$posts) return [];

    $out = [];

    foreach ($posts as $p) {
      $title = $p->post_title ? $p->post_title : '';
      $body = $p->post_content ? wp_strip_all_tags($p->post_content) : '';

      $out[] = [
        'id' => (int) $p->ID,
        'title' => $title,
        'body' => $body,
        'priority' => (int) get_post_meta($p->ID, self::META_PRIORITY, true),
        'active' => (string) get_post_meta($p->ID, self::META_ACTIVE, true),
      ];
    }

    // Dedupe básico por ID
    $seen = [];
    $final = [];
    foreach ($out as $it) {
      if (isset($seen[$it['id']])) continue;
      $seen[$it['id']] = true;
      $final[] = $it;
    }

    return $final;
  }
}

TWT_TCRM_Insights::boot();
