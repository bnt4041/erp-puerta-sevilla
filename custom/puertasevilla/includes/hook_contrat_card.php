<?php
/**
 * Hook en la vista de contrato para agregar botón de renovación
 * Se ejecuta en /contrat/card.php cuando se visualiza un contrato
 */

// Solo se ejecuta en la vista de contrato
if ($object->element !== 'contrat' || empty($object->id)) {
	return;
}

// Verificar permisos
if (empty($user->rights->contrat->creer)) {
	return;
}

// Inyectar recursos al final de la página
if (empty($conf->global->PUERTASEVILLA_BUTTON_INJECTED)) {
	?>
	<script src="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js"></script>
	<link rel="stylesheet" href="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/css/renovacion.css">
	<?php
	
	// Marcar que ya fue inyectado para no repetir
	$conf->global->PUERTASEVILLA_BUTTON_INJECTED = 1;
}
?>
<script>
(function() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', agregarBotonRenovacion);
	} else {
		agregarBotonRenovacion();
	}
	
	function agregarBotonRenovacion() {
		// Buscar el contenedor de botones de acciones
		var tabsAction = document.querySelector('.tabsAction');
		if (!tabsAction) {
			setTimeout(agregarBotonRenovacion, 100);
			return;
		}
		
		// Verificar si el botón ya existe
		if (tabsAction.querySelector('.renovar-contrato-btn')) {
			return;
		}
		
		// Crear el botón
		var btn = document.createElement('a');
		btn.href = 'javascript:void(0)';
		btn.className = 'butAction renovar-contrato-btn';
		btn.innerHTML = '<i class="fas fa-sync-alt"></i> Renovar contrato';
		
		// Evento click
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			if (typeof abrirModalRenovacion === 'function') {
				abrirModalRenovacion(<?php echo (int)$object->id; ?>, '<?php echo addslashes($object->ref); ?>');
			} else {
				alert('Error: Función de renovación no cargada correctamente');
				console.error('abrirModalRenovacion no está definida');
			}
		});
		
		// Agregar botón al contenedor
		tabsAction.appendChild(btn);
	}
})();
</script>
<?php
