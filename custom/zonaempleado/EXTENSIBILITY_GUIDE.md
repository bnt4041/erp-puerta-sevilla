# Zona de Empleado - Guía de Extensibilidad

## Descripción

El módulo **Zona de Empleado** proporciona un portal independiente y simplificado para los empleados de la empresa. Este módulo está diseñado para ser extensible, permitiendo que otros módulos agreguen funcionalidades específicas de manera integrada.

## Características Principales

- **Portal Independiente**: Interface limpia y simplificada separada del backoffice
- **Autenticación Integrada**: Utiliza las credenciales existentes de Dolibarr
- **Sistema de Permisos**: Control granular de acceso y funcionalidades
- **Responsive Design**: Optimizado para móviles y tablets
- **Extensible**: Sistema de hooks para que otros módulos se integren fácilmente

## Arquitectura de Extensibilidad

### Carga de assets (CSS/JS) en páginas integradas

Algunos módulos necesitan cargar CSS/JS propios dentro del `<head>` del layout de Zona de Empleado (por ejemplo, para asegurar que los estilos responsive se aplican correctamente y evitar inyecciones de `<link>` dentro del `body`).

Para mantener la zona **normalizada para todos los módulos**, Zona de Empleado soporta 2 variables globales opcionales:

- `$GLOBALS['zonaempleado_extra_css']`: array de rutas (desde `DOL_URL_ROOT`) a CSS.
- `$GLOBALS['zonaempleado_extra_js']`: array de rutas (desde `DOL_URL_ROOT`) a JS.

Uso recomendado en tu página:

```php
// Antes de llamar a zonaempleado_print_header()
$GLOBALS['zonaempleado_extra_css'] = array('/custom/mimodulo/css/mimodulo.css.php');
$GLOBALS['zonaempleado_extra_js']  = array('/custom/mimodulo/js/mimodulo.js.php');

zonaempleado_print_header($langs->trans('MiTitulo'));
```

Notas:

- Usa rutas tipo `/custom/...` (con o sin `/` inicial).
- Evita imprimir `<link>` o `<script>` directamente dentro del `body`.


### Hooks Disponibles

El módulo Zona de Empleado proporciona múltiples puntos de extensión a través de hooks:

#### 1. `registerEmployeeZoneExtension`
Permite a otros módulos registrarse como extensiones de la zona de empleado.

```php
public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs;

    if (empty($conf->zonaempleado->enabled)) return 0;

    if (isset($parameters['extensions'])) {
        $parameters['extensions']['mimodulo'] = array(
            'name' => $langs->trans('MiModuloName'),
            'description' => $langs->trans('MiModuloDesc'),
            'url' => '/custom/mimodulo/empleado.php',
            'icon' => 'fa-star',
            'enabled' => !empty($conf->mimodulo->enabled),
            'order' => 10
        );
    }

    return 0;
}
```

#### 2. `addQuickLinks`
Agrega enlaces rápidos al dashboard principal.

```php
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;

    if (isset($parameters['quickLinks'])) {
        if ($user->rights->mimodulo->read) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('MiAccionRapida'),
                'url' => '/custom/mimodulo/accion.php',
                'icon' => 'fa-bolt',
                'order' => 20
            );
        }
    }

    return 0;
}
```

#### 3. `getEmployeeZoneMenu`
Agrega elementos al menú de navegación principal.

```php
public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;

    if (isset($parameters['menu']) && isset($parameters['user'])) {
        $current_page = isset($parameters['current_page']) ? $parameters['current_page'] : '';
        
        if ($user->rights->mimodulo->read) {
            $parameters['menu']['mimodulo'] = array(
                'label' => $langs->trans('MiAreaModulo'),
                'url' => '/custom/mimodulo/zona_empleado.php',
                'icon' => 'fa-cog',
                'active' => ($current_page == 'mimodulo'),
                'order' => 30
            );
        }
    }

    return 0;
}
```

#### 4. `getRecentActivity`
Agrega actividades recientes del usuario.

```php
public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db;

    if (isset($parameters['activities']) && isset($parameters['user'])) {
        $user = $parameters['user'];
        
        $sql = "SELECT date_activity, description FROM " . MAIN_DB_PREFIX . "mimodulo_actividad";
        $sql .= " WHERE fk_user = " . (int) $user->id;
        $sql .= " ORDER BY date_activity DESC LIMIT 5";
        
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $parameters['activities'][] = array(
                    'date' => $db->jdate($obj->date_activity),
                    'text' => $obj->description,
                    'module' => 'mimodulo'
                );
            }
            $db->free($resql);
        }
    }

    return 0;
}
```

#### 5. `getUserProfileStats`
Agrega estadísticas al perfil del usuario.

```php
public function getUserProfileStats($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $db;

    if (isset($parameters['stats']) && isset($parameters['user'])) {
        $user = $parameters['user'];
        
        $sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "mimodulo_items";
        $sql .= " WHERE fk_user = " . (int) $user->id;
        
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $parameters['stats'][] = array(
                'label' => $langs->trans('MisElementos'),
                'value' => $obj->count,
                'module' => 'mimodulo'
            );
            $db->free($resql);
        }
    }

    return 0;
}
```

#### 6. `getUserProfileActions`
Agrega acciones rápidas al perfil del usuario.

