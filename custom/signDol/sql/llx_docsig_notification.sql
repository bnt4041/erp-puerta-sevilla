-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de notificaciones enviadas
-- Registro de todas las notificaciones (emails, SMS) enviadas

CREATE TABLE llx_docsig_notification(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Relaciones
    fk_envelope INTEGER DEFAULT NULL,                   -- ID del envelope (puede ser NULL para notificaciones generales)
    fk_signer INTEGER DEFAULT NULL,                     -- ID del firmante destino
    fk_socpeople INTEGER DEFAULT NULL,                  -- ID del contacto destino
    fk_actioncomm INTEGER DEFAULT NULL,                 -- ID del evento en actioncomm (para vincular)
    
    -- Referencia
    envelope_ref VARCHAR(128) DEFAULT NULL,             -- Referencia del envelope (para consultas rápidas)
    
    -- Contenido
    notification_type VARCHAR(50) NOT NULL,             -- Tipo: signature_request, otp, reminder, completed, canceled
    channel VARCHAR(20) DEFAULT 'email' NOT NULL,       -- Canal: email, sms, whatsapp (preparado para futuro)
    destination VARCHAR(255) NOT NULL,                  -- Email/teléfono destino
    
    -- Email/mensaje
    subject VARCHAR(512) DEFAULT NULL,                  -- Asunto del email
    body_text TEXT DEFAULT NULL,                        -- Cuerpo en texto plano
    body_html MEDIUMTEXT DEFAULT NULL,                  -- Cuerpo en HTML
    
    -- Estado de envío
    status INTEGER DEFAULT 0 NOT NULL,                  -- 0=pendiente, 1=enviado, 2=fallido, 3=rebotado
    error_message TEXT DEFAULT NULL,                    -- Mensaje de error si falló
    
    -- Timestamps
    sent_at DATETIME DEFAULT NULL,                      -- Fecha/hora de envío
    delivered_at DATETIME DEFAULT NULL,                 -- Fecha/hora de entrega (si hay webhook)
    opened_at DATETIME DEFAULT NULL,                    -- Fecha/hora de apertura (si hay tracking)
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    fk_user_create INTEGER DEFAULT NULL,                -- Usuario que disparó el envío (puede ser NULL si es automático)
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
