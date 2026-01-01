/**
 * Script de diagnóstico para verificar token CSRF
 * Ejecutar en consola del navegador (F12) mientras estés en una página de contrato
 */

// ============================================================
// DIAGNÓSTICO DE TOKEN CSRF
// ============================================================

console.log('=== DIAGNÓSTICO DE TOKEN CSRF ===');
console.log('');

// 1. Verificar variable global newtoken
console.log('1. Variable global "newtoken":');
if (typeof newtoken !== 'undefined') {
	console.log('   ✓ Disponible:', newtoken.substring(0, 20) + '...');
} else {
	console.log('   ✗ NO disponible');
}
console.log('');

// 2. Verificar inputs hidden con name="token"
console.log('2. Inputs hidden con name="token":');
var tokenInputs = document.querySelectorAll('input[name="token"]');
console.log('   Encontrados:', tokenInputs.length);
tokenInputs.forEach(function(input, index) {
	console.log('   Input ' + index + ':', input.value.substring(0, 20) + '...');
});
console.log('');

// 3. Verificar meta tags
console.log('3. Meta tags CSRF:');
var metaTags = document.querySelectorAll('meta[name*="csrf"], meta[name*="token"]');
metaTags.forEach(function(meta) {
	console.log('   ' + meta.getAttribute('name') + ':', meta.getAttribute('content').substring(0, 20) + '...');
});
if (metaTags.length === 0) console.log('   No encontrados');
console.log('');

// 4. Verificar cookies
console.log('4. Cookies relacionadas con CSRF/Token:');
var cookies = document.cookie.split(';');
var foundCookie = false;
cookies.forEach(function(cookie) {
	if (cookie.toLowerCase().includes('csrf') || cookie.toLowerCase().includes('token')) {
		console.log('   ' + cookie.trim().substring(0, 40) + '...');
		foundCookie = true;
	}
});
if (!foundCookie) console.log('   No encontradas');
console.log('');

// 5. Función para obtener token (de renovar_contrato_modal.js)
console.log('5. Prueba función obtenerTokenCSRF():');
function obtenerTokenCSRF() {
	if (typeof newtoken !== 'undefined' && newtoken) {
		return newtoken;
	}
	var tokenInput = document.querySelector('input[name="token"]');
	if (tokenInput && tokenInput.value) {
		return tokenInput.value;
	}
	var metaToken = document.querySelector('meta[name="csrf-token"]');
	if (metaToken && metaToken.getAttribute('content')) {
		return metaToken.getAttribute('content');
	}
	var dataToken = document.querySelector('[data-csrf]');
	if (dataToken && dataToken.getAttribute('data-csrf')) {
		return dataToken.getAttribute('data-csrf');
	}
	return '';
}

var token = obtenerTokenCSRF();
if (token) {
	console.log('   ✓ Token obtenido:', token.substring(0, 20) + '...');
} else {
	console.log('   ✗ No se pudo obtener token');
}
console.log('');

// 6. Prueba de solicitud AJAX
console.log('6. Prueba de solicitud AJAX:');
console.log('   Enviando solicitud POST a renovar_contrato.php con token...');

jQuery.ajax({
	type: 'POST',
	url: '/custom/puertasevilla/core/actions/renovar_contrato.php',
	data: {
		action: 'obtenerIPC',
		token: obtenerTokenCSRF()
	},
	dataType: 'json',
	success: function(response) {
		console.log('   ✓ Respuesta exitosa:', response);
	},
	error: function(xhr, status, error) {
		console.log('   ✗ Error:', xhr.status, error);
		console.log('   Respuesta:', xhr.responseText);
	}
});

console.log('');
console.log('=== FIN DIAGNÓSTICO ===');
