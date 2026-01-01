# Diagnóstico y Resolución del Token CSRF

## Problema Reportado

```
POST /custom/puertasevilla/core/actions/renovar_contrato.php
Token CSRF "Token not provided. If you access your server behind a proxy..."
```

El error indica que el token CSRF no se está encontrando en la solicitud POST.

---

## Causas Posibles

1. **Token no se obtiene correctamente**: `newtoken` no está disponible en la página
2. **Token no se envía en la solicitud**: El JavaScript no está incluyendo el token
3. **Token se invalida**: El token expire entre solicitudes
4. **Configuración de Dolibarr**: CSRF está demasiado estricto

---

## Diagnóstico Paso a Paso

### Paso 1: Abrir Consola del Navegador
1. Ir a `/contrat/card.php?id=1748`
2. Presionar `F12` para abrir Developer Tools
3. Ir a la pestaña "Console"

### Paso 2: Ejecutar Script de Diagnóstico

Copiar y ejecutar en la consola:

```javascript
// Script para diagnosticar token CSRF
console.log('Verificando token CSRF...');

// 1. Verificar variable global newtoken
if (typeof newtoken !== 'undefined') {
    console.log('✓ newtoken disponible:', newtoken.substring(0, 20) + '...');
} else {
    console.log('✗ newtoken NO disponible');
}

// 2. Verificar inputs hidden
var tokenInput = document.querySelector('input[name="token"]');
if (tokenInput) {
    console.log('✓ Input token encontrado:', tokenInput.value.substring(0, 20) + '...');
} else {
    console.log('✗ Input token NO encontrado');
}

// 3. Hacer solicitud de prueba
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', {
    action: 'obtenerIPC',
    token: typeof newtoken !== 'undefined' ? newtoken : ''
}, function(response) {
    console.log('✓ Solicitud exitosa:', response);
}, 'json').fail(function(xhr) {
    console.log('✗ Error:', xhr.status, xhr.responseText);
});
```

### Paso 3: Interpretar Resultados

**Caso A: newtoken disponible ✓**
```
✓ newtoken disponible: abc123def456...
✓ Input token encontrado: abc123def456...
✓ Solicitud exitosa: {success: true, ipc: 2.4, ...}
```
→ **Todo funciona correctamente**

**Caso B: newtoken NO disponible ✗**
```
✗ newtoken NO disponible
✗ Input token NO encontrado
```
→ **Ver soluciones abajo**

**Caso C: Token no se envía**
```
✗ newtoken NO disponible
✓ Input token encontrado: abc123def456...
```
→ **El token existe pero no se envía correctamente**

---

## Soluciones Según Diagnóstico

### Solución 1: Si `newtoken` no está disponible

**Causa**: La página de contrato no expone el token como variable global.

**Opción A - Inyectar token en la página** (recomendado):

Agregar al archivo `renovacion_buttons.php` (que se incluye en card.php):

```php
// En /custom/puertasevilla/includes/renovacion_buttons.php
// Agregar después de la validación inicial:

global $user, $db, $conf, $langs;

// Exponemos el token CSRF como variable global JavaScript
if (!empty($_SESSION['newtoken'])) {
    echo '<script>var newtoken = "'.sanitizeFilename($_SESSION['newtoken']).'";</script>';
} else {
    // Fallback: generar un token nuevo si no existe
    dol_syslog("Warning: newtoken not available in session", LOG_WARNING);
    echo '<script>var newtoken = "";</script>';
}
```

**Opción B - Usar meta tag**:

```html
<meta name="csrf-token" content="<?php echo sanitizeFilename($_SESSION['newtoken'] ?? ''); ?>">
```

**Opción C - Usar input hidden**:

```html
<input type="hidden" name="token" value="<?php echo sanitizeFilename($_SESSION['newtoken'] ?? ''); ?>">
```

---

### Solución 2: Si el token se invalida

**Causa**: El token CSRF expira después de cierto tiempo o sesión.

**Solución**: Usar `validateToken()` con parámetro `'auto'`:

```php
// En renovar_contrato.php, cambiar la validación:

// Antes:
if (!validateToken($token)) {
    // Error
}

// Después:
// validateToken verifica automáticamente el token de sesión
if (!validateToken($token, 'auto')) {
    // Error
}
```

---

### Solución 3: Si Dolibarr está muy estricto

**Causa**: Configuración CSRF muy restrictiva en Dolibarr.

**Opción A - Desactivar para este endpoint** (NO recomendado en producción):

```php
// En renovar_contrato.php, antes de require main.inc.php:
// NOTA: Esto es un último recurso, usar solo si es necesario
// define('MAIN_SECURITY_CSRF_WITH_TOKEN', 0);
```

**Opción B - Usar token de sesión directo**:

```php
// En renovar_contrato.php, después de cargar Dolibarr:
if (empty($_POST['token'])) {
    // Si no hay token en POST, usar el de sesión
    $_POST['token'] = $_SESSION['newtoken'] ?? '';
}
```

---

### Solución 4: Verificar configuración de Dolibarr

1. Acceder a Admin → Configuración → Seguridad
2. Buscar "MAIN_SECURITY_CSRF_WITH_TOKEN"
3. Verificar que esté en 2 (máximo nivel, requiere token)

**Si es 0**: CSRF desactivado (inseguro)
**Si es 1**: CSRF básico
**Si es 2**: CSRF máximo (recomendado)

---

## Verificación de Funcionamiento

### Test de Token CSRF

```bash
#!/bin/bash

# 1. Obtener sesión y token
curl -c cookies.txt http://doli.local/user/login.php -s > /dev/null

# 2. Obtener token de sesión
TOKEN=$(curl -b cookies.txt http://doli.local/contrat/card.php?id=1748 -s | grep -oP 'newtoken = "\K[^"]+')

# 3. Hacer solicitud POST
curl -b cookies.txt \
  -X POST \
  -d "action=obtenerIPC&token=$TOKEN" \
  http://doli.local/custom/puertasevilla/core/actions/renovar_contrato.php

# Resultado esperado: JSON con IPC actual
```

---

## Checklist de Verificación

- [ ] `newtoken` está disponible en variable global JavaScript
- [ ] `obtenerTokenCSRF()` retorna un token no vacío
- [ ] El token se envía en parámetro POST `token`
- [ ] PHP recibe el token en `$_POST['token']`
- [ ] `validateToken()` valida correctamente el token
- [ ] No hay errores 403 en respuesta

---

## Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `js/renovar_contrato_modal.js` | Token en input hidden, múltiples fuentes para obtener token |
| `core/actions/renovar_contrato.php` | Búsqueda desde múltiples fuentes, validación mejorada |
| `js/diagnostico_csrf.js` | Script para diagnosticar problemas CSRF |

---

## Próximos Pasos

1. **Ejecutar diagnóstico**: Usar `diagnostico_csrf.js` en consola
2. **Identificar causa**: Ver qué falla en el diagnóstico
3. **Aplicar solución**: Seguir la solución según el caso
4. **Verificar funcionamiento**: Hacer clic en "Renovar contrato"

---

**Última actualización**: 29/12/2025