```php
public function getUserProfileActions($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;

    if (isset($parameters['actions']) && isset($parameters['user'])) {
        if ($user->rights->mimodulo->write) {
            $parameters['actions'][] = array(
                'label' => $langs->trans('CrearElemento'),
                'url' => '/custom/mimodulo/elemento_card.php?action=create',
                'icon' => 'fa-plus',
                'order' => 10
            );
        }
    }

    return 0;
}
```

#### 7. `addEmployeeZoneContent`
Agrega contenido adicional al dashboard.

```php
public function addEmployeeZoneContent($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;

    if ($user->rights->mimodulo->read) {
        $this->resprints = '
        <div class="dashboard-card extension-card">
            <div class="card-header">
                <h3><i class="fa fa-star"></i> ' . $langs->trans('MiWidget') . '</h3>
            </div>
            <div class="card-content">
                <p>' . $langs->trans('MiWidgetContenido') . '</p>
                <a href="/custom/mimodulo/zona_empleado.php" class="btn btn-primary">
                    ' . $langs->trans('IrAMiModulo') . '
                </a>
            </div>
        </div>';
    }

    return 0;
}
```

#### 8. `addEmployeeProfileContent`
Agrega contenido adicional a la página de perfil.

```php
public function addEmployeeProfileContent($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;

    if ($user->rights->mimodulo->read) {
        $this->resprints = '
        <div class="col-md-12">
            <div class="profile-card">
                <div class="card-header">
                    <h3><i class="fa fa-star"></i> ' . $langs->trans('MiPerfilModulo') . '</h3>
                </div>
                <div class="card-content">
                    <p>' . $langs->trans('MiPerfilContenido') . '</p>
                </div>
            </div>
        </div>';
    }

    return 0;
}
```

## Cómo Crear una Extensión

### Paso 1: Crear el Archivo de Hooks

Crear un archivo `class/actions_mimodulo.class.php` en tu módulo:

```php
<?php

class ActionsMiModulo
{
    public $db;
    public $resprints = '';

    public function __construct($db)
    {
        $this->db = $db;
    }

    // Implementar los hooks necesarios aquí
}
```

### Paso 2: Registrar los Hooks en tu Módulo

En el archivo `core/modules/modMiModulo.class.php`, agregar los hooks:

```php
'hooks' => array(
    'data' => array(
        'registerEmployeeZoneExtension',
        'addQuickLinks',
        'getEmployeeZoneMenu',
        'getRecentActivity',
        'getUserProfileStats',
        'getUserProfileActions',
        'addEmployeeZoneContent',
        'addEmployeeProfileContent'
    ),
    'entity' => '0',
),
```

### Paso 3: Crear las Páginas para Empleados

Crear páginas específicas para la zona de empleados en tu módulo, por ejemplo:
- `mimodulo/zona_empleado.php` - Página principal del módulo en la zona de empleado
- `mimodulo/empleado_accion.php` - Páginas de acciones específicas

### Paso 4: Agregar Traducciones

En tu archivo de idioma (`langs/es_ES/mimodulo.lang`):

```php
$langs->trans['MiModuloName'] = 'Mi Módulo';
$langs->trans['MiModuloDesc'] = 'Descripción de mi módulo para empleados';
$langs->trans['MiAccionRapida'] = 'Mi Acción Rápida';
$langs->trans['MiAreaModulo'] = 'Área de Mi Módulo';
// ... más traducciones
```

### Paso 5: Implementar Permisos

Asegurate de que tu módulo tenga los permisos apropiados para la zona de empleados:

```php
$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
$this->rights[$r][1] = 'Acceso a zona de empleado de Mi Módulo';
$this->rights[$r][4] = 'empleado';
$this->rights[$r][5] = 'read';
```

## Sistema de Permisos

La Zona de Empleado utiliza tres niveles de permisos:

1. **`zonaempleado->access->read`**: Permiso básico para acceder a la zona de empleado
2. **`zonaempleado->use->write`**: Permiso para usar las funcionalidades de la zona
3. **`zonaempleado->config->write`**: Permiso para configurar la zona de empleado

Los módulos que extienden la zona deben verificar tanto los permisos de la zona de empleado como sus propios permisos.

## Ejemplo Completo

Ver el archivo `class/actions_zonaempleado.class.php` para ejemplos completos de implementación de hooks.

## Estilos CSS

Los módulos pueden usar las clases CSS existentes de la zona de empleado:

- `.dashboard-card` - Tarjetas del dashboard
- `.profile-card` - Tarjetas del perfil
- `.quick-link-button` - Botones de enlaces rápidos
- `.action-btn` - Botones de acción
- `.extension-card` - Tarjetas de extensión

## JavaScript

El objeto global `ZonaEmpleado` proporciona funciones utilitarias:

- `ZonaEmpleado.showNotification(message, type)` - Mostrar notificaciones
- `ZonaEmpleado.loadContent(url, container, callback)` - Cargar contenido via AJAX
- `ZonaEmpleado.submitForm(form, callback)` - Enviar formularios via AJAX

## Consideraciones de Rendimiento

- Los hooks se ejecutan en cada carga de página de la zona de empleado
- Mantener las consultas SQL optimizadas
- Usar caché cuando sea apropiado
- Verificar permisos antes de ejecutar código costoso

## Debugging

Para debug, usar:

```php
dol_syslog("MiModulo: mensaje de debug", LOG_DEBUG);
```

Y habilitar el log de debug en Dolibarr.