-- Copyright (C) 2026 DocSig Module
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

-- Datos iniciales para el módulo DocSig
-- Este archivo se ejecuta al activar el módulo

-- Tipo de actioncomm para notificaciones DocSig
-- Se usa para dejar rastro de todas las notificaciones enviadas (email, whatsapp, sms)
INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60010, 'AC_DOCSIG', 'module', 'DocSig - Notificación firma', 'docsig', 1, '#3498db', 'fa-file-signature', 100);

-- Tipos específicos de notificación DocSig
INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60011, 'AC_DOCSIG_REQUEST', 'module', 'DocSig - Solicitud de firma', 'docsig', 1, '#2980b9', 'fa-paper-plane', 101);

INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60012, 'AC_DOCSIG_OTP', 'module', 'DocSig - Envío OTP', 'docsig', 1, '#9b59b6', 'fa-key', 102);

INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60013, 'AC_DOCSIG_REMINDER', 'module', 'DocSig - Recordatorio', 'docsig', 1, '#f39c12', 'fa-bell', 103);

INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60014, 'AC_DOCSIG_COMPLETED', 'module', 'DocSig - Firma completada', 'docsig', 1, '#27ae60', 'fa-check-circle', 104);

INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60015, 'AC_DOCSIG_CANCELLED', 'module', 'DocSig - Firma cancelada', 'docsig', 1, '#e74c3c', 'fa-times-circle', 105);

INSERT IGNORE INTO llx_c_actioncomm (id, code, type, libelle, module, active, color, picto, position) 
VALUES (60016, 'AC_DOCSIG_WHATSAPP', 'module', 'DocSig - WhatsApp enviado', 'docsig', 1, '#25D366', 'fa-whatsapp', 106);

-- Las plantillas de email se crean desde la interfaz de administración
