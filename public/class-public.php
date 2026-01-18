<?php

if (!defined('ABSPATH')) {
  exit;
}

final class TWT_TCRM_Public {

  const QV_PICK = 'twt_tcrm_pick';
  const QV_LOCATION_ID = 'twt_tcrm_location_id';

  const AJAX_ACTION = 'twt_tcrm_pick';

  public static function boot() {
    // Shortcodes em builders/caches
    add_filter('widget_text', 'do_shortcode');
    add_filter('widget_text_content', 'do_shortcode');
    add_filter('the_content', [__CLASS__, 'maybe_do_shortcodes'], 9);

    // Rotas públicas (rewrite)
    add_action('init', [__CLASS__, 'register_rewrite_rules']);
    add_filter('query_vars', [__CLASS__, 'register_query_vars']);
    add_action('template_redirect', [__CLASS__, 'maybe_render_pick_page']);

    // Endpoint pick (AJAX)
    add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_pick']);
    add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_pick']);
  }

  public static function maybe_do_shortcodes($content) {
    if (is_string($content) && strpos($content, '[twt_') !== false) {
      return do_shortcode($content);
    }
    return $content;
  }

  /**
   * Helper para ler brand_id de user meta, e validar que é marca
   */
  public static function get_user_brand_id($user_id) {
    $brand_id = (int) get_user_meta((int) $user_id, 'twt_brand_id', true);
    if (!$brand_id) return 0;

    $p = get_post($brand_id);
    if (!$p || $p->post_type !== 'twt_brand') return 0;

    return $brand_id;
  }

  public static function require_login_message() {
    return '<p>Precisas de login.</p>';
  }

  /**
   * Rota pública:
   * /twt-pick/{location_id}/
   */
  public static function register_rewrite_rules() {
    // Garante que os query vars são reconhecidos em setups mais rígidos
    add_rewrite_tag('%' . self::QV_PICK . '%', '([0-9]+)');
    add_rewrite_tag('%' . self::QV_LOCATION_ID . '%', '([0-9]+)');

    add_rewrite_rule(
      '^twt-pick/([0-9]+)/?$',
      'index.php?' . self::QV_PICK . '=1&' . self::QV_LOCATION_ID . '=$matches[1]',
      'top'
    );
  }

  public static function register_query_vars($vars) {
    $vars[] = self::QV_PICK;
    $vars[] = self::QV_LOCATION_ID;
    return $vars;
  }

