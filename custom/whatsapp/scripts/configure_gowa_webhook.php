<?php
/**
 * Script para configurar el webhook de GoWA
 * Ejecutar desde: /custom/whatsapp/scripts/configure_gowa_webhook.php
 */

// Cargar entorno Dolibarr
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Verificar permisos de administrador
if (!$user->admin) {
    accessforbidden('Necesitas ser administrador para ejecutar este script');
}

// Detectar dominio
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$webhookUrl = $protocol . "://" . $domain . "/custom/whatsapp/public/webhook.php";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Configurar Webhook de GoWA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .code {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
            font-size: 14px;
        }
        .step {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #25D366;
            border-radius: 5px;
        }
        .step h3 {
            margin-top: 0;
            color: #25D366;
        }
        .button {
            display: inline-block;
            background: #25D366;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover {
            background: #128C7E;
        }
        .button-secondary {
            background: #6c757d;
        }
        .button-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Configurar Webhook de GoWA</h1>
        
        <div class="alert alert-info">
            <strong>📍 URL del Webhook detectada:</strong><br>
            <code><?php echo $webhookUrl; ?></code>
        </div>
        
        <div class="step">
            <h3>Paso 1: Configurar via API de GoWA</h3>
            <p>Copia y ejecuta este comando en tu terminal SSH:</p>
            <div class="code">curl -X POST http://localhost:3000/api/webhook \
  -H "Content-Type: application/json" \
  -d '{"url": "<?php echo $webhookUrl; ?>", "events": ["message"]}'</div>
        </div>
        
        <div class="step">
            <h3>Paso 2: Verificar configuración</h3>
            <p>Después de configurar, verifica que el webhook esté activo:</p>
            <div class="code">curl http://localhost:3000/api/webhook</div>
        </div>
        
        <div class="step">
            <h3>Paso 3: Probar el webhook</h3>
            <p>Envía un mensaje de WhatsApp al número conectado y verifica los logs:</p>
            <a href="view_webhook_logs.php" class="button">📊 Ver Logs</a>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h2>🔍 Diagnóstico</h2>
        
        <?php
        // Verificar si GoWA está corriendo
        $gowaRunning = false;
        exec("pgrep -f gowa", $output, $return);
        if ($return === 0 && !empty($output)) {
            echo '<div class="alert alert-success">✅ GoWA está corriendo (PID: ' . implode(', ', $output) . ')</div>';
            $gowaRunning = true;
        } else {
            echo '<div class="alert alert-warning">⚠️ GoWA no parece estar corriendo</div>';
        }
        
        // Verificar puerto 3000
        $port3000Open = false;
        exec("lsof -i :3000 -P -n 2>/dev/null | grep LISTEN", $output3000);
        if (!empty($output3000)) {
            echo '<div class="alert alert-success">✅ Puerto 3000 está abierto y escuchando</div>';
            $port3000Open = true;
        } else {
            echo '<div class="alert alert-warning">⚠️ Puerto 3000 no está escuchando</div>';
        }
        
        // Verificar logs de GoWA
        $gowaLogFile = dirname(__DIR__) . '/storages/gowa.log';
        if (file_exists($gowaLogFile)) {
            $lastLines = shell_exec("tail -5 " . escapeshellarg($gowaLogFile));
            echo '<h3>Últimas líneas del log de GoWA:</h3>';
            echo '<div class="code"><pre>' . htmlspecialchars($lastLines) . '</pre></div>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <h2>📚 Documentación</h2>
        
        <p><strong>Formatos de teléfono soportados:</strong></p>
        <ul>
            <li>9 cifras: <code>600123456</code></li>
            <li>Con código de país: <code>34600123456</code></li>
            <li>Con +: <code>+34600123456</code></li>
        </ul>
        
        <p><strong>Tipos de mensajes soportados:</strong></p>
        <ul>
            <li>📝 Texto</li>
            <li>📷 Fotos/Imágenes</li>
            <li>📄 Documentos (PDF, Word, Excel, etc.)</li>
            <li>🎥 Videos</li>
            <li>🎤 Audio/Notas de voz</li>
            <li>🎨 Stickers</li>
            <li>📍 Ubicaciones</li>
            <li>👤 Contactos</li>
        </ul>
        
        <hr style="margin: 30px 0;">
        
        <div style="text-align: center;">
            <a href="view_webhook_logs.php" class="button">📊 Ver Logs</a>
            <a href="webhook_test.php" target="_blank" class="button button-secondary">🧪 Test Webhook</a>
            <a href="../admin/setup.php" class="button button-secondary">⚙️ Configuración</a>
        </div>
    </div>
</body>
</html>
