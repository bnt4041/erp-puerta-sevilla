# Ejemplos Prácticos - Módulo PuertaSevilla

Este documento contiene ejemplos prácticos paso a paso para usar el módulo PuertaSevilla.

---

## 📋 Ejemplo 1: Crear un Alquiler Completo (Caso Real)

### Escenario
Tenemos una vivienda en "Calle Sierpes 25, Sevilla" que vamos a alquilar a Juan Pérez por 850€/mes + 80€ comunidad. El pago se hace el día 5 de cada mes.

### Pasos

#### 1. Crear el Propietario
```
Terceros → Nuevo Tercero
Nombre: María González López
Tipo: Particular
NIF: 12345678A
Email: maria.gonzalez@email.com
Teléfono: 954123456

Pestaña "Campos Extra":
- Rol: Propietario
- Nacionalidad: España
- Forma de Pago Origen: Transferencia
```

#### 2. Crear el Inquilino
```
Terceros → Nuevo Tercero
Nombre: Juan Pérez Martínez
Tipo: Particular
NIF: 87654321B
Email: juan.perez@email.com
Teléfono: 954654321

Pestaña "Campos Extra":
- Rol: Inquilino
- Nacionalidad: España
- Forma de Pago Origen: Domiciliación
```

#### 3. Crear la Vivienda (como Proyecto)
```
Proyectos → Nuevo Proyecto
Título: Vivienda Sierpes 25
Ref: VIV-SIE-025
Tercero: María González López (propietario)

Pestaña "Campos Extra":
- Referencia de Vivienda: SIE025
- Dirección Completa: Calle Sierpes 25, 2º A
- Localidad: Sevilla
- Superficie (m²): 85
- Nº Baños: 1
- Nº Dormitorios: 2
- Referencia Catastral: 1234567VG1234S0001AB
- Estado de la Vivienda: Vacia
```

#### 4. Crear el Contrato
```
Contratos → Nuevo Contrato
Tercero: Juan Pérez Martínez (inquilino)
Proyecto: Vivienda Sierpes 25

Pestaña "Campos Extra":
- Día de Pago (1-31): 5
- Inventario: "1 Nevera, 1 Lavadora, 1 Microondas..."
```

#### 5. Añadir Línea: Alquiler
```
En el contrato → Añadir Servicio

Descripción: Alquiler mensual - Calle Sierpes 25, 2º A
Fecha inicio: 01/01/2025
Fecha fin prevista: 31/12/2025 (opcional)
Precio unitario: 850.00
IVA: 21%

Pestaña "Campos Extra" de la línea:
- Cuenta Bancaria (CCC/IBAN): ES79 2100 0813 4502 0005 1234
- Entidad Bancaria: La Caixa

✅ Activar el servicio
```

**→ RESULTADO:** Se crea automáticamente una factura plantilla mensual de 850€ que se generará el día 5 de cada mes.

#### 6. Añadir Línea: Comunidad
```
En el contrato → Añadir Servicio

Descripción: Gastos de comunidad - Calle Sierpes 25, 2º A
Fecha inicio: 01/01/2025
Precio unitario: 80.00
IVA: 21%

Pestaña "Campos Extra" de la línea:
- Cuenta Bancaria (CCC/IBAN): ES79 2100 0813 4502 0005 1234
- Entidad Bancaria: La Caixa

✅ Activar el servicio
```

**→ RESULTADO:** Se crea otra factura plantilla mensual de 80€.

#### 7. Verificar Facturas Plantilla
```
Facturas → Facturas Recurrentes/Plantillas

Deberías ver:
✅ Factura plantilla: "Factura recurrente - Contrato XXX - Alquiler mensual"
   - Tercero: Juan Pérez Martínez
   - Importe: 850€ + IVA
   - Frecuencia: Mensual
   - Día de generación: 5

✅ Factura plantilla: "Factura recurrente - Contrato XXX - Gastos de comunidad"
   - Tercero: Juan Pérez Martínez
   - Importe: 80€ + IVA
   - Frecuencia: Mensual
   - Día de generación: 5
```

---

## 🔧 Ejemplo 2: Registrar un Mantenimiento

### Escenario
La lavadora de la vivienda Sierpes 25 se ha estropeado. Llamamos a un técnico.

### Pasos

#### 1. Crear Tercero Proveedor (si no existe)
```
Terceros → Nuevo Tercero
Nombre: Electrodomésticos Sevilla S.L.
Tipo: Empresa
CIF: B12345678
Email: info@electrosevilla.com
Proveedor: ✅ Sí
```

