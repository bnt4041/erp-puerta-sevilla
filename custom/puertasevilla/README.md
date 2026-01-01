# Módulo PuertaSevilla para Dolibarr

## Descripción

Módulo completo de gestión inmobiliaria para Dolibarr que permite gestionar:

- **Propietarios e Inquilinos**: Como terceros con campos personalizados
- **Viviendas**: Como proyectos con información detallada (superficie, habitaciones, etc.)
- **Contratos de Alquiler**: Con generación automática de facturas recurrentes
- **Mantenimientos**: Como pedidos con seguimiento completo
- **Facturación Automática**: Generación de facturas plantilla al activar líneas de contrato

## Instalación

1. Copiar la carpeta `puertasevilla` en `htdocs/custom/`
2. Ir a Inicio → Configuración → Módulos
3. Buscar "PuertaSevilla" y activarlo
4. El módulo creará automáticamente:
   - Campos extra (extrafields) en terceros, proyectos, contratos, facturas y pedidos
   - Diccionarios personalizados (tipos de mantenimiento, estados de vivienda, etc.)
   - Triggers para automatización

## Funcionalidades Principales

### 1. Campos Extra (Extrafields)

#### Terceros (Propietarios/Inquilinos)
- Rol (Propietario/Inquilino/Administrador)
- ID Origen (para migración)
- Nacionalidad
- Forma de Pago Origen
- Auto-informe

#### Proyectos (Viviendas)
- ID Origen
- Referencia de Vivienda
- Dirección completa
- Localidad
- Superficie (m²)
- Número de baños y dormitorios
- Referencia catastral
- Estado de la vivienda
- Datos de suministros (compañía, nº contrato)

#### Contratos
- ID Origen
- Día de pago (1-31)
- Inventario
- Auto-factura

#### Líneas de Contrato
- Cuenta bancaria (CCC/IBAN)
- Entidad bancaria

#### Facturas
- ID Origen
- Tipo de factura (Alquiler/Comunidad/Otros)

#### Pedidos (Mantenimientos)
- ID Origen
- Tipo de mantenimiento
- Horas trabajadas
- Observaciones

### 2. Diccionarios

El módulo crea los siguientes diccionarios:

- **Tipos de Mantenimiento**: Urgencia, Suministros, Reparación, Limpieza, Revisión, Otros
- **Categorías Contables**: Alquiler, Comunidad, Mantenimiento, Suministros, Otros
- **Estados de Vivienda**: Ocupada, Vacía, En Reforma, Baja
- **Formas de Pago**: Efectivo, Transferencia, Domiciliación, Tarjeta, Cheque

### 3. Generación Automática de Facturas

**Funcionamiento:**

1. Crear un contrato asociado a un tercero (inquilino)
2. Añadir líneas al contrato con:
   - Descripción del servicio
   - Importe mensual
   - IVA aplicable
   - Campos opcionales: cuenta bancaria, entidad bancaria
3. Configurar el "Día de Pago" en los campos extra del contrato (1-31)
4. **Al activar la línea del contrato**, el trigger automáticamente:
   - Crea una factura plantilla (recurrente) mensual
   - Asociada al tercero del contrato
   - Con el importe y descripción de la línea
   - Configurada para generar automáticamente cada mes
   - Con el día de pago especificado

**Ventajas:**
- Automatización completa de la facturación mensual
- Trazabilidad contrato → factura plantilla
- Configuración flexible por línea de contrato
- Información bancaria incluida en las facturas

## Uso

### Crear un Propietario/Inquilino

1. Ir a Terceros → Nuevo tercero
2. Rellenar datos básicos
3. En la pestaña "Campos Extra":
   - Seleccionar "Rol": Propietario o Inquilino
   - Completar nacionalidad, forma de pago, etc.

### Crear una Vivienda

1. Ir a Proyectos → Nuevo proyecto
2. En la pestaña "Campos Extra":
   - Referencia de vivienda
   - Dirección completa
   - Superficie, baños, dormitorios
   - Estado de la vivienda
   - Referencia catastral

### Crear un Contrato de Alquiler

1. Ir a Contratos → Nuevo contrato
2. Asociar al tercero (inquilino)
3. En "Campos Extra" del contrato:
   - Configurar "Día de Pago" (ej: 5 para el día 5 de cada mes)
   - Opcional: Inventario, Auto-factura
4. Añadir líneas al contrato:
   - Descripción: "Alquiler mensual"
   - Precio unitario: 800.00
   - Cantidad: 1
   - IVA: 21%
   - En campos extra de la línea: cuenta bancaria del propietario
5. **Activar la línea**: Click en "Activar servicio"
6. El sistema generará automáticamente una factura plantilla mensual

### Gestionar Mantenimientos

1. Ir a Pedidos → Nuevo pedido
2. Asociar al tercero (proveedor de mantenimiento)
3. Opcional: asociar al proyecto (vivienda)
4. En "Campos Extra":
   - Tipo de mantenimiento
   - Horas trabajadas
   - Observaciones

## Estructura de Archivos

```
puertasevilla/
├── core/
│   ├── modules/
│   │   └── modPuertaSevilla.class.php    # Descriptor del módulo
│   └── triggers/
│       └── interface_99_modPuertaSevilla_PuertaSevillaTriggers.class.php
├── admin/
│   ├── setup.php                          # Configuración
│   └── about.php                          # Acerca de
├── lib/
│   └── puertasevilla.lib.php              # Funciones auxiliares
├── langs/
│   └── es_ES/
│       └── puertasevilla.lang             # Traducciones
├── sql/
│   ├── llx_c_psv_tipo_mantenimiento.sql
│   ├── llx_c_psv_categoria_contable.sql
│   ├── llx_c_psv_estado_vivienda.sql
│   └── llx_c_psv_forma_pago.sql
└── README.md
```

## Configuración

Acceder a: **Inicio → Configuración → Módulos → PuertaSevilla → Configuración**

Parámetros disponibles:
- **Activar generación automática de facturas**: Habilita/deshabilita el trigger
- **ID plantilla de factura por defecto**: (Opcional) ID de plantilla base

## Logs y Depuración

Los eventos del módulo se registran en el log de Dolibarr (`syslog`).

Para activar logs detallados:
1. Ir a Inicio → Configuración → Otro → Syslog
2. Activar el módulo Syslog
3. Configurar nivel de log: Debug

## Requisitos

- Dolibarr 15.0 o superior
- Módulos necesarios activados:
  - Terceros (Societe)
  - Facturas (Facture)
  - Contratos (Contrat)
  - Proyectos (Projet)

## Soporte

Para soporte, contactar con:
- **Web**: https://www.puertasevillainmobiliaria.online
- **Email**: info@puertasevillainmobiliaria.online

## Licencia

GPL v3 - Ver archivo COPYING para más detalles

## Créditos

Desarrollado por PuertaSevilla Inmobiliaria
Copyright (C) 2024 PuertaSevilla
