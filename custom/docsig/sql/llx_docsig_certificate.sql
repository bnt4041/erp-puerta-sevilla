-- Copyright (C) 2026 Document Signature Module

CREATE TABLE llx_docsig_certificate (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Relación
    fk_envelope INTEGER NOT NULL UNIQUE,
    
    -- Certificado de cumplimiento
    certificate_type VARCHAR(20) DEFAULT 'pdf',     -- pdf, json, both
    certificate_path VARCHAR(512),                  -- Ruta del PDF
    certificate_json LONGTEXT,                      -- JSON completo del certificado
    
    -- Datos principales
    certificate_number VARCHAR(128) NOT NULL,       -- Número único del certificado
    original_document_hash VARCHAR(64) NOT NULL,
    signed_document_hash VARCHAR(64) NOT NULL,
    
    -- TSA (Time Stamp Authority)
    tsa_url VARCHAR(512),
    tsa_serial VARCHAR(255),
    tsa_date DATETIME,
    tsa_response LONGTEXT,                          -- Respuesta completa TSA (base64)
    
    -- Firma del sistema sobre el certificado
    system_certificate_hash VARCHAR(64) NOT NULL,   -- Hash del certificado
    system_signature TEXT NOT NULL,                 -- Firma RSA/ECDSA del hash
    
    -- Metadata
    completion_date DATETIME NOT NULL,
    all_signers_completed BOOLEAN DEFAULT FALSE,
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_envelope (fk_envelope),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
