NOTIFICACIONES - ZONA EMPLEADO
====================================

Este documento describe cómo se han agregado las notificaciones de Dolibarr al módulo Zona Empleado.

CAMBIOS REALIZADOS
------------------

1. **Actualización de Triggers** (interface_99_modZonaEmpleado_ZonaEmpleadoTriggers.class.php)
   - Agregado import de clase Notify
   - Definido array de eventos soportados (13 eventos)
   - Implementado método sendNotification()
   - Integración con sistema de triggers para enviar notificaciones automáticas

2. **Registro de Eventos** (modZonaEmpleado.class.php)
   - Agregado método registerNotificationEvents()
   - Registro automático de eventos en tabla c_action_trigger
   - Llamada al método en init() para registro al activar módulo

3. **Archivo SQL** (sql/llx_zonaempleado_notification_events.sql)
   - Define todas las entradas de eventos de notificación
   - Se ejecuta automáticamente al cargar el módulo

4. **Documentación**
   - NOTIFICATIONS.md: Descripción completa de todos los eventos
   - NOTIFICATION_EXAMPLES.md: Ejemplos prácticos de configuración

EVENTOS DISPONIBLES (13 Total)
------------------------------

Autenticación:
- ZONAEMPLEADO_USER_LOGIN        → Cuando un usuario inicia sesión
- ZONAEMPLEADO_USER_LOGOUT       → Cuando un usuario cierra sesión
- ZONAEMPLEADO_USER_REGISTRATION → Cuando se registra un nuevo usuario
- ZONAEMPLEADO_PROFILE_UPDATED   → Cuando se actualiza un perfil

Documentos:
- ZONAEMPLEADO_DOCUMENT_SHARED   → Cuando se comparte un documento

Anuncios:
- ZONAEMPLEADO_ANNOUNCEMENT_CREATED → Nuevo anuncio
- ZONAEMPLEADO_ANNOUNCEMENT_UPDATED → Anuncio actualizado

Vacaciones:
- ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED → Solicitud enviada
- ZONAEMPLEADO_HOLIDAY_REQUEST_APPROVED  → Solicitud aprobada
- ZONAEMPLEADO_HOLIDAY_REQUEST_REJECTED  → Solicitud rechazada

Nóminas:
- ZONAEMPLEADO_PAYSLIP_PUBLISHED → Nómina publicada

Mensajes:
- ZONAEMPLEADO_MESSAGE_RECEIVED  → Mensaje recibido

Horarios:
- ZONAEMPLEADO_SCHEDULE_MODIFIED → Horario modificado

REQUISITOS
---------

✓ Módulo "Notification" de Dolibarr debe estar habilitado
✓ Módulo "ZonaEmpleado" debe estar habilitado
✓ Email debe estar configurado en Dolibarr

CÓMO USAR
---------

1. Activar el módulo ZonaEmpleado (si no está activo)
   → Los eventos se registran automáticamente en la base de datos

2. Ir a Administración → Notificaciones y eventos de email

3. Buscar eventos que comiencen con "ZONAEMPLEADO_"

4. Configurar para cada evento:
   - Quién recibe las notificaciones
   - Qué plantilla de email usar
   - Condiciones especiales (si aplica)

5. Guardar configuración

CÓMO FUNCIONA INTERNAMENTE
--------------------------

1. Usuario realiza acción (p.ej. inicia sesión)
2. Sistema dispara trigger "USER_LOGIN"
3. Trigger es capturado por InterfaceZonaEmpleadoTriggers
4. Se llama a sendNotification('ZONAEMPLEADO_USER_LOGIN', ...)
5. Si módulo Notification está activo:
   - Se crea instancia de clase Notify
   - Notify::send() procesa el evento
   - Si hay suscriptores configurados, envía emails

INSTALACIÓN/ACTIVACIÓN
---------------------

Los eventos se registran automáticamente cuando:
1. Se activa el módulo ZonaEmpleado
2. Se ejecuta el método init() de modZonaEmpleado
3. Se llama a registerNotificationEvents()

No se requiere instalación manual de SQL.

VERIFICACIÓN
-----------

Para verificar que los eventos están registrados:

1. En base de datos:
   SELECT * FROM llx_c_action_trigger 
   WHERE code LIKE 'ZONAEMPLEADO_%';

2. En Dolibarr:
   Administración → Notificaciones y eventos de email
   → Buscar "ZONAEMPLEADO"

3. En logs del sistema:
   Administración → Módulos → Logs
   → Buscar "ZonaEmpleado"

COMPATIBILIDAD
--------------

✓ Dolibarr 14.0+
✓ PHP 5.6+
✓ MySQL/MariaDB

ARQUITECTURA
-----------

notifications/
├── Triggers (InterfaceZonaEmpleadoTriggers)
│   ├── sendNotification()
│   └── Array de eventos soportados
├── Módulo (modZonaEmpleado)
│   ├── registerNotificationEvents()
│   └── init() → Llamada al registro
└── SQL
    └── llx_zonaempleado_notification_events.sql

EXTENSIÓN FUTURA
---------------

Para agregar nuevos eventos de notificación:

1. Agregar evento al array en InterfaceZonaEmpleadoTriggers::$arrayofnotifsupported
2. Agregar case en runTrigger()
3. Llamar a sendNotification() con código del evento
4. Agregar entrada en registerNotificationEvents() en modZonaEmpleado
5. Agregar fila en llx_zonaempleado_notification_events.sql

Ejemplo:

```php
case 'TU_NUEVO_EVENTO':
    $this->sendNotification('ZONAEMPLEADO_TU_NUEVO_EVENTO', $object, $user, $langs, $conf);
    break;
```

TROUBLESHOOTING
--------------

P: ¿Las notificaciones no se envían?
R: 
1. Verificar módulo Notification habilitado
2. Verificar configuración de email en Dolibarr
3. Revisar eventos en Administración → Notificaciones
4. Revisar logs del sistema

P: ¿Cómo desactivo notificaciones?
R: Ve a Administración → Notificaciones y eventos de email
   → Busca el evento → No selecciones destinatarios

P: ¿Puedo personalizar plantillas?
R: Sí, en Administración → Notificaciones puedes crear/editar plantillas personalizadas

REFERENCIAS
----------

- Sistema de Notificaciones: docs/NOTIFICATIONS.md
- Ejemplos Prácticos: docs/NOTIFICATION_EXAMPLES.md
- Código: core/triggers/interface_99_modZonaEmpleado_ZonaEmpleadoTriggers.class.php
- Registro: core/modules/modZonaEmpleado.class.php

SOPORTE
-------

Para problemas o sugerencias, consultar:
- Documentación de Dolibarr: https://wiki.dolibarr.org
- Logs del sistema: Administración → Módulos → Logs
