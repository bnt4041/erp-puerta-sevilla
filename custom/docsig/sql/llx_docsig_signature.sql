-- Copyright (C) 2026 Document Signature Module

CREATE TABLE llx_docsig_signature (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Relación con envelope
    fk_envelope INTEGER NOT NULL,
    
    -- Firmante (contacto)
    fk_socpeople INTEGER NOT NULL,                  -- ID del contacto
    signer_name VARCHAR(255) NOT NULL,
    signer_email VARCHAR(255) NOT NULL,
    signer_dni VARCHAR(50),                         -- DNI/documento identidad
    signer_order INTEGER DEFAULT 0,                 -- Orden de firma (si ordered)
    
    -- Token único
    token VARCHAR(128) NOT NULL,                    -- Hash del token real
    token_plain VARCHAR(128),                       -- Token en claro (solo para generar link)
    token_expiry DATETIME,
    
    -- Estado
    status INTEGER DEFAULT 0 NOT NULL,              -- 0:pending, 1:opened, 2:authenticated, 3:signed, 4:failed, 5:cancelled, 6:expired
    
    -- OTP
    otp_code VARCHAR(10),
    otp_expiry DATETIME,
    otp_attempts INTEGER DEFAULT 0,
    otp_sent_count INTEGER DEFAULT 0,
    last_otp_sent DATETIME,
    
    -- Datos de la firma
    signature_image LONGTEXT,                       -- Base64 de la imagen manuscrita
    signature_date DATETIME,
    signature_ip VARCHAR(45),
    signature_user_agent VARCHAR(512),
    
    -- Posición firma visible (si aplica)
    signature_position_x FLOAT,
    signature_position_y FLOAT,
    signature_page INTEGER,
    
    -- Auditoría
    date_creation DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER,
    
    -- Estadísticas
    link_opened_count INTEGER DEFAULT 0,
    first_opened_date DATETIME,
    last_activity DATETIME,
    
    INDEX idx_envelope (fk_envelope),
    INDEX idx_socpeople (fk_socpeople),
    INDEX idx_token (token),
    INDEX idx_status (status),
    INDEX idx_entity (entity),
    UNIQUE KEY uk_envelope_socpeople (fk_envelope, fk_socpeople)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
