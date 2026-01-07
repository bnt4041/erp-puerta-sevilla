# 🎯 Referencia Rápida de Hooks - Zona de Empleado

Guía condensada para implementar hooks en tus módulos.

---

## 📋 Checklist de Implementación

- [ ] Registrar hooks en el módulo (`module_parts['hooks']`)
- [ ] Crear clase `ActionsNombreModulo` en `class/actions_nombremodulo.class.php`
- [ ] Implementar hooks necesarios
- [ ] Verificar permisos en cada hook
- [ ] Activar/reactivar el módulo
- [ ] Probar en Zona de Empleado

Opcional (recomendado si tu página tiene UI propia):

- [ ] Registrar CSS/JS del módulo en el `<head>` usando `$GLOBALS['zonaempleado_extra_css']` / `$GLOBALS['zonaempleado_extra_js']`

---

## 🎨 Cargar CSS/JS del módulo (en el `<head>`)

Si tu página dentro de Zona de Empleado necesita estilos o scripts propios, no los imprimas dentro del `body`. En su lugar, registra assets antes de llamar a `zonaempleado_print_header()`:

```php
$GLOBALS['zonaempleado_extra_css'] = array('/custom/mimodulo/css/mimodulo.css.php');
$GLOBALS['zonaempleado_extra_js']  = array('/custom/mimodulo/js/mimodulo.js.php');

zonaempleado_print_header($langs->trans('MiTitulo'));
```

---

## 🔧 Registro de Hooks en el Módulo

```php
// En core/modules/modMiModulo.class.php

public function __construct($db)
{
    // ... código existente ...
    
    // Registrar hooks
    $this->module_parts = array(
        'hooks' => array(
            'zonaempleadoindex',    // Para dashboard
            'zonaempleadoprofile'   // Para perfil
        )
    );
}
```

---

## 📝 Estructura Base de la Clase

```php
// class/actions_mimodulo.class.php

/**
 * Clase de hooks para integración con Zona de Empleado
 */
class ActionsMiModulo
{
    /**
     * @var string Resultado de impresión
     */
    public $resprints = '';
    
    /**
     * @var DoliDB Base de datos
     */
    public $db;
    
    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    // ... implementar hooks aquí ...
}
```

---

## 🎪 Hook 1: Registrar Extensión

**Dónde aparece**: Dashboard principal (tarjeta de extensión)

```php
/**
 * Registrar extensión en la Zona de Empleado
 * 
 * @param array $parameters Hook parameters
 * @param object $object Current object
 * @param string $action Current action
 * @param HookManager $hookmanager Hook manager
 * @return int 0 on success
 */
public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;
    
    // Verificar que el array existe
    if (isset($parameters['extensions'])) {
        // Verificar permisos
        if (!empty($user->rights->mimodulo->read)) {
            $parameters['extensions'][] = array(
                'title' => $langs->trans('MiModulo'),
                'description' => $langs->trans('MiModuloDesc'),
                'url' => dol_buildpath('/mimodulo/index.php', 1),
                'icon' => 'fa-puzzle-piece',  // FontAwesome icon
                'position' => 10,              // Orden (menor = primero)
                'permissions' => true          // Ya verificado arriba
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `title`, `url`, `icon`
**Campos opcionales**: `description`, `position`, `permissions`

---

## 🔗 Hook 2: Enlaces Rápidos

**Dónde aparece**: Dashboard principal (sección de enlaces rápidos)

```php
/**
 * Agregar enlaces rápidos al dashboard
 */
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;
    
    if (isset($parameters['quickLinks'])) {
        // Enlace 1
        if ($user->rights->mimodulo->leer) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('MiAccion'),
                'url' => dol_buildpath('/mimodulo/accion.php', 1),
                'icon' => 'fa-star',
                'position' => 10,
                'target' => '_self'  // o '_blank' para nueva pestaña
            );
        }
        
        // Enlace 2
        if ($user->rights->mimodulo->crear) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('CrearNuevo'),
                'url' => dol_buildpath('/mimodulo/crear.php', 1),
                'icon' => 'fa-plus-circle',
                'position' => 20
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `label`, `url`, `icon`
**Campos opcionales**: `position`, `target`

---

## 🧭 Hook 3: Menú de Navegación

**Dónde aparece**: Menú lateral izquierdo

```php
/**
 * Agregar items al menú de navegación
 */
public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;
    
    if (isset($parameters['menu'])) {
        if ($user->rights->mimodulo->read) {
            $parameters['menu'][] = array(
                'label' => $langs->trans('MiModulo'),
                'url' => dol_buildpath('/mimodulo/index.php', 1),
                'icon' => 'fa-cube',
                'position' => 30,
                'active' => ($_SERVER['PHP_SELF'] == dol_buildpath('/mimodulo/index.php', 0))
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `label`, `url`, `icon`
**Campos opcionales**: `position`, `active`

---

## 📊 Hook 4: Actividad Reciente

**Dónde aparece**: Dashboard principal y página de perfil

```php
/**
 * Agregar actividades recientes del usuario
 */
