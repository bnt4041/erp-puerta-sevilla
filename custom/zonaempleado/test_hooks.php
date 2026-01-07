<?php
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

echo "=== TEST DE HOOKS ===\n\n";

// Verificar que el módulo Clínicas está activo
echo "1. Módulo Clínicas activo: " . (empty($conf->clinicas->enabled) ? "NO" : "SI") . "\n";

// Verificar permisos del usuario
echo "2. Usuario actual: " . $user->login . "\n";
echo "3. Permisos clinicas->technician->access: " . (empty($user->rights->clinicas->technician->access) ? "NO" : "SI") . "\n";

// Inicializar hookmanager
if (empty($hookmanager)) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
    $hookmanager = new HookManager($db);
}

echo "\n4. Inicializando hooks para 'zonaempleadoindex'...\n";
$hookmanager->initHooks(array('zonaempleadoindex'));

// Verificar qué clases de hooks están cargadas
echo "5. Hooks cargados:\n";
if (!empty($hookmanager->hooks)) {
    foreach ($hookmanager->hooks as $context => $hooks) {
        echo "   Context: $context\n";
        if (is_array($hooks)) {
            foreach ($hooks as $hook) {
                echo "      - " . get_class($hook) . "\n";
            }
        }
    }
} else {
    echo "   ¡NINGÚN HOOK CARGADO!\n";
}

// Probar ejecución del hook addQuickLinks
echo "\n6. Probando hook 'addQuickLinks'...\n";
$quickLinks = array();
$parameters = array('quickLinks' => &$quickLinks);
$reshook = $hookmanager->executeHooks('addQuickLinks', $parameters);

echo "   Resultado: $reshook\n";
echo "   QuickLinks encontrados: " . count($quickLinks) . "\n";
if (!empty($quickLinks)) {
    foreach ($quickLinks as $link) {
        echo "      - " . $link['label'] . " (" . $link['url'] . ")\n";
    }
}

echo "\n=== FIN TEST ===\n";
