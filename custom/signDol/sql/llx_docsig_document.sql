-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de documentos asociados a un envelope
-- Permite que un envelope contenga múltiples documentos para firmar
-- NOTA: Un documento solo puede pertenecer a un envelope a la vez

CREATE TABLE llx_docsig_document(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Relación con envelope (obligatoria)
    fk_envelope INTEGER NOT NULL,                       -- ID del envelope padre
    
    -- Identificación del documento
    ref VARCHAR(128) DEFAULT NULL,                      -- Referencia única del documento dentro del envelope (DOC-001, etc.)
    label VARCHAR(255) DEFAULT NULL,                    -- Nombre descriptivo del documento
    
    -- Archivo original
    original_filename VARCHAR(255) NOT NULL,            -- Nombre original del archivo
    file_path VARCHAR(512) NOT NULL,                    -- Ruta del PDF original
    file_hash VARCHAR(128) DEFAULT NULL,                -- Hash SHA-256 del PDF original
    file_size INTEGER DEFAULT NULL,                     -- Tamaño en bytes
    
    -- Archivo firmado
    signed_file_path VARCHAR(512) DEFAULT NULL,         -- Ruta del PDF firmado
    signed_hash VARCHAR(128) DEFAULT NULL,              -- Hash SHA-256 del PDF firmado
    
    -- Orden y estado
    sign_order INTEGER DEFAULT 1,                       -- Orden de firma (1, 2, 3...)
    status INTEGER DEFAULT 0 NOT NULL,                  -- 0=pendiente, 1=firmado parcial, 2=completado
    
    -- Configuración opcional por documento
    require_all_signers INTEGER DEFAULT 1,              -- 1=todos los firmantes deben firmar este doc
    specific_signers_json TEXT DEFAULT NULL,            -- JSON con IDs de firmantes específicos para este doc
    
    -- Metadatos del documento
    page_count INTEGER DEFAULT NULL,                    -- Número de páginas
    mime_type VARCHAR(128) DEFAULT 'application/pdf',   -- Tipo MIME
    
    -- Token para descarga pública
    download_token VARCHAR(128) DEFAULT NULL,           -- Token único para descarga pública
    token_expires DATETIME DEFAULT NULL,                -- Expiración del token de descarga
    
    -- Auditoría
    fk_user_creat INTEGER DEFAULT NULL,                 -- Usuario que añadió el documento
    date_creation DATETIME NOT NULL,                    -- Fecha de creación
    date_modification DATETIME DEFAULT NULL,            -- Fecha de modificación
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    signed_at DATETIME DEFAULT NULL,                    -- Fecha en que se completó la firma
    
    import_key VARCHAR(14) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
