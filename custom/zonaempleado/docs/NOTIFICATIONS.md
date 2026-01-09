# Sistema de Notificaciones - Zona Empleado

## Descripción

El módulo Zona Empleado implementa un sistema completo de notificaciones que se integra con el sistema de notificaciones de Dolibarr. Este sistema permite enviar notificaciones por email automáticamente cuando ocurren eventos específicos en la plataforma.

## Eventos Soportados

### Autenticación y Gestión de Usuarios

#### ZONAEMPLEADO_USER_LOGIN
- **Tipo**: Evento de usuario
- **Descripción**: Se dispara cuando un usuario inicia sesión en la Zona de Empleado
- **Uso**: Notificar administratores de accesos o registrar auditoría
- **Contexto**: Usuario que inicia sesión

#### ZONAEMPLEADO_USER_LOGOUT
- **Tipo**: Evento de usuario
- **Descripción**: Se dispara cuando un usuario cierra sesión
- **Uso**: Auditoría y registro de actividades
- **Contexto**: Usuario que cierra sesión

#### ZONAEMPLEADO_USER_REGISTRATION
- **Tipo**: Evento de usuario
- **Descripción**: Se dispara cuando se crea un nuevo usuario en el sistema
- **Uso**: Notificar bienvenida al nuevo empleado
- **Contexto**: Nuevo usuario creado

#### ZONAEMPLEADO_PROFILE_UPDATED
- **Tipo**: Evento de usuario
- **Descripción**: Se dispara cuando se actualiza el perfil de un usuario
- **Uso**: Notificar cambios de perfil o información personal
- **Contexto**: Usuario cuyo perfil fue modificado

### Documentos

#### ZONAEMPLEADO_DOCUMENT_SHARED
- **Tipo**: Evento de documento
- **Descripción**: Se dispara cuando se comparte un documento con empleados
- **Uso**: Notificar a empleados sobre documentos compartidos
- **Contexto**: Documento compartido
- **Ejemplos**: Manuales, políticas, procedimientos

### Anuncios

#### ZONAEMPLEADO_ANNOUNCEMENT_CREATED
- **Tipo**: Evento de anuncio
- **Descripción**: Se dispara cuando se crea un nuevo anuncio
- **Uso**: Notificar a empleados sobre anuncios importantes
- **Contexto**: Nuevo anuncio creado

#### ZONAEMPLEADO_ANNOUNCEMENT_UPDATED
- **Tipo**: Evento de anuncio
- **Descripción**: Se dispara cuando se actualiza un anuncio existente
- **Uso**: Notificar cambios en anuncios
- **Contexto**: Anuncio actualizado

### Solicitudes de Vacaciones

#### ZONAEMPLEADO_HOLIDAY_REQUEST_SUBMITTED
- **Tipo**: Evento de vacaciones
- **Descripción**: Se dispara cuando un empleado envía una solicitud de vacaciones
- **Uso**: Notificar a supervisores/administrativos de nuevas solicitudes
- **Contexto**: Solicitud de vacaciones enviada

#### ZONAEMPLEADO_HOLIDAY_REQUEST_APPROVED
- **Tipo**: Evento de vacaciones
- **Descripción**: Se dispara cuando se aprueba una solicitud de vacaciones
- **Uso**: Notificar al empleado que su solicitud fue aprobada
- **Contexto**: Solicitud de vacaciones aprobada

#### ZONAEMPLEADO_HOLIDAY_REQUEST_REJECTED
- **Tipo**: Evento de vacaciones
- **Descripción**: Se dispara cuando se rechaza una solicitud de vacaciones
- **Uso**: Notificar al empleado que su solicitud fue rechazada
- **Contexto**: Solicitud de vacaciones rechazada

### Nóminas

#### ZONAEMPLEADO_PAYSLIP_PUBLISHED
- **Tipo**: Evento de nómina
- **Descripción**: Se dispara cuando se publica una nómina para los empleados
- **Uso**: Notificar disponibilidad de nuevas nóminas
- **Contexto**: Nómina publicada

### Mensajería

