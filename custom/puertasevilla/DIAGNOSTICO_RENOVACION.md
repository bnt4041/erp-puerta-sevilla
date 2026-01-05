# 📋 Diagnóstico del Sistema de Renovación de Contratos

## Estado del Sistema

### ✅ Archivos Críticos Verificados

#### 1. **renovar_contrato.php** (/custom/puertasevilla/core/actions/renovar_contrato.php)
- ✅ Líneas 1-38: Constantes y carga de Dolibarr
  - Define NOREQUIREUSER y NOREQUIREDB
  - Calcula dinámicamente la ruta a main.inc.php
  - Incluye fallback para diferentes estructuras
- ✅ Líneas 39-120: Función obtenerIPCActual()
  - Implementa caché de 24 horas
  - Usa FRED API (Federal Reserve Economic Data)
  - Fallback a 2.4% si API no disponible
  - Maneja excepciones correctamente
- ✅ Líneas 121-138: POST handler para obtenerIPC
  - Retorna JSON con IPC actual y timestamp
  - Encabezado Content-Type: application/json
- ✅ Líneas 140-247: POST handler para renovarContrato
  - Validación de parámetros de entrada
  - Transacciones de base de datos (begin/commit/rollback)
  - Actualización de líneas del contrato
  - Cálculo de nuevo precio (IPC o importe fijo)
  - Ejecución de triggers para actualizar facturas recurrentes

#### 2. **renovar_contrato_modal.js** (/custom/puertasevilla/js/renovar_contrato_modal.js)
- ✅ Funciones globales (sin namespace IIFE)
  - abrirModalRenovacion()
  - obtenerIPCActual()
  - previewRenovacion()
  - guardarRenovacion()
  - actualizarFechasFinales()

#### 3. **Inyecciones en Core**
- ✅ /htdocs/contrat/card.php (línea 2384)
  - Inyecta botón "Renovar contrato" en vista de tarjeta
  - Usa onClick="abrirModalRenovacion({id})"
- ✅ /htdocs/contrat/list.php (línea 1392)
  - Inyecta acción en menú de lista
  - Permite renovación masiva

---

## 🧪 Procedimiento de Diagnóstico

### Paso 1: Verificar Dolibarr Bootstrap

**URL**: `http://localhost/custom/puertasevilla/test_renovar.php`

**Qué verificar**:
- [ ] DOL_DOCUMENT_ROOT esté definido
- [ ] Usuario esté cargado
- [ ] Base de datos conectada
- [ ] Módulo puertasevilla habilitado

**Salida esperada**:
```
DOL_DOCUMENT_ROOT: ✓ DEFINIDO
Valor: /var/www/html/dolpuerta/htdocs
Usuario: ✓ Cargado
Base de Datos: ✓ Conectada
Módulo PuertaSevilla: ✓ Habilitado
```

### Paso 2: Probar Endpoint AJAX

1. Desde el archivo de prueba, hacer clic en "Obtener IPC Actual"
2. Verificar que retorne JSON válido:
```json
{
  "success": true,
  "ipc": 2.4,
  "timestamp": "2024-01-15 14:30:45"
}
```

**Si falla**:
- Abrir consola de navegador (F12)
- Verificar respuesta AJAX
- Revisar logs: `tail -50 /var/www/html/dolpuerta/documents/dolibarr.log`

### Paso 3: Probar Interfaz de Usuario

1. Navegar a un contrato: `/contrat/card.php?id=1`
2. Buscar botón "Renovar contrato"
3. Hacer clic para abrir modal
4. Verificar que modal cargue sin errores JavaScript

**Consola esperada** (sin errores):
- ✓ Modal abierto
- ✓ Campo de fechas accesible
- ✓ Botón de obtener IPC funciona

---

## 🔧 Estructura de Archivos

```
/custom/puertasevilla/
├── core/
│   ├── actions/
│   │   └── renovar_contrato.php          ✅ AJAX endpoint
│   ├── hooks/
│   │   └── interface_*.class.php         ✅ Hooks definidos
│   └── triggers/
│       └── interface_99_*.class.php      ✅ Triggers para facturas
├── js/
│   └── renovar_contrato_modal.js         ✅ Lógica del modal
├── css/
│   └── renovar_contrato_modal.css        ✅ Estilos
├── includes/
│   ├── inject_renovacion_button.php      ✅ Inyección en card.php
│   └── inject_renovacion_list.php        ✅ Inyección en list.php
└── test_renovar.php                      ✅ Archivo de diagnóstico
```

