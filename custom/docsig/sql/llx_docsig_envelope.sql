-- Copyright (C) 2026 Document Signature Module
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_docsig_envelope (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(128) NOT NULL,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Documento original
    element_type VARCHAR(50) NOT NULL,              -- invoice, contract, order, propal, etc
    element_id INTEGER NOT NULL,                    -- ID del objeto
    document_path VARCHAR(512) NOT NULL,            -- Ruta relativa del PDF
    document_hash VARCHAR(64) NOT NULL,             -- SHA-256 del PDF original
    document_name VARCHAR(255) NOT NULL,            -- Nombre del archivo
    
    -- PDF firmado
    signed_document_path VARCHAR(512),
    signed_document_hash VARCHAR(64),
    
    -- Configuración del envelope
    signature_mode VARCHAR(20) DEFAULT 'parallel',  -- parallel, ordered
    expiration_date DATETIME,                       -- Fecha expiración
    custom_message TEXT,                            -- Mensaje personalizado email
    
    -- Estado
    status INTEGER DEFAULT 0 NOT NULL,              -- 0:draft, 1:sent, 2:in_progress, 3:completed, 4:cancelled, 5:expired
    cancel_reason TEXT,
    
    -- Certificado de cumplimiento
    certificate_path VARCHAR(512),
    certificate_hash VARCHAR(64),
    certificate_date DATETIME,
    
    -- Firma del sistema
    system_signature TEXT,                          -- Firma del hash del certificado
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER,
    fk_user_modif INTEGER,
    
    -- Estadísticas
    nb_signers INTEGER DEFAULT 0,
    nb_signed INTEGER DEFAULT 0,
    last_activity DATETIME,
    
    INDEX idx_element (element_type, element_id),
    INDEX idx_status (status),
    INDEX idx_entity (entity),
    UNIQUE KEY uk_ref (ref, entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
