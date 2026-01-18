<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Security {

  /**
   * Verifica se o utilizador actual tem uma capability.
   * Se falhar, termina com wp_die().
   */
  public static function require_cap($cap, $message = 'Sem permissões.') {
    if (!current_user_can($cap)) {
      wp_die(esc_html($message));
    }
  }

  /**
   * Verifica um nonce num array (tipicamente $_POST ou $_GET).
   * Retorna boolean.
   */
  public static function verify_nonce($source, $field, $action) {
    if (!is_array($source)) return false;
    if (!isset($source[$field])) return false;

    $nonce = sanitize_text_field(wp_unslash($source[$field]));
    if (!$nonce) return false;

    return (bool) wp_verify_nonce($nonce, $action);
  }

  /**
   * Faz require do nonce, e em caso de falha:
   * - redirecciona para URL com erro, se fornecido
   * - senão, wp_die
   */
  public static function require_nonce_or_die($source, $field, $action, $redirect_url = '') {
    if (self::verify_nonce($source, $field, $action)) {
      return true;
    }

    if ($redirect_url) {
      wp_safe_redirect(esc_url_raw($redirect_url));
      exit;
    }

    wp_die('Falha de segurança (nonce).');
  }

  /**
   * Sanitiza um int vindo de request.
   */
  public static function get_int($source, $key, $default = 0) {
    if (!is_array($source) || !isset($source[$key])) return (int) $default;
    return (int) wp_unslash($source[$key]);
  }

  /**
   * Sanitiza um float vindo de request.
   */
  public static function get_float($source, $key, $default = 0.0) {
    if (!is_array($source) || !isset($source[$key])) return (float) $default;

    $raw = wp_unslash($source[$key]);
    $raw = is_string($raw) ? trim($raw) : $raw;

    if ($raw === '' || $raw === null) return (float) $default;

    // aceita vírgulas
    if (is_string($raw)) $raw = str_replace(',', '.', $raw);

    $val = is_numeric($raw) ? (float) $raw : (float) $default;
    return $val;
  }

  /**
   * Sanitiza um boolean vindo de request.
   * Aceita: '1', 1, true, 'true', 'on', 'yes'
   */
  public static function get_bool($source, $key, $default = false) {
    if (!is_array($source) || !isset($source[$key])) return (bool) $default;

    $raw = wp_unslash($source[$key]);

    if ($raw === true || $raw === 1 || $raw === '1') return true;
    if (is_string($raw)) {
      $v = strtolower(trim($raw));
      if (in_array($v, ['true', 'on', 'yes'], true)) return true;
      if (in_array($v, ['false', 'off', 'no', '0', ''], true)) return false;
    }

    return (bool) $default;
  }

  /**
   * Sanitiza um texto simples (uma linha).
   */
  public static function get_text($source, $key, $default = '') {
    if (!is_array($source) || !isset($source[$key])) return (string) $default;
    return sanitize_text_field(wp_unslash($source[$key]));
  }

  /**
   * Sanitiza textarea (multilinha).
   */
  public static function get_textarea($source, $key, $default = '') {
    if (!is_array($source) || !isset($source[$key])) return (string) $default;
    return sanitize_textarea_field(wp_unslash($source[$key]));
  }

  /**
   * Sanitiza URL.
   */
  public static function get_url($source, $key, $default = '') {
    if (!is_array($source) || !isset($source[$key])) return (string) $default;
    return esc_url_raw(wp_unslash($source[$key]));
  }

  /**
   * Sanitiza email.
   */
  public static function get_email($source, $key, $default = '') {
    if (!is_array($source) || !isset($source[$key])) return (string) $default;
    $email = sanitize_email(wp_unslash($source[$key]));
    return $email ? $email : (string) $default;
  }

  /**
   * Sanitiza array de ints vindo de request.
   */
  public static function get_int_array($source, $key) {
    if (!is_array($source) || !isset($source[$key]) || !is_array($source[$key])) return [];
    return array_values(array_filter(array_map('intval', $source[$key]), function ($v) {
      return $v > 0;
    }));
  }

  /**
   * Sanitiza array de strings (curto).
   */
  public static function get_text_array($source, $key) {
    if (!is_array($source) || !isset($source[$key]) || !is_array($source[$key])) return [];
    $out = [];
    foreach ($source[$key] as $v) {
      $out[] = sanitize_text_field(wp_unslash($v));
    }
    return array_values(array_filter($out, function ($v) {
      return $v !== '';
    }));
  }

  /**
   * Sanitiza uma chave (slug).
   */
  public static function get_key($source, $key, $default = '') {
    if (!is_array($source) || !isset($source[$key])) return (string) $default;
    return sanitize_key(wp_unslash($source[$key]));
  }

  /**
   * Redirecciona de forma segura para o referer do WP ou fallback.
   */
  public static function redirect_back($fallback = '') {
    $ref = '';

    if (isset($_REQUEST['_wp_http_referer'])) {
      $ref = wp_unslash($_REQUEST['_wp_http_referer']);
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
      $ref = (string) $_SERVER['HTTP_REFERER'];
    }

    $ref = $ref ? esc_url_raw($ref) : '';
    if (!$ref) {
      $ref = $fallback ? esc_url_raw($fallback) : home_url('/');
    }

    wp_safe_redirect($ref);
    exit;
  }

  /**
   * Redirecciona para um URL com um parâmetro de mensagem.
   */
  public static function redirect_with_msg($url, $key, $value) {
    $url = esc_url_raw($url);
    if (!$url) $url = home_url('/');
    $url = add_query_arg([$key => $value], $url);
    wp_safe_redirect($url);
    exit;
  }

  /**
   * Normaliza e sanitiza JSON (para schemas/layouts).
   * Retorna array, ou null se inválido.
   */
  public static function decode_json($json) {
    if ($json === null) return null;
    if ($json === '') return null;
    if (is_array($json)) return $json;

    $json = wp_unslash($json);
    if (!is_string($json)) return null;

    $arr = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    if (!is_array($arr)) return null;

    return $arr;
  }
}