---

## 📊 Flujo de Ejecución

```
Usuario hace clic en "Renovar contrato"
        ↓
abrirModalRenovacion() - Abre jQuery UI dialog
        ↓
Usuario ingresa fechas y selecciona tipo de renovación
        ↓
obtenerIPCActual() - Solicita AJAX a renovar_contrato.php?action=obtenerIPC
        ↓
renovar_contrato.php obtiene IPC de FRED API o caché
        ↓
Retorna JSON con IPC actual
        ↓
previewRenovacion() - Calcula y muestra nuevo precio
        ↓
Usuario hace clic en "Guardar"
        ↓
guardarRenovacion() - Envía POST a renovar_contrato.php?action=renovarContrato
        ↓
renovar_contrato.php:
  1. Valida parámetros
  2. Carga contrato
  3. Verifica permisos
  4. Actualiza líneas del contrato
  5. Ejecuta triggers para actualizar facturas recurrentes
  6. Retorna JSON con resultado
        ↓
Modal cierra y página se recarga
        ↓
Contrato renovado correctamente ✅
```

---

## 🐛 Troubleshooting

### Error: "Undefined constant DOL_DOCUMENT_ROOT"
**Solución**: Verificar que renovar_contrato.php calcule la ruta correctamente
```php
$rootPath = dirname(dirname(dirname(dirname(__FILE__))));
require_once $rootPath.'/main.inc.php';
```

### Error: "Cannot read properties of undefined"
**Solución**: Verificar que funciones JavaScript sean globales, no dentro de IIFE
```javascript
// ✓ CORRECTO
window.abrirModalRenovacion = function() { ... }

// ✗ INCORRECTO
(function() { function abrirModalRenovacion() { ... } })();
```

### Error: AJAX retorna HTML en lugar de JSON
**Solución**: 
1. Verificar que main.inc.php cargue correctamente
2. Revisar que no haya errores PHP antes del JSON
3. Revisar logs: `tail -50 /var/www/html/dolpuerta/documents/dolibarr.log`

### Error: "AJAX failed to fetch"
**Solución**:
1. Verificar que la ruta sea correcta: `/custom/puertasevilla/core/actions/renovar_contrato.php`
2. Verificar CORS si está usando dominio diferente
3. Abrir F12 → Red → Revisar respuesta exacta

---

## 📝 Logs Importantes

**Archivo principal**: `/var/www/html/dolpuerta/documents/dolibarr.log`

**Comandos útiles**:
```bash
# Ver últimas 50 líneas
tail -50 /var/www/html/dolpuerta/documents/dolibarr.log

# Ver errores de hoy
grep "$(date +%Y-%m-%d)" /var/www/html/dolpuerta/documents/dolibarr.log | grep -i error

# Ver logs en tiempo real
tail -f /var/www/html/dolpuerta/documents/dolibarr.log
```

---

## ✅ Checklist Final

Antes de considerar el sistema listo:

- [ ] `test_renovar.php` muestra todas las verificaciones en verde
- [ ] AJAX obtenerIPC retorna JSON válido
- [ ] Botón "Renovar contrato" aparece en vista de contrato
- [ ] Modal abre sin errores JavaScript
- [ ] Se puede ingresar fechas y ver preview
- [ ] Guardar renovación actualiza el contrato
- [ ] Facturas recurrentes se actualizan correctamente
- [ ] No hay errores en F12 Console
- [ ] No hay errores en dolibarr.log

---

## 🎯 Próximas Acciones

1. **Ejecutar test_renovar.php** para diagnosticar problemas
2. **Revisar consola JavaScript** (F12) para errores
3. **Verificar logs de Dolibarr** si algo falla
4. **Probar flujo completo** de renovación
5. **Documentar cualquier error** encontrado

---

**Última actualización**: 2024-01-15
**Estado**: LISTO PARA PROBAR
