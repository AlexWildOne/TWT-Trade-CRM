<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Roles {

  const ROLE_BRAND = 'twt_brand';
  const ROLE_FIELD = 'twt_field_user';
  const ROLE_MANAGER = 'twt_trade_manager';

  /**
   * Roles e capabilities (recomendado chamar na ativação do plugin)
   */
  public static function add_roles_caps() {

    // 1) Garante roles
    if (!get_role(self::ROLE_BRAND)) {
      add_role(self::ROLE_BRAND, 'Marca (Cliente)', ['read' => true]);
    }

    if (!get_role(self::ROLE_FIELD)) {
      add_role(self::ROLE_FIELD, 'Utilizador Terreno', ['read' => true]);
    }

    if (!get_role(self::ROLE_MANAGER)) {
      add_role(self::ROLE_MANAGER, 'Gestor Trade (Interno)', ['read' => true]);
    }

    // 2) Garante caps para cada role (mesmo se já existia)
    $brand = get_role(self::ROLE_BRAND);
    if ($brand) {
      $brand->add_cap('twt_tcrm_view_brand_dashboard');
      $brand->add_cap('twt_tcrm_view_brand_reports');
      $brand->add_cap('twt_tcrm_export_brand_reports');
    }

    $field = get_role(self::ROLE_FIELD);
    if ($field) {
      $field->add_cap('twt_tcrm_submit_reports');
      $field->add_cap('twt_tcrm_view_own_reports');
      $field->add_cap('twt_tcrm_view_own_dashboard');
    }

    $manager = get_role(self::ROLE_MANAGER);
    if ($manager) {
      $manager->add_cap('twt_tcrm_manage_brands');
      $manager->add_cap('twt_tcrm_manage_campaigns');
      $manager->add_cap('twt_tcrm_manage_forms');
      $manager->add_cap('twt_tcrm_manage_assignments');
      $manager->add_cap('twt_tcrm_manage_locations');
      $manager->add_cap('twt_tcrm_view_all_reports');
      $manager->add_cap('twt_tcrm_export_all_reports');
      $manager->add_cap('twt_tcrm_manage_insights');
    }

    // 3) Administrator recebe tudo
    $admin = get_role('administrator');
    if ($admin) {
      foreach (self::all_caps() as $cap) {
        $admin->add_cap($cap);
      }
    }

    // 4) (Opcional) Editor - por defeito não damos nada
    // $editor = get_role('editor');
    // if ($editor) { ... }
  }

  /**
   * Algumas caps convém existir sempre em runtime (por exemplo, após migrações de site).
   * Não cria roles, só garante que o admin tem as caps.
   */
  public static function maybe_register_runtime_caps() {
    $admin = get_role('administrator');
    if (!$admin) return;

    foreach (self::all_caps() as $cap) {
      if (!$admin->has_cap($cap)) {
        $admin->add_cap($cap);
      }
    }
  }

  public static function all_caps() {
    return [
      // Gestão BO
      'twt_tcrm_manage_brands',
      'twt_tcrm_manage_campaigns',
      'twt_tcrm_manage_forms',
      'twt_tcrm_manage_assignments',
      'twt_tcrm_manage_insights',
      'twt_tcrm_manage_locations',

      // Visualização e export
      'twt_tcrm_view_all_reports',
      'twt_tcrm_export_all_reports',

      // Marca/cliente
      'twt_tcrm_view_brand_dashboard',
      'twt_tcrm_view_brand_reports',
      'twt_tcrm_export_brand_reports',

      // Utilizador terreno
      'twt_tcrm_submit_reports',
      'twt_tcrm_view_own_reports',
      'twt_tcrm_view_own_dashboard',
    ];
  }

  /**
   * Helpers de permissões para usar nos shortcodes e endpoints
   */
  public static function is_admin_like($user_id = 0) {
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
    if (!$user || !$user->exists()) return false;

    // administrator ou gestor interno
    return user_can($user, 'twt_tcrm_view_all_reports')
      || user_can($user, 'twt_tcrm_manage_forms')
      || user_can($user, 'twt_tcrm_manage_locations')
      || user_can($user, 'manage_options');
  }

  public static function is_brand_user($user_id = 0) {
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
    if (!$user || !$user->exists()) return false;

    return in_array(self::ROLE_BRAND, (array) $user->roles, true)
      || user_can($user, 'twt_tcrm_view_brand_dashboard');
  }

  public static function is_field_user($user_id = 0) {
    $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
    if (!$user || !$user->exists()) return false;

    return in_array(self::ROLE_FIELD, (array) $user->roles, true)
      || user_can($user, 'twt_tcrm_submit_reports');
  }
}