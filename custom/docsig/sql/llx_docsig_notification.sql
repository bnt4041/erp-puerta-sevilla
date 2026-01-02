-- Copyright (C) 2026 Document Signature Module

CREATE TABLE llx_docsig_notification (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    
    -- Relaciones
    fk_envelope INTEGER NOT NULL,
    fk_signature INTEGER,                           -- NULL si notif. general del envelope
    fk_socpeople INTEGER NOT NULL,                  -- Contacto destinatario
    
    -- Email
    notification_type VARCHAR(50) NOT NULL,         -- request, otp, reminder, completed, cancelled
    email_to VARCHAR(255) NOT NULL,
    email_subject VARCHAR(512) NOT NULL,
    email_body LONGTEXT NOT NULL,                   -- HTML o texto
    email_format VARCHAR(10) DEFAULT 'html',        -- html, text
    
    -- Estado
    sent_date DATETIME NOT NULL,
    status INTEGER DEFAULT 0,                       -- 0:pending, 1:sent, 2:failed, 3:bounced
    error_message TEXT,
    
    -- Tracking
    opened_date DATETIME,
    clicked_date DATETIME,
    
    INDEX idx_envelope (fk_envelope),
    INDEX idx_signature (fk_signature),
    INDEX idx_socpeople (fk_socpeople),
    INDEX idx_type (notification_type),
    INDEX idx_sent_date (sent_date),
    INDEX idx_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