public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db, $langs, $user;
    
    if (isset($parameters['activities'])) {
        // Consultar actividades recientes
        $sql = "SELECT fecha, accion, descripcion";
        $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_actividades";
        $sql .= " WHERE fk_user = ".(int)$user->id;
        $sql .= " ORDER BY fecha DESC";
        $sql .= " LIMIT 5";
        
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $parameters['activities'][] = array(
                    'date' => $obj->fecha,
                    'title' => $langs->trans($obj->accion),
                    'description' => $obj->descripcion,
                    'icon' => 'fa-check-circle',
                    'url' => dol_buildpath('/mimodulo/ver.php?id='.$obj->rowid, 1)
                );
            }
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `date`, `title`
**Campos opcionales**: `description`, `icon`, `url`

---

## 📈 Hook 5: Estadísticas de Perfil

**Dónde aparece**: Página de perfil (sección de estadísticas)

```php
/**
 * Agregar estadísticas al perfil del usuario
 */
public function getUserProfileStats($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db, $user;
    
    if (isset($parameters['stats'])) {
        // Contar elementos del usuario
        $sql = "SELECT COUNT(*) as total";
        $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_elementos";
        $sql .= " WHERE fk_user = ".(int)$user->id;
        
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $parameters['stats'][] = array(
                'label' => 'Mis Elementos',
                'value' => $obj->total,
                'icon' => 'fa-cubes',
                'position' => 40
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `label`, `value`, `icon`
**Campos opcionales**: `position`

---

## ⚡ Hook 6: Acciones Rápidas de Perfil

**Dónde aparece**: Página de perfil (botones de acción)

```php
/**
 * Agregar acciones rápidas al perfil
 */
