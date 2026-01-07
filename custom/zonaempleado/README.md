# Zona de Empleado - Módulo para Dolibarr

## Descripción

El módulo **Zona de Empleado** transforma Dolibarr en un portal independiente y simplificado para los empleados de la empresa. Proporciona una interfaz limpia y moderna, optimizada para uso móvil, que permite a los empleados acceder a funcionalidades operativas específicas sin la complejidad del backoffice completo.

## 🎯 Características Principales

### Portal Independiente
- Interfaz simplificada y moderna, separada del backoffice de Dolibarr
- Diseño limpio y profesional con gradientes y animaciones suaves
- Navegación intuitiva con menú lateral responsive

### Autenticación y Seguridad
- Utiliza las credenciales existentes de Dolibarr
- Sistema de permisos granular con tres niveles:
  - Acceso básico a la zona de empleado
  - Uso de funcionalidades
  - Configuración del portal
- Verificación automática de permisos en cada página

### Diseño Responsive
- Optimizado para móviles y tablets
- Menú colapsible para dispositivos pequeños
- Interfaz táctil amigable
- Animaciones CSS suaves

### Extensibilidad
- Sistema completo de hooks para que otros módulos se integren
- Puntos de extensión bien definidos
- API JavaScript para funcionalidades del lado cliente
- Documentación completa para desarrolladores

### Carga de CSS/JS por módulo (normalizado)
- Las páginas integradas pueden registrar CSS/JS propios para que se carguen en el `<head>`
- Mecanismo: `$GLOBALS['zonaempleado_extra_css']` y `$GLOBALS['zonaempleado_extra_js']` definidos antes de `zonaempleado_print_header()`

## 🚀 Instalación y Configuración

### ⚡ Inicio Rápido

**¿Primera vez con el módulo?** Sigue nuestra guía paso a paso:

👉 **[GETTING_STARTED.md](GETTING_STARTED.md)** - Guía completa de inicio rápido

La guía incluye:
- Activación del módulo paso a paso
- Configuración de permisos
- Activación del módulo demo
- Verificación de todas las integraciones
- Tu primera integración personalizada
- Solución de problemas comunes

### 📋 Instalación Rápida

1. **Copiar archivos**: Coloca la carpeta `zonaempleado` en `htdocs/custom/`

2. **Activar módulo**: Ve a Configuración → Módulos → Otros → Zona de Empleado y activa el módulo

3. **Configurar permisos**: 
   - Ve a Usuarios → Grupos/Permisos
   - Asigna los permisos apropiados:
     - "Acceder a la Zona de Empleado" - para usuarios que pueden acceder
     - "Usar funcionalidades de la Zona de Empleado" - para usuarios activos
     - "Configurar la Zona de Empleado" - para administradores

4. **Acceder al portal**: Ve a la URL `/custom/zonaempleado/index.php` o usa el menú principal

5. **Probar con el demo**: Activa el módulo "Zona Empleado Demo" para ver ejemplos funcionales

## 📱 Experiencia de Usuario

### Dashboard Principal
- Tarjeta de perfil con información del usuario
- Accesos rápidos a funcionalidades disponibles
- Extensiones de otros módulos
- Actividad reciente del usuario

### Página de Perfil
- Información detallada del usuario
- Estadísticas personales
- Acciones rápidas
- Preferencias (próximamente)

### Navegación
- Menú superior con navegación principal
- Menú de usuario con opciones de perfil y logout
- Breadcrumbs en páginas secundarias
- Búsqueda rápida (próximamente)

## 🛠 Arquitectura Técnica

### Estructura de Archivos
```
zonaempleado/
├── index.php                 # Página principal
├── profile.php               # Página de perfil
├── class/
│   ├── zonaempleado.class.php # Clase principal
│   └── actions_zonaempleado.class.php # Hooks
├── core/
│   ├── modules/modZonaEmpleado.class.php # Definición del módulo
│   └── triggers/              # Triggers para logging
├── lib/zonaempleado.lib.php   # Funciones auxiliares
├── tpl/
│   ├── header.tpl.php         # Header personalizado
│   └── footer.tpl.php         # Footer personalizado
├── css/zonaempleado.css.php   # Estilos CSS
├── js/zonaempleado.js.php     # JavaScript
└── langs/es_ES/zonaempleado.lang # Traducciones
```

### Hooks Disponibles

El módulo proporciona 8 hooks principales para extensibilidad:

