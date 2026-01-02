-- Copyright (C) 2026 Document Signature Module
-- Almacenamiento seguro de claves del sistema

CREATE TABLE llx_docsig_key (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Tipo de clave
    key_type VARCHAR(50) NOT NULL,                  -- signing, encryption
    key_algorithm VARCHAR(50) NOT NULL,             -- RSA-2048, RSA-4096, ECDSA-P256, ECDSA-P384
    
    -- Clave pública (PEM)
    public_key LONGTEXT NOT NULL,
    
    -- Clave privada cifrada (AES-256-GCM)
    private_key_encrypted LONGTEXT NOT NULL,
    private_key_iv VARCHAR(64) NOT NULL,            -- IV para descifrado
    private_key_tag VARCHAR(64) NOT NULL,           -- Tag para verificación
    
    -- Certificado X.509 auto-firmado (PEM)
    certificate LONGTEXT NOT NULL,
    certificate_serial VARCHAR(255) NOT NULL,
    certificate_subject VARCHAR(512) NOT NULL,
    certificate_issuer VARCHAR(512) NOT NULL,
    certificate_valid_from DATETIME NOT NULL,
    certificate_valid_to DATETIME NOT NULL,
    
    -- Estado
    is_active BOOLEAN DEFAULT TRUE,
    revoked_date DATETIME,
    revocation_reason TEXT,
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER,
    
    -- Uso
    usage_count INTEGER DEFAULT 0,
    last_used DATETIME,
    
    INDEX idx_key_type (key_type),
    INDEX idx_active (is_active),
    INDEX idx_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
