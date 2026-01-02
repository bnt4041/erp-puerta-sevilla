-- Copyright (C) 2026 Document Signature Module
-- Rate limiting para OTP y accesos

CREATE TABLE llx_docsig_rate_limit (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificador
    limiter_type VARCHAR(50) NOT NULL,              -- otp_email, otp_ip, signature_ip
    limiter_key VARCHAR(255) NOT NULL,              -- Email, IP, etc
    
    -- Relaciones opcionales
    fk_signature INTEGER,
    
    -- Contadores
    attempt_count INTEGER DEFAULT 0,
    first_attempt DATETIME NOT NULL,
    last_attempt DATETIME NOT NULL,
    
    -- Bloqueo
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_until DATETIME,
    
    -- Limpieza automática
    expires_at DATETIME NOT NULL,
    
    INDEX idx_limiter (limiter_type, limiter_key),
    INDEX idx_signature (fk_signature),
    INDEX idx_blocked (is_blocked),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