#### 2. Crear Pedido de Mantenimiento
```
Pedidos → Nuevo Pedido (Proveedor)
Tercero: Electrodomésticos Sevilla S.L.
Proyecto: Vivienda Sierpes 25
Fecha: 15/01/2025

Pestaña "Campos Extra":
- Tipo de Mantenimiento: Reparación
- Horas Trabajadas: 2.5
- Observaciones: "Reparación de lavadora - Cambio de bomba de agua"

Añadir línea:
Descripción: Reparación lavadora - Cambio bomba
Cantidad: 1
Precio unitario: 85.00
```

#### 3. Validar y Gestionar
```
→ Validar pedido
→ Marcar como recibido (cuando se complete)
→ Generar factura de compra (si procede)
```

---

## 📊 Ejemplo 3: Consultar Datos de Gestión

### Ver todas las viviendas
```
Proyectos → Lista
Filtrar por: (ninguno para ver todas)

Ver campos extra para:
- Estado (Ocupada/Vacía)
- Superficie
- Ubicación
```

### Ver inquilinos activos
```
Terceros → Lista
Usar búsqueda avanzada:
- Campo extra "Rol" = "Inquilino"

O usar búsqueda estándar y filtrar visualmente
```

### Ver contratos activos
```
Contratos → Lista
Estado: Activos

Ver campos extra para:
- Día de pago
- Auto-factura
```

### Ver facturas recurrentes
```
Facturas → Facturas Recurrentes
Aquí verás todas las plantillas creadas automáticamente
```

---

## 🔄 Ejemplo 4: Finalizar un Alquiler

### Escenario
Juan Pérez finaliza su alquiler el 31/03/2025.

### Pasos

#### 1. Cerrar líneas del contrato
```
Contrato → Ver líneas
Para cada línea activa:
→ Click en "Cerrar servicio"
→ Fecha de cierre: 31/03/2025
```

#### 2. Cerrar contrato
```
En el contrato:
→ Cerrar contrato
→ Fecha: 31/03/2025
```

#### 3. Desactivar facturas plantilla
```
Facturas → Facturas Recurrentes
Para cada factura del contrato:
→ Editar
→ Poner "Suspendida" o eliminar

O configurar "Fecha máxima de generación": 31/03/2025
```

#### 4. Actualizar estado vivienda
```
Proyectos → Vivienda Sierpes 25
Pestaña "Campos Extra":
- Estado de la Vivienda: Vacía
```

---

## 🏗️ Ejemplo 5: Migración desde Sistema Antiguo

### Escenario
Tenemos datos en el SQL de PuertaSevilla antiguo y queremos migrarlos.

### Preparación (manual)

#### 1. Crear Terceros con ID Origen
```
Al crear cada tercero desde datos antiguos:

Pestaña "Campos Extra":
- ID Origen del Tercero: 123 (ID del sistema antiguo)

Esto permite trazabilidad y evitar duplicados en futuras migraciones.
```

#### 2. Crear Viviendas con ID Origen
```
Al crear cada proyecto:

Pestaña "Campos Extra":
- ID Origen de Vivienda: 456 (ID del sistema antiguo)
```

#### 3. Crear Contratos con ID Origen
```
Al crear cada contrato:

Pestaña "Campos Extra":
- ID Origen Contrato Usuario: 789 (ID del sistema antiguo)
```

#### 4. Crear Facturas con ID Origen
```
Al crear cada factura:

Pestaña "Campos Extra":
- ID Origen Factura: 101112 (ID del sistema antiguo)
```

**Nota:** Para migración masiva, ver punto 3 del documento ADAPTACION_DOLIBARR_PUERTASEVILLA.md (pendiente de implementar interfaz web).

---

## 💡 Ejemplo 6: Configuración de Día de Pago Variable

### Escenario
Tenemos inquilinos que pagan en días diferentes del mes.

### Solución

Cada contrato tiene su propio "Día de Pago" en campos extra:

```
Contrato A (Juan Pérez):
- Día de Pago: 5
→ Facturas se generan el día 5 de cada mes

Contrato B (Ana García):
- Día de Pago: 1
→ Facturas se generan el día 1 de cada mes

Contrato C (Luis Martín):
- Día de Pago: 15
→ Facturas se generan el día 15 de cada mes
```

