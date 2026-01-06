-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla principal de sobres de firma (envelopes)
-- Representa una solicitud de firma sobre un documento PDF

CREATE TABLE llx_docsig_envelope(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(128) NOT NULL,                          -- Referencia única del envelope
    entity INTEGER DEFAULT 1 NOT NULL,                  -- Entidad multi-empresa
    
    -- Documento origen
    fk_object INTEGER DEFAULT NULL,                     -- ID del objeto Dolibarr (factura, pedido, etc.)
    element VARCHAR(64) DEFAULT NULL,                   -- Tipo de elemento (facture, commande, propal, contrat...)
    file_path VARCHAR(512) NOT NULL,                    -- Ruta del PDF original
    file_hash VARCHAR(128) DEFAULT NULL,                -- Hash SHA-256 del PDF original (usado por código PHP)
    original_hash VARCHAR(64) DEFAULT NULL,             -- Hash SHA-256 del PDF original (legacy)
    
    -- Documento firmado
    signed_file_path VARCHAR(512) DEFAULT NULL,         -- Ruta del PDF firmado
    signed_hash VARCHAR(64) DEFAULT NULL,               -- Hash SHA-256 del PDF firmado
    certificate_path VARCHAR(512) DEFAULT NULL,         -- Ruta del certificado de cumplimiento (legacy)
    compliance_cert_path VARCHAR(512) DEFAULT NULL,     -- Ruta del certificado de cumplimiento
    
    -- Estado
    status INTEGER DEFAULT 0 NOT NULL,                  -- 0=borrador, 1=enviado, 2=parcialmente firmado, 3=completado, 4=cancelado, 5=expirado
    expire_date DATETIME DEFAULT NULL,                  -- Fecha de expiración (usado por código PHP)
    
    -- Configuración del envelope
    signature_mode VARCHAR(20) DEFAULT 'parallel',      -- 'parallel' o 'sequential'
    token_expiration DATETIME DEFAULT NULL,             -- Fecha de expiración del token (legacy)
    custom_message TEXT DEFAULT NULL,                   -- Mensaje personalizado para el email
    settings_json TEXT DEFAULT NULL,                    -- Configuración adicional en JSON
    
    -- TSA Info (se llena al firmar)
    tsa_url VARCHAR(512) DEFAULT NULL,                  -- URL TSA usada
    tsa_serial VARCHAR(128) DEFAULT NULL,               -- Serial del sello TSA
    tsa_timestamp DATETIME DEFAULT NULL,                -- Fecha/hora del sello TSA
    
    -- Cancelación
    cancel_reason TEXT DEFAULT NULL,                    -- Motivo de cancelación
    canceled_at DATETIME DEFAULT NULL,                  -- Fecha de cancelación
    canceled_by INTEGER DEFAULT NULL,                   -- Usuario que canceló
    
    -- Auditoría
    fk_user_create INTEGER DEFAULT NULL,                -- Usuario que creó (legacy)
    fk_user_creat INTEGER DEFAULT NULL,                 -- Usuario que creó (usado por código PHP)
    fk_user_modif INTEGER DEFAULT NULL,                 -- Último usuario que modificó
    date_creation DATETIME NOT NULL,                    -- Fecha de creación
    date_modification DATETIME DEFAULT NULL,            -- Fecha de modificación
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,                 -- Fecha de completado (todas las firmas)
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
