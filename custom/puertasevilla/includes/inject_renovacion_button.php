<?php
/**
 * Inyección automática de botón de renovación en fichas de contrato
 * 
 * Este archivo debe ser incluido en /contrat/card.php para agregar el botón
 * de renovación. Se puede hacer al final del archivo con:
 * 
 * // === PuertaSevilla Hook ===
 * if (file_exists(DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/inject_renovacion_button.php')) {
 *     include DOL_DOCUMENT_ROOT.'/custom/puertasevilla/includes/inject_renovacion_button.php';
 * }
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    die('Access denied');
}

global $conf, $user, $object, $db, $langs, $hookmanager;

// Verificar que sea un contrato y que exista el ID
if (empty($object) || $object->element !== 'contrat' || empty($object->id)) {
    return;
}

// Verificar permisos de edición
if (empty($user->rights->contrat->creer)) {
    return;
}

// Verificar que el módulo esté habilitado
if (!isModEnabled('puertasevilla')) {
    return;
}

// Cargar archivos necesarios
static $renovacion_recursos_cargados = false;

if (!$renovacion_recursos_cargados) {
    ?>
    <!-- PuertaSevilla Renovación Resources -->
    <script src="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js"></script>
    <link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/css/renovacion.css">
    <?php
    $renovacion_recursos_cargados = true;
}
?>

<!-- PuertaSevilla Renovación Button Injection -->
<script>
(function() {
    'use strict';
    
    // Función para agregar el botón
    function agregarBoton() {
        var tabsAction = document.querySelector('div.tabsAction');
        if (!tabsAction) {
            // Reintentar en 100ms
            setTimeout(agregarBoton, 100);
            return;
        }
        
        // Verificar que no existe ya
        if (document.querySelector('a.renovar-contrato-btn')) {
            return;
        }
        
        // Crear botón
        var btn = document.createElement('a');
        btn.href = 'javascript:void(0)';
        btn.className = 'butAction renovar-contrato-btn';
        btn.setAttribute('title', 'Renovar este contrato');
        
        // HTML del botón con icono
        btn.innerHTML = '<span class="fa fa-refresh"></span> Renovar contrato';
        
        // Handler del click
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof abrirModalRenovacion !== 'undefined') {
                abrirModalRenovacion(
                    <?php echo (int)$object->id; ?>,
                    '<?php echo addslashes($object->ref); ?>'
                );
            } else {
                console.error('Error: abrirModalRenovacion no está definida');
                alert('Error: La función de renovación no se cargó correctamente.');
            }
        });
        
        // Agregar al DOM
        tabsAction.appendChild(btn);
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', agregarBoton);
    } else {
        agregarBoton();
    }
})();
</script>

<?php
// Fin del archivo
