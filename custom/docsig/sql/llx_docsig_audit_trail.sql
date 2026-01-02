-- Copyright (C) 2026 Document Signature Module
-- Tabla append-only para auditoría inmutable

CREATE TABLE llx_docsig_audit_trail (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Relaciones
    fk_envelope INTEGER NOT NULL,
    fk_signature INTEGER,                           -- NULL si es evento del envelope
    
    -- Evento
    event_type VARCHAR(50) NOT NULL,                -- envelope_created, envelope_sent, envelope_cancelled, 
                                                     -- link_opened, otp_requested, otp_sent, otp_validated, 
                                                     -- otp_failed, signature_completed, document_signed, 
                                                     -- certificate_generated, email_sent
    event_date DATETIME NOT NULL,
    
    -- Contexto
    event_data TEXT,                                -- JSON con datos del evento
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    session_id VARCHAR(128),
    
    -- Usuario si aplica
    fk_user INTEGER,
    
    -- Datos inmutables
    event_hash VARCHAR(64) NOT NULL,                -- Hash SHA-256 del evento para integridad
    previous_hash VARCHAR(64),                      -- Hash del evento anterior (blockchain-like)
    
    INDEX idx_envelope (fk_envelope),
    INDEX idx_signature (fk_signature),
    INDEX idx_event_type (event_type),
    INDEX idx_event_date (event_date),
    INDEX idx_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
