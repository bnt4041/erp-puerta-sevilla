# 🚀 Guía de Inicio Rápido - Zona de Empleado

Esta guía te llevará paso a paso para activar y probar el módulo Zona de Empleado y su sistema de extensibilidad.

---

## ✅ Paso 1: Activar el Módulo Principal

### 1.1 Acceder al Panel de Administración

1. Inicia sesión en Dolibarr como administrador
2. Ve a: **Inicio → Configuración → Módulos/Aplicaciones**

### 1.2 Buscar y Activar

1. En el buscador escribe: "Zona de Empleado" o busca en la categoría "Otros"
2. Encuentra el módulo **"Zona de Empleado"**
3. Haz clic en **"Activar"**
4. Espera el mensaje de confirmación

### 1.3 Configurar Permisos

1. Ve a: **Inicio → Usuarios & Grupos → Grupos**
2. Selecciona el grupo de tus empleados (o crea uno nuevo)
3. En la pestaña **"Permisos"**, busca la sección **"Zona de Empleado"**
4. Marca las siguientes casillas:
   - ☑️ **Acceder a la Zona de Empleado** (read)
   - ☑️ **Usar funcionalidades de la Zona de Empleado** (write)
   - ⬜ **Configurar la Zona de Empleado** (solo para administradores)
5. Haz clic en **"Guardar"**

### 1.4 Primer Acceso

1. Abre una nueva pestaña o ventana del navegador
2. Navega a: `https://tu-dominio.com/custom/zonaempleado/`
3. Deberías ver el dashboard principal con:
   - Tu perfil de usuario
   - Sección de "Extensiones" (vacía por ahora)
   - Sección de "Enlaces Rápidos" (vacía por ahora)
   - Sección de "Actividad Reciente" (vacía por ahora)

---

## 🎯 Paso 2: Activar el Módulo Demo

El módulo demo implementa todos los 8 hooks disponibles y demuestra cómo integrar funcionalidades en la Zona de Empleado.

### 2.1 Acceder al Panel de Módulos

1. Ve a: **Inicio → Configuración → Módulos/Aplicaciones**
2. En el buscador escribe: "Demo"

### 2.2 Activar el Módulo Demo

1. Encuentra el módulo **"Zona Empleado Demo"**
2. Haz clic en **"Activar"**
3. Espera el mensaje de confirmación

### 2.3 Configurar Permisos del Demo

1. Ve a: **Inicio → Usuarios & Grupos → Grupos**
2. Selecciona el mismo grupo que configuraste antes
3. Busca la sección **"Zona Empleado Demo"**
4. Marca las casillas:
   - ☑️ **Leer el módulo Demo** (read)
   - ☑️ **Escribir en el módulo Demo** (write)
5. Haz clic en **"Guardar"**

---

## 🔍 Paso 3: Verificar las Integraciones

Ahora vamos a verificar que todas las integraciones del módulo demo funcionan correctamente.

### 3.1 Recargar la Zona de Empleado

1. Regresa a la pestaña de la Zona de Empleado
2. Recarga la página (F5 o Ctrl+R)

### 3.2 Verificar Dashboard Principal

Deberías ver las siguientes integraciones del módulo demo:

#### ✅ Tarjeta de Extensión
**Ubicación**: Sección "Extensiones"
- **Título**: "Demo Module"
- **Descripción**: "Módulo de demostración de integración..."
- **Icono**: Pieza de puzzle (🧩)
- **Botón**: "Acceder" → lleva a `/custom/zonaempleadodemo/employee.php`

#### ✅ Enlaces Rápidos (2 enlaces)
**Ubicación**: Sección "Enlaces Rápidos"
1. **"Ver Documentación"**
   - Icono: Libro (📖)
   - URL: `/custom/zonaempleado/INTEGRATION_EXAMPLE.md`
   
2. **"Página de Demo"**
   - Icono: Código (💻)
   - URL: `/custom/zonaempleadodemo/employee.php`

#### ✅ Menú de Navegación
**Ubicación**: Menú lateral izquierdo
- **Item**: "Demo Module"
- **Icono**: Cubo (📦)
- Al hacer clic debería llevarte a la página del demo

#### ✅ Actividades Recientes (2 actividades)
**Ubicación**: Sección "Actividad Reciente"
1. **"Hook ejecutado"**
   - Descripción: "El hook registerEmployeeZoneExtension..."
   - Icono: Engranaje (⚙️)
   - Fecha: Hoy
   
2. **"Módulo activado"**
   - Descripción: "El módulo Zona Empleado Demo..."
   - Icono: Check (✅)
   - Fecha: Hoy

#### ✅ Widget Personalizado
**Ubicación**: Parte inferior del dashboard
- **Título**: "Demo de Widget Personalizado"
- **Contenido**: Panel con explicación y ejemplo de contador
- **Estilo**: Ancho completo con fondo blanco

### 3.3 Verificar Página de Perfil

1. En el menú superior derecho, haz clic en tu **nombre de usuario**
2. Selecciona **"Mi Perfil"**

