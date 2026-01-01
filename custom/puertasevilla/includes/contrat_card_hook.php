<?php
/* Copyright (C) 2024 PuertaSevilla
 * 
 * Inyección de botón de renovación en la ficha de contrato
 */

// Este archivo se incluye desde el hook de Dolibarr en contrat/card.php
// Agrega el botón de renovación directamente a las acciones

if (!defined('ABSPATH') && !defined('DOL_DOCUMENT_ROOT')) {
	return;
}

global $conf, $user, $object, $hookmanager;

// Verificar permisos
if (empty($user->rights->contrat->creer)) {
	return;
}

// Verificar que es un contrato
if (empty($object) || $object->element !== 'contrat' || empty($object->id)) {
	return;
}

// Mostrar botón de renovación
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Cargar el script de modal
	var script = document.createElement('script');
	script.src = '<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js';
	document.head.appendChild(script);
	
	// Cargar el CSS
	var link = document.createElement('link');
	link.rel = 'stylesheet';
	link.href = '<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/css/renovacion.css';
	document.head.appendChild(link);
	
	// Agregar botón después que todo esté cargado
	setTimeout(function() {
		var tabsAction = document.querySelector('.tabsAction');
		if (tabsAction) {
			var btn = document.createElement('a');
			btn.href = 'javascript:void(0)';
			btn.className = 'butAction renovar-btn';
			btn.innerHTML = '<i class="fas fa-sync"></i> Renovar contrato';
			btn.onclick = function(e) {
				e.preventDefault();
				abrirModalRenovacion(<?php echo (int)$object->id; ?>, '<?php echo addslashes($object->ref); ?>');
			};
			tabsAction.appendChild(btn);
		}
	}, 500);
});
</script>