El trigger lee este campo y configura automáticamente la factura plantilla.

---

## 📱 Ejemplo 7: Gestión de Suministros

### Escenario
Registrar datos de suministros de la vivienda.

### Pasos

```
Proyectos → Vivienda Sierpes 25
Pestaña "Campos Extra":

Suministro Eléctrico:
- Compañía Suministros: Endesa
- Nº Contrato Suministros: 12345678
- Nombre Compañía: Endesa Energía S.A.

(Para agua, gas, etc., se pueden añadir más campos extra si es necesario,
o usar el campo "Observaciones" en el proyecto)
```

---

## 🔍 Ejemplo 8: Búsquedas y Filtros Útiles

### Encontrar viviendas vacías
```
Proyectos → Lista → Búsqueda avanzada
Campo extra "Estado de la Vivienda" = "Vacía"
```

### Encontrar contratos que vencen este mes
```
Contratos → Lista
Filtrar por fecha fin prevista: entre 01/01/2025 y 31/01/2025
```

### Ver facturas de alquiler vs comunidad
```
Facturas → Lista → Búsqueda avanzada
Campo extra "Tipo de Factura" = "Alquiler"
```

### Ver mantenimientos urgentes
```
Pedidos → Lista → Búsqueda avanzada
Campo extra "Tipo de Mantenimiento" = "Urgencia"
```

---

## ⚠️ Ejemplo 9: Resolución de Problemas Comunes

### Problema: La factura plantilla no se creó

**Verificar:**
1. El módulo PuertaSevilla está activado
2. El contrato tiene un tercero asociado
3. La línea del contrato está activada (no en borrador)
4. La línea tiene precio > 0
5. Ver logs: Configuración → Otro → Syslog

**Solución:**
```
Si la línea ya está activada pero no se creó la factura:
1. Desactivar la línea
2. Activarla de nuevo
3. El trigger debería ejecutarse
```

### Problema: No veo los campos extra

**Verificar:**
1. El módulo está activado
2. Desactivar y reactivar el módulo
3. Limpiar caché: Herramientas → Limpiar caché

### Problema: Las facturas se generan en día incorrecto

**Verificar:**
1. Campo "Día de Pago" en el contrato (1-31)
2. Si está vacío o es 0, se usa día 1 por defecto
3. Editar factura plantilla manualmente si es necesario

---

## 🎓 Tips y Mejores Prácticas

### 1. Nomenclatura de Referencias
```
Viviendas:
- VIV-CALLE-NUMERO: VIV-SIE-025, VIV-TRI-103

Contratos:
- El sistema genera automáticamente (CO2024-XXXX)

Facturas:
- El sistema genera automáticamente (FA2024-XXXX)
```

### 2. Uso de Proyectos
```
- Cada vivienda = 1 proyecto
- Asociar todos los contratos a ese proyecto
- Asociar todos los mantenimientos a ese proyecto
- Permite ver histórico completo por vivienda
```

### 3. Gestión de Contactos
```
En el tercero (inquilino/propietario):
→ Pestaña "Contactos"
→ Añadir contactos adicionales (avalistas, co-inquilinos, etc.)
```

### 4. Documentos
```
Adjuntar documentos importantes:
- DNI/NIE en la ficha del tercero
- Contrato firmado en el contrato
- Facturas de mantenimiento en el pedido
- Fotos de la vivienda en el proyecto
```

### 5. Categorías
```
Usar categorías de Dolibarr para:
- Agrupar viviendas por zona
- Clasificar inquilinos por perfil
- Separar propiedades propias de gestionadas
```

---

## 📈 Ejemplo 10: Informes Básicos

### Ingresos mensuales por concepto
```
Facturas → Lista
Filtros:
- Fecha: Último mes
- Estado: Pagadas
- Campo extra "Tipo": Alquiler

Sumar total → Ingresos de alquiler del mes

Repetir con "Tipo": Comunidad → Ingresos de comunidad
```

### Gastos de mantenimiento por vivienda
```
Pedidos → Lista
Filtros:
- Proyecto: Vivienda Sierpes 25
- Fecha: Año actual
- Campo extra "Tipo de Mantenimiento": (cualquiera)

Exportar a Excel para análisis
```

---

**💡 Consejo Final:** Mantén siempre actualizados los campos extra, especialmente el "Estado de la Vivienda" y los IDs de origen. Esto facilita la gestión y permite generar informes precisos.
