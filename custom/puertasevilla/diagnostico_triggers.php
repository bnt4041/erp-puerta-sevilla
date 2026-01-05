<?php
/**
 * Script de diagnóstico para verificar triggers de contrato
 * Ruta: /custom/puertasevilla/diagnostico_triggers.php
 */

// Incluir configuración de Dolibarr
require '../../master.inc.php';

// Verificar permisos
if ($user->admin == 0) {
    die("Acceso denegado. Se requieren permisos de administrador.");
}

global $db, $user;

echo "<h2>Diagnóstico de Triggers PuertaSevilla</h2>";

// 1. Verificar si el módulo está habilitado
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."modules WHERE name = 'puertasevilla'";
$res = $db->query($sql);
$obj = $db->fetch_object($res);
echo "<p><strong>1. Módulo habilitado:</strong> " . ($obj->cnt > 0 ? "✓ SÍ" : "✗ NO") . "</p>";

// 2. Verificar archivos de trigger
echo "<p><strong>2. Archivo de triggers:</strong>";
$triggerFile = DOL_DOCUMENT_ROOT.'/custom/puertasevilla/core/triggers/interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php';
echo (file_exists($triggerFile) ? " ✓ EXISTE" : " ✗ NO EXISTE") . "</p>";

// 3. Buscar líneas de contrato activas
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."contratdet WHERE statut = 4";
$res = $db->query($sql);
$obj = $db->fetch_object($res);
echo "<p><strong>3. Líneas de contrato ACTIVAS (status=4):</strong> " . $obj->cnt . "</p>";

// 4. Buscar líneas de contrato con ID de factura en comentario
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."contratdet WHERE commentaire LIKE '%PSV_FACTUREREC_ID%'";
$res = $db->query($sql);
$obj = $db->fetch_object($res);
echo "<p><strong>4. Líneas con ID de factura recurrente en comentario:</strong> " . $obj->cnt . "</p>";

// 5. Listar todas las líneas con comentarios
echo "<p><strong>5. Líneas de contrato con comentarios:</strong></p>";
$sql = "SELECT rowid, fk_contrat, statut, commentaire FROM ".MAIN_DB_PREFIX."contratdet WHERE commentaire != '' AND commentaire IS NOT NULL ORDER BY rowid DESC LIMIT 10";
$res = $db->query($sql);
echo "<table border='1' style='width:100%; margin: 10px 0;'>";
echo "<tr><th>ID Línea</th><th>ID Contrato</th><th>Estado</th><th>Comentario</th></tr>";
while ($row = $db->fetch_object($res)) {
    $status_label = $row->statut == 4 ? "ABIERTA (4)" : ($row->statut == 5 ? "CERRADA (5)" : "Otro (".$row->statut.")");
    echo "<tr>";
    echo "<td>" . $row->rowid . "</td>";
    echo "<td>" . $row->fk_contrat . "</td>";
    echo "<td>" . $status_label . "</td>";
    echo "<td><pre>" . htmlspecialchars($row->commentaire) . "</pre></td>";
    echo "</tr>";
}
echo "</table>";

// 6. Buscar facturas recurrentes con suspended = 1
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."facture_rec WHERE suspended = 1";
$res = $db->query($sql);
$obj = $db->fetch_object($res);
echo "<p><strong>6. Facturas recurrentes SUSPENDIDAS:</strong> " . $obj->cnt . "</p>";

// 7. Listar facturas recurrentes recientes
echo "<p><strong>7. Últimas facturas recurrentes creadas:</strong></p>";
$sql = "SELECT rowid, socid, suspended, date_creation, nb_gen_max FROM ".MAIN_DB_PREFIX."facture_rec ORDER BY rowid DESC LIMIT 10";
$res = $db->query($sql);
echo "<table border='1' style='width:100%; margin: 10px 0;'>";
echo "<tr><th>ID Factura Rec</th><th>ID Cliente</th><th>Suspendida</th><th>Fecha Creación</th><th>Máx Generaciones</th></tr>";
while ($row = $db->fetch_object($res)) {
    echo "<tr>";
    echo "<td>" . $row->rowid . "</td>";
    echo "<td>" . $row->socid . "</td>";
    echo "<td>" . ($row->suspended ? "SÍ ✓" : "NO") . "</td>";
    echo "<td>" . dol_print_date($row->date_creation, 'dayhour') . "</td>";
    echo "<td>" . $row->nb_gen_max . "</td>";
    echo "</tr>";
}
echo "</table>";

// 8. Revisar logs del sistema
echo "<p><strong>8. Últimos logs relevantes del sistema:</strong></p>";
$sql = "SELECT DATE_FORMAT(tms, '%Y-%m-%d %H:%i:%S') as fecha, message FROM ".MAIN_DB_PREFIX."log 
        WHERE message LIKE '%PuertaSevilla%' OR message LIKE '%suspendInvoiceTemplate%'
        ORDER BY rowid DESC LIMIT 20";
$res = $db->query($sql);
if ($db->num_rows($res) > 0) {
    echo "<table border='1' style='width:100%; margin: 10px 0;'>";
    echo "<tr><th>Fecha</th><th>Mensaje</th></tr>";
    while ($row = $db->fetch_object($res)) {
        echo "<tr>";
        echo "<td>" . $row->fecha . "</td>";
        echo "<td><pre>" . htmlspecialchars($row->message) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No hay logs de PuertaSevilla. Los triggers pueden no estar ejecutándose.</p>";
}

echo "<p><strong>Conclusión:</strong></p>";
echo "<ul>";
echo "<li>Si la sección 4 muestra 0, el ID de la factura NO se está guardando correctamente.</li>";
echo "<li>Si la sección 8 está vacía, los triggers NO se están ejecutando.</li>";
echo "<li>Si la sección 6 muestra 0, las suspensiones NO se están registrando.</li>";
echo "</ul>";

?>
