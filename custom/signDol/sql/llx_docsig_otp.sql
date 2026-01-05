-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de códigos OTP
-- Gestión de códigos de un solo uso para autenticación

CREATE TABLE llx_docsig_otp(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Relación con firmante
    fk_signer INTEGER NOT NULL,                         -- ID del firmante
    
    -- OTP
    code_hash VARCHAR(128) NOT NULL,                    -- Hash SHA-256 del código OTP (no guardar plano)
    code_salt VARCHAR(64) NOT NULL,                     -- Salt usado para el hash
    
    -- Validez
    expires_at DATETIME NOT NULL,                       -- Fecha/hora de expiración
    
    -- Intentos
    attempts INTEGER DEFAULT 0 NOT NULL,                -- Intentos de verificación realizados
    max_attempts INTEGER DEFAULT 5 NOT NULL,            -- Máximo de intentos permitidos
    
    -- Estado
    status INTEGER DEFAULT 0 NOT NULL,                  -- 0=pendiente, 1=verificado, 2=expirado, 3=bloqueado
    verified_at DATETIME DEFAULT NULL,                  -- Fecha de verificación exitosa
    
    -- Método de envío
    channel VARCHAR(20) DEFAULT 'email' NOT NULL,       -- 'email' o 'sms'
    destination VARCHAR(255) NOT NULL,                  -- Email o teléfono destino
    
    -- Auditoría
    sent_at DATETIME NOT NULL,                          -- Fecha/hora de envío
    ip_address VARCHAR(45) DEFAULT NULL,                -- IP desde donde se solicitó
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
