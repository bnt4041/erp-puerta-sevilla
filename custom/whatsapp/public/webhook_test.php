<?php
/**
 * Script de prueba del webhook de WhatsApp
 * Este script verifica que el webhook es accesible y registra cualquier petición
 * URL: /custom/whatsapp/public/webhook_test.php
 */

if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOLOGIN')) define('NOLOGIN', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');

// Load API environment
require_once __DIR__ . '/../../../main.inc.php';

// Obtener toda la información de la petición
$method = $_SERVER['REQUEST_METHOD'];
$headers = function_exists('getallheaders') ? getallheaders() : $_SERVER;
$body = file_get_contents('php://input');
$get = $_GET;
$post = $_POST;
$timestamp = date('Y-m-d H:i:s');

// Log SIEMPRE, incluso si no hay datos
$logMessage = "\n========== WEBHOOK TEST - $timestamp ==========\n";
$logMessage .= "Method: $method\n";
$logMessage .= "URL: " . $_SERVER['REQUEST_URI'] . "\n";
$logMessage .= "Remote IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$logMessage .= "\nHeaders:\n" . print_r($headers, true);
$logMessage .= "\nGET params:\n" . print_r($get, true);
$logMessage .= "\nPOST params:\n" . print_r($post, true);
$logMessage .= "\nBody:\n" . $body . "\n";
$logMessage .= "========== END TEST ==========\n\n";

// Guardar en archivo de log específico
$logFile = DOL_DATA_ROOT . '/whatsapp_webhook_test.log';
file_put_contents($logFile, $logMessage, FILE_APPEND);

// También en el log de Dolibarr
dol_syslog("WEBHOOK TEST: Received $method request from " . $_SERVER['REMOTE_ADDR'], LOG_INFO);
dol_syslog("WEBHOOK TEST: Body: " . $body, LOG_DEBUG);

// Respuesta HTML para cuando se accede desde el navegador
if ($method == 'GET' && empty($body)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WhatsApp Webhook Test</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1200px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            h1 {
                color: #25D366;
                border-bottom: 3px solid #25D366;
                padding-bottom: 10px;
            }
            .success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .info {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .code {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 15px;
                border-radius: 5px;
                font-family: 'Courier New', monospace;
                overflow-x: auto;
                margin: 10px 0;
            }
            .url {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                word-break: break-all;
            }
            .button {
                display: inline-block;
                background: #25D366;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px 5px;
            }
            .button:hover {
                background: #128C7E;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: left;
            }
            th {
                background: #25D366;
                color: white;
            }
            tr:nth-child(even) {
                background: #f9f9f9;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>✅ Webhook de WhatsApp - Test OK</h1>
            
            <div class="success">
                <strong>✓ El webhook es accesible correctamente</strong><br>
                Esta petición ha sido registrada en el log.
            </div>
            
            <div class="url">
                <strong>📍 URL de este webhook:</strong><br>
                <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; ?></code>
            </div>
            
            <h2>🔧 Configuración de GoWA</h2>
            
            <div class="info">
                <strong>URL del webhook para configurar en GoWA:</strong>
            </div>
            
            <div class="code">
<?php 
$webhookUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace('webhook_test.php', 'webhook.php', $_SERVER['REQUEST_URI']);
echo $webhookUrl;
?>
            </div>
            
            <h2>📋 Información de la petición actual</h2>
            
            <table>
                <tr>
                    <th>Parámetro</th>
                    <th>Valor</th>
                </tr>
                <tr>
                    <td>Método</td>
                    <td><?php echo $method; ?></td>
                </tr>
                <tr>
                    <td>IP Remota</td>
                    <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                </tr>
                <tr>
                    <td>User Agent</td>
                    <td><?php echo isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td>Timestamp</td>
                    <td><?php echo $timestamp; ?></td>
                </tr>
                <tr>
                    <td>Archivo de log</td>
                    <td><?php echo $logFile; ?></td>
                </tr>
            </table>
            
            <h2>🧪 Probar el webhook</h2>
            
            <p>Puedes probar el webhook enviando una petición POST con curl:</p>
            
            <div class="code">
curl -X POST <?php echo $webhookUrl; ?> \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message",
    "payload": {
      "from": "34600123456@s.whatsapp.net",
      "fromMe": false,
      "text": "Mensaje de prueba",
      "pushName": "Test User",
      "type": "text"
    }
  }'
            </div>
            
            <h2>📊 Ver logs</h2>
            
            <a href="../scripts/view_webhook_logs.php" class="button">Ver logs de Dolibarr</a>
            <a href="?view_test_log=1" class="button">Ver log de pruebas</a>
            <a href="../admin/setup.php" class="button">Configuración del módulo</a>
            
            <?php if (isset($_GET['view_test_log']) && file_exists($logFile)): ?>
            <h2>📄 Últimas 50 líneas del log de pruebas</h2>
            <div class="code" style="max-height: 400px; overflow-y: auto;">
                <pre><?php echo htmlspecialchars(shell_exec("tail -n 50 " . escapeshellarg($logFile))); ?></pre>
            </div>
            <?php endif; ?>
            
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si es POST, responder OK
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Webhook test received',
    'timestamp' => $timestamp,
    'logged_to' => $logFile
]);
