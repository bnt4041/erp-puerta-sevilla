<?php
/**
 * Script para crear manualmente los tipos de eventos de WhatsApp
 * Ejecutar desde: /custom/whatsapp/scripts/create_event_types.php
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

echo "<h1>Creación de tipos de eventos WhatsApp</h1>";
echo "<hr>";

// Crear AC_WA (mensajes enviados)
echo "<h2>1. Verificando AC_WA (WhatsApp message sent)...</h2>";
$sql = "SELECT id, code, type, libelle FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA'";
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    echo "✅ <strong>AC_WA ya existe</strong><br>";
    echo "ID: " . $obj->id . "<br>";
    echo "Código: " . $obj->code . "<br>";
    echo "Tipo: " . $obj->type . "<br>";
    echo "Etiqueta: " . $obj->libelle . "<br>";
} else {
    echo "⚠️ AC_WA no existe, creando...<br>";
    
    // Obtener el siguiente ID disponible
    $sql_max = "SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm";
    $resql_max = $db->query($sql_max);
    $next_id = 1;
    if ($resql_max) {
        $obj_max = $db->fetch_object($resql_max);
        $next_id = ($obj_max->maxid ? $obj_max->maxid : 0) + 1;
    }
    
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) 
            VALUES (".$next_id.", 'AC_WA', 'whatsapp', 'WhatsApp message sent', 'whatsapp', 1, 10)";
    $result = $db->query($sql);
    
    if ($result) {
        echo "✅ <strong>AC_WA creado correctamente</strong><br>";
        echo "ID: " . $next_id . "<br>";
    } else {
        echo "❌ <strong>Error al crear AC_WA:</strong> " . $db->lasterror() . "<br>";
    }
}

echo "<br><hr><br>";

// Crear AC_WA_IN (mensajes recibidos)
echo "<h2>2. Verificando AC_WA_IN (WhatsApp message received)...</h2>";
$sql = "SELECT id, code, type, libelle FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = 'AC_WA_IN'";
$resql = $db->query($sql);

if ($resql && $db->num_rows($resql) > 0) {
    $obj = $db->fetch_object($resql);
    echo "✅ <strong>AC_WA_IN ya existe</strong><br>";
    echo "ID: " . $obj->id . "<br>";
    echo "Código: " . $obj->code . "<br>";
    echo "Tipo: " . $obj->type . "<br>";
    echo "Etiqueta: " . $obj->libelle . "<br>";
} else {
    echo "⚠️ AC_WA_IN no existe, creando...<br>";
    
    // Obtener el siguiente ID disponible
    $sql_max = "SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm";
    $resql_max = $db->query($sql_max);
    $next_id = 1;
    if ($resql_max) {
        $obj_max = $db->fetch_object($resql_max);
        $next_id = ($obj_max->maxid ? $obj_max->maxid : 0) + 1;
    }
    
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) 
            VALUES (".$next_id.", 'AC_WA_IN', 'whatsapp', 'WhatsApp message received', 'whatsapp', 1, 11)";
    $result = $db->query($sql);
    
    if ($result) {
        echo "✅ <strong>AC_WA_IN creado correctamente</strong><br>";
        echo "ID: " . $next_id . "<br>";
    } else {
        echo "❌ <strong>Error al crear AC_WA_IN:</strong> " . $db->lasterror() . "<br>";
    }
}

echo "<br><hr><br>";

// Mostrar todos los tipos de WhatsApp
echo "<h2>3. Tipos de eventos WhatsApp en la base de datos:</h2>";
$sql = "SELECT id, code, type, libelle, module, active, position 
        FROM ".MAIN_DB_PREFIX."c_actioncomm 
        WHERE code LIKE 'AC_WA%' 
        ORDER BY position";
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Código</th><th>Tipo</th><th>Etiqueta</th><th>Módulo</th><th>Activo</th><th>Posición</th></tr>";
        
        while ($obj = $db->fetch_object($resql)) {
            echo "<tr>";
            echo "<td>" . $obj->id . "</td>";
            echo "<td><strong>" . $obj->code . "</strong></td>";
            echo "<td>" . $obj->type . "</td>";
            echo "<td>" . $obj->libelle . "</td>";
            echo "<td>" . $obj->module . "</td>";
            echo "<td>" . ($obj->active ? '✅' : '❌') . "</td>";
            echo "<td>" . $obj->position . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "⚠️ No se encontraron tipos de eventos WhatsApp";
    }
} else {
    echo "❌ Error al consultar tipos: " . $db->lasterror();
}

echo "<br><hr><br>";
echo "<h3>✅ Proceso completado</h3>";
echo "<p><a href='../admin/setup.php'>← Volver a configuración del módulo</a></p>";