#### ZONAEMPLEADO_MESSAGE_RECEIVED
- **Tipo**: Evento de mensaje
- **Descripción**: Se dispara cuando un empleado recibe un mensaje
- **Uso**: Notificar sobre nuevos mensajes recibidos
- **Contexto**: Nuevo mensaje recibido

### Horarios

#### ZONAEMPLEADO_SCHEDULE_MODIFIED
- **Tipo**: Evento de horario
- **Descripción**: Se dispara cuando se modifica el horario de un empleado
- **Uso**: Notificar cambios en horarios de trabajo
- **Contexto**: Horario modificado

## Configuración de Notificaciones

### Requisitos

1. El módulo **Notification** de Dolibarr debe estar habilitado
2. El módulo **ZonaEmpleado** debe estar habilitado
3. Las configuraciones de email deben estar correctamente establecidas en Dolibarr

### Habilitación de Eventos

Los eventos de notificación se registran automáticamente cuando se activa el módulo ZonaEmpleado. Para configurar quién recibe las notificaciones:

1. Navegar a: **Administración > Notificaciones y eventos de email**
2. Buscar los eventos que comienzan con **ZONAEMPLEADO_**
3. Para cada evento, configurar:
   - Destinatarios (usuarios específicos, departamentos, etc.)
   - Plantillas de email
   - Condiciones de envío

### Plantillas de Email

Cada evento puede tener asociada una plantilla de email personalizada. La plantilla puede incluir variables específicas del evento, como:

- **{user}**: Nombre del usuario involucrado
- **{email}**: Email del usuario
- **{date}**: Fecha del evento
- **{object}**: Detalles del objeto afectado

## Integración en el Código

### Envío Manual de Notificaciones

En el código PHP, las notificaciones se envían automáticamente a través del sistema de triggers. Sin embargo, si necesitas enviar una notificación manual:

```php
// Incluir la clase Notify
require_once DOL_DOCUMENT_ROOT.'/core/class/notify.class.php';

// Crear instancia y enviar
$notify = new Notify($db);
$notify->send('ZONAEMPLEADO_USER_LOGIN', $userObject);
```

### Disparar Eventos desde Módulos Personalizados

Si desarrollas extensiones para ZonaEmpleado, puedes disparar eventos propios:

```php
// En tus triggers
case 'TU_EVENTO_PERSONALIZADO':
    $this->sendNotification('ZONAEMPLEADO_CUSTOM_EVENT', $object, $user, $langs, $conf);
    break;
```

## Registro de Eventos

Todos los eventos generados se registran en:
- **Logs del sistema**: `[DOCUMENT_ROOT]/documents/admin/logs/`
- **Base de datos**: tabla `llx_notify` (cuando el módulo de notificaciones está activo)

Para ver los registros del sistema, habilita el modo DEBUG en configuración.

## Troubleshooting

### Las notificaciones no se envían

1. Verificar que el módulo **Notification** está habilitado
2. Verificar que los eventos están configurados en la página de notificaciones
3. Verificar la configuración de email en Administración > Configuración del sistema
4. Revisar los logs: `[DOCUMENT_ROOT]/documents/admin/logs/`

### Email incorrecto o inválido

1. Verificar que los usuarios tienen email válido en su perfil
2. Verificar las configuraciones de remitente de email

### Plantillas no encontradas

Las plantillas se seleccionan en la interfaz de administración de notificaciones. Si falta una plantilla:

1. Crear una plantilla personalizada
2. Vincularla en Administración > Notificaciones y eventos de email

## Ampliación Futura

El sistema está diseñado para ser extensible. Eventos futuros que pueden agregarse:

- Eventos de proyectos
- Eventos de formularios
- Eventos de evaluación de desempeño
- Eventos de capacitación
- Eventos de presencia/asistencia

## Bibliografía

- [Documentación oficial de Dolibarr - Notificaciones](https://wiki.dolibarr.org/index.php/Notifications)
- [Sistema de Triggers de Dolibarr](https://wiki.dolibarr.org/index.php/Trigger)