Deberías ver las siguientes integraciones:

#### ✅ Estadísticas (2 estadísticas)
**Ubicación**: Sección "Estadísticas"
1. **"Extensiones Demo"**
   - Valor: 1
   - Icono: Puzzle (🧩)
   
2. **"Hooks Implementados"**
   - Valor: 8
   - Icono: Plugin (🔌)

#### ✅ Acciones Rápidas (2 botones)
**Ubicación**: Debajo de la información del usuario
1. **"Ver Demo"**
   - Estilo: Botón azul
   - Icono: Ojo (👁️)
   
2. **"Ver Documentación"**
   - Estilo: Botón azul
   - Icono: Libro (📖)

#### ✅ Sección Personalizada
**Ubicación**: Parte inferior del perfil
- **Título**: "Información del Módulo Demo"
- **Contenido**: Panel con lista de características implementadas
- **Estilo**: Tabla con borde

---

## 🎓 Paso 4: Explorar la Página del Demo

### 4.1 Acceder a la Página

Haz clic en cualquiera de estos enlaces:
- Botón "Acceder" en la tarjeta de extensión del dashboard
- Enlace "Página de Demo" en enlaces rápidos
- Item "Demo Module" en el menú de navegación
- Botón "Ver Demo" en el perfil

### 4.2 Contenido de la Página

La página muestra **4 tarjetas informativas**:

1. **¿Qué es este módulo?**
   - Explicación general del propósito del demo

2. **Características Integradas**
   - Lista de las 8 integraciones implementadas
   - Checkmarks verdes para cada característica

3. **Cómo Funciona**
   - Explicación del sistema de hooks
   - Referencias a archivos de código

4. **Documentación**
   - Enlaces a guías y referencias
   - Botones para acceder a cada documento

---

## 📚 Paso 5: Estudiar el Código

Para aprender cómo implementar tus propias integraciones, estudia estos archivos:

### 5.1 Archivo Principal de Hooks
**Ubicación**: `/custom/zonaempleadodemo/class/actions_zonaempleadodemo.class.php`

**Qué contiene**:
- Implementación completa de los 8 hooks
- Comentarios detallados en español
- Ejemplos de verificación de permisos
- Ejemplos de consultas SQL
- Generación de HTML para widgets

**Cómo estudiarlo**:
1. Abre el archivo en tu editor
2. Lee los comentarios PHPDoc de cada método
3. Observa la estructura de los arrays devueltos
4. Nota las verificaciones de permisos (`$user->rights->...`)

### 5.2 Definición del Módulo
**Ubicación**: `/custom/zonaempleadodemo/core/modules/modZonaEmpleadoDemo.class.php`

**Qué contiene**:
- Configuración del módulo
- Registro de hooks: `'zonaempleadoindex'`, `'zonaempleadoprofile'`
- Dependencias: requiere `zonaempleado`
- Permisos del módulo

**Cómo usarlo como plantilla**:
1. Copia la estructura para tu módulo
2. Cambia el número del módulo (`numero`)
3. Modifica nombre y descripción
4. Mantén la estructura de hooks

### 5.3 Página de Ejemplo
**Ubicación**: `/custom/zonaempleadodemo/employee.php`

**Qué contiene**:
- Ejemplo de página integrada en la Zona de Empleado
- Uso de header/footer personalizados
- Tarjetas informativas con estilos
- Verificación de permisos

---

## 🔧 Paso 6: Crear Tu Primera Integración

Ahora que has visto cómo funciona, crea tu propia integración simple.

### 6.1 Escenario de Ejemplo

Vamos a crear un enlace rápido desde tu módulo existente.

### 6.2 Modificar Tu Módulo

**Archivo**: `/custom/tumodulo/core/modules/modTuModulo.class.php`

Agrega esto en el constructor:

```php
// Registrar hooks para Zona de Empleado
$this->module_parts = array(
    'hooks' => array(
        'zonaempleadoindex',  // Para integraciones en el dashboard
    )
);
```

### 6.3 Crear Clase de Acciones

**Archivo**: `/custom/tumodulo/class/actions_tumodulo.class.php`

```php
<?php
/**
 * Acciones para integración con Zona de Empleado
 */
class ActionsTuModulo
{
    public $resprints = '';
    
    /**
     * Agregar enlace rápido al dashboard
     */
    public function addQuickLinks($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;
        
        // Verificar que el array existe y que el usuario tiene permisos
        if (isset($parameters['quickLinks']) && $user->rights->tumodulo->read) {
            // Agregar el enlace
            $parameters['quickLinks'][] = array(
                'label' => $langs->trans('TuModuloAccion'),
                'url' => dol_buildpath('/tumodulo/index.php', 1),
                'icon' => 'fa-star',  // Elige tu icono de FontAwesome
                'position' => 10       // Orden de aparición
            );
        }
        
        return 0;
    }
}
```

### 6.4 Actualizar Traducciones

**Archivo**: `/custom/tumodulo/langs/es_ES/tumodulo.lang`

