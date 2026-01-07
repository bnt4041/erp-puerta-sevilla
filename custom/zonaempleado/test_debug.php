<?php
/* Debug test file */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

echo "<h1>Zona Empleado Debug Test</h1>";

// Check user authentication
echo "<h2>1. User Authentication</h2>";
if (empty($user) || !$user->id) {
    echo "<p style='color: red;'>❌ User not authenticated</p>";
    echo "<p>Redirecting to login...</p>";
    header("Location: ".DOL_URL_ROOT."/");
    exit;
} else {
    echo "<p style='color: green;'>✅ User authenticated: " . $user->login . "</p>";
    echo "<p>User ID: " . $user->id . "</p>";
    echo "<p>User Name: " . $user->getFullName($langs) . "</p>";
}

// Check file paths
echo "<h2>2. File Paths</h2>";
$zonaempleado_class_path = DOL_DOCUMENT_ROOT.'/custom/zonaempleado/class/zonaempleado.class.php';
$zonaempleado_lib_path = DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';

echo "<p>DOL_DOCUMENT_ROOT: " . DOL_DOCUMENT_ROOT . "</p>";
echo "<p>Class path: " . $zonaempleado_class_path . "</p>";
if (file_exists($zonaempleado_class_path)) {
    echo "<p style='color: green;'>✅ Class file exists</p>";
} else {
    echo "<p style='color: red;'>❌ Class file NOT found</p>";
}

echo "<p>Library path: " . $zonaempleado_lib_path . "</p>";
if (file_exists($zonaempleado_lib_path)) {
    echo "<p style='color: green;'>✅ Library file exists</p>";
} else {
    echo "<p style='color: red;'>❌ Library file NOT found</p>";
}

// Try to load files
echo "<h2>3. Loading Files</h2>";
try {
    require_once $zonaempleado_class_path;
    echo "<p style='color: green;'>✅ Class file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading class: " . $e->getMessage() . "</p>";
}

try {
    require_once $zonaempleado_lib_path;
    echo "<p style='color: green;'>✅ Library file loaded successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading library: " . $e->getMessage() . "</p>";
}

// Check if class exists
echo "<h2>4. Class Check</h2>";
if (class_exists('ZonaEmpleado')) {
    echo "<p style='color: green;'>✅ ZonaEmpleado class exists</p>";
    try {
        $zonaempleado = new ZonaEmpleado($db);
        echo "<p style='color: green;'>✅ ZonaEmpleado object created successfully</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating object: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ ZonaEmpleado class does NOT exist</p>";
}

// Check if function exists
echo "<h2>5. Function Check</h2>";
if (function_exists('zonaempleado_get_extensions')) {
    echo "<p style='color: green;'>✅ zonaempleado_get_extensions function exists</p>";
    try {
        $extensions = zonaempleado_get_extensions();
        echo "<p style='color: green;'>✅ Function executed successfully</p>";
        echo "<p>Extensions found: " . count($extensions) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error executing function: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ zonaempleado_get_extensions function does NOT exist</p>";
}

// Check hookmanager
echo "<h2>6. HookManager Check</h2>";
if (empty($hookmanager)) {
    echo "<p style='color: orange;'>⚠️ HookManager not initialized, creating...</p>";
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
    echo "<p style='color: green;'>✅ HookManager created</p>";
} else {
    echo "<p style='color: green;'>✅ HookManager already initialized</p>";
}

echo "<h2>7. Test Complete</h2>";
echo "<p>If all checks passed, the module should work correctly.</p>";
echo "<p><a href='index.php'>Go to Zona Empleado Dashboard</a></p>";
?>
