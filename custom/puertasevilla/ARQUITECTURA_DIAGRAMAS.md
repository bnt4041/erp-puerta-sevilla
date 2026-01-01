# Diagrama del Sistema de Renovación de Contratos

## Arquitectura General

```
┌─────────────────────────────────────────────────────────────────┐
│                     DOLIBARR ERP 20.x                           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │           MÓDULO PUERTASEVILLA                           │  │
│  │                                                          │  │
│  │  ┌──────────────────┐      ┌──────────────────────┐    │  │
│  │  │   Frontend       │      │    Backend           │    │  │
│  │  │  (JavaScript)    │      │   (PHP)              │    │  │
│  │  │                  │      │                      │    │  │
│  │  ├─ Modal           │      ├─ Acciones AJAX       │    │  │
│  │  ├─ Validaciones    │      ├─ Lógica renovación   │    │  │
│  │  ├─ Preview         │      ├─ Triggers            │    │  │
│  │  └─ CSS             │      └─ BD                  │    │  │
│  │                     │                             │    │  │
│  └─────────────────────┴─────────────────────────────┘    │  │
│         ↕ AJAX / JSON                                      │  │
│                                                             │  │
│  ┌──────────────────────────────────────────────────────┐  │  │
│  │            Externa API (FRED)                        │  │  │
│  │  Obtención automática de IPC actual                  │  │  │
│  │  Fallback: Valor configurado en Dolibarr            │  │  │
│  └──────────────────────────────────────────────────────┘  │  │
│                                                             │  │
│  ┌──────────────────────────────────────────────────────┐  │  │
│  │          BASE DE DATOS (MySQL/MariaDB)              │  │  │
│  │                                                      │  │  │
│  │  ├─ llx_contrat (contratos)                         │  │  │
│  │  ├─ llx_contratdet (líneas)                         │  │  │
│  │  ├─ llx_facture_rec (facturas recurrentes)          │  │  │
│  │  ├─ llx_element_element (enlaces)                   │  │  │
│  │  └─ llx_puertasevilla_contract_renewal (auditoría)  │  │  │
│  │                                                      │  │  │
│  └──────────────────────────────────────────────────────┘  │  │
│                                                             │  │
└─────────────────────────────────────────────────────────────┘  │
```

## Flujo de Renovación Detallado

