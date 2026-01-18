<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Deactivator {

  public static function deactivate() {
    // Opcional/defensivo: garantir que as rules do plugin existem antes do flush
    if (defined('TWT_TCRM_PLUGIN_DIR')) {
      $public = TWT_TCRM_PLUGIN_DIR . 'public/class-public.php';
      if (file_exists($public)) {
        require_once $public;
      }
    }

    flush_rewrite_rules();
  }
}