Agrega:
```
TuModuloAccion=Mi Acción Rápida
```

### 6.5 Activar y Probar

1. **Desactiva tu módulo** desde Configuración → Módulos
2. **Actívalo nuevamente** (esto recarga los hooks)
3. **Recarga la Zona de Empleado**
4. **Verifica** que aparece tu enlace en "Enlaces Rápidos"

---

## 🐛 Solución de Problemas

### ❌ No veo las integraciones del demo

**Posibles causas**:
1. El módulo demo no está activado
2. No tienes permisos configurados
3. No has recargado la página

**Solución**:
1. Verifica en Configuración → Módulos que "Zona Empleado Demo" está activo
2. Verifica en Usuarios & Grupos que tienes los permisos
3. Recarga la página con Ctrl+Shift+R (limpia caché)

### ❌ Los enlaces no funcionan

**Posibles causas**:
1. La ruta base de Dolibarr no está bien configurada
2. El archivo destino no existe

**Solución**:
1. Verifica que `$dolibarr_main_url_root` esté correcto en `conf.php`
2. Verifica que los archivos existan en las rutas especificadas

### ❌ Error 403 al acceder

**Causa**: Permisos insuficientes

**Solución**:
1. Ve a Usuarios & Grupos → Tu usuario/grupo
2. Verifica que tienes:
   - ☑️ Acceder a la Zona de Empleado (read)
   - ☑️ Leer el módulo Demo (read)

### ❌ CSS no se aplica correctamente

**Causa**: Cache del navegador

**Solución**:
1. Limpia caché del navegador (Ctrl+Shift+R)
2. O abre en modo incógnito para verificar

### ❌ Los hooks no se ejecutan en mi módulo

**Posibles causas**:
1. Hooks no registrados correctamente
2. Clase de acciones mal nombrada
3. Módulo no reactivado

**Solución**:
1. Verifica que `module_parts['hooks']` está en tu módulo
2. Verifica que la clase se llama `Actions[NombreModulo]`
3. Desactiva y reactiva tu módulo

---

## 📖 Siguientes Pasos

### Para Usuarios
- Explora todas las funcionalidades del portal
- Configura tus preferencias
- Reporta cualquier problema encontrado

### Para Desarrolladores
1. **Lee la documentación completa**:
   - [INDEX.md](docs/INDEX.md) - Índice general
   - [INTEGRATION_EXAMPLE.md](INTEGRATION_EXAMPLE.md) - Guía detallada
   - [QUICK_REFERENCE.md](docs/QUICK_REFERENCE.md) - Referencia rápida

2. **Estudia el módulo demo**:
   - Revisa cada hook implementado
   - Comprende la estructura de datos
   - Adapta los ejemplos a tu caso

3. **Implementa tus integraciones**:
   - Comienza con hooks simples (enlaces rápidos)
   - Avanza a hooks más complejos (widgets)
   - Prueba cada integración antes de avanzar

4. **Comparte tus módulos**:
   - Documenta tus integraciones
   - Comparte ejemplos con la comunidad
   - Contribuye al ecosistema

---

## 📞 Soporte

Si encuentras problemas:

1. **Consulta la documentación**:
   - [Troubleshooting en README](README.md#-troubleshooting)
   - [Errores comunes en QUICK_REFERENCE](docs/QUICK_REFERENCE.md#-errores-comunes)

2. **Revisa los logs**:
   - Ubicación: `/documents/dolibarr.log`
   - Busca mensajes con "ZonaEmpleado" o el nombre de tu módulo

3. **Modo debug**:
   ```php
   // En conf.php
   $dolibarr_main_prod = 0;  // Modo desarrollo
   ```

4. **Comunidad**:
   - Foro de Dolibarr
   - GitHub Issues (si aplica)
   - Documentación oficial

---

## ✅ Checklist Final

Marca cuando completes cada paso:

- [ ] Módulo "Zona de Empleado" activado
- [ ] Permisos configurados para tu usuario/grupo
- [ ] Acceso exitoso al dashboard principal
- [ ] Módulo "Zona Empleado Demo" activado
- [ ] Permisos del demo configurados
- [ ] Tarjeta de extensión visible en dashboard
- [ ] Enlaces rápidos (2) visibles
- [ ] Item de menú "Demo Module" visible
- [ ] Actividades recientes (2) visibles
- [ ] Widget personalizado visible en dashboard
- [ ] Estadísticas (2) visibles en perfil
- [ ] Acciones (2) visibles en perfil
- [ ] Sección personalizada visible en perfil
- [ ] Página del demo accesible y funcionando
- [ ] Código del demo estudiado
- [ ] Documentación leída
- [ ] Primera integración propia creada (opcional)

---

**¡Felicidades! 🎉**

Has completado la configuración de la Zona de Empleado y su sistema de extensibilidad. Ahora estás listo para crear tus propias integraciones y llevar tu Dolibarr al siguiente nivel.

---

**Zona de Empleado** - Guía de Inicio Rápido v1.0