public function getUserProfileActions($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $langs, $user;
    
    if (isset($parameters['actions'])) {
        if ($user->rights->mimodulo->crear) {
            $parameters['actions'][] = array(
                'label' => $langs->trans('NuevoElemento'),
                'url' => dol_buildpath('/mimodulo/crear.php', 1),
                'icon' => 'fa-plus',
                'class' => 'butAction',  // o 'butActionDelete' para botones rojos
                'position' => 10
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `label`, `url`, `icon`
**Campos opcionales**: `class`, `position`

---

## 🎨 Hook 7: Widget Personalizado en Dashboard

**Dónde aparece**: Dashboard principal (widget ancho completo)

```php
/**
 * Agregar contenido personalizado al dashboard
 */
public function addEmployeeZoneContent($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db, $langs, $user;
    
    if (isset($parameters['content'])) {
        if ($user->rights->mimodulo->read) {
            // Generar HTML del widget
            $html = '<div class="employee-zone-widget">';
            $html .= '<h3><i class="fa fa-chart-line"></i> '.$langs->trans('MisEstadisticas').'</h3>';
            $html .= '<div class="widget-content">';
            
            // Consultar datos
            $sql = "SELECT COUNT(*) as total, SUM(importe) as suma";
            $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_datos";
            $sql .= " WHERE fk_user = ".(int)$user->id;
            
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $html .= '<div class="stat-box">';
                $html .= '<span class="stat-value">'.$obj->total.'</span>';
                $html .= '<span class="stat-label">Total</span>';
                $html .= '</div>';
                $html .= '<div class="stat-box">';
                $html .= '<span class="stat-value">'.price($obj->suma).'</span>';
                $html .= '<span class="stat-label">Suma</span>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            
            $parameters['content'][] = array(
                'html' => $html,
                'position' => 50
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `html`
**Campos opcionales**: `position`

---

## 👤 Hook 8: Sección Personalizada en Perfil

**Dónde aparece**: Página de perfil (sección personalizada)

```php
/**
 * Agregar contenido personalizado al perfil
 */
public function addEmployeeProfileContent($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db, $langs, $user;
    
    if (isset($parameters['profileContent'])) {
        if ($user->rights->mimodulo->read) {
            // Generar HTML de la sección
            $html = '<div class="profile-section">';
            $html .= '<h3><i class="fa fa-history"></i> '.$langs->trans('MiHistorial').'</h3>';
            
            // Consultar historial
            $sql = "SELECT fecha, accion, detalle";
            $sql .= " FROM ".MAIN_DB_PREFIX."mimodulo_historial";
            $sql .= " WHERE fk_user = ".(int)$user->id;
            $sql .= " ORDER BY fecha DESC LIMIT 10";
            
            $resql = $db->query($sql);
            if ($resql) {
                $html .= '<table class="border centpercent">';
                while ($obj = $db->fetch_object($resql)) {
                    $html .= '<tr>';
                    $html .= '<td>'.dol_print_date($obj->fecha, 'dayhour').'</td>';
                    $html .= '<td>'.$langs->trans($obj->accion).'</td>';
                    $html .= '<td>'.$obj->detalle.'</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
            
            $html .= '</div>';
            
            $parameters['profileContent'][] = array(
                'html' => $html,
                'position' => 30
            );
        }
    }
    
    return 0;
}
```

**Campos requeridos**: `html`
**Campos opcionales**: `position`

---

## 🎨 Clases CSS Disponibles

### Contenedores
- `.employee-zone-widget` - Widget estándar
- `.profile-section` - Sección de perfil
- `.stat-box` - Caja de estadística

### Tarjetas
- `.ez-card` - Tarjeta básica
- `.ez-card-header` - Cabecera de tarjeta
- `.ez-card-body` - Cuerpo de tarjeta

### Botones
- `.butAction` - Botón de acción estándar
- `.butActionDelete` - Botón de acción peligrosa (rojo)
- `.butActionRefused` - Botón deshabilitado

### Utilidades
- `.centpercent` - Ancho 100%
- `.border` - Borde de tabla Dolibarr
- `.opacitymedium` - Opacidad media

---

## 🔍 Debugging

### Verificar que los hooks se ejecutan

```php
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    // Agregar log temporal
    dol_syslog("MiModulo: Hook addQuickLinks ejecutado", LOG_DEBUG);
    
    // ... resto del código ...
}
```

Ver logs en: `/documents/dolibarr.log`

### Verificar permisos

```php
// Imprimir permisos en el hook
if (isset($parameters['quickLinks'])) {
    dol_syslog("MiModulo: User rights = ".print_r($user->rights->mimodulo, true), LOG_DEBUG);
}
```

### Verificar estructura de datos

```php
// Ver qué hay en los parámetros
if (isset($parameters['extensions'])) {
    dol_syslog("MiModulo: Extensions = ".print_r($parameters['extensions'], true), LOG_DEBUG);
}
```

---

## ⚠️ Errores Comunes

### ❌ Hook no se ejecuta
**Causa**: No está registrado en `module_parts`
**Solución**: Agregar contexto correcto en el módulo

### ❌ Datos no aparecen
**Causa**: Verificación de permisos falla
**Solución**: Verificar que `$user->rights->mimodulo->read` existe

### ❌ Error "undefined index"
**Causa**: No verificar que el parámetro existe
**Solución**: Siempre usar `isset($parameters['key'])`

### ❌ SQL no devuelve datos
**Causa**: Tabla o campo no existe
**Solución**: Verificar nombres con `MAIN_DB_PREFIX`

---

## 📚 Recursos

- **[Guía completa](INTEGRATION_EXAMPLE.md)** - Ejemplos detallados
- **[Módulo demo](../zonaempleadodemo/)** - Código funcional
- **[Documentación](INDEX.md)** - Índice general

---

## 🚀 Plantilla Completa

```php
<?php
/**
 * Clase de hooks para integración con Zona de Empleado
 */
class ActionsMiModulo
{
    public $resprints = '';
    public $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    // 1. Registrar extensión
    public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        if (isset($parameters['extensions']) && $user->rights->mimodulo->read) {
            $parameters['extensions'][] = array(
                'title' => $langs->trans('MiModulo'),
                'description' => $langs->trans('MiModuloDesc'),
                'url' => dol_buildpath('/mimodulo/index.php', 1),
                'icon' => 'fa-puzzle-piece',
                'position' => 10,
                'permissions' => true
            );
        }
        return 0;
    }
    
    // 2. Enlaces rápidos
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        if (isset($parameters['quickLinks']) && $user->rights->mimodulo->read) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('MiAccion'),
                'url' => dol_buildpath('/mimodulo/accion.php', 1),
                'icon' => 'fa-star',
                'position' => 10
            );
        }
        return 0;
    }
    
    // 3. Menú de navegación
    public function getEmployeeZoneMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        if (isset($parameters['menu']) && $user->rights->mimodulo->read) {
            $parameters['menu'][] = array(
                'label' => $langs->trans('MiModulo'),
                'url' => dol_buildpath('/mimodulo/index.php', 1),
                'icon' => 'fa-cube',
                'position' => 30
            );
        }
        return 0;
    }
    
    // 4. Actividad reciente
    public function getRecentActivity($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $user;
        if (isset($parameters['activities'])) {
            // Consultar actividades...
        }
        return 0;
    }
    
    // 5. Estadísticas de perfil
    public function getUserProfileStats($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $user;
        if (isset($parameters['stats'])) {
            // Consultar estadísticas...
        }
        return 0;
    }
    
    // 6. Acciones de perfil
    public function getUserProfileActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        if (isset($parameters['actions'])) {
            // Agregar acciones...
        }
        return 0;
    }
    
    // 7. Widget en dashboard
    public function addEmployeeZoneContent($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $user;
        if (isset($parameters['content'])) {
            // Generar HTML...
        }
        return 0;
    }
    
    // 8. Sección en perfil
    public function addEmployeeProfileContent($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $langs, $user;
        if (isset($parameters['profileContent'])) {
            // Generar HTML...
        }
        return 0;
    }
}
```

---

**Zona de Empleado** - Referencia Rápida de Hooks v1.0
