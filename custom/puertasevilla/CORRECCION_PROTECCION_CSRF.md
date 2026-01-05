# Corrección: Protección CSRF en Endpoints AJAX

## Problema Identificado

**Error CSRF**:
```
Access to this page this way (POST method or GET with a sensible value for 'action' parameter) 
is refused by CSRF protection in main.inc.php. Token not provided.
```

**Causa**: El endpoint AJAX estaba recibiendo solicitudes POST sin enviar un token CSRF válido. Dolibarr requiere un token CSRF para validar que las solicitudes son legítimas.

---

## Cómo Funciona CSRF en Dolibarr

### Token CSRF
- Se genera automáticamente en cada solicitud GET
- Se almacena en `$_SESSION['newtoken']`
- Está disponible en la variable global JavaScript `newtoken`
- Debe incluirse en el parámetro POST `token` para validación

### Validación
```php
// En PHP, se valida con:
validateToken($token)  // Retorna true/false
```

---

## Solución Implementada

### ✅ Cambio 1: Agregar función para obtener token CSRF (JavaScript)

**Archivo**: `renovar_contrato_modal.js`

```javascript
/**
 * Obtiene el token CSRF de Dolibarr
 */
function obtenerTokenCSRF() {
    // El token CSRF está en la variable global 'newtoken' de Dolibarr
    if (typeof newtoken !== 'undefined') {
        return newtoken;
    }
    // Fallback: intentar obtenerlo del DOM (input hidden)
    var tokenInput = document.querySelector('input[name="token"]');
    if (tokenInput) {
        return tokenInput.value;
    }
    return '';
}
```

**Funcionalidad**:
- Busca primero la variable global `newtoken` (la forma estándar en Dolibarr)
- Si no existe, busca un input hidden con name="token" (fallback)
- Retorna vacío si no encuentra token

---

### ✅ Cambio 2: Incluir token en solicitud obtenerIPC (JavaScript)

**Antes**:
```javascript
function obtenerIPCActual() {
    jQuery.post(
        '/custom/puertasevilla/core/actions/renovar_contrato.php',
        { action: 'obtenerIPC' },  // ✗ Sin token
        function(response) { ... }
    );
}
```

**Después**:
```javascript
function obtenerIPCActual() {
    var token = obtenerTokenCSRF();
    jQuery.post(
        '/custom/puertasevilla/core/actions/renovar_contrato.php',
        { action: 'obtenerIPC', token: token },  // ✓ Con token
        function(response) { ... }
    );
}
```

---

### ✅ Cambio 3: Incluir token en solicitud renovarContrato (JavaScript)

**Antes**:
```javascript
function procesarRenovacion(contratId) {
    var data = {
        action: 'renovarContrato',
        contrat_id: formData.get('contrat_id'),
        date_start: formData.get('date_start'),
        date_end: formData.get('date_end'),
        tipo_renovacion: formData.get('tipo_renovacion'),
        valor: formData.get('valor')
        // ✗ Sin token
    };
    
    jQuery.post(..., data, ...);
}
```

**Después**:
```javascript
function procesarRenovacion(contratId) {
    var token = obtenerTokenCSRF();
    
    var data = {
        action: 'renovarContrato',
        token: token,  // ✓ Token agregado
        contrat_id: formData.get('contrat_id'),
        date_start: formData.get('date_start'),
        date_end: formData.get('date_end'),
        tipo_renovacion: formData.get('tipo_renovacion'),
        valor: formData.get('valor')
    };
    
    jQuery.post(..., data, ...);
}
```

---

### ✅ Cambio 4: Validar token en endpoint PHP

**Archivo**: `renovar_contrato.php`

**Antes**:
```php
// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = sanitizeFilename($_POST['action']);
    
    if ($action === 'obtenerIPC') {
        // Obtener IPC...
    }
    // ✗ Sin validación de token
}
```

**Después**:
```php
// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // Validar token CSRF
    // En Dolibarr, el token se verifica con validateToken()
    $token = $_POST['token'] ?? '';
    if (!validateToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }
    
    $action = sanitizeFilename($_POST['action']);
    
    if ($action === 'obtenerIPC') {
        // Obtener IPC...
    }
    // ✓ Token validado
}
```

---

## Flujo de Validación CSRF

```
1. Usuario accede a /contrat/card.php?id=1
   ↓
2. Dolibarr genera token CSRF y lo guarda en $_SESSION['newtoken']
   ↓
3. Token también está disponible en variable global JavaScript: newtoken
   ↓
4. Usuario hace clic en "Renovar contrato"
   ↓
5. JavaScript llama obtenerTokenCSRF() → obtiene 'newtoken' de Dolibarr
   ↓
6. JavaScript envía AJAX POST con { action: 'obtenerIPC', token: 'abc123...' }
   ↓
7. PHP recibe solicitud y valida: validateToken($token)
   ↓
8. Si token válido → Procesa solicitud y retorna respuesta
   Si token inválido → Retorna error 403 "Token CSRF inválido"
```

---

## Archivos Afectados

| Archivo | Cambios |
|---------|---------|
| `/custom/puertasevilla/js/renovar_contrato_modal.js` | +Función obtenerTokenCSRF() |
| | +Incluye token en obtenerIPCActual() |
| | +Incluye token en procesarRenovacion() |
| `/custom/puertasevilla/core/actions/renovar_contrato.php` | +Validación de token CSRF |

---

## Verificación

Para verificar que la protección CSRF funciona correctamente:

### Test 1: Con token válido ✅
```javascript
// Desde consola de navegador:
var token = newtoken;  // Obtener token de Dolibarr
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', 
    { action: 'obtenerIPC', token: token },
    function(response) { console.log(response); }
);
// Resultado esperado: JSON con IPC actual
```

### Test 2: Sin token ❌
```javascript
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', 
    { action: 'obtenerIPC' },
    function(response) { console.log(response); }
);
// Resultado esperado: Error 403 "Token CSRF inválido"
```

### Test 3: Token inválido ❌
```javascript
jQuery.post('/custom/puertasevilla/core/actions/renovar_contrato.php', 
    { action: 'obtenerIPC', token: 'token_falso_123' },
    function(response) { console.log(response); }
);
// Resultado esperado: Error 403 "Token CSRF inválido"
```

---

## Notas Importantes

1. **Variable global `newtoken`**: Dolibarr expone el token CSRF como variable global JavaScript
2. **No es necesario desactivar CSRF**: La solución respeta la protección de Dolibarr
3. **Fallback a input hidden**: Si `newtoken` no está disponible, se busca en un input del formulario
4. **Validación dual**: Se valida en JavaScript (UI) y en PHP (servidor)

---

**Última actualización**: 29/12/2025
