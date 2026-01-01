# Changelog - Módulo PuertaSevilla

Todos los cambios notables en este módulo serán documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.0.0] - 2024-12-28

### ✨ Añadido

#### Campos Extra (Extrafields)
- **Terceros (societe):** 5 campos personalizados
  - Rol (Propietario/Inquilino/Administrador)
  - ID Origen para migración
  - Nacionalidad
  - Forma de Pago Origen
  - Auto-informe (boolean)

- **Proyectos (projet):** 11 campos personalizados para viviendas
  - ID Origen para migración
  - Referencia de Vivienda
  - Dirección Completa
  - Localidad
  - Superficie en m²
  - Número de Baños
  - Número de Dormitorios
  - Referencia Catastral
  - Estado de la Vivienda
  - Datos de Suministros (Compañía, Nº Contrato, Nombre)

- **Contratos (contrat):** 4 campos personalizados
  - ID Origen para migración
  - Día de Pago (1-31)
  - Inventario (texto)
  - Auto-factura (boolean)

- **Líneas de Contrato (contratdet):** 2 campos personalizados
  - Cuenta Bancaria (CCC/IBAN)
  - Entidad Bancaria

- **Facturas (facture):** 2 campos personalizados
  - ID Origen para migración
  - Tipo de Factura (Alquiler/Comunidad/Otros)

- **Pedidos (commande):** 4 campos personalizados para mantenimientos
  - ID Origen para migración
  - Tipo de Mantenimiento
  - Horas Trabajadas
  - Observaciones

#### Diccionarios
- **Tipos de Mantenimiento:** Urgencia, Suministros, Reparación, Limpieza, Revisión, Otros
- **Categorías Contables:** Alquiler, Comunidad, Mantenimiento, Suministros, Otros
- **Estados de Vivienda:** Ocupada, Vacía, En Reforma, Baja
- **Formas de Pago:** Efectivo, Transferencia, Domiciliación, Tarjeta, Cheque

#### Automatización
- **Trigger de Contratos:** Generación automática de facturas plantilla (recurrentes) al activar líneas de contrato
  - Captura eventos `LINECONTRACT_ACTIVATE` y `LINECONTRACT_CREATE`
  - Crea FactureRec (factura plantilla) mensual
  - Configura frecuencia y día de pago según campos extra del contrato
  - Copia líneas del contrato a la factura plantilla
  - Mantiene trazabilidad completa
  - Incluye información bancaria en las facturas

#### Administración
- Página de configuración (`admin/setup.php`)
  - Parámetro: Activar generación automática de facturas
  - Parámetro: ID de plantilla de factura por defecto
- Página "Acerca de" (`admin/about.php`)
- Funciones auxiliares de navegación (`lib/puertasevilla.lib.php`)

#### Internacionalización
- Traducciones completas al español (`langs/es_ES/puertasevilla.lang`)
  - 80+ strings traducidos
  - Descripciones de todos los campos extra
  - Valores de diccionarios
  - Mensajes del sistema

#### Documentación
- README completo con guía de uso
- INSTALL.md con guía de instalación paso a paso
- RESUMEN_EJECUTIVO.md con overview técnico
- CHANGELOG.md (este archivo)
- Código completamente comentado en español

#### Estructura
- Estructura de módulo Dolibarr estándar
- Cumplimiento con convenciones de Dolibarr
- Preparado para futuras extensiones
- Compatibilidad con Dolibarr 15.0+

### 🔧 Configuración

#### Dependencias requeridas
- Módulo Terceros (Societe)
- Módulo Contratos (Contrat)
- Módulo Facturas (Facture)
- Módulo Proyectos (Projet)

#### Scripts SQL
- `llx_c_psv_tipo_mantenimiento.sql`: Diccionario de tipos de mantenimiento
- `llx_c_psv_categoria_contable.sql`: Diccionario de categorías contables
- `llx_c_psv_estado_vivienda.sql`: Diccionario de estados de vivienda
- `llx_c_psv_forma_pago.sql`: Diccionario de formas de pago

### 📝 Notas de implementación

#### Trigger de Facturas Plantilla
El trigger implementa la lógica descrita en el punto 2.5 del documento de adaptación:
1. Escucha la activación de líneas de contrato
2. Verifica que el contrato tenga tercero asociado
3. Obtiene el día de pago de los campos extra del contrato
4. Crea una factura plantilla (FactureRec) con:
   - Frecuencia mensual
   - Tercero del contrato
   - Líneas copiadas del contrato
   - Día de generación según configuración
   - Proyecto asociado (si existe)
   - Condiciones y modo de pago del contrato
5. Registra trazabilidad en nota privada de la línea de contrato
6. Genera logs en syslog de Dolibarr

#### Campos Extra
Todos los campos extra se crean automáticamente al activar el módulo mediante el método `init()` del descriptor. Los campos están organizados por:
- Posición (100-600, agrupados por objeto)
- Visibilidad (todos visibles en formularios)
- Tipo de dato apropiado (int, varchar, double, boolean, select, text)
- Descripciones legibles en español

#### Diccionarios
Los diccionarios se integran en el sistema de diccionarios de Dolibarr y aparecen automáticamente en:
- Configuración → Diccionarios
- Listas desplegables en formularios
- Pueden ser extendidos por el administrador

### 🎯 Casos de uso implementados

1. **Gestión de Propietarios e Inquilinos**
   - Clasificación mediante campo "Rol"
   - Datos completos (nacionalidad, forma de pago)
   - Trazabilidad con ID origen para migración

2. **Gestión de Viviendas**
   - Como proyectos de Dolibarr
   - Información completa (superficie, habitaciones, catastro)
   - Estado de ocupación
   - Datos de suministros

3. **Contratos de Alquiler**
   - Asociados a inquilino (tercero)
   - Día de pago configurable
   - Inventario incluido
   - Generación automática de facturas mensuales

4. **Facturación Recurrente**
   - Automática al activar línea de contrato
   - Mensual por defecto
   - Información bancaria incluida
   - Sin intervención manual

5. **Mantenimientos**
   - Como pedidos de Dolibarr
   - Clasificación por tipo
   - Registro de horas trabajadas
   - Asociados a viviendas (proyectos)

### 🚀 Roadmap futuro

Posibles mejoras para versiones futuras:
- [ ] Página de migración de datos desde SQL (punto 3 del documento)
- [ ] Sistema de firma digital de contratos (punto 8.2 del documento)
- [ ] Botón para añadir terceros como contactos automáticamente
- [ ] Dashboard de gestión inmobiliaria
- [ ] Informes personalizados (ocupación, ingresos, mantenimientos)
- [ ] Integración con pasarelas de pago
- [ ] Notificaciones automáticas a inquilinos/propietarios
- [ ] Portal del inquilino
- [ ] Gestión de incidencias
- [ ] Calendario de vencimientos

### 🐛 Problemas conocidos

Ninguno en la versión inicial.

### 🔒 Seguridad

- Todos los accesos requieren autenticación de Dolibarr
- Configuración limitada a administradores
- Sin exposición de datos sensibles en logs públicos
- Validación de permisos mediante sistema de Dolibarr

---

## Formato de versiones

- **[X.Y.Z]**: Versión liberada
- **X**: Cambio mayor (incompatible con versiones anteriores)
- **Y**: Nuevas funcionalidades (compatible con versiones anteriores)
- **Z**: Corrección de errores (compatible con versiones anteriores)

---

**Versión actual: 1.0.0**
**Fecha: 28 de diciembre de 2024**
**Estado: Estable - Producción**
