<?php
/* Copyright (C) 2024 PuertaSevilla
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    includes/renovacion_buttons.php
 * \ingroup puertasevilla
 * \brief   Inyecta botones de renovación en fichas de contrato
 * 
 * Este archivo debe incluirse en contrat/card.php después de mostrar los botones de acción
 */

// Evitar incluir dos veces
if (defined('PUERTASEVILLA_RENOVACION_BUTTONS_LOADED')) {
	return;
}
define('PUERTASEVILLA_RENOVACION_BUTTONS_LOADED', true);

global $db, $user, $conf, $langs, $object;

// Validaciones
if (empty($object) || empty($object->element) || $object->element !== 'contrat') {
	return;
}

if (!isModEnabled('puertasevilla')) {
	return;
}

if (empty($user->rights->contrat->creer)) {
	return;
}

// Si es una vista (no en creación/edición), mostrar botón
$showButton = false;
if (empty($action) || $action === 'view') {
	if (!empty($object->id) && $object->statut != 0) {
		$showButton = true;
	}
}

if ($showButton) {
	// Cargar recursos
	echo '<!-- PuertaSevilla Contract Renewal -->' . "\n";
	echo '<script src="/custom/puertasevilla/js/renovar_contrato_modal.js"></script>' . "\n";
	echo '<link rel="stylesheet" href="/custom/puertasevilla/css/renovacion.css">' . "\n";
	
	// Inyectar token CSRF como variable global JavaScript
	if (!empty($_SESSION['newtoken'])) {
		echo '<script type="text/javascript">' . "\n";
		echo 'if (typeof newtoken === "undefined") {' . "\n";
		echo '  window.newtoken = "' . htmlspecialchars($_SESSION['newtoken'], ENT_QUOTES, 'UTF-8') . '";' . "\n";
		echo '}' . "\n";
		echo '</script>' . "\n";
	}
	
	// Botón de renovación
	echo '<div class="button-group-renovacion">' . "\n";
	echo '<button id="btn-renovar-contrato" class="butAction" type="button" onclick="abrirModalRenovacion(' . (int)$object->id . ', \'' . addslashes($object->ref) . '\'); return false;">' . "\n";
	echo '  <i class="fa fa-refresh"></i> ' . $langs->trans('Renovar') . "\n";
	echo '</button>' . "\n";
	echo '</div>' . "\n";
}

?>
