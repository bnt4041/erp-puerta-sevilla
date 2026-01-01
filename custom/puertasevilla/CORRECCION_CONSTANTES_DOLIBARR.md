# Corrección: Constantes NOREQUIREUSER y NOREQUIREDB en Dolibarr

## Problema Identificado

**Error Fatal**:
```
Fatal error: Uncaught Error: Call to a member function fetch() on null in /var/www/html/htdocs/main.inc.php:1090
```

**Causa**: El archivo `renovar_contrato.php` estaba definiendo las constantes `NOREQUIREUSER` y `NOREQUIREDB`, lo que prevenía que Dolibarr inicializara correctamente:
- La sesión del usuario (`$user` quedaba null)
- La conexión a base de datos (`$db` quedaba null)

Cuando `main.inc.php` intentaba ejecutar `$user->fetch()` en la línea 1090, fallaba porque `$user` era null.

---

## Solución Implementada

### ✅ Cambio 1: renovar_contrato.php

**Antes** (INCORRECTO):
```php
// ✗ INCORRECTO - Previene inicialización de sesión y BD
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}

require_once $rootPath.'/main.inc.php';
```

**Después** (CORRECTO):
```php
// ✓ CORRECTO - Permite que Dolibarr cargue sesión y BD normalmente
// Este es un endpoint AJAX que requiere sesión autenticada
// NO establecemos NOREQUIREUSER ni NOREQUIREDB para permitir que Dolibarr
// cargue correctamente la sesión y la base de datos

require_once $rootPath.'/main.inc.php';

// Validar que la sesión esté cargada
if (empty($user) || empty($user->id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Responder con JSON
header('Content-Type: application/json; charset=utf-8');
```

**Cambios**:
- ✅ Removidas las constantes NOREQUIREUSER, NOREQUIREDB, NOREQUIREMENU
- ✅ Ahora Dolibarr carga la sesión correctamente
- ✅ Se valida que `$user->id` no esté vacío DESPUÉS de cargar Dolibarr
- ✅ Se establece header JSON una sola vez al inicio

---

### ✅ Cambio 2: test_renovar.php

**Antes** (INCORRECTO):
```php
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}

require_once $rootPath.'/main.inc.php';
```

**Después** (CORRECTO):
```php
// Encontrar main.inc.php
require_once $rootPath.'/main.inc.php';

// Si llegamos aquí, Dolibarr se cargó correctamente
```

**Cambios**:
- ✅ Removidas las constantes innecesarias
- ✅ Permite que Dolibarr cargue sesión normalmente

---

### ✅ Cambio 3: renovacion_buttons.php

**Antes** (INCORRECTO):
```php
if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}

global $db, $user, $conf, $langs, $object;
```

**Después** (CORRECTO):
```php
// Evitar incluir dos veces
if (defined('PUERTASEVILLA_RENOVACION_BUTTONS_LOADED')) {
    return;
}
define('PUERTASEVILLA_RENOVACION_BUTTONS_LOADED', true);

global $db, $user, $conf, $langs, $object;
```

**Cambios**:
- ✅ Removidas las constantes innecesarias
- ✅ Este archivo se incluye DENTRO de card.php, así que ya tendrá Dolibarr cargado

---

## Cuándo Usar NOREQUIREUSER y NOREQUIREDB

### ✅ USO CORRECTO: Páginas públicas/sin sesión
```php
// ✓ Usar NOREQUIREUSER y NOREQUIREDB para páginas públicas
define('NOREQUIREUSER', '1');
define('NOREQUIREDB', '1');
define('NOREQUIREMENU', '1');

require_once $rootPath.'/main.inc.php';
```

**Ejemplos**: Formularios de registro públicos, APIs sin autenticación, webhooks

### ❌ USO INCORRECTO: Endpoints AJAX con autenticación
```php
// ✗ NO usar estas constantes si necesitas $user y $db
// ✗ INCORRECTO:
define('NOREQUIREUSER', '1');
require_once $rootPath.'/main.inc.php';

// ✓ CORRECTO:
require_once $rootPath.'/main.inc.php';

if (empty($user->id)) {
    // Manejar error de sesión
}
```

---

## Archivos Afectados

| Archivo | Estado | Cambio |
|---------|--------|--------|
| `/custom/puertasevilla/core/actions/renovar_contrato.php` | ✅ CORREGIDO | Removidas constantes NOREQUIRE* |
| `/custom/puertasevilla/test_renovar.php` | ✅ CORREGIDO | Removidas constantes NOREQUIRE* |
| `/custom/puertasevilla/includes/renovacion_buttons.php` | ✅ CORREGIDO | Removidas constantes NOREQUIRE* |

---

## Verificación

Para verificar que los cambios funcionan:

1. **Eliminar caché del navegador**: Ctrl+Shift+Del
2. **Acceder a un contrato**: http://localhost/contrat/card.php?id=1
3. **Hacer clic en "Renovar contrato"**
4. **Verificar en consola (F12)** que no haya errores

**Errores esperados de antes**:
- ❌ "Call to a member function fetch() on null"
- ❌ "Cannot read properties of undefined"

**Estado esperado ahora**:
- ✅ Modal abre sin errores
- ✅ Se puede obtener IPC
- ✅ Se puede guardar renovación

---

## Reglas de Dolibarr sobre Constantes

### NOREQUIREUSER
- Cuando está definida: No requiere sesión de usuario autenticada
- `$user` será null o una sesión genérica

### NOREQUIREDB
- Cuando está definida: No conecta a la base de datos
- `$db` será null

### NOREQUIREMENU
- Cuando está definida: No carga el menú de navegación
- Reduce carga de memoria y mejora velocidad

### Restricción importante:
> **Si defines NOREQUIREDB, TAMBIÉN debes definir NOREQUIREMENU**
> 
> (O no definir ninguno de los dos)

En nuestro caso, simplemente removemos ambas constantes porque necesitamos sesión y BD.

---

## Impacto

- ✅ Error fatal eliminado
- ✅ AJAX endpoint funciona correctamente
- ✅ Sesión de usuario se carga normalmente
- ✅ Base de datos disponible para operaciones

---

**Última actualización**: 29/12/2025
