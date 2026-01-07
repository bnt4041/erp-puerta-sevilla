# 🏗️ Arquitectura del Sistema - Zona de Empleado

Este documento describe la arquitectura completa del módulo Zona de Empleado y cómo se integran las diferentes piezas.

---

## 📊 Vista General del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                    ZONA DE EMPLEADO (Portal)                     │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   Dashboard  │  │    Perfil    │  │  Página X    │          │
│  │  index.php   │  │  profile.php │  │  custom.php  │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                   │
│         └──────────────────┴──────────────────┘                  │
│                            │                                      │
│                    ┌───────▼────────┐                           │
│                    │  HookManager   │                           │
│                    │   (Dolibarr)   │                           │
│                    └───────┬────────┘                           │
│                            │                                      │
└────────────────────────────┼──────────────────────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
   ┌────▼─────┐        ┌────▼─────┐        ┌────▼─────┐
   │ Módulo A │        │ Módulo B │        │ Módulo C │
   │          │        │          │        │          │
   │ Actions  │        │ Actions  │        │ Actions  │
   │  Class   │        │  Class   │        │  Class   │
   └──────────┘        └──────────┘        └──────────┘
```

---

## 🔄 Flujo de Ejecución

### 1. Carga de Página

```
Usuario accede → index.php
         ↓
    main.inc.php (Dolibarr core)
         ↓
    Verificar sesión
         ↓
    Verificar permisos
         ↓
    Inicializar HookManager
         ↓
    Ejecutar hooks
         ↓
    Renderizar página
```

### 2. Sistema de Hooks

```
┌──────────────────────────────────────────────────────┐
│             Página (index.php / profile.php)         │
│                                                       │
│  1. Preparar parámetros                              │
│     $parameters = ['extensions' => []]               │
│                                                       │
│  2. Ejecutar hook                                    │
│     $hookmanager->executeHooks('hookName',           │
│                                 $parameters, ...)    │
│         │                                             │
│         └──────────────────┐                         │
│                            │                         │
└────────────────────────────┼─────────────────────────┘
                             │
         ┌───────────────────┴───────────────────┐
         │                                        │
    ┌────▼─────┐                           ┌────▼─────┐
    │ Módulo A │                           │ Módulo B │
    │          │                           │          │
    │ public function hookName()           │ public function hookName()
    │ {                                    │ {
    │   $parameters['extensions'][]        │   $parameters['extensions'][]
    │   return 0;                          │   return 0;
    │ }                                    │ }
    └──────────┘                           └──────────┘
         │                                        │
         └───────────────────┬───────────────────┘
                             │
┌────────────────────────────▼─────────────────────────┐
│             Página (index.php / profile.php)         │
│                                                       │
│  3. Recibir resultados modificados                   │
│     $extensions = $parameters['extensions']          │
│                                                       │
│  4. Renderizar contenido                             │
│     foreach ($extensions as $ext) { ... }            │
│                                                       │
└──────────────────────────────────────────────────────┘
```

---

## 📁 Estructura de Archivos Detallada

### Módulo Principal: zonaempleado/

```
zonaempleado/
│
├── 📄 Páginas Principales
│   ├── index.php              # Dashboard principal
│   │   └── Hooks: registerEmployeeZoneExtension, addQuickLinks,
│   │              getRecentActivity, addEmployeeZoneContent
│   │
│   └── profile.php            # Página de perfil
│       └── Hooks: getUserProfileStats, getUserProfileActions,
│                  getRecentActivity, addEmployeeProfileContent
│
├── 📚 Documentación
│   ├── README.md              # Documentación principal
│   ├── GETTING_STARTED.md     # Guía de inicio rápido
│   ├── INTEGRATION_EXAMPLE.md # Ejemplos de integración
│   ├── CHANGELOG.md           # Historial de cambios
│   ├── ARCHITECTURE.md        # Este archivo
│   └── docs/
│       ├── INDEX.md           # Índice de documentación
│       └── QUICK_REFERENCE.md # Referencia rápida
│
├── 🎨 Presentación
│   ├── tpl/
│   │   ├── header.tpl.php     # Header personalizado con menú
│   │   └── footer.tpl.php     # Footer con scripts
│   │
│   ├── css/
│   │   └── zonaempleado.css.php  # CSS dinámico con colores del tema
│   │
│   └── js/
│       └── zonaempleado.js.php   # JavaScript para interactividad

