<?php
/**
 * Script para ver los logs del webhook de WhatsApp
 * Ejecutar desde: /custom/whatsapp/scripts/view_webhook_logs.php
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

// Obtener el archivo de log
$logfile = DOL_DATA_ROOT . '/dolibarr.log';

// Número de líneas a mostrar
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : 200;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'WhatsApp Webhook';

?>
<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp Webhook Logs</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .controls {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .controls label {
            color: #9cdcfe;
            margin-right: 10px;
        }
        .controls input, .controls select {
            background: #3c3c3c;
            color: #d4d4d4;
            border: 1px solid #555;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .controls button {
            background: #0e639c;
            color: white;
            border: none;
            padding: 7px 15px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 10px;
        }
        .controls button:hover {
            background: #1177bb;
        }
        .log-container {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            max-height: 800px;
            overflow-y: auto;
            border: 1px solid #3c3c3c;
        }
        .log-line {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid transparent;
            font-size: 13px;
            line-height: 1.5;
        }
        .log-line.debug {
            color: #808080;
            border-left-color: #808080;
        }
        .log-line.info {
            color: #4ec9b0;
            border-left-color: #4ec9b0;
        }
        .log-line.warning {
            color: #dcdcaa;
            border-left-color: #dcdcaa;
        }
        .log-line.error {
            color: #f48771;
            border-left-color: #f48771;
            background: rgba(244, 135, 113, 0.1);
        }
        .log-line.start {
            color: #569cd6;
            border-left-color: #569cd6;
            font-weight: bold;
            margin-top: 15px;
        }
        .timestamp {
            color: #858585;
            margin-right: 10px;
        }
        .level {
            font-weight: bold;
            margin-right: 10px;
            display: inline-block;
            min-width: 60px;
        }
        .stats {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-box {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4ec9b0;
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #9cdcfe;
            font-size: 14px;
        }
        .stat-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #4ec9b0;
        }
        .no-logs {
            color: #dcdcaa;
            text-align: center;
            padding: 40px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>📱 WhatsApp Webhook Logs</h1>
    
    <div class="controls">
        <form method="GET">
            <label>Líneas:</label>
            <select name="lines">
                <option value="50" <?php echo $lines == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $lines == 100 ? 'selected' : ''; ?>>100</option>
                <option value="200" <?php echo $lines == 200 ? 'selected' : ''; ?>>200</option>
                <option value="500" <?php echo $lines == 500 ? 'selected' : ''; ?>>500</option>
                <option value="1000" <?php echo $lines == 1000 ? 'selected' : ''; ?>>1000</option>
            </select>
            
            <label>Filtro:</label>
            <input type="text" name="filter" value="<?php echo htmlspecialchars($filter); ?>" placeholder="WhatsApp Webhook">
            
            <button type="submit">🔍 Buscar</button>
            <button type="button" onclick="location.reload()">🔄 Recargar</button>
            <button type="button" onclick="location.href='?lines=<?php echo $lines; ?>&filter='">📋 Ver todo</button>
        </form>
    </div>

<?php

if (!file_exists($logfile)) {
    echo "<div class='no-logs'>❌ Archivo de log no encontrado: $logfile</div>";
    echo "</body></html>";
    exit;
}

// Leer las últimas N líneas del archivo
$command = "tail -n $lines " . escapeshellarg($logfile);
if ($filter) {
    $command .= " | grep " . escapeshellarg($filter);
}

$output = shell_exec($command);

if (!$output) {
    echo "<div class='no-logs'>⚠️ No se encontraron logs con el filtro: <strong>" . htmlspecialchars($filter) . "</strong></div>";
    echo "<p style='text-align: center;'><a href='?lines=$lines&filter=' style='color: #4ec9b0;'>Ver todos los logs</a></p>";
    echo "</body></html>";
    exit;
}

$logLines = explode("\n", trim($output));

// Estadísticas
$stats = array(
    'total' => count($logLines),
    'debug' => 0,
    'info' => 0,
    'warning' => 0,
    'error' => 0,
    'webhooks' => 0
);

foreach ($logLines as $line) {
    if (stripos($line, 'DEBUG') !== false) $stats['debug']++;
    if (stripos($line, 'INFO') !== false) $stats['info']++;
    if (stripos($line, 'WARNING') !== false) $stats['warning']++;
    if (stripos($line, 'ERR') !== false) $stats['error']++;
    if (stripos($line, 'Webhook START') !== false) $stats['webhooks']++;
}

?>

<div class="stats">
    <div class="stat-box">
        <h3>Total Líneas</h3>
        <div class="value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="stat-box" style="border-left-color: #569cd6;">
        <h3>Webhooks Recibidos</h3>
        <div class="value" style="color: #569cd6;"><?php echo $stats['webhooks']; ?></div>
    </div>
    <div class="stat-box" style="border-left-color: #4ec9b0;">
        <h3>Info</h3>
        <div class="value" style="color: #4ec9b0;"><?php echo $stats['info']; ?></div>
    </div>
    <div class="stat-box" style="border-left-color: #dcdcaa;">
        <h3>Warnings</h3>
        <div class="value" style="color: #dcdcaa;"><?php echo $stats['warning']; ?></div>
    </div>
    <div class="stat-box" style="border-left-color: #f48771;">
        <h3>Errores</h3>
        <div class="value" style="color: #f48771;"><?php echo $stats['error']; ?></div>
    </div>
</div>

<div class="log-container">
<?php

foreach ($logLines as $line) {
    if (empty(trim($line))) continue;
    
    // Determinar el tipo de log
    $class = 'debug';
    if (stripos($line, 'Webhook START') !== false) {
        $class = 'start';
    } elseif (stripos($line, 'ERR') !== false || stripos($line, '❌') !== false || stripos($line, 'FAILED') !== false) {
        $class = 'error';
    } elseif (stripos($line, 'WARNING') !== false || stripos($line, '⚠️') !== false) {
        $class = 'warning';
    } elseif (stripos($line, 'INFO') !== false || stripos($line, '✅') !== false) {
        $class = 'info';
    }
    
    // Extraer timestamp si existe
    $displayLine = htmlspecialchars($line);
    
    // Resaltar partes importantes
    $displayLine = preg_replace('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', '<span class="timestamp">$1</span>', $displayLine);
    $displayLine = preg_replace('/(DEBUG|INFO|WARNING|ERR)/', '<span class="level">$1</span>', $displayLine);
    $displayLine = str_replace('✅', '<span style="color: #4ec9b0;">✅</span>', $displayLine);
    $displayLine = str_replace('❌', '<span style="color: #f48771;">❌</span>', $displayLine);
    $displayLine = str_replace('⚠️', '<span style="color: #dcdcaa;">⚠️</span>', $displayLine);
    
    echo "<div class='log-line $class'>$displayLine</div>\n";
}

?>
</div>

<div style="margin-top: 20px; text-align: center; color: #808080;">
    <p>Archivo de log: <code><?php echo $logfile; ?></code></p>
    <p><a href="../admin/setup.php" style="color: #4ec9b0;">← Volver a configuración del módulo</a></p>
</div>

<script>
// Auto-scroll al final
window.onload = function() {
    var container = document.querySelector('.log-container');
    container.scrollTop = container.scrollHeight;
};

// Auto-reload cada 10 segundos si se está filtrando
<?php if ($filter): ?>
setTimeout(function() {
    location.reload();
}, 10000);
<?php endif; ?>
</script>

</body>
</html>