```
    USUARIO ABRE CONTRATO
            ↓
    ┌───────────────────────┐
    │ card.php carga:       │
    │ - renovar_buttons.php │──→ Inyecta botón "Renovar"
    │ - CSS renovacion.css  │
    │ - JS renovacion.js    │
    └───────────────────────┘
            ↓
        USUARIO HACE CLIC
            ↓
    ┌─────────────────────────────┐
    │ abrirModalRenovacion()      │
    │ - Crea HTML modal           │
    │ - Abre con jQuery UI dialog │
    │ - Llamada AJAX obtenerIPC   │
    └─────────────────────────────┘
            ↓
    ┌──────────────────────────────────────┐
    │  SERVIDOR: renovar_contrato.php      │
    │  action=obtenerIPC                   │
    │                                      │
    │  ├─ Intenta API FRED                 │
    │  ├─ Si falla → Valor configurado     │
    │  ├─ Cachea 24h                       │
    │  └─ Retorna JSON {ipc: 2.4}          │
    └──────────────────────────────────────┘
            ↓
    ┌──────────────────────────────┐
    │ Modal muestra:               │
    │ - Input fecha_inicio         │
    │ - Input fecha_fin            │
    │ - Radio: IPC o Importe       │
    │ - Input valor (IPC o €)      │
    │ - Preview de cambios         │
    └──────────────────────────────┘
            ↓
        USUARIO COMPLETA Y RENUEVA
            ↓
    ┌──────────────────────────────────────┐
    │ procesarRenovacion()                 │
    │ Envía POST:                          │
    │ - action=renovarContrato             │
    │ - contrat_id=123                     │
    │ - date_start=2025-01-01              │
    │ - date_end=2025-12-31                │
    │ - tipo_renovacion=ipc                │
    │ - valor=2.4                          │
    └──────────────────────────────────────┘
            ↓
    ┌─────────────────────────────────────────────┐
    │ SERVIDOR: renovar_contrato.php              │
    │ action=renovarContrato                      │
    │                                             │
    │ 1. Validaciones:                            │
    │    ├─ Usuario autenticado ✓                 │
    │    ├─ Parámetros válidos ✓                  │
    │    ├─ Permisos (creer contratos) ✓          │
    │    └─ Contrato existe ✓                     │
    │                                             │
    │ 2. Inicia transacción BD                    │
    │                                             │
    │ 3. Por cada línea del contrato:             │
    │    ├─ Actualiza date_start                  │
    │    ├─ Actualiza date_end                    │
    │    ├─ Si IPC: subprice *= (1 + ipc/100)    │
    │    ├─ Si Importe: subprice = valor          │
    │    ├─ Guarda línea (notrigger)              │
    │    └─ IMPORTANTE: NO DISPARA TRIGGER AQUÍ   │
    │                                             │
    │ 4. Actualiza contrato:                      │
    │    ├─ date_start                            │
    │    └─ date_end                              │
    │                                             │
    │ 5. Por cada línea del contrato:             │
    │    └─ Dispara LINECONTRACT_MODIFY trigger   │
    │                                             │
    │ 6. Commit transacción                       │
    │                                             │
    │ 7. Retorna JSON {success: true}             │
    └─────────────────────────────────────────────┘
            ↓
    ┌────────────────────────────────┐
    │ TRIGGER: LINECONTRACT_MODIFY   │
    │                                │
    │ Por cada línea:                │
    │ ├─ Busca factura recurrente    │
    │ │  asociada (element_element)  │
    │ │                              │
    │ ├─ Recalcula nb_gen_max:       │
    │ │  Meses entre date_start,end  │
    │ │                              │
    │ ├─ Actualiza precio en         │
    │ │  factura recurrente          │
    │ │                              │
    │ └─ Actualiza enlace contrato   │
    │    ↔ factura recurrente        │
    │                                │
    └────────────────────────────────┘
            ↓
    ┌──────────────────────────────┐
    │ CLIENT: Modal se cierra      │
    │ Página se recarga            │
    │ Nuevo contrato y factura     │
    │ se muestran con valores      │
    │ actualizados                 │
    └──────────────────────────────┘
            ↓
        ✅ RENOVACIÓN COMPLETADA
```

## Cambios en Base de Datos

```
ANTES DE RENOVACIÓN:
┌─ llx_contrat (ID=123)
│  ├─ date_start: 2024-01-01
│  └─ date_end: 2024-12-31
│
├─ llx_contratdet (ID=456, fk_contrat=123)
│  ├─ date_start: 2024-01-01
│  ├─ date_end: 2024-12-31
│  ├─ subprice: 100.00
│  └─ ForeignKey: fk_contrat=123
│
└─ llx_facture_rec (ID=789)
   ├─ nb_gen_max: 12
   ├─ subprice: 100.00
   └─ note_private: "Línea de contrato: 456"


DESPUÉS DE RENOVACIÓN (aplicar IPC 2.4%):
┌─ llx_contrat (ID=123)
│  ├─ date_start: 2025-01-01 ← ACTUALIZADO
│  └─ date_end: 2025-12-31 ← ACTUALIZADO
│
├─ llx_contratdet (ID=456, fk_contrat=123)
│  ├─ date_start: 2025-01-01 ← ACTUALIZADO
│  ├─ date_end: 2025-12-31 ← ACTUALIZADO
│  ├─ subprice: 102.40 ← ACTUALIZADO (100 × 1.024)
│  └─ ForeignKey: fk_contrat=123
│
├─ llx_facture_rec (ID=789)
│  ├─ nb_gen_max: 12 ← RECALCULADO (meses entre fechas)
│  ├─ subprice: 102.40 ← ACTUALIZADO
│  ├─ note_private: "Línea de contrato: 456"
│  └─ date_when: actualizado
│
└─ llx_element_element (nuevo)
   ├─ fk_source_type: contratdet
   ├─ fk_source_id: 456
   ├─ fk_target_type: facturerec
   ├─ fk_target_id: 789
   └─ bidireccional: ✓


AUDITORÍA (Opcional):
└─ llx_puertasevilla_contract_renewal
   ├─ fk_contrat: 123
   ├─ date_renewal: 2024-12-29 10:30:00
   ├─ user_renewal_id: 1
   ├─ date_start_old: 2024-01-01
   ├─ date_start_new: 2025-01-01
   ├─ date_end_old: 2024-12-31
   ├─ date_end_new: 2025-12-31
   ├─ type_renovation: ipc
   ├─ value_applied: 2.4
   └─ status: success
```

