<?php

/**
 * Plugin Name: Nuki API
 * Description: Gestión automática del código semanal de clientes
 * Version: 6.1
 * Author: Alejandro Gómez de Lara Medina
 */

// CONFIGURACIÓN
define('NUKI_TOKEN', '6920200e78a5800ebc73c1149d0de58ee41cf8d278e0eccd09ca4bcb90f2fa0b7c1478f6277c083d');
define('SMARTLOCK_ID', 22693880943);
define('EHOLO_BASE', 'https://app.eholo.health');
define('EHOLO_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI2IiwianRpIjoiNmYxOGEyM2IxZmQxOWUzZGNmZmQzMzZhZTM2NzZjMTk2ODdjZTBhZWNhZjk0M2EzNDM4ZjI5Y2I2ZTlhYTEwN2FlNzE3MTIzNGM4OTA5OTciLCJpYXQiOjE3NTA5MzcxMDMuODA5Mzg4LCJuYmYiOjE3NTA5MzcxMDMuODA5MzksImV4cCI6MTc4MjQ3MzEwMy44MDA2NzYsInN1YiI6IjEzNzY5Iiwic2NvcGVzIjpbXX0.iwbl4GJxflfSxP_NYprwpEd4vGzwwDPYoQsy8hQeREDfp-ciaUu1MehtyNz-CstAyx9wJuP6NgTb-IGDN-mp4ZRy304rKddqLo-pVqIYXfTZ1Ha82Ax86QjWrkdM13WqFFMbq_UpGVZesPko_6_ZgbbScd40w_dKdasPChvxJf18_yJfQQA1Gpa31AwwdxKuxV0xDL7IYydNnRcU2QQw0rgmmUMv5cRuaoH_9yVovKR_E9HVLGXOHU0N-ZqZXiflM34PVJM_uRhIVUGuoMBbaWm9fv6Q_zaHiq03PyjvqRoX_gaUeDs22v1j6juo01dJNYVX_MgsGamTYohyktt2uikhJYi0BV4Ao6EB-_zxKjmVb2rupu1wclEdadPg_aEaFSLlPP7c35y47YYgu_O1bGxbjxwxWqeUv_PYn7VzdWt3IZPINUgXs3uY4LXfVIwyamjusZeDpn1IVN1s5J1ibLIwkvL2YFkHlZcmkRvsRWudNPQQyreXVXG9sIogeypb-4i2YyzO1VejHE1g_K2JW9zfD1XALCxEeEPEKhMnWKTXu_v-0iCoTKpgvPzKn4mPyyjpmsSEtgmxpfFy7YRqTpQNoKcjzOaTPiJQm0Kt9x_GCEosmbv67niLPqf6fEmbyG5HMjRSj1skvdcDQDrGCycREJ4OScaS1m7lLnGwixg'); // Bearer token
define('EHOLO_ORG_ID', 11091); // tu organization id (puedes obtenerlo con /api/get-organizations)


/* ================================
   1. ENDPOINT PARA MOSTRAR EL CÓDIGO
================================ */
add_action('rest_api_init', function () {
  register_rest_route('nuki/v1', '/code', [
    'methods' => 'GET',
    'callback' => 'get_nuki_code',
  ]);
});

function get_nuki_code()
{
  $response = wp_remote_get(
    'https://api.nuki.io/smartlock/auth',
    ['headers' => ['Authorization' => 'Bearer ' . NUKI_TOKEN]]
  );

  if (is_wp_error($response)) {
    return ['error' => 'Error conectando con Nuki'];
  }

  $auths = json_decode(wp_remote_retrieve_body($response), true);

  foreach ($auths as $auth) {
    if ($auth['type'] == 13 && strpos($auth['name'], 'CLIENTES -') === 0) {
      return [
        'code'  => $auth['code'],
        'name'  => $auth['name'],
        'from'  => $auth['allowedFromDate'] ?? null,
        'until' => $auth['allowedUntilDate'] ?? null
      ];
    }
  }

  return ['error' => 'No hay código de clientes'];
}


/* ================================
   2. GENERAR CÓDIGO VÁLIDO
================================ */
function generar_codigo_cliente()
{
  do {
    $code = '';
    for ($i = 0; $i < 6; $i++) {
      $code .= rand(1, 9);
    }
  } while (str_starts_with($code, '12'));

  return $code;
}