1. `registerEmployeeZoneExtension` - Registrar extensiones
2. `addQuickLinks` - Agregar enlaces rápidos al dashboard
3. `getEmployeeZoneMenu` - Agregar items al menú de navegación
4. `getRecentActivity` - Agregar actividades recientes
5. `getUserProfileStats` - Agregar estadísticas al perfil
6. `getUserProfileActions` - Agregar acciones rápidas al perfil
7. `addEmployeeZoneContent` - Agregar contenido al dashboard
8. `addEmployeeProfileContent` - Agregar contenido al perfil

### Sistema de Permisos

```php
// Verificar acceso básico
if (!$user->rights->zonaempleado->access->read) {
    accessforbidden();
}

// Verificar uso de funcionalidades
if ($user->rights->zonaempleado->use->write) {
    // Usuario puede usar funcionalidades
}

// Verificar permisos de configuración
if ($user->rights->zonaempleado->config->write) {
    // Usuario puede configurar
}
```

## 🔧 Extensión por Otros Módulos

La Zona de Empleado proporciona un sistema completo de extensibilidad mediante hooks que permite a otros módulos integrarse de forma automática y transparente.

### 📚 Documentación de Extensibilidad

- **[INTEGRATION_EXAMPLE.md](INTEGRATION_EXAMPLE.md)** - Guía completa con ejemplos de todos los hooks
- **[zonaempleadodemo/README.md](../zonaempleadodemo/README.md)** - Módulo de demostración funcional

### ⚡ Inicio Rápido

Para ver un ejemplo funcional completo:

1. Activa el módulo **"Zona Empleado Demo"** desde el área de administración
2. Refresca la Zona de Empleado para ver las integraciones automáticas:
   - Extensión "Demo Module" en el dashboard
   - Enlaces rápidos adicionales
   - Items en el menú de navegación
   - Actividades de ejemplo
   - Estadísticas y acciones en el perfil

### 🎯 Hooks Disponibles

| Hook | Propósito | Ubicación |
|------|-----------|-----------|
| `registerEmployeeZoneExtension` | Registrar extensiones que aparecen como tarjetas | Dashboard principal |
| `addQuickLinks` | Agregar enlaces rápidos | Dashboard principal |
| `getEmployeeZoneMenu` | Agregar items al menú | Menú de navegación |
| `getRecentActivity` | Agregar actividades | Dashboard y perfil |
| `getUserProfileStats` | Agregar estadísticas | Página de perfil |
| `getUserProfileActions` | Agregar acciones rápidas | Página de perfil |
| `addEmployeeZoneContent` | Agregar widgets personalizados | Dashboard principal |
| `addEmployeeProfileContent` | Agregar secciones | Página de perfil |

### 💡 Ejemplo Básico de Extensión

```php
class ActionsMiModulo
{
    public $resprints = '';
    
    /**
     * Hook para agregar enlaces rápidos al dashboard
     */
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;
        
        // Verificar permisos
        if (isset($parameters['quickLinks']) && $user->rights->mimodulo->read) {
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('MiAccionRapida'),
                'url' => '/custom/mimodulo/accion.php',
                'icon' => 'fa-star',
                'position' => 10  // Orden de aparición
            );
        }
        
        return 0;
    }
    
    /**
     * Hook para registrar una extensión completa
     */
    public function registerEmployeeZoneExtension($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;
        
        if (isset($parameters['extensions'])) {
            $parameters['extensions'][] = array(
                'title' => $langs->trans('MiModulo'),
                'description' => $langs->trans('MiModuloDesc'),
                'url' => '/custom/mimodulo/index.php',
                'icon' => 'fa-puzzle-piece',
                'position' => 20,
                'permissions' => $user->rights->mimodulo->read
            );
        }
        
        return 0;
    }
}
```

### 🔗 Integración en tu Módulo

1. **Registra los hooks** en tu módulo:
```php
// En tu clase modMiModulo
$this->module_parts = array(
    'hooks' => array(
        'zonaempleadoindex',    // Para hooks del dashboard
        'zonaempleadoprofile'   // Para hooks del perfil
    )
);
```

2. **Crea la clase de acciones** en `class/actions_mimodulo.class.php`

3. **Implementa los hooks** que necesites (ver ejemplos en INTEGRATION_EXAMPLE.md)

4. **Activa tu módulo** - las integraciones aparecerán automáticamente

### 📦 Módulo Demo

El módulo **zonaempleadodemo** implementa todos los hooks disponibles y sirve como:
- Guía de implementación completa
- Plantilla para nuevos desarrollos
- Herramienta de testing de integraciones