## Relaciones Objetos

```
Contrato
├─ ID: 123
├─ Ref: C-2024-001
├─ Status: Activo (4)
│
└─ Líneas (ContratLigne)
   │
   ├─ Línea 1 (ID: 456)
   │  ├─ Fechas: 2024-01-01 a 2024-12-31
   │  ├─ Precio: 100€
   │  └─ Element_Element → Factura Recurrente 789
   │                       ├─ nb_gen_max: 12
   │                       ├─ Precio: 100€
   │                       └─ Fecha inicio/fin generación
   │
   └─ Línea 2 (ID: 457)
      ├─ Fechas: 2024-01-01 a 2024-12-31
      ├─ Precio: 50€
      └─ Element_Element → Factura Recurrente 790
                           ├─ nb_gen_max: 12
                           ├─ Precio: 50€
                           └─ Fecha inicio/fin generación
```

## Stack Tecnológico

```
FRONTEND:
├─ JavaScript (vanilla)
├─ jQuery (venía con Dolibarr)
├─ jQuery UI Dialog (modal)
└─ CSS3 (responsive)

BACKEND:
├─ PHP 7.4+
├─ MySQL/MariaDB
├─ Dolibarr API interna
├─ cURL (para FRED API)
└─ Transacciones BD

EXTERNA:
└─ FRED API (Federal Reserve Economic Data)
```

## Seguridad - Capas de Protección

```
┌─────────────────────────────────────┐
│ 1. AUTHENTICATION                   │
│    ├─ Session Dolibarr validada     │
│    └─ $user->id requerido           │
├─────────────────────────────────────┤
│ 2. AUTHORIZATION                    │
│    ├─ user->rights->contrat->creer  │
│    └─ Propiedad del contrato        │
├─────────────────────────────────────┤
│ 3. INPUT VALIDATION                 │
│    ├─ Dates: dol_stringtotime()     │
│    ├─ Numbers: (int), (float)       │
│    └─ Strings: sanitizeFilename()   │
├─────────────────────────────────────┤
│ 4. DATA INTEGRITY                   │
│    ├─ Transacciones BD              │
│    ├─ Foreign keys                  │
│    └─ Constraints                   │
├─────────────────────────────────────┤
│ 5. SQL INJECTION PREVENTION         │
│    ├─ $db->escape()                 │
│    ├─ $db->idate()                  │
│    └─ Prepared statements           │
├─────────────────────────────────────┤
│ 6. CSRF PROTECTION                  │
│    ├─ Token de sesión Dolibarr      │
│    └─ Same-origin policy            │
└─────────────────────────────────────┘
```

## Timeline de Ejecución

```
Acción                              Tiempo aproximado
─────────────────────────────────────────────────────
Abre modal                          50ms
Obtiene IPC (con caché)             200ms
Preview                             10ms
Prepara datos                       50ms
POST al servidor                    100ms
├─ Validaciones                     50ms
├─ Actualiza BD                     200ms
├─ Dispara triggers                 300ms
└─ Respuesta JSON                   50ms
Modal cierra                        100ms
Recarga página                      500ms
─────────────────────────────────────────────────────
TOTAL (sin caché)                   ~1.5s
TOTAL (con caché)                   ~1.1s
```

---

Este diagrama proporciona una visión completa de cómo funciona el sistema
internamente y cómo interactúan todos sus componentes.