/* ================================
   3. BORRAR CÓDIGOS ANTIGUOS
================================ */
function borrar_codigos_clientes()
{

  $response = wp_remote_get(
    'https://api.nuki.io/smartlock/auth',
    ['headers' => ['Authorization' => 'Bearer ' . NUKI_TOKEN]]
  );

  if (is_wp_error($response)) return;

  $auths = json_decode(wp_remote_retrieve_body($response), true);
  $ids = [];

  foreach ($auths as $auth) {
    if ($auth['type'] == 13 && strpos($auth['name'], 'CLIENTES -') === 0) {
      $ids[] = $auth['id'];
    }
  }

  if (empty($ids)) return;

  wp_remote_request(
    'https://api.nuki.io/smartlock/auth',
    [
      'method'  => 'DELETE',
      'headers' => [
        'Authorization' => 'Bearer ' . NUKI_TOKEN,
        'Content-Type'  => 'application/json'
      ],
      'body' => json_encode($ids)
    ]
  );
}


/* ================================
   4. CREAR NUEVO CÓDIGO (7 DÍAS)
================================ */
function crear_codigo_cliente()
{

  $codigo = generar_codigo_cliente();
  $nombre = 'CLIENTES - ' . date('d/m');

  $desde = gmdate('Y-m-d\TH:i:s.000\Z');
  $hasta = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+7 days'));

  return wp_remote_request(
    'https://api.nuki.io/smartlock/auth',
    [
      'method' => 'PUT',
      'headers' => [
        'Authorization' => 'Bearer ' . NUKI_TOKEN,
        'Content-Type' => 'application/json'
      ],
      'body' => json_encode([
        'name' => $nombre,
        'type' => 13,
        'code' => $codigo,
        'allowedFromDate' => $desde,
        'allowedUntilDate' => $hasta,
        'allowedWeekDays' => 127,
        'smartlockIds' => [SMARTLOCK_ID]
      ])
    ]
  );
}


/* ================================
   5. CRON EXTERNO (?nuki_cron=rotate)
================================ */
add_action('init', function () {
  if (isset($_GET['nuki_cron']) && $_GET['nuki_cron'] === 'rotate') {

    echo 'CRON OK - Ejecutando rotación';
    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    } else {
      flush();
    }

    borrar_codigos_clientes();
    crear_codigo_cliente();
    exit;
  }

  // NUEVO: CRON EXTERNO PARA RECORDATORIOS (?nuki_cron=reminders)
  if (isset($_GET['nuki_cron']) && $_GET['nuki_cron'] === 'reminders') {

    echo 'CRON OK - Enviando recordatorios (24h antes)';
    if (function_exists('fastcgi_finish_request')) {
      fastcgi_finish_request();
    } else {
      flush();
    }

    nuki_run_eholo_reminders(false);
    exit;
  }
});


/* ================================
   6. SISTEMA DE TOKENS TEMPORALES
================================ */

// Crear token temporal (48h por defecto)
function nuki_crear_token_temporal($horas = 48)
{
  $token = wp_generate_password(32, false);
  set_transient('nuki_token_' . $token, true, $horas * HOUR_IN_SECONDS);
  return $token;
}

// Validar token
function nuki_validar_token()
{
  if (!isset($_GET['access'])) return false;
  return get_transient('nuki_token_' . $_GET['access']) !== false;
}


/* ================================
   7. SHORTCODE SEGURO PARA MOSTRAR EL CÓDIGO
================================ */
add_shortcode('nuki_codigo_seguro', function () {

  if (!nuki_validar_token()) {
    return '<h2>Este enlace ha caducado o no es válido.</h2>';
  }

  return '
    <h1 id="nuki-code">Cargando...</h1>

    <script>
    fetch("/wp-json/nuki/v1/code")
      .then(res => res.json())
      .then(data => {
        if (data.code) {
          document.getElementById("nuki-code").innerText = data.code;
        } else {
          document.getElementById("nuki-code").innerText = "No hay código activo";
        }
      })
      .catch(() => {
        document.getElementById("nuki-code").innerText = "Error cargando el código";
      });
    </script>
  ';
});

