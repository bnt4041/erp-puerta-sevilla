# 📚 Índice de Documentación - Zona de Empleado

Bienvenido a la documentación completa del módulo Zona de Empleado para Dolibarr.

## 🎯 Navegación Rápida

### Para Usuarios
- **[README.md](../README.md)** - Información general del módulo, instalación y características

### Para Desarrolladores
- **[INTEGRATION_EXAMPLE.md](../INTEGRATION_EXAMPLE.md)** - Guía completa de integración con ejemplos de código
- **[Módulo Demo](../../zonaempleadodemo/README.md)** - Implementación funcional de todos los hooks

---

## 📖 Contenido por Tema

### 1️⃣ Introducción

#### ¿Qué es la Zona de Empleado?
Portal independiente y simplificado para empleados que proporciona:
- Interfaz moderna y responsive
- Acceso a funcionalidades operativas
- Sistema de extensibilidad completo

#### Casos de Uso
- Portal de autoservicio para empleados
- Plataforma para apps internas
- Hub de integraciones personalizadas

---

### 2️⃣ Instalación y Configuración

#### Instalación Básica
1. Copiar carpeta `zonaempleado` a `htdocs/custom/`
2. Activar módulo desde Configuración → Módulos
3. Configurar permisos de usuario

Ver detalles en: **[README.md - Instalación](../README.md#-instalación)**

#### Configuración de Permisos
- `zonaempleado->access->read` - Acceso básico
- `zonaempleado->use->write` - Uso de funcionalidades
- `zonaempleado->config->write` - Configuración

#### Primer Acceso
Navega a: `/custom/zonaempleado/index.php`

---

### 3️⃣ Arquitectura del Sistema

#### Estructura de Archivos
```
zonaempleado/
├── index.php                    # Dashboard principal
├── profile.php                  # Página de perfil
├── class/
│   ├── zonaempleado.class.php            # Clase principal
│   └── actions_zonaempleado.class.php    # Sistema de hooks
├── core/modules/
│   └── modZonaEmpleado.class.php         # Definición del módulo
├── lib/
│   └── zonaempleado.lib.php              # Funciones auxiliares
├── tpl/
│   ├── header.tpl.php                    # Header personalizado
│   └── footer.tpl.php                    # Footer personalizado
├── css/
│   └── zonaempleado.css.php              # Estilos dinámicos
├── js/
│   └── zonaempleado.js.php               # JavaScript
└── langs/
    └── es_ES/zonaempleado.lang           # Traducciones
```

#### Flujo de Ejecución
1. **Autenticación** - Verificación de sesión Dolibarr
2. **Permisos** - Validación de acceso
3. **Hooks** - Llamadas a extensiones
4. **Renderizado** - Generación de HTML

Ver: **[README.md - Arquitectura Técnica](../README.md#-arquitectura-técnica)**

---

### 4️⃣ Sistema de Extensibilidad

#### Conceptos Clave
- **Hooks**: Puntos de extensión en el código
- **Actions**: Clases que responden a hooks
- **Parámetros**: Datos compartidos entre hooks

#### Hooks Disponibles

| Hook | Contexto | Propósito |
|------|----------|-----------|
| `registerEmployeeZoneExtension` | Dashboard | Registrar módulos como extensiones |
| `addQuickLinks` | Dashboard | Agregar enlaces rápidos |
| `getEmployeeZoneMenu` | Global | Agregar items al menú |
| `getRecentActivity` | Dashboard/Perfil | Mostrar actividad reciente |
| `getUserProfileStats` | Perfil | Estadísticas del usuario |
| `getUserProfileActions` | Perfil | Acciones rápidas |
| `addEmployeeZoneContent` | Dashboard | Widgets personalizados |
| `addEmployeeProfileContent` | Perfil | Secciones personalizadas |

#### Guías de Implementación

**Nivel Básico**
- Agregar enlaces rápidos
- Registrar extensiones simples
- Ejemplo: **[INTEGRATION_EXAMPLE.md - Ejemplo 2](../INTEGRATION_EXAMPLE.md#ejemplo-2-agregar-enlaces-rápidos)**

**Nivel Intermedio**
- Agregar items al menú
- Mostrar actividades recientes
- Ejemplo: **[INTEGRATION_EXAMPLE.md - Ejemplo 3](../INTEGRATION_EXAMPLE.md#ejemplo-3-agregar-items-al-menú)**

**Nivel Avanzado**
- Widgets personalizados completos
- Secciones de perfil complejas
- Ejemplo: **[Módulo Demo Completo](../../zonaempleadodemo/README.md)**

---

### 5️⃣ Tutoriales Paso a Paso

#### Tutorial 1: Primera Extensión
**Objetivo**: Agregar un enlace rápido desde tu módulo

1. **Registra el hook** en tu módulo:
```php
$this->module_parts = array('hooks' => array('zonaempleadoindex'));
```

2. **Crea la clase de acciones**: `class/actions_mimodulo.class.php`

3. **Implementa el hook**:
```php
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
{
    if (isset($parameters['quickLinks'])) {
        $parameters['quickLinks'][] = array(
            'label' => 'Mi Acción',
            'url' => '/custom/mimodulo/accion.php',
            'icon' => 'fa-star',
            'position' => 10
        );
    }
    return 0;
}
```

4. **Activa tu módulo** y verás el enlace en el dashboard

Ver ejemplo completo: **[INTEGRATION_EXAMPLE.md](../INTEGRATION_EXAMPLE.md)**

#### Tutorial 2: Módulo Demo Completo
**Objetivo**: Comprender todos los hooks mediante un ejemplo funcional

1. **Instala el módulo demo**:
   - Ya está en `/custom/zonaempleadodemo/`
   - Actívalo desde Configuración → Módulos

2. **Explora las integraciones**:
   - Dashboard: extensión, enlaces, widget
   - Perfil: estadísticas, acciones, sección

3. **Estudia el código**:
   - Ver: `zonaempleadodemo/class/actions_zonaempleadodemo.class.php`
   - Cada hook tiene ejemplos comentados

4. **Adapta a tu módulo**:
   - Copia los hooks que necesites
   - Modifica según tus requerimientos

Ver: **[zonaempleadodemo/README.md](../../zonaempleadodemo/README.md)**

---

### 6️⃣ Referencia de API

#### Clase: ActionsZonaEmpleado

**Hook: registerEmployeeZoneExtension**
```php
public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
```
- **Parámetros**: `$parameters['extensions']` - Array de extensiones
- **Retorna**: 0 (éxito), -1 (error)
- **Estructura**: `['title', 'description', 'url', 'icon', 'position', 'permissions']`

**Hook: addQuickLinks**
```php
public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
```
- **Parámetros**: `$parameters['quickLinks']` - Array de enlaces
- **Retorna**: 0 (éxito), -1 (error)
- **Estructura**: `['label', 'url', 'icon', 'position', 'target']`

Ver documentación completa de cada hook: **[INTEGRATION_EXAMPLE.md](../INTEGRATION_EXAMPLE.md)**

#### Funciones Auxiliares

**zonaempleado_get_extensions()**
```php
function zonaempleado_get_extensions()
```
- Obtiene todas las extensiones registradas
- Ordena por campo `position`
- Retorna array de extensiones

**zonaempleado_check_permission()**
```php
function zonaempleado_check_permission($type = 'read')
```
- Verifica permisos del usuario actual
- Tipos: 'read', 'write', 'config'
- Retorna boolean

Ver: `lib/zonaempleado.lib.php`

---

### 7️⃣ Personalización

#### Estilos CSS
El módulo hereda automáticamente los colores del tema Dolibarr:
- Lee `THEME_ELDY_TOPMENU_BACK1` de la base de datos
- Genera variable CSS `--ze-primary-color`
- Aplica en toda la interfaz

**Personalizar colores adicionales**:
Edita `css/zonaempleado.css.php` y agrega variables CSS:
```css
:root {
    --ze-secondary-color: #your-color;
    --ze-accent-color: #your-color;
}
```

#### Templates
Los templates están en `tpl/`:
- `header.tpl.php` - Header con menú
- `footer.tpl.php` - Footer con scripts

**Sobrescribir templates**:
Crea versiones personalizadas en tu módulo.

---

### 8️⃣ Solución de Problemas

#### Error: "Acceso Denegado"
**Causa**: Permisos insuficientes
**Solución**:
1. Verifica que el módulo esté activo
2. Asigna permiso `zonaempleado->access->read` al usuario
3. Verifica que el usuario tenga un grupo asignado

#### Error: "Estilos no se cargan"
**Causa**: CSS no se genera correctamente
**Solución**:
1. Verifica permisos del archivo `css/zonaempleado.css.php`
2. Comprueba que `NOREQUIREDB` NO esté definido
3. Limpia caché del navegador

#### Los hooks no se ejecutan
**Causa**: Hooks mal registrados
**Solución**:
1. Verifica `module_parts['hooks']` en tu módulo
2. Contextos correctos: `zonaempleadoindex`, `zonaempleadoprofile`
3. Desactiva/activa el módulo para refrescar

#### Debug Mode
Habilitar logs detallados:
```php
// En conf.php
$dolibarr_main_prod = 0;  // Modo desarrollo
```

Ver logs en: `documents/dolibarr.log`

---

### 9️⃣ Mejores Prácticas

#### Desarrollo de Extensiones

✅ **Hacer**
- Verificar permisos en cada hook
- Ordenar items con campo `position`
- Usar traducciones (`$langs->trans()`)
- Manejar errores gracefully
- Documentar tu código

❌ **Evitar**
- Modificar archivos del módulo base
- Hardcodear URLs o textos
- Ignorar verificación de permisos
- Retornar valores distintos de 0
- Modificar el array `$object`

#### Performance

**Optimizaciones**:
- Cachear resultados pesados
- Limitar queries a base de datos
- Cargar solo datos necesarios
- Usar lazy loading para imágenes

**Ejemplo de cache**:
```php
static $cache = null;
if ($cache === null) {
    $cache = $this->getExpensiveData();
}
return $cache;
```

#### Seguridad

**Validación de entrada**:
```php
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
```

**Verificación de permisos**:
```php
if (!$user->rights->mimodulo->read) {
    accessforbidden();
}
```

**SQL Injection**:
Usa prepared statements o `$db->escape()`

---

### 🔟 Recursos Adicionales

#### Archivos de Referencia
- **[README.md](../README.md)** - Documentación principal
- **[INTEGRATION_EXAMPLE.md](../INTEGRATION_EXAMPLE.md)** - Guía de integración
- **[zonaempleadodemo/](../../zonaempleadodemo/)** - Código de ejemplo funcional

#### Comunidad Dolibarr
- [Wiki oficial](https://wiki.dolibarr.org)
- [Documentación de desarrollo](https://wiki.dolibarr.org/index.php/Developer_documentation)
- [Foro de desarrolladores](https://dolibarr.org/forum)

#### Herramientas
- [Module Builder](https://wiki.dolibarr.org/index.php/Module_builder) - Generador de módulos
- [PHPDoc](https://www.phpdoc.org/) - Documentación de código
- [Git Flow](https://nvie.com/posts/a-successful-git-branching-model/) - Flujo de trabajo

---

## 🚀 Siguiente Paso

### Nuevo en el módulo?
Empieza con: **[README.md](../README.md)**

### Quieres extender el módulo?
Ve a: **[INTEGRATION_EXAMPLE.md](../INTEGRATION_EXAMPLE.md)**

### Necesitas ejemplos de código?
Revisa: **[Módulo Demo](../../zonaempleadodemo/README.md)**

---

**Zona de Empleado** - Sistema completo de extensibilidad para Dolibarr 🎯
