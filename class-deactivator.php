<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Deactivator {

  public static function deactivate() {
    flush_rewrite_rules();
  }
}
