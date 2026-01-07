# 🔌 Guía de Integración con Zona de Empleado

Esta guía muestra cómo integrar tu módulo personalizado con la **Zona de Empleado**.

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [Hooks Disponibles](#hooks-disponibles)
3. [Ejemplo Completo](#ejemplo-completo)
4. [Implementación Paso a Paso](#implementación-paso-a-paso)

---

## Introducción

La Zona de Empleado proporciona **8 hooks diferentes** que permiten a otros módulos:

- ✅ Registrar extensiones (páginas completas)
- ✅ Añadir enlaces rápidos al dashboard
- ✅ Añadir elementos al menú de navegación
- ✅ Mostrar actividad reciente del usuario
- ✅ Mostrar estadísticas del usuario
- ✅ Añadir acciones rápidas en el perfil
- ✅ Añadir contenido personalizado al dashboard
- ✅ Añadir contenido personalizado al perfil

Además, para mantener la UI **normalizada** y permitir estilos/scripts por módulo, las páginas integradas pueden registrar CSS/JS adicionales para el `<head>` (ver ejemplo más abajo).

---

## Cargar CSS/JS del módulo (recomendado)

Si tu página tiene UI propia (componentes, responsive, canvas, etc.), registra assets antes de imprimir el header:

```php
// Antes de zonaempleado_print_header()
$GLOBALS['zonaempleado_extra_css'] = array('/custom/mimodulo/css/mimodulo.css.php');
$GLOBALS['zonaempleado_extra_js']  = array('/custom/mimodulo/js/mimodulo.js.php');

zonaempleado_print_header($langs->trans('MiModulo'));
```

---

## Hooks Disponibles

### 1. `registerEmployeeZoneExtension`
**Propósito**: Registrar tu módulo como extensión completa con su propia página.

**Parámetros esperados**:
```php
$parameters['extensions'][] = array(
    'id' => 'unique_module_id',
    'name' => 'Nombre del Módulo',
    'description' => 'Descripción breve',
    'icon' => 'fa-icon-name',
    'url' => '/custom/mymodule/employee.php',
    'enabled' => true,
    'position' => 10
);
```

### 2. `addQuickLinks`
**Propósito**: Añadir enlaces de acceso rápido en el dashboard.

**Parámetros esperados**:
```php
$parameters['quickLinks'][] = array(
    'label' => 'Crear Nuevo',
    'url' => DOL_URL_ROOT.'/custom/mymodule/create.php',
    'icon' => 'fa-plus',
    'position' => 5
);
```

### 3. `getEmployeeZoneMenu`
**Propósito**: Añadir elementos al menú de navegación superior.

**Parámetros esperados**:
```php
$parameters['menu'][] = array(
    'id' => 'mymodule_menu',
    'label' => 'Mi Módulo',
    'url' => '/custom/mymodule/employee.php',
    'icon' => 'fas fa-cog',
    'position' => 20
);
```

### 4. `getRecentActivity`
**Propósito**: Mostrar actividades recientes del usuario en el dashboard.

**Parámetros esperados**:
```php
$parameters['activities'][] = array(
    'date' => time(), // timestamp
    'text' => 'Descripción de la actividad',
    'icon' => 'fa-check',
    'module' => 'mymodule'
);
```

### 5. `getUserProfileStats`
**Propósito**: Mostrar estadísticas del usuario en su perfil.

**Parámetros esperados**:
```php
$parameters['stats'][] = array(
    'label' => 'Total de Items',
    'value' => 42,
    'icon' => 'fa-chart-bar'
);
```

### 6. `getUserProfileActions`
**Propósito**: Añadir acciones rápidas en la página de perfil.

**Parámetros esperados**:
```php
$parameters['actions'][] = array(
    'label' => 'Mi Acción',
    'url' => DOL_URL_ROOT.'/custom/mymodule/action.php',
    'icon' => 'fa-download',
    'target' => '_blank' // opcional
);
```

### 7. `addEmployeeZoneContent`
**Propósito**: Añadir contenido HTML personalizado al final del dashboard.

**Uso**: Imprimir HTML directamente o usar `$hookmanager->resPrint`.

### 8. `addEmployeeProfileContent`
**Propósito**: Añadir contenido HTML personalizado al final del perfil.

**Uso**: Imprimir HTML directamente o usar `$hookmanager->resPrint`.

---

## Ejemplo Completo

Aquí hay un ejemplo completo de cómo integrar un módulo llamado "MiModulo":

### Estructura del módulo
```
custom/
└── mimodulo/
    ├── class/
    │   └── actions_mimodulo.class.php
    ├── core/
    │   └── modules/
    │       └── modMiModulo.class.php
    ├── employee.php
    └── langs/
        └── es_ES/
            └── mimodulo.lang
```

### 1. Archivo: `core/modules/modMiModulo.class.php`

```php
<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modMiModulo extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        
        $this->numero = 500000; // Número único de módulo
        $this->rights_class = 'mimodulo';
        $this->family = "other";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Mi Módulo Integrado con Zona de Empleado";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        // Hooks para integración con Zona de Empleado
        $this->module_parts = array(
            'hooks' => array(
                'zonaempleadoindex',    // Dashboard
                'zonaempleadoprofile',  // Perfil
            )
        );
        
        // ... resto de la configuración del módulo
    }
}
```

### 2. Archivo: `class/actions_mimodulo.class.php`

```php
<?php

class ActionsMiModulo
{
    public $db;
    public $error = '';
    public $errors = array();
    public $results = array();
    public $resprints;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Registrar el módulo como extensión
     */
    public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        $parameters['extensions'][] = array(
            'id' => 'mimodulo',
            'name' => $langs->trans('MiModulo'),
            'description' => $langs->trans('MiModuloDesc'),
            'icon' => 'fa-rocket',
            'url' => '/custom/mimodulo/employee.php',
            'enabled' => true,
            'position' => 10
        );

        return 0;
    }

    /**
     * Añadir enlaces rápidos al dashboard
     */
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        // Verificar permisos si es necesario
        if (!empty($user->rights->mimodulo->create)) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('CreateNew'),
                'url' => DOL_URL_ROOT.'/custom/mimodulo/create.php',
                'icon' => 'fa-plus',
                'position' => 5
            );
        }

        if (!empty($user->rights->mimodulo->read)) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('ViewList'),
                'url' => DOL_URL_ROOT.'/custom/mimodulo/list.php',
                'icon' => 'fa-list',
                'position' => 10
            );
        }

        return 0;
    }

    /**
     * Añadir elementos al menú de navegación
     */
    public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        $parameters['menu'][] = array(
            'id' => 'mimodulo_menu',
            'label' => $langs->trans('MiModulo'),
            'url' => '/custom/mimodulo/employee.php',
            'icon' => 'fas fa-rocket',
            'position' => 20
        );

        return 0;
    }

    /**
     * Mostrar actividad reciente
     */
    public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $db, $langs;

        if (empty($conf->mimodulo->enabled)) return 0;
        if (empty($parameters['user'])) return 0;

        $langs->load('mimodulo@mimodulo');
        $user = $parameters['user'];

        // Consultar últimas actividades del usuario
        $sql = "SELECT rowid, date_creation, description";
        $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_activity";
        $sql .= " WHERE fk_user = ".((int) $user->id);
        $sql .= " ORDER BY date_creation DESC";
        $sql .= " LIMIT 5";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $parameters['activities'][] = array(
                    'date' => $db->jdate($obj->date_creation),
                    'text' => $obj->description,
                    'icon' => 'fa-rocket',
                    'module' => 'mimodulo'
                );
            }
            $db->free($resql);
        }

        return 0;
    }

    /**
     * Mostrar estadísticas en el perfil
     */
    public function getUserProfileStats($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $db, $langs;

        if (empty($conf->mimodulo->enabled)) return 0;
        if (empty($parameters['user'])) return 0;

        $langs->load('mimodulo@mimodulo');
        $user = $parameters['user'];

        // Calcular estadísticas
        $sql = "SELECT COUNT(rowid) as total";
        $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_items";
        $sql .= " WHERE fk_user = ".((int) $user->id);

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $parameters['stats'][] = array(
                'label' => $langs->trans('TotalItems'),
                'value' => $obj->total,
                'icon' => 'fa-chart-bar'
            );
            $db->free($resql);
        }

        return 0;
    }

    /**
     * Añadir acciones rápidas en el perfil
     */
    public function getUserProfileActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        $parameters['actions'][] = array(
            'label' => $langs->trans('DownloadReport'),
            'url' => DOL_URL_ROOT.'/custom/mimodulo/report.php',
            'icon' => 'fa-download'
        );

        $parameters['actions'][] = array(
            'label' => $langs->trans('ViewDocuments'),
            'url' => DOL_URL_ROOT.'/custom/mimodulo/documents.php',
            'icon' => 'fa-file-pdf'
        );

        return 0;
    }

    /**
     * Añadir contenido personalizado al dashboard
     */
    public function addEmployeeZoneContent($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        // Widget personalizado
        $out = '<div class="dashboard-card" style="grid-column: span 2;">';
        $out .= '<div class="card-header">';
        $out .= '<h3><i class="fas fa-rocket"></i> '.$langs->trans('MiModuloWidget').'</h3>';
        $out .= '</div>';
        $out .= '<div class="card-content">';
        $out .= '<p>Contenido personalizado de tu módulo...</p>';
        $out .= '</div>';
        $out .= '</div>';

        $this->resprints = $out;
        return 1; // Return 1 to print resprints
    }

    /**
     * Añadir contenido personalizado al perfil
     */
    public function addEmployeeProfileContent($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs;

        if (empty($conf->mimodulo->enabled)) return 0;

        $langs->load('mimodulo@mimodulo');

        $out = '<div class="profile-custom-section">';
        $out .= '<h2>'.$langs->trans('MiModuloSection').'</h2>';
        $out .= '<p>Información adicional específica de tu módulo...</p>';
        $out .= '</div>';

        $this->resprints = $out;
        return 1;
    }
}
```

### 3. Archivo: `employee.php` (Página principal del módulo en Zona de Empleado)

```php
<?php
// OPCIÓN RECOMENDADA: usar bootstrap/teardown de Zona Empleado para forzar header/footer

// Define el título antes de incluir el bootstrap si quieres personalizarlo
$title = 'Mi Módulo';

// Incluye el bootstrap (carga entorno, verifica seguridad y pinta el header estándar)
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/inc/bootstrap.php';

// A partir de aquí, imprime tu contenido
?>

<div class="employee-zone">
    <div class="welcome-section">
        <h1><?php echo $langs->trans('MiModulo'); ?></h1>
        <p><?php echo $langs->trans('MiModuloDescription'); ?></p>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Mi Contenido</h3>
            </div>
            <div class="card-content">
                <p>Contenido de tu módulo personalizado...</p>
            </div>
        </div>
    </div>
</div>

<?php
// Cierra con el teardown estándar para imprimir el footer
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/inc/teardown.php';
```

También puedes usar directamente los helpers si necesitas un control más fino:

```php
require_once DOL_DOCUMENT_ROOT.'/custom/zonaempleado/lib/zonaempleado.lib.php';
zonaempleado_print_header('Mi Módulo');
// ... tu contenido ...
zonaempleado_print_footer();
```

### 4. Archivo: `langs/es_ES/mimodulo.lang`

```
# Language file for module MiModulo

CHARSET=UTF-8
MiModulo=Mi Módulo
MiModuloDesc=Descripción de mi módulo
MiModuloWidget=Widget de Mi Módulo
MiModuloSection=Sección de Mi Módulo
MiModuloDescription=Esta es la página principal de mi módulo en la Zona de Empleado

CreateNew=Crear Nuevo
ViewList=Ver Lista
TotalItems=Total de Items
DownloadReport=Descargar Reporte
ViewDocuments=Ver Documentos
```

---

## Implementación Paso a Paso

### Paso 1: Preparar tu módulo

1. Asegúrate de que tu módulo está en `/custom/tumodulo/`
2. Crea la estructura básica si no existe

### Paso 2: Configurar hooks

En tu archivo `core/modules/modTuModulo.class.php`, añade:

```php
$this->module_parts = array(
    'hooks' => array(
        'zonaempleadoindex',    // Para el dashboard
        'zonaempleadoprofile',  // Para el perfil de usuario
    )
);
```

### Paso 3: Crear la clase de acciones

Crea `class/actions_tumodulo.class.php` e implementa los hooks que necesites.

### Paso 4: Crear página de empleado (opcional)

Si quieres una página completa, crea `employee.php` usando el template de Zona de Empleado.

### Paso 5: Activar tu módulo

1. Ve a Inicio → Configuración → Módulos
2. Activa tu módulo
3. Activa el módulo "Zona de Empleado"
4. Recarga la página de Zona de Empleado

### Paso 6: Verificar integración

Navega a `/custom/zonaempleado/` y verifica que:
- Tus enlaces aparecen en "Acceso Rápido"
- Tu extensión aparece en la card de "Extensiones"
- Tu menú aparece en la navegación
- Tus estadísticas aparecen en el perfil

---

## 🎯 Mejores Prácticas

1. **Verificar permisos**: Siempre verifica `$user->rights` antes de añadir elementos
2. **Cargar traducciones**: Usa `$langs->load()` para textos multiidioma
3. **Verificar módulo activo**: Comprueba `$conf->tumodulo->enabled`
4. **Usar iconos Font Awesome**: Para consistencia visual
5. **Posicionamiento**: Usa el campo `position` para ordenar elementos
6. **Error handling**: Captura errores en consultas SQL
7. **Performance**: No hagas consultas pesadas en hooks que se ejecutan frecuentemente

---

## 📞 Soporte

Para más información sobre la Zona de Empleado, consulta:
- README.md
- EXTENSIBILITY_GUIDE.md (si existe)

---

**Desarrollado para Dolibarr ERP/CRM**