function eholo_request($endpoint, $method = 'POST', $body = null)
{
  $args = [
    'method'  => $method,
    'headers' => [
      'Authorization' => 'Bearer ' . EHOLO_TOKEN,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
    ],
    'timeout' => 20,
    'sslverify' => false,          // <-- parche cURL error 60 en hostings
    'httpversion' => '1.1',
    'user-agent'  => 'curl/7.x (WordPress)',
  ];

  if ($body !== null) {
    $args['body'] = wp_json_encode($body);
  }

  $url = rtrim(EHOLO_BASE, '/') . $endpoint;
  $res = wp_remote_request($url, $args);

  if (is_wp_error($res)) return $res;

  $code = wp_remote_retrieve_response_code($res);
  $raw  = wp_remote_retrieve_body($res);
  $json = json_decode($raw, true);

  if ($code < 200 || $code >= 300) {
    return new WP_Error('eholo_http', 'Eholo HTTP error: ' . $code, [
      'raw' => $raw,
      'json' => $json,
      'headers' => wp_remote_retrieve_headers($res),
    ]);
  }

  // Si Eholo devuelve algo que no es JSON válido, te lo devuelvo en crudo
  if ($json === null && $raw !== '' && json_last_error() !== JSON_ERROR_NONE) {
    return new WP_Error('eholo_json', 'Eholo JSON parse error: ' . json_last_error_msg(), [
      'raw' => $raw,
      'code' => $code,
    ]);
  }

  return $json;
}


/* ================================
   8. LOG ERRORES WP_MAIL
================================ */

$GLOBALS['nuki_last_mail_error'] = null;

add_action('wp_mail_failed', function ($wp_error) {
  $GLOBALS['nuki_last_mail_error'] = [
    'message' => $wp_error->get_error_message(),
    'data'    => $wp_error->get_error_data(),
  ];
});


/* ================================
   9. RECORDATORIOS EHOLO (24H ANTES)
================================ */

function nuki_reminder_sent($session_id)
{
  return get_transient('nuki_reminded_' . intval($session_id)) === '1';
}

function nuki_mark_reminder_sent($session_id)
{
  set_transient('nuki_reminded_' . intval($session_id), '1', 7 * DAY_IN_SECONDS);
}

function nuki_format_from_madrid($fromUtc)
{
  try {
    $dt = new DateTime($fromUtc); // detecta Z como UTC
    $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
    return $dt->format('d/m/Y H:i');
  } catch (Exception $e) {
    return $fromUtc;
  }
}

function nuki_build_reminder_email($session, $link)
{
  $subject = 'Código de acceso';

  $message =
    "Hola,\n\n" .
    "Te facilitamos el código de entrada al centro:\n" .
    "{$link}\n\n" .
    "Gracias.\n";

  return [$subject, $message];
}


function nuki_run_eholo_reminders($dry_run = true)
{
  $from_api = gmdate('Y-m-d H:i:s', time() + 24 * HOUR_IN_SECONDS);
  $to_api   = gmdate('Y-m-d H:i:s', time() + 25 * HOUR_IN_SECONDS);

  $payload = [
    'from' => $from_api,
    'to' => $to_api,
    'organization' => EHOLO_ORG_ID,
    'page' => 1,
    'perPage' => 50,
    'relationsSelectType' => 0,
    'pending' => 1,
    'done' => 0,
    'canceled' => 0,
    'online' => 0,
    'presential' => 1,
  ];

  $res = eholo_request('/api/get-sessions', 'POST', $payload);
  if (is_wp_error($res)) {
    return [
      'ok' => false,
      'from' => $from_api,
      'to' => $to_api,
      'error' => $res->get_error_message(),
      'details' => $res->get_error_data(),
      'items' => [],
    ];
  }

  $sessions = $res['collection'] ?? [];
  $items = [];

  foreach ($sessions as $s) {
    $session_id = $s['id'] ?? null;
    if (!$session_id) continue;

    $email = $s['relation']['email'] ?? '';
    if (!is_email($email)) {
      $items[] = [
        'session_id' => $session_id,
        'to' => $email,
        'status' => 'SKIP (sin email válido)',
        'when' => isset($s['from']) ? $s['from'] : null,
      ];
      continue;
    }

    if (nuki_reminder_sent($session_id)) {
      $items[] = [
        'session_id' => $session_id,
        'to' => $email,
        'status' => 'SKIP (ya enviado)',
        'when' => isset($s['from']) ? $s['from'] : null,
      ];
      continue;
    }

    $token = nuki_crear_token_temporal(48);
    $link  = home_url('/a9f3k2m8zq1w7x5r?access=' . $token);

    list($subject, $message) = nuki_build_reminder_email($s, $link);

    if ($dry_run) {
      $items[] = [
        'session_id' => $session_id,
        'to' => $email,
        'status' => 'DRY-RUN (se enviaría)',
        'when' => isset($s['from']) ? $s['from'] : null,
        'subject' => $subject,
        'message' => $message,
        'link' => $link,
      ];
      continue;
    }

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $GLOBALS['nuki_last_mail_error'] = null;

    $sent = wp_mail($email, $subject, $message, $headers);

    if ($sent) {
      nuki_mark_reminder_sent($session_id);
      $items[] = [
        'session_id' => $session_id,
        'to' => $email,
        'status' => 'SENT',
        'when' => isset($s['from']) ? $s['from'] : null,
        'subject' => $subject,
        'link' => $link,
      ];
    } else {
      $items[] = [
        'session_id' => $session_id,
        'to' => $email,
        'status' => 'ERROR (wp_mail)',
        'when' => isset($s['from']) ? $s['from'] : null,
        'subject' => $subject,
        'link' => $link,
        'mail_error' => $GLOBALS['nuki_last_mail_error'],
      ];
    }
  }

  return [
    'ok' => true,
    'from' => $from_api,
    'to' => $to_api,
    'total_sessions' => $res['total_sessions'] ?? null,
    'items' => $items,
  ];
}