  public static function maybe_render_pick_page() {
    $is_pick = (int) get_query_var(self::QV_PICK);
    if ($is_pick !== 1) return;

    $location_id = (int) get_query_var(self::QV_LOCATION_ID);
    if (!$location_id) {
      self::render_simple_page('Local inválido.', 'Não foi possível identificar o local.');
      exit;
    }

    $loc = get_post($location_id);
    if (!$loc || $loc->post_type !== 'twt_location') {
      self::render_simple_page('Local não encontrado.', 'Este local não existe.');
      exit;
    }

    $status = (string) get_post_meta($location_id, 'twt_location_status', true);
    if ($status && $status !== 'active') {
      self::render_simple_page('Local inactivo.', 'Este local está inactivo.');
      exit;
    }

    $brand_id = (int) get_post_meta($location_id, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($location_id, 'twt_campaign_id', true);

    $brand_title = $brand_id ? get_the_title($brand_id) : '';
    $campaign_title = $campaign_id ? get_the_title($campaign_id) : '';

    $address = (string) get_post_meta($location_id, 'twt_location_address', true);

    $lat = get_post_meta($location_id, 'twt_location_lat', true);
    $lng = get_post_meta($location_id, 'twt_location_lng', true);

    $radius = (int) get_post_meta($location_id, 'twt_location_radius_m', true);
    if ($radius <= 0) $radius = 80;

    $email_prefill = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';

    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce(self::AJAX_ACTION);

    status_header(200);
    nocache_headers();

    echo '<!doctype html><html><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Picking, ' . esc_html($loc->post_title) . '</title>';

    echo '<style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f6f7f7;color:#111;}
      .wrap{max-width:760px;margin:0 auto;padding:18px;}
      .card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:16px;box-shadow:0 1px 0 rgba(0,0,0,.04);}
      h1{font-size:18px;margin:0 0 8px 0;}
      .meta{font-size:13px;opacity:.8;line-height:1.35;margin-bottom:10px;}
      .row{margin:12px 0;}
      label{display:block;font-size:13px;margin:0 0 6px 0;opacity:.9;}
      input[type="email"]{width:100%;border:1px solid rgba(0,0,0,.18);border-radius:12px;padding:12px 12px;font-size:16px;outline:none;}
      input[type="email"]:focus{border-color:rgba(0,0,0,.35);box-shadow:0 0 0 3px rgba(0,0,0,.08);}
      .btn{appearance:none;border:0;border-radius:12px;padding:12px 14px;font-size:15px;font-weight:800;cursor:pointer;background:#111;color:#fff;}
      .btn:disabled{opacity:.5;cursor:not-allowed;}
      .notice{border-radius:12px;padding:10px 12px;margin:12px 0;font-size:14px;}
      .ok{background:rgba(46,204,113,.14);border:1px solid rgba(46,204,113,.35);}
      .err{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.35);}
      .small{font-size:12px;opacity:.75;line-height:1.35;}
      code{background:rgba(0,0,0,.05);padding:2px 6px;border-radius:8px;}
    </style>';

    echo '</head><body><div class="wrap">';
    echo '<div class="card">';
    echo '<h1>' . esc_html($loc->post_title) . '</h1>';

    $meta_bits = [];
    if ($brand_title) $meta_bits[] = 'Marca: ' . esc_html($brand_title);
    if ($campaign_title) $meta_bits[] = 'Campanha: ' . esc_html($campaign_title);
    if ($address) $meta_bits[] = 'Morada: ' . esc_html($address);
    $meta_bits[] = 'Raio: ' . esc_html($radius) . ' m';

    echo '<div class="meta">' . implode('<br>', $meta_bits) . '</div>';

    if ($lat === '' || $lng === '') {
      echo '<div class="notice err">Este local ainda não tem coordenadas (lat/lng). Podes registar, mas sem validação de raio.</div>';
    }

    echo '<div id="twtNotice"></div>';

    echo '<div class="row">';
    echo '<label>Email do utilizador</label>';
    echo '<input id="twtEmail" type="email" placeholder="nome@empresa.com" value="' . esc_attr($email_prefill) . '">';
    echo '<div class="small">Dica: este link pode vir já com o email. Ex: <code>?email=nome@empresa.com</code></div>';
    echo '</div>';

    echo '<div class="row">';
    echo '<button id="twtBtn" class="btn">Fazer picking agora</button>';
    echo '</div>';

    echo '<div class="small">Vamos pedir a localização do teu dispositivo para validar se estás dentro do raio do local.</div>';

    echo '</div></div>';

    echo '<script>
      (function(){
        var ajaxUrl = ' . json_encode($ajax_url) . ';
        var nonce = ' . json_encode($nonce) . ';
        var locationId = ' . (int) $location_id . ';

        var btn = document.getElementById("twtBtn");
        var emailEl = document.getElementById("twtEmail");
        var notice = document.getElementById("twtNotice");

        function show(kind, msg){
          notice.innerHTML = "<div class=\\"notice " + (kind === "ok" ? "ok" : "err") + "\\">" + msg + "</div>";
        }

        function post(data){
          var fd = new FormData();
          Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });

          return fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: fd
          }).then(function(r){ return r.json(); });
        }

        function doPick(lat, lng, acc){
          btn.disabled = true;

          var email = (emailEl.value || "").trim();

          post({
            action: ' . json_encode(self::AJAX_ACTION) . ',
            _ajax_nonce: nonce,
            location_id: locationId,
            email: email,
            lat: (lat !== null && lat !== undefined) ? String(lat) : "",
            lng: (lng !== null && lng !== undefined) ? String(lng) : "",
            accuracy: (acc !== null && acc !== undefined) ? String(acc) : ""
          }).then(function(res){
            btn.disabled = false;

            if (!res || !res.success) {
              var m = (res && res.data && res.data.message) ? res.data.message : "Falha ao registar.";
              show("err", m);
              return;
            }

            var d = res.data || {};
            var action = d.pick_action || "";
            var inside = (d.within_radius === 1) ? "Sim" : (d.within_radius === 0 ? "Não" : "Não calculado");
            var dist = (d.distance_m !== null && d.distance_m !== undefined) ? (String(d.distance_m) + " m") : "-";

            var nice = (action === "checkout") ? "Checkout registado." : "Check-in registado.";
            var extra = "<br><span class=\\"small\\">Dentro do raio: " + inside + ", Distância: " + dist + "</span>";
            show("ok", nice + extra);
          }).catch(function(){
            btn.disabled = false;
            show("err", "Erro de rede ao registar.");
          });
        }

        btn.addEventListener("click", function(){
          notice.innerHTML = "";

          if (!navigator.geolocation) {
            doPick(null, null, null);
            return;
          }

          btn.disabled = true;

          navigator.geolocation.getCurrentPosition(function(pos){
            btn.disabled = false;
            var c = pos && pos.coords ? pos.coords : null;
            doPick(c ? c.latitude : null, c ? c.longitude : null, c ? c.accuracy : null);
          }, function(){
            btn.disabled = false;
            // Se o user recusar, regista na mesma, mas sem validação
            doPick(null, null, null);
          }, {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
          });
        });

      })();
    </script>';

    echo '</body></html>';
    exit;
  }

  private static function render_simple_page($title, $message) {
    status_header(200);
    nocache_headers();
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . esc_html($title) . '</title>';
    echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f6f7f7;color:#111;}
      .wrap{max-width:760px;margin:0 auto;padding:18px;}
      .card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:16px;}
      h1{font-size:18px;margin:0 0 8px 0;}
      .small{font-size:13px;opacity:.8;line-height:1.35;}
    </style></head><body><div class="wrap"><div class="card">';
    echo '<h1>' . esc_html($title) . '</h1>';
    echo '<div class="small">' . esc_html($message) . '</div>';
    echo '</div></div></body></html>';
  }

  /**
   * AJAX: regista pick (checkin/checkout) com toggle.
   */
  public static function ajax_pick() {
    if (!check_ajax_referer(self::AJAX_ACTION, '_ajax_nonce', false)) {
      wp_send_json_error(['message' => 'Nonce inválido.'], 403);
    }

    $location_id = isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0;
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

    $lat = isset($_POST['lat']) ? self::to_float_or_null(wp_unslash($_POST['lat'])) : null;
    $lng = isset($_POST['lng']) ? self::to_float_or_null(wp_unslash($_POST['lng'])) : null;
    $accuracy = isset($_POST['accuracy']) ? self::to_float_or_null(wp_unslash($_POST['accuracy'])) : null;

    if (!$location_id) {
      wp_send_json_error(['message' => 'Local inválido.'], 400);
    }

    $loc = get_post($location_id);
    if (!$loc || $loc->post_type !== 'twt_location') {
      wp_send_json_error(['message' => 'Local não encontrado.'], 404);
    }

    $status = (string) get_post_meta($location_id, 'twt_location_status', true);
    if ($status && $status !== 'active') {
      wp_send_json_error(['message' => 'Local inactivo.'], 400);
    }

    // Determinar o user
    $user_id = 0;

    if (is_user_logged_in()) {
      $user_id = get_current_user_id();
      // se não enviou email, usamos o do utilizador autenticado (para log)
      if (!$email) {
        $u = wp_get_current_user();
        if ($u && $u->exists()) $email = (string) $u->user_email;
      }
    } else {
      if (!$email) {
        wp_send_json_error(['message' => 'Email obrigatório.'], 400);
      }
      $user = get_user_by('email', $email);
      if ($user && !empty($user->ID)) {
        $user_id = (int) $user->ID;
      } else {
        wp_send_json_error(['message' => 'Email não corresponde a nenhum utilizador.'], 403);
      }
    }

    // Verificar atribuição à loja (segurança)
    if (!self::user_is_assigned_to_location($user_id, $location_id)) {
      wp_send_json_error(['message' => 'Não estás atribuído a este local.'], 403);
    }

    $brand_id = (int) get_post_meta($location_id, 'twt_brand_id', true);
    $campaign_id = (int) get_post_meta($location_id, 'twt_campaign_id', true);

    // Toggle
    $pick_action = self::determine_next_pick_action($location_id, $user_id);

    // Cálculo distância e within_radius (se houver coords do local e coords do user)
    $loc_lat = get_post_meta($location_id, 'twt_location_lat', true);
    $loc_lng = get_post_meta($location_id, 'twt_location_lng', true);

    $radius = (int) get_post_meta($location_id, 'twt_location_radius_m', true);
    if ($radius <= 0) $radius = 80;

    $distance_m = null;
    $within = null;

    if ($loc_lat !== '' && $loc_lng !== '' && $lat !== null && $lng !== null) {
      $distance_m = self::distance_meters((float) $loc_lat, (float) $loc_lng, (float) $lat, (float) $lng);
      $within = ($distance_m <= $radius) ? 1 : 0;
    }

    if (!method_exists('TWT_TCRM_DB', 'table_location_picks')) {
      wp_send_json_error(['message' => 'Tabela de picks ainda não existe na DB.'], 500);
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_location_picks();

    $meta = [
      'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
      'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
    ];

    // IMPORTANTE (alinhado com DB 0.3.0):
    // - within_radius pode ser NULL (não calculado)
    // - wpdb formats: usar %s para NULLables (lat/lng/etc), porque %f/%d convertem NULL -> 0
    $ins = $wpdb->insert(
      $t,
      [
        'location_id' => $location_id,
        'brand_id' => $brand_id ? $brand_id : null,
        'campaign_id' => $campaign_id ? $campaign_id : null,
        'user_id' => $user_id ? $user_id : null,
        'user_email' => $email ? $email : null,

        'pick_action' => $pick_action,
        'pick_source' => 'web',
        'picked_at' => current_time('mysql'),

        'lat' => ($lat === null) ? null : (string) $lat,
        'lng' => ($lng === null) ? null : (string) $lng,
        'accuracy_m' => ($accuracy === null) ? null : (int) round($accuracy),

        'within_radius' => $within,
        'distance_m' => ($distance_m === null) ? null : (int) round($distance_m),

        'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
      ],
      [
        '%d', // location_id
        '%d', // brand_id
        '%d', // campaign_id
        '%d', // user_id
        '%s', // user_email

        '%s', // pick_action
        '%s', // pick_source
        '%s', // picked_at

        '%s', // lat (nullable)
        '%s', // lng (nullable)
        '%d', // accuracy_m (nullable ok)

        '%d', // within_radius (nullable ok)
        '%d', // distance_m (nullable ok)

        '%s', // meta_json
      ]
    );

    if (!$ins) {
      wp_send_json_error(['message' => 'Falha ao gravar o pick.'], 500);
    }

    wp_send_json_success([
      'pick_action' => $pick_action,
      'within_radius' => $within,
      'distance_m' => ($distance_m === null) ? null : (int) round($distance_m),
      'radius_m' => $radius,
    ]);
  }

  private static function user_is_assigned_to_location($user_id, $location_id) {
    $user_id = (int) $user_id;
    $location_id = (int) $location_id;

    // Admin like pode sempre
    if (class_exists('TWT_TCRM_Roles') && method_exists('TWT_TCRM_Roles', 'is_admin_like')) {
      if (TWT_TCRM_Roles::is_admin_like($user_id)) return true;
    }

    if (!method_exists('TWT_TCRM_DB', 'table_location_assignments')) {
      return false;
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_location_assignments();

    $found = $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $t WHERE user_id = %d AND location_id = %d AND active = 1 LIMIT 1",
      $user_id,
      $location_id
    ));

    return !empty($found);
  }

  private static function determine_next_pick_action($location_id, $user_id) {
    $location_id = (int) $location_id;
    $user_id = (int) $user_id;

    if (!method_exists('TWT_TCRM_DB', 'table_location_picks')) {
      return 'checkin';
    }

    global $wpdb;
    $t = TWT_TCRM_DB::table_location_picks();

    $last = $wpdb->get_var($wpdb->prepare(
      "SELECT pick_action FROM $t
       WHERE location_id = %d AND user_id = %d
       ORDER BY id DESC
       LIMIT 1",
      $location_id,
      $user_id
    ));

    $last = is_string($last) ? strtolower($last) : '';
    if ($last === 'checkin') return 'checkout';
    return 'checkin';
  }

  private static function distance_meters($lat1, $lng1, $lat2, $lng2) {
    $r = 6371000; // metros
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dphi = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lng2 - $lng1);

    $a = sin($dphi / 2) * sin($dphi / 2) +
      cos($phi1) * cos($phi2) *
      sin($dl / 2) * sin($dl / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
  }

  private static function to_float_or_null($raw) {
    if ($raw === null || $raw === '') return null;
    $s = is_string($raw) ? trim($raw) : (string) $raw;
    if ($s === '') return null;

    $s = str_replace(',', '.', $s);
    $s = preg_replace('/[^0-9\.\-]/', '', $s);

    if ($s === '' || $s === '-' || $s === '.' || $s === '-.') return null;

    $v = is_numeric($s) ? (float) $s : null;
    return is_finite($v) ? $v : null;
  }
}