### Assets por módulo (CSS/JS)

Para mantener el layout de Zona de Empleado **normalizado** y permitir que otros módulos añadan UI propia sin romper el renderizado, el header admite la carga opcional de assets adicionales en el `<head>`:

- `$GLOBALS['zonaempleado_extra_css']`: rutas a CSS (por ejemplo `'/custom/mimodulo/css/mimodulo.css.php'`).
- `$GLOBALS['zonaempleado_extra_js']`: rutas a JS (por ejemplo `'/custom/mimodulo/js/mimodulo.js.php'`).

El módulo/página que integra debe definirlos **antes** de llamar a `zonaempleado_print_header()`.
│
├── 🔧 Lógica de Negocio
│   ├── class/
│   │   ├── zonaempleado.class.php
│   │   │   └── Clase principal (futuras funcionalidades)
│   │   │
│   │   └── actions_zonaempleado.class.php
│   │       └── Definición de hooks (base, sin implementación)
│   │
│   ├── lib/
│   │   └── zonaempleado.lib.php
│   │       ├── zonaempleado_get_extensions()
│   │       ├── zonaempleado_check_permission()
│   │       └── Funciones auxiliares
│   │
│   └── core/
│       └── modules/
│           └── modZonaEmpleado.class.php
│               ├── Configuración del módulo
│               ├── Permisos
│               └── Definición de contextos de hooks
│
└── 🌍 Internacionalización
    └── langs/
        └── es_ES/
            └── zonaempleado.lang  # Traducciones español
```

### Módulo Demo: zonaempleadodemo/

```
zonaempleadodemo/
│
├── 📄 Páginas
│   └── employee.php           # Página de demostración
│       └── 4 tarjetas informativas sobre el sistema
│
├── 📚 Documentación
│   └── README.md              # Guía del módulo demo
│
├── 🔧 Lógica
│   ├── class/
│   │   └── actions_zonaempleadodemo.class.php
│   │       └── Implementación de los 8 hooks:
│   │           ├── registerEmployeeZoneExtension
│   │           ├── addQuickLinks
│   │           ├── getEmployeeZoneMenu
│   │           ├── getRecentActivity
│   │           ├── getUserProfileStats
│   │           ├── getUserProfileActions
│   │           ├── addEmployeeZoneContent
│   │           └── addEmployeeProfileContent
│   │
│   └── core/modules/
│       └── modZonaEmpleadoDemo.class.php
│           ├── numero: 500100
│           ├── depends: ['zonaempleado']
│           └── hooks: ['zonaempleadoindex', 'zonaempleadoprofile']
│
└── 🌍 Internacionalización
    └── langs/es_ES/
        └── zonaempleadodemo.lang