Ver código fuente en: `/custom/zonaempleadodemo/`

## 🎨 Personalización de Estilos

El módulo utiliza CSS moderno con:

- Variables CSS para colores y espaciado
- Flexbox y CSS Grid para layouts
- Animaciones CSS suaves
- Soporte para modo oscuro (preparado)
- Media queries para responsive design

### Colores Principales
- Primario: `#667eea` (azul gradiente)
- Secundario: `#764ba2` (púrpura gradiente)
- Fondo: `#f8f9fa` (gris claro)
- Texto: `#333` (gris oscuro)

## 📊 Funcionalidades Futuras

### En Desarrollo
- [ ] Sistema de notificaciones en tiempo real
- [ ] Búsqueda rápida global
- [ ] Configuración de preferencias de usuario
- [ ] Dashboard configurable con widgets arrastrables
- [ ] Modo oscuro completo
- [ ] PWA (Progressive Web App) support

### Extensiones Planificadas
- [ ] Integración con módulo de Timesheet
- [ ] Solicitudes de vacaciones
- [ ] Reportes de gastos simplificados
- [ ] Chat interno
- [ ] Calendario de eventos

## 🐛 Troubleshooting

### Problemas Comunes

**Error 403 - Acceso Denegado**
- Verificar que el módulo esté activado
- Verificar permisos del usuario
- Comprobar configuración de grupos

**Estilos no se cargan**
- Verificar permisos de archivo CSS
- Limpiar caché del navegador
- Verificar configuración de MAIN_FEATURES_LEVEL

**JavaScript no funciona**
- Verificar que JavaScript esté habilitado
- Comprobar consola del navegador para errores
- Verificar carga del archivo JS

### Debug Mode

Para habilitar debug:
1. Ve a Configuración → Sistema → Debug
2. Activa "Log debug info"
3. Revisa logs en `documents/dolibarr.log`

## 📝 Changelog

### Versión 1.0.0 (2025-11-17)
- Lanzamiento inicial
- Portal básico para empleados
- Sistema de permisos
- Diseño responsive
- Sistema de hooks para extensibilidad
- Documentación completa

## 🤝 Contribución

Para contribuir al desarrollo:

1. Fork del repositorio
2. Crear rama para nueva funcionalidad
3. Implementar cambios con tests
4. Enviar Pull Request

### Estándares de Código
- Seguir PSR-12 para PHP
- Usar ESLint para JavaScript
- Documentar todas las funciones públicas
- Incluir tests unitarios cuando sea posible

## 📚 Documentación Completa

Este módulo incluye documentación exhaustiva para usuarios y desarrolladores:

### Para Usuarios
- **[README.md](README.md)** (este archivo) - Vista general, instalación y características

### Para Desarrolladores
- **[📚 Índice de Documentación](docs/INDEX.md)** - Navegación completa por toda la documentación
- **[🎯 Referencia Rápida de Hooks](docs/QUICK_REFERENCE.md)** - Guía condensada para implementación
- **[📖 Guía de Integración](INTEGRATION_EXAMPLE.md)** - Ejemplos detallados de todos los hooks
- **[💡 Módulo Demo](../zonaempleadodemo/README.md)** - Implementación funcional completa

### Inicio Rápido para Desarrolladores

1. **Primeros pasos**: Lee [INTEGRATION_EXAMPLE.md](INTEGRATION_EXAMPLE.md)
2. **Referencia rápida**: Consulta [QUICK_REFERENCE.md](docs/QUICK_REFERENCE.md)
3. **Ejemplo funcional**: Activa el módulo "Zona Empleado Demo"
4. **Explora el código**: Revisa `zonaempleadodemo/class/actions_zonaempleadodemo.class.php`

## 📄 Licencia

Este módulo está licenciado bajo GPL v3. Ver archivo `COPYING` para detalles.

## 💬 Soporte

Para soporte y reportar bugs:

- **Documentación**: Consulta [docs/INDEX.md](docs/INDEX.md) para navegación completa
- **Ejemplos**: Revisa el módulo demo en `/custom/zonaempleadodemo/`
- **GitHub Issues**: [Crear issue] (si aplica)
- **Comunidad Dolibarr**: [Foro oficial](https://dolibarr.org/forum)

## 👥 Créditos

Desarrollado por el equipo de Zona Empleado Dev.

Basado en el framework de módulos de Dolibarr y siguiendo las mejores prácticas de desarrollo.

---

**Zona de Empleado** - Transformando la experiencia de usuario en Dolibarr 🚀
