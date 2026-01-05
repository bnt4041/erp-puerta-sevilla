-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de firmantes
-- Cada firmante asociado a un envelope

CREATE TABLE llx_docsig_signer(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Relaciones
    fk_envelope INTEGER NOT NULL,                       -- ID del envelope
    fk_socpeople INTEGER DEFAULT NULL,                  -- ID del contacto (puede ser NULL si es tercero directo)
    fk_soc INTEGER DEFAULT NULL,                        -- ID del tercero (para firma directa del tercero)
    
    -- Datos del firmante
    email VARCHAR(255) NOT NULL,                        -- Email del firmante
    phone VARCHAR(50) DEFAULT NULL,                     -- Teléfono del firmante
    dni VARCHAR(20) DEFAULT NULL,                       -- DNI/NIE del firmante
    fullname VARCHAR(255) DEFAULT NULL,                 -- Nombre completo del firmante
    
    -- Token de acceso
    token_hash VARCHAR(128) NOT NULL,                   -- Hash SHA-256 del token (no guardar token plano)
    token_expires DATETIME NOT NULL,                    -- Fecha de expiración del token
    
    -- Estado de firma
    status INTEGER DEFAULT 0 NOT NULL,                  -- 0=pendiente, 1=visto, 2=otp_enviado, 3=otp_verificado, 4=firmado, 5=rechazado, 6=expirado, 7=bloqueado
    order_index INTEGER DEFAULT 0,                      -- Orden de firma (para modo secuencial)
    
    -- Datos de firma
    signed_at DATETIME DEFAULT NULL,                    -- Fecha/hora de firma
    signature_image MEDIUMBLOB DEFAULT NULL,            -- Imagen de firma manuscrita (base64 decodificado)
    signature_image_path VARCHAR(512) DEFAULT NULL,     -- Ruta alternativa si se guarda en filesystem
    signer_name VARCHAR(255) DEFAULT NULL,              -- Nombre introducido al firmar
    
    -- Autenticación
    auth_method VARCHAR(20) DEFAULT 'email',            -- Método de autenticación: 'email', 'phone'
    auth_identifier VARCHAR(255) DEFAULT NULL,          -- Email o teléfono usado para OTP
    
    -- Rechazo
    reject_reason TEXT DEFAULT NULL,                    -- Motivo de rechazo si aplica
    rejected_at DATETIME DEFAULT NULL,                  -- Fecha de rechazo
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_access DATETIME DEFAULT NULL,                  -- Última vez que accedió al link
    last_access_ip VARCHAR(45) DEFAULT NULL,            -- IP del último acceso
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