```

---

## 🔌 Sistema de Hooks en Detalle

### Contextos de Hooks

El módulo define 2 contextos principales:

```php
// En modZonaEmpleado.class.php
$this->module_parts = array(
    'hooks' => array(
        'zonaempleadoindex',    // Dashboard (index.php)
        'zonaempleadoprofile'   // Perfil (profile.php)
    )
);
```

### Hooks por Contexto

#### Contexto: `zonaempleadoindex` (Dashboard)

| Hook | Parámetro | Descripción |
|------|-----------|-------------|
| `registerEmployeeZoneExtension` | `extensions[]` | Registrar módulos como tarjetas |
| `addQuickLinks` | `quickLinks[]` | Enlaces rápidos |
| `getEmployeeZoneMenu` | `menu[]` | Items del menú |
| `getRecentActivity` | `activities[]` | Actividades recientes |
| `addEmployeeZoneContent` | `content[]` | Widgets HTML |

#### Contexto: `zonaempleadoprofile` (Perfil)

| Hook | Parámetro | Descripción |
|------|-----------|-------------|
| `getUserProfileStats` | `stats[]` | Estadísticas del usuario |
| `getUserProfileActions` | `actions[]` | Botones de acción |
| `getRecentActivity` | `activities[]` | Actividades (compartido) |
| `addEmployeeProfileContent` | `profileContent[]` | Secciones HTML |

### Estructura de Datos por Hook

#### 1. registerEmployeeZoneExtension
```php
$parameters['extensions'][] = [
    'title' => string,        // Requerido
    'description' => string,  // Opcional
    'url' => string,          // Requerido
    'icon' => string,         // Requerido (FontAwesome)
    'position' => int,        // Opcional (default: 999)
    'permissions' => bool     // Opcional (default: true)
];
```

#### 2. addQuickLinks
```php
$parameters['quickLinks'][] = [
    'label' => string,        // Requerido
    'url' => string,          // Requerido
    'icon' => string,         // Requerido (FontAwesome)
    'position' => int,        // Opcional (default: 999)
    'target' => string        // Opcional ('_self', '_blank')
];
```

#### 3. getEmployeeZoneMenu
```php
$parameters['menu'][] = [
    'label' => string,        // Requerido
    'url' => string,          // Requerido
    'icon' => string,         // Requerido (FontAwesome)
    'position' => int,        // Opcional (default: 999)
    'active' => bool          // Opcional (resaltar si activo)
];
```

#### 4. getRecentActivity
```php
$parameters['activities'][] = [
    'date' => int|string,     // Requerido (timestamp o fecha)
    'title' => string,        // Requerido
    'description' => string,  // Opcional
    'icon' => string,         // Opcional (FontAwesome)
    'url' => string           // Opcional (enlace a detalle)
];
```

#### 5. getUserProfileStats
```php
$parameters['stats'][] = [
    'label' => string,        // Requerido
    'value' => mixed,         // Requerido (número, string, etc.)
    'icon' => string,         // Requerido (FontAwesome)
    'position' => int         // Opcional (default: 999)
];
```

#### 6. getUserProfileActions
```php
$parameters['actions'][] = [
    'label' => string,        // Requerido
    'url' => string,          // Requerido
    'icon' => string,         // Requerido (FontAwesome)
    'class' => string,        // Opcional ('butAction', 'butActionDelete')
    'position' => int         // Opcional (default: 999)
];
```

#### 7. addEmployeeZoneContent
```php
$parameters['content'][] = [
    'html' => string,         // Requerido (HTML completo del widget)
    'position' => int         // Opcional (default: 999)
];
```

#### 8. addEmployeeProfileContent
```php
$parameters['profileContent'][] = [
    'html' => string,         // Requerido (HTML completo de la sección)
    'position' => int         // Opcional (default: 999)
];
```

---

## 🎨 Sistema de Estilos

### Generación Dinámica de CSS

```
Usuario solicita → zonaempleado.css.php
         ↓
    Incluir main.inc.php (sin NOREQUIREDB)
         ↓
    Conectar a base de datos
         ↓
    SQL: SELECT value FROM vol_const
         WHERE name = 'THEME_ELDY_TOPMENU_BACK1'
         ↓
    Resultado: rgb(173,15,15)
         ↓
    Generar variable CSS:
    :root { --ze-primary-color: rgb(173,15,15); }
         ↓
    Enviar headers HTTP (Content-Type: text/css)
         ↓
    Output CSS completo
```

### Variables CSS Disponibles

```css
:root {
    /* Color principal (heredado del tema) */
    --ze-primary-color: rgb(173,15,15);
    
    /* Colores del sistema (pueden personalizarse) */
    --ze-secondary-color: #764ba2;
    --ze-background: #f8f9fa;
    --ze-text: #333;
    --ze-text-light: #6c757d;
    --ze-border: #dee2e6;
    --ze-shadow: rgba(0,0,0,0.1);
    
    /* Espaciado */
    --ze-spacing-xs: 0.25rem;
    --ze-spacing-sm: 0.5rem;
    --ze-spacing-md: 1rem;
    --ze-spacing-lg: 1.5rem;
    --ze-spacing-xl: 2rem;
    
    /* Bordes */
    --ze-radius: 8px;
    --ze-radius-lg: 12px;
    
    /* Transiciones */
    --ze-transition: all 0.3s ease;
}
```

### Clases CSS Principales

```css
/* Contenedores */
.ez-container         /* Contenedor principal */
.ez-card              /* Tarjeta estándar */
.ez-card-header       /* Cabecera de tarjeta */
.ez-card-body         /* Cuerpo de tarjeta */

/* Dashboard */
.ez-dashboard         /* Grid del dashboard */
.ez-profile-card      /* Tarjeta de perfil */
.ez-extensions-grid   /* Grid de extensiones */
.ez-quick-links       /* Grid de enlaces rápidos */
.ez-recent-activity   /* Lista de actividades */

