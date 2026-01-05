-- Copyright (C) 2026 DocSig Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- DocSig - Tabla de rate limiting
-- Control de límites de solicitudes por IP y firmante

CREATE TABLE llx_docsig_ratelimit(
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificador
    identifier VARCHAR(255) NOT NULL,                   -- IP, email, o combinación
    identifier_type VARCHAR(20) NOT NULL,               -- 'ip', 'email', 'phone', 'signer'
    
    -- Contadores
    action_type VARCHAR(50) NOT NULL,                   -- 'otp_request', 'sign_attempt', 'page_view'
    request_count INTEGER DEFAULT 1 NOT NULL,           -- Contador de solicitudes
    
    -- Ventana de tiempo
    window_start DATETIME NOT NULL,                     -- Inicio de la ventana de tiempo
    window_end DATETIME NOT NULL,                       -- Fin de la ventana de tiempo
    
    -- Estado
    is_blocked TINYINT(1) DEFAULT 0 NOT NULL,           -- Si está bloqueado
    blocked_until DATETIME DEFAULT NULL,                -- Hasta cuándo está bloqueado
    
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
