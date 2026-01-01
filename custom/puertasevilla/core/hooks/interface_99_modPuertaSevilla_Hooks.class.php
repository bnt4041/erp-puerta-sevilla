<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/hooks/interface_99_modPuertaSevilla_Hooks.class.php
 * \ingroup puertasevilla
 * \brief   Hooks para PuertaSevilla
 */

/**
 * Class of hooks for PuertaSevilla module
 */
class InterfacePuertaSevilla Hooks extends CommonHooks
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook printActionButtons para agregar botón en fichas de contrato
	 * Se ejecuta en la vista de contrato para agregar acciones
	 *
	 * @param  array   $parameters Parameter array
	 * @param  Object  $object     The object
	 * @param  string  $action     Identifier of the action
	 * @param  Object  $hookmanager Hook manager
	 * @return int                 0 on success
	 */
	public function printActionButtons(&$parameters, &$object, &$action, &$hookmanager)
	{
		global $conf, $user, $langs;

		// Verificar que está habilitado el módulo
		if (!isModEnabled('puertasevilla')) {
			return 0;
		}

		// Solo en fichas de contrato
		if (empty($object->element) || $object->element !== 'contrat') {
			return 0;
		}

		// Solo si el usuario tiene permisos de edición
		if (empty($user->rights->contrat->creer)) {
			return 0;
		}

		// Mostrar botón de renovación en la vista del contrato
		if ($action === 'view' && !empty($object->id)) {
			?>
			<script src="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js"></script>
			<script>
				jQuery(document).ready(function() {
					// Buscar el contenedor de botones de acciones y agregar botón de renovación
					var tabsAction = jQuery('.tabsAction').first();
					if (tabsAction.length > 0) {
						var renovarButton = jQuery('<a class="butAction" href="javascript:void(0)" onclick="abrirModalRenovacion(' + <?php echo (int)$object->id; ?> + ', ' + JSON.stringify('<?php echo addslashes($object->ref); ?>') + ')"><span class="fa fa-refresh"></span> Renovar contrato</a>');
						tabsAction.append(renovarButton);
					}
				});
			</script>
			<?php
		}

		return 0;
	}

	/**
	 * Hook para agregar acciones masivas en la lista de contratos
	 * Se llama al renderizar filas en la lista de contratos
	 *
	 * @param  array   $parameters Parameter array
	 * @param  Object  $object     The object
	 * @param  string  $action     Identifier of the action
	 * @param  Object  $hookmanager Hook manager
	 * @return int                 0 on success
	 */
	public function printFieldListAction(&$parameters, &$object, &$action, &$hookmanager)
	{
		global $conf, $user, $langs;

		// Verificar que está habilitado el módulo
		if (!isModEnabled('puertasevilla')) {
			return 0;
		}

		// Solo en listas de contratos
		if (empty($parameters['tablename']) || $parameters['tablename'] !== 'llx_contrat') {
			return 0;
		}

		// Solo si el usuario tiene permisos
		if (empty($user->rights->contrat->creer)) {
			return 0;
		}

		// Agregar opción de acción masiva
		?>
		<script src="<?php echo DOL_URL_ROOT; ?>/custom/puertasevilla/js/renovar_contrato_modal.js"></script>
		<script>
			jQuery(document).ready(function() {
				// Buscar el select de acciones masivas
				var selectActions = jQuery('select[name*="massaction"]');
				if (selectActions.length === 0) {
					selectActions = jQuery('select[name="action"]');
				}
				
				// Agregar opción de renovación si no existe ya
				if (selectActions.length > 0 && !selectActions.find('option[value="renovar_masivo"]').length) {
					selectActions.append('<option value="renovar_masivo">Renovar contrato (masivo)</option>');
				}
			});
		</script>
		<?php

		return 0;
	}

	/**
	 * Hook ejecutado cuando se realiza una acción en contratos
	 * Aquí procesamos las acciones masivas
	 *
	 * @param  array   $parameters Parameter array
	 * @param  Object  $object     The object
	 * @param  string  $action     Identifier of the action
	 * @param  Object  $hookmanager Hook manager
	 * @return int                 0 on success
	 */
	public function printActionButtons2(&$parameters, &$object, &$action, &$hookmanager)
	{
		global $user, $db, $langs;

		// Verificar que está habilitado el módulo
		if (!isModEnabled('puertasevilla')) {
			return 0;
		}

		// Procesar acciones masivas de renovación
		if (!empty($action) && $action === 'renovar_masivo') {
			if (empty($user->rights->contrat->creer)) {
				return 0;
			}

			// Aquí se procesaría la renovación masiva
			// Por ahora simplemente mostramos un script para abrirla individualmente
		}

		return 0;
	}
}