/* Perfil */
.ez-profile-stats     /* Grid de estadísticas */
.ez-stat-box          /* Caja de estadística individual */
.ez-profile-actions   /* Contenedor de acciones */

/* Navegación */
.ez-header            /* Header principal */
.ez-menu              /* Menú lateral */
.ez-menu-item         /* Item del menú */
.ez-menu-item.active  /* Item activo */

/* Utilidades */
.ez-icon              /* Iconos FontAwesome */
.ez-button            /* Botones estándar */
.ez-link              /* Enlaces estándar */
```

---

## 🔐 Sistema de Permisos

### Jerarquía de Permisos

```
Módulo: zonaempleado
    │
    ├── access (Acceso)
    │   └── read → Puede acceder al portal
    │
    ├── use (Uso)
    │   └── write → Puede usar funcionalidades
    │
    └── config (Configuración)
        └── write → Puede configurar el portal
```

### Verificación en Código

```php
// En index.php
if (!$user->rights->zonaempleado->access->read) {
    accessforbidden();  // 403 Forbidden
}

// En funcionalidades específicas
if ($user->rights->zonaempleado->use->write) {
    // Usuario puede usar esta funcionalidad
}

// En configuración
if ($user->rights->zonaempleado->config->write) {
    // Usuario puede configurar
}
```

### Permisos en Hooks

```php
// Verificar permisos antes de agregar contenido
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    global $user;
    
    // Verificar permiso del módulo base
    if (!$user->rights->zonaempleado->use->write) {
        return 0;  // No agregar nada
    }
    
    // Verificar permiso del módulo propio
    if (!$user->rights->mimodulo->read) {
        return 0;  // No agregar nada
    }
    
    // Usuario tiene permisos, agregar contenido
    // ...
}
```

---

## 💾 Base de Datos

### Tablas Utilizadas

El módulo NO crea tablas propias, pero usa:

```sql
-- Configuración de Dolibarr
vol_const
    ├── name: 'THEME_ELDY_TOPMENU_BACK1'
    └── value: 'rgb(173,15,15)'  -- Color del tema

-- Usuarios
vol_user
    └── Información de usuarios para el perfil

-- Permisos
vol_rights_def
    └── Definición de permisos del módulo

vol_user_rights
    └── Asignación de permisos a usuarios
```

### Queries Típicas

```sql
-- Obtener color del tema (en zonaempleado.css.php)
SELECT value 
FROM vol_const 
WHERE name = 'THEME_ELDY_TOPMENU_BACK1'
AND entity IN (0, 1);

-- Verificar permisos (manejado por Dolibarr)
SELECT r.id 
FROM vol_user_rights r
WHERE r.fk_user = :user_id
AND r.fk_id = :right_id;
```

---

## 🔄 Ciclo de Vida de una Página

### Ejemplo: Carga de index.php

```
1. REQUEST: GET /custom/zonaempleado/index.php
         ↓
2. INCLUDE: main.inc.php
         ↓
3. AUTHENTICATE: Verificar sesión de usuario
         ↓
4. AUTHORIZE: Verificar permisos (zonaempleado->access->read)
         ↓
5. INITIALIZE:
   - $langs->load("zonaempleado")
   - $hookmanager = new HookManager($db)
   - $hookmanager->initHooks(['zonaempleadoindex'])
         ↓
6. PREPARE HOOKS:
   - $parameters = ['extensions' => [], 'quickLinks' => [], ...]
   - $object = null
   - $action = ''
         ↓
7. EXECUTE HOOKS:
   - $hookmanager->executeHooks('registerEmployeeZoneExtension', ...)
   - $hookmanager->executeHooks('addQuickLinks', ...)
   - $hookmanager->executeHooks('getEmployeeZoneMenu', ...)
   - $hookmanager->executeHooks('getRecentActivity', ...)
         ↓
8. PROCESS RESULTS:
   - $extensions = $parameters['extensions']
   - usort($extensions, ...) // Ordenar por position
   - $quickLinks = $parameters['quickLinks']
   - usort($quickLinks, ...)
         ↓
9. RENDER:
   - include 'tpl/header.tpl.php'
   - foreach ($extensions as $ext) { ... }
   - foreach ($quickLinks as $link) { ... }
   - include 'tpl/footer.tpl.php'
         ↓
