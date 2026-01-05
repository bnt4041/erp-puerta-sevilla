-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de eventos de auditoría (append-only)
-- Registro inmutable de todos los eventos del proceso de firma

CREATE TABLE llx_docsig_event(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Relaciones
    fk_envelope INTEGER NOT NULL,                       -- ID del envelope
    fk_signer INTEGER DEFAULT NULL,                     -- ID del firmante (si aplica)
    
    -- Evento
    event_type VARCHAR(50) NOT NULL,                    -- Tipo: created, sent, opened, otp_requested, otp_sent, otp_verified, otp_failed, signed, rejected, canceled, expired, completed, reminder_sent, downloaded
    event_subtype VARCHAR(50) DEFAULT NULL,             -- Subtipo adicional si es necesario
    
    -- Datos del evento
    payload_json TEXT DEFAULT NULL,                     -- Datos adicionales en JSON (hash, nombre, etc.)
    
    -- Información de cliente
    ip_address VARCHAR(45) NOT NULL,                    -- Dirección IP (IPv4 o IPv6)
    user_agent TEXT DEFAULT NULL,                       -- User-Agent del navegador
    
    -- Timestamp (inmutable)
    created_at DATETIME NOT NULL,
    
    -- Hash de integridad (para verificar que no se ha modificado)
    integrity_hash VARCHAR(64) DEFAULT NULL,            -- SHA-256 del registro anterior + datos actuales
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: Esta tabla es append-only. No se deben hacer UPDATE ni DELETE.
-- Se pueden añadir triggers para prevenir modificaciones si es necesario.
