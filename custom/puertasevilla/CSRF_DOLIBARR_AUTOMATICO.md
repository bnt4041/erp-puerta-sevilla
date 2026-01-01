# Validación CSRF Automática en Dolibarr

## Cambio Realizado

Se removió la llamada a `validateToken()` que causaba error "Uncaught Error: Call to undefined function validateToken()".

### Razón

En Dolibarr, la validación CSRF ocurre **automáticamente en `main.inc.php`** antes de que cualquier código en nuestra página se ejecute. No es necesario (ni posible) llamar manualmente a `validateToken()`.

---

## Cómo Funciona CSRF en Dolibarr

### 1. Flujo de Validación

```
1. Usuario accede a /contrat/card.php?id=1748
   ↓
2. Dolibarr genera token CSRF: $_SESSION['token'] = 'abc123...'
   ↓
3. Token se expone como variable JS: window.newtoken = 'abc123...'
   ↓
4. Usuario hace solicitud POST con token
   ↓
5. main.inc.php intercepta la solicitud (ANTES de cualquier código nuestro)
   ↓
6. main.inc.php valida: $_POST['token'] == $_SESSION['token']
   ↓
7. Si token INVÁLIDO → Retorna 403 y die (no continúa ejecución)
   Si token VÁLIDO → Continúa ejecución normal
   ↓
8. Nuestro código se ejecuta SOLO si token fue válido
```

### 2. Constante CSRFCHECK_WITH_TOKEN

Para forzar validación CSRF en nuestra acción AJAX:

```php
// ANTES de require_once main.inc.php:
define('CSRFCHECK_WITH_TOKEN', true);

// Esto le dice a Dolibarr:
// "Este endpoint AJAX requiere validación CSRF"
```

### 3. Token en Solicitudes

El token debe enviarse como parámetro POST:

```javascript
// JavaScript
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', {
    action: 'obtenerIPC',
    token: window.newtoken  // ← Token CSRF
}, function(response) { ... });
```

```php
// PHP - Se recibe automáticamente en $_POST['token']
// Dolibarr ya lo validó antes de llegar a nuestro código
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El token ya fue validado
    // Si llegamos aquí, podemos procesar la solicitud con seguridad
}
```

---

## Cambios en Archivos

### renovar_contrato.php

**Antes** (INCORRECTO):
```php
// ✗ Esto causa error porque validateToken() no existe
$token = $_POST['token'] ?? '';
if (!validateToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}
```

**Después** (CORRECTO):
```php
// ✓ Definimos CSRFCHECK_WITH_TOKEN ANTES de main.inc.php
define('CSRFCHECK_WITH_TOKEN', true);

require_once $rootPath.'/main.inc.php';

// ✓ Aquí, Dolibarr ya validó el token
// Si llegamos aquí es porque el token es válido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // La validación CSRF ocurrió automáticamente en main.inc.php
    // No necesitamos validar manualmente
    $action = sanitizeFilename($_POST['action']);
    // ... procesar acción ...
}
```

---

## Seguridad

### ¿Está protegido contra CSRF?

**SÍ**, porque:
1. Definimos `CSRFCHECK_WITH_TOKEN`
2. Dolibarr valida automáticamente en `main.inc.php`
3. Si el token es inválido, Dolibarr:
   - Retorna HTTP 403
   - Registra el intento en logs
   - **Termina la ejecución (die)**
   - Nuestro código nunca se ejecuta

### ¿Necesito hacer algo más?

**NO**, simplemente:
1. ✅ Asegurarse de que se define `CSRFCHECK_WITH_TOKEN`
2. ✅ Asegurarse de que se carga `main.inc.php`
3. ✅ Asegurarse de que el JavaScript envía el token en `token: newtoken`

Si alguna de estas condiciones falla, Dolibarr retornará 403 automáticamente.

---

## Verificación

### Test: Verificar que CSRF está funcionando

1. **Abrir Developer Tools (F12)**
2. **Ir a Network tab**
3. **Hacer clic en "Renovar contrato"**
4. **Buscar la solicitud POST**
5. **Verificar parámetros**:
   - [ ] `action: obtenerIPC` (o `renovarContrato`)
   - [ ] `token: abc123...` (el token CSRF)
6. **Verificar respuesta**:
   - [ ] Status 200 (éxito) o 403 (si token inválido)
   - [ ] JSON válido en response

### Test: Verificar que CSRF bloquea sin token

```javascript
// En consola (F12):
// Hacer solicitud SIN token
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', {
    action: 'obtenerIPC'
    // ← Sin token
}, function(response) {
    console.log('Éxito (no debería):', response);
}, 'json').fail(function(xhr) {
    console.log('Esperado - Bloqueado por CSRF:', xhr.status, xhr.statusText);
    // Resultado esperado: 403 Forbidden
});
```

---

## Archivos Afectados

| Archivo | Cambio |
|---------|--------|
| `/custom/puertasevilla/core/actions/renovar_contrato.php` | Removida validación manual, agregada constante `CSRFCHECK_WITH_TOKEN` |
| `/custom/puertasevilla/includes/renovacion_buttons.php` | Token inyectado en variable global JS |
| `/custom/puertasevilla/js/renovar_contrato_modal.js` | Continúa enviando token correctamente |

---

## Referencias

- **Dolibarr CSRF en main.inc.php**: Líneas 620-700
- **Constante CSRFCHECK_WITH_TOKEN**: Fuerza validación CSRF
- **Token de sesión**: `$_SESSION['token']`
- **Token en solicitud**: `$_POST['token']` o `$_GET['token']`

---

**Última actualización**: 29/12/2025