10. RESPONSE: HTML completo al navegador
```

---

## 🧩 Integración de Módulos Externos

### Paso a Paso

```
1. DESARROLLO DEL MÓDULO
   ├── Crear estructura del módulo
   ├── Definir modMiModulo.class.php
   └── Registrar hooks en module_parts['hooks']

2. IMPLEMENTAR HOOKS
   ├── Crear actions_mimodulo.class.php
   ├── Implementar métodos de hooks necesarios
   └── Verificar permisos en cada hook

3. ACTIVACIÓN
   ├── Usuario activa el módulo desde admin
   ├── Dolibarr registra los hooks
   └── HookManager conoce el nuevo módulo

4. EJECUCIÓN
   ├── Usuario accede a Zona de Empleado
   ├── HookManager llama a todos los módulos registrados
   ├── Módulo agrega su contenido a los parámetros
   └── Zona de Empleado renderiza todo el contenido
```

### Diagrama de Integración

```
┌─────────────────────────────────────────────────────────┐
│          ZONA DE EMPLEADO (Core)                        │
│                                                          │
│  ┌────────────────────────────────────────────────┐   │
│  │         HookManager (Dolibarr)                  │   │
│  │                                                  │   │
│  │  registered_hooks = [                           │   │
│  │    'zonaempleadoindex' => [                    │   │
│  │       'modulo1',                                │   │
│  │       'modulo2',                                │   │
│  │       'modulo3'                                 │   │
│  │    ],                                           │   │
│  │    'zonaempleadoprofile' => [...]              │   │
│  │  ]                                              │   │
│  └────────────────────────────────────────────────┘   │
│                                                          │
└──────────────────────────┬───────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
   ┌────▼─────┐      ┌────▼─────┐      ┌────▼─────┐
   │ Módulo 1 │      │ Módulo 2 │      │ Módulo 3 │
   │          │      │          │      │          │
   │ - Hooks  │      │ - Hooks  │      │ - Hooks  │
   │ - Perms  │      │ - Perms  │      │ - Perms  │
   │ - Logic  │      │ - Logic  │      │ - Logic  │
   └──────────┘      └──────────┘      └──────────┘
```

---

## 📈 Performance y Optimización

### Estrategias de Optimización

```
1. CARGA CONDICIONAL
   └── Solo cargar extensiones si el usuario tiene permisos

2. CACHE ESTÁTICO
   └── Variables estáticas en métodos para evitar queries repetidas

3. LÍMITES
   └── Limitar cantidad de actividades (LIMIT 10)

4. ORDENAMIENTO EFICIENTE
   └── usort() en PHP en lugar de ORDER BY múltiple en SQL

5. CSS/JS DINÁMICO
   └── Generado una vez y cacheable por el navegador
```

### Ejemplo de Cache en Hook

```php
public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
{
    global $db, $user;
    
    // Cache estático para evitar queries múltiples
    static $activities = null;
    
    if ($activities === null) {
        $activities = [];
        
        // Query pesada solo una vez
        $sql = "SELECT ...";
        $resql = $db->query($sql);
        
        while ($obj = $db->fetch_object($resql)) {
            $activities[] = [/* ... */];
        }
    }
    
    // Agregar actividades cacheadas
    if (isset($parameters['activities'])) {
        $parameters['activities'] = array_merge(
            $parameters['activities'],
            $activities
        );
    }
    
    return 0;
}
```

---

## 🔍 Debugging y Logging

### Niveles de Debug

```php
// En conf.php
$dolibarr_main_prod = 0;  // Modo desarrollo (más logs)
$dolibarr_main_prod = 1;  // Modo producción (menos logs)
```

### Logging en Hooks

```php
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    global $user;
    
    dol_syslog("MiModulo: Hook addQuickLinks ejecutado", LOG_DEBUG);
    dol_syslog("MiModulo: User ID = ".$user->id, LOG_DEBUG);
    dol_syslog("MiModulo: Parameters = ".print_r($parameters, true), LOG_DEBUG);
    
    // ... resto del código ...
    
    return 0;
}
```

### Ubicación de Logs

```
/documents/dolibarr.log
```

---

## 📚 Recursos Adicionales

- [Dolibarr Developer Documentation](https://wiki.dolibarr.org/index.php/Developer_documentation)
- [Hook System](https://wiki.dolibarr.org/index.php/Hooks_system)
- [Module Development](https://wiki.dolibarr.org/index.php/Module_development)

---

**Zona de Empleado** - Documentación de Arquitectura v1.0