/* ================================
   10. BOTÓN MANUAL (SOLO PRUEBAS)
================================ */
add_action('admin_menu', function () {
  add_menu_page(
    'Nuki Test',
    'Nuki Test',
    'manage_options',
    'nuki-test',
    'nuki_test_page'
  );
});

function nuki_test_page()
{
  if (isset($_POST['run'])) {
    borrar_codigos_clientes();
    $response = crear_codigo_cliente();

    echo "<pre>";
    echo "Código creado manualmente\n";
    echo "HTTP: " . wp_remote_retrieve_response_code($response) . "\n";
    echo wp_remote_retrieve_body($response);
    echo "</pre>";
  }

  if (isset($_POST['token'])) {
    $token = nuki_crear_token_temporal(48);
    echo "<p><strong>Enlace generado:</strong><br>";
    echo home_url('/a9f3k2m8zq1w7x5r?access=' . $token);
    echo "</p>";
  }

  // NUEVO: Listar sesiones próximas 2 semanas -> session_id + email
  if (isset($_POST['list_next_2weeks_emails'])) {

    $from_api = gmdate('Y-m-d H:i:s', time());
    $to_api   = gmdate('Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS);

    $payload = [
      'from' => $from_api,
      'to' => $to_api,
      'organization' => EHOLO_ORG_ID,
      'page' => 1,
      'perPage' => 50,
      'relationsSelectType' => 0,
      'pending' => 1,
      'done' => 0,
      'canceled' => 0,
      'online' => 0,
      'presential' => 1,
    ];

    $res = eholo_request('/api/get-sessions', 'POST', $payload);

    echo "<h3>Sesiones próximas 2 semanas (presenciales) → Session ID / Email</h3>";
    echo "<p><strong>Ventana Eholo (UTC):</strong> " . esc_html($from_api) . " → " . esc_html($to_api) . "</p>";

    if (is_wp_error($res)) {
      echo "<pre style='background:#fff3f3;border:1px solid #f0b4b4;padding:10px;max-width:900px;overflow:auto;'>";
      echo "ERROR:\n" . esc_html($res->get_error_message()) . "\n\n";
      echo "DETALLES:\n";
      print_r($res->get_error_data());
      echo "</pre>";
    } else {
      $sessions = $res['collection'] ?? [];

      if (empty($sessions)) {
        echo "<p>No hay sesiones en las próximas 2 semanas.</p>";
      } else {
        echo "<table style='border-collapse:collapse; width:100%; max-width:900px;'>";
        echo "<thead><tr>";
        echo "<th style='text-align:left;border:1px solid #ddd;padding:8px;'>Session ID</th>";
        echo "<th style='text-align:left;border:1px solid #ddd;padding:8px;'>Email cliente</th>";
        echo "<th style='text-align:left;border:1px solid #ddd;padding:8px;'>Fecha (Madrid)</th>";
        echo "</tr></thead><tbody>";

        foreach ($sessions as $s) {
          $sid = $s['id'] ?? '';
          $email = $s['relation']['email'] ?? '';
          $from = $s['from'] ?? '';
          $when_madrid = $from ? nuki_format_from_madrid($from) : '';

          echo "<tr>";
          echo "<td style='border:1px solid #ddd;padding:8px;'>" . esc_html((string)$sid) . "</td>";
          echo "<td style='border:1px solid #ddd;padding:8px;'>" . esc_html((string)$email) . "</td>";
          echo "<td style='border:1px solid #ddd;padding:8px;'>" . esc_html((string)$when_madrid) . "</td>";
          echo "</tr>";
        }

        echo "</tbody></table>";
      }
    }
  }

  // NUEVO: Simular recordatorios reales (NO envía, muestra el email real que se enviaría)
  if (isset($_POST['simulate_reminders'])) {
    $result = nuki_run_eholo_reminders(true);

    echo "<h3>Simulación recordatorios (24h antes)</h3>";
    echo "<p><strong>Ventana Eholo (UTC):</strong> " . esc_html($result['from']) . " → " . esc_html($result['to']) . "</p>";

    if (!$result['ok']) {
      echo "<pre style='background:#fff3f3;border:1px solid #f0b4b4;padding:10px;max-width:900px;overflow:auto;'>";
      echo "Error:\n" . print_r($result['error'], true) . "\n\nDetalles:\n" . print_r($result['details'], true);
      echo "</pre>";
    } else {
      echo "<p><strong>Total sessions (Eholo):</strong> " . esc_html((string)($result['total_sessions'] ?? '')) . "</p>";

      if (empty($result['items'])) {
        echo "<p>No hay sesiones en la ventana (now+24h → now+25h).</p>";
      } else {
        foreach ($result['items'] as $it) {
          echo "<hr>";
          echo "<p><strong>Session ID:</strong> " . esc_html((string)$it['session_id']) . "</p>";
          echo "<p><strong>Estado:</strong> " . esc_html($it['status']) . "</p>";
          echo "<p><strong>Para:</strong> " . esc_html((string)($it['to'] ?? '')) . "</p>";
          if (!empty($it['when'])) {
            echo "<p><strong>Starts (UTC):</strong> " . esc_html((string)$it['when']) . "</p>";
            echo "<p><strong>Starts (Europe/Madrid):</strong> " . esc_html(nuki_format_from_madrid((string)$it['when'])) . "</p>";
          }
          if (!empty($it['subject'])) {
            echo "<p><strong>Asunto:</strong> " . esc_html($it['subject']) . "</p>";
          }
          if (!empty($it['link'])) {
            echo "<p><strong>Link:</strong> " . esc_html($it['link']) . "</p>";
          }
          if (!empty($it['message'])) {
            echo "<pre style='background:#f7f7f7;border:1px solid #ddd;padding:10px;max-width:900px;overflow:auto;'>";
            echo $it['message'];
            echo "</pre>";
          }
        }
      }
    }
  }

  // NUEVO: Enviar recordatorios reales ahora (usa emails reales)
  if (isset($_POST['send_reminders_now'])) {
    $result = nuki_run_eholo_reminders(false);

    echo "<h3>Envío recordatorios (24h antes)</h3>";
    echo "<p><strong>Ventana Eholo (UTC):</strong> " . esc_html($result['from']) . " → " . esc_html($result['to']) . "</p>";

    if (!$result['ok']) {
      echo "<pre style='background:#fff3f3;border:1px solid #f0b4b4;padding:10px;max-width:900px;overflow:auto;'>";
      echo "Error:\n" . print_r($result['error'], true) . "\n\nDetalles:\n" . print_r($result['details'], true);
      echo "</pre>";
    } else {
      $sentCount = 0;
      $skipCount = 0;
      $errCount  = 0;

      foreach ($result['items'] as $it) {
        if ($it['status'] === 'SENT') $sentCount++;
        else if (strpos($it['status'], 'SKIP') === 0) $skipCount++;
        else if (strpos($it['status'], 'ERROR') === 0) $errCount++;
      }

      echo "<p><strong>Resumen:</strong> enviados={$sentCount}, omitidos={$skipCount}, errores={$errCount}</p>";

      foreach ($result['items'] as $it) {
        echo "<hr>";
        echo "<p><strong>Session ID:</strong> " . esc_html((string)$it['session_id']) . "</p>";
        echo "<p><strong>Estado:</strong> " . esc_html($it['status']) . "</p>";
        echo "<p><strong>Para:</strong> " . esc_html((string)($it['to'] ?? '')) . "</p>";
        if (!empty($it['subject'])) echo "<p><strong>Asunto:</strong> " . esc_html($it['subject']) . "</p>";
        if (!empty($it['link'])) echo "<p><strong>Link:</strong> " . esc_html($it['link']) . "</p>";

        if (!empty($it['mail_error'])) {
          echo "<pre style='background:#fff3f3;border:1px solid #f0b4b4;padding:10px;max-width:900px;overflow:auto;'>";
          echo "wp_mail_failed:\n" . print_r($it['mail_error'], true);
          echo "</pre>";
        }
      }
    }
  }

  // Enviar correo de prueba (MISMO email real que recordatorios)
  if (isset($_POST['send_test_email'])) {
    $to = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
    $test_when = isset($_POST['test_when']) ? sanitize_text_field($_POST['test_when']) : '';

    if (empty($to) || !is_email($to)) {
      echo "<p style='color:red;'><strong>Error:</strong> Email no válido.</p>";
    } else {

      // Link real (igual que recordatorios)
      $token = nuki_crear_token_temporal(48);
      $link  = home_url('/a9f3k2m8zq1w7x5r?access=' . $token);

      // Fecha/hora opcional para simular la sesión en el email
      // datetime-local -> "YYYY-MM-DDTHH:MM" (hora Europe/Madrid)
      if (!empty($test_when)) {
        try {
          $dt = new DateTime(str_replace('T', ' ', $test_when), new DateTimeZone('Europe/Madrid'));
          $dt->setTimezone(new DateTimeZone('UTC'));
          $from_utc = $dt->format('Y-m-d\TH:i:s.000\Z');
        } catch (Exception $e) {
          $from_utc = gmdate('Y-m-d\TH:i:s.000\Z', time() + 24 * HOUR_IN_SECONDS);
        }
      } else {
        $from_utc = gmdate('Y-m-d\TH:i:s.000\Z', time() + 24 * HOUR_IN_SECONDS);
      }

      // “Sesión fake” SOLO para construir el email real
      $fake_session = [
        'from' => $from_utc,
        'relation' => [
          'name' => 'Hola',
          'last_name' => '',
          'email' => $to,
        ],
      ];

      // Email real (mismo subject + body que recordatorios)
      list($subject, $message) = nuki_build_reminder_email($fake_session, $link);

      $headers = ['Content-Type: text/plain; charset=UTF-8'];
      $GLOBALS['nuki_last_mail_error'] = null;

      $sent = wp_mail($to, $subject, $message, $headers);

      if ($sent) {
        echo "<p style='color:green;'><strong>OK:</strong> Email REAL enviado a " . esc_html($to) . "</p>";
        echo "<p><strong>Asunto:</strong> " . esc_html($subject) . "</p>";
        echo "<p><strong>Enlace enviado:</strong><br>" . esc_html($link) . "</p>";
        echo "<pre style='background:#f7f7f7;border:1px solid #ddd;padding:10px;max-width:900px;overflow:auto;'>" . esc_html($message) . "</pre>";
      } else {
        echo "<p style='color:red;'><strong>Error:</strong> wp_mail() no pudo enviar el correo. Revisa SMTP / configuración del servidor.</p>";

        if (!empty($GLOBALS['nuki_last_mail_error'])) {
          echo "<pre style='background:#fff3f3;border:1px solid #f0b4b4;padding:10px;max-width:900px;overflow:auto;'>";
          echo "Último error wp_mail_failed:\n";
          echo "Mensaje: " . print_r($GLOBALS['nuki_last_mail_error']['message'], true) . "\n\n";
          echo "Data:\n" . print_r($GLOBALS['nuki_last_mail_error']['data'], true);
          echo "</pre>";
        } else {
          echo "<p><em>No se capturó detalle en wp_mail_failed (puede ser un fallo silencioso del servidor o configuración).</em></p>";
        }
      }
    }
  }

  echo '
    <form method="post">
      <button name="run">Generar código ahora</button>
      <br><br>
      <button name="token">Generar enlace temporal</button>

      <hr style="margin:20px 0;">

      <h3>Recordatorios reales (Eholo → emails de relation.email)</h3>
      <button name="simulate_reminders">Simular recordatorios (mostrar email real y contenido)</button>
      <button name="send_reminders_now" style="margin-left:8px;" onclick="return confirm(\'¿Seguro que quieres enviar los recordatorios reales ahora?\')">Enviar recordatorios ahora</button>

      <hr style="margin:20px 0;">

      <h3>Comprobar emails de pacientes (Eholo)</h3>
      <button name="list_next_2weeks_emails">Listar sesiones próximas 2 semanas (ID → Email)</button>

      <hr style="margin:20px 0;">

      <h3>Enviar correo de prueba</h3>
      <input type="email" name="test_email" placeholder="email@dominio.com" style="width:320px; padding:6px;" required>
      <button name="send_test_email" style="margin-left:8px;">Enviar email de prueba</button>
    </form>
  ';
}
