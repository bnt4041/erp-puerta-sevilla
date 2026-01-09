-- Copyright (C) 2025 ZonaJob Dev
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- Table for order signatures
CREATE TABLE IF NOT EXISTS llx_zonajob_signature (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    ref             VARCHAR(128) NOT NULL,
    fk_commande     INTEGER NOT NULL,
    fk_soc          INTEGER DEFAULT NULL,
    fk_socpeople    INTEGER DEFAULT NULL,
    signer_name     VARCHAR(255) DEFAULT NULL,
    signer_email    VARCHAR(255) DEFAULT NULL,
    signer_phone    VARCHAR(64) DEFAULT NULL,
    signature_data  LONGTEXT DEFAULT NULL,
    signature_file  VARCHAR(255) DEFAULT NULL,
    ip_address      VARCHAR(64) DEFAULT NULL,
    user_agent      TEXT DEFAULT NULL,
    latitude        VARCHAR(32) DEFAULT NULL,
    longitude       VARCHAR(32) DEFAULT NULL,
    status          SMALLINT DEFAULT 0,
    date_creation   DATETIME NOT NULL,
    date_signature  DATETIME DEFAULT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INTEGER DEFAULT NULL,
    fk_user_modif   INTEGER DEFAULT NULL,
    import_key      VARCHAR(14) DEFAULT NULL,
    entity          INTEGER DEFAULT 1
) ENGINE=InnoDB;

-- Index for signature table
ALTER TABLE llx_zonajob_signature ADD INDEX idx_zonajob_signature_fk_commande (fk_commande);
ALTER TABLE llx_zonajob_signature ADD INDEX idx_zonajob_signature_ref (ref);
ALTER TABLE llx_zonajob_signature ADD UNIQUE INDEX uk_zonajob_signature_ref (ref, entity);

-- Table for order photos
CREATE TABLE IF NOT EXISTS llx_zonajob_photo (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_commande     INTEGER NOT NULL,
    filename        VARCHAR(255) NOT NULL,
    filepath        VARCHAR(512) NOT NULL,
    filetype        VARCHAR(64) DEFAULT NULL,
    filesize        INTEGER DEFAULT NULL,
    description     TEXT DEFAULT NULL,
    photo_type      VARCHAR(64) DEFAULT 'general',
    latitude        VARCHAR(32) DEFAULT NULL,
    longitude       VARCHAR(32) DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INTEGER DEFAULT NULL,
    entity          INTEGER DEFAULT 1
) ENGINE=InnoDB;

-- Index for photo table
ALTER TABLE llx_zonajob_photo ADD INDEX idx_zonajob_photo_fk_commande (fk_commande);

-- Table for sending history (WhatsApp/Email)
CREATE TABLE IF NOT EXISTS llx_zonajob_send_history (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_commande     INTEGER NOT NULL,
    fk_signature    INTEGER DEFAULT NULL,
    fk_soc          INTEGER DEFAULT NULL,
    fk_socpeople    INTEGER DEFAULT NULL,
    send_type       VARCHAR(32) NOT NULL,
    recipient       VARCHAR(255) NOT NULL,
    subject         VARCHAR(512) DEFAULT NULL,
    message         TEXT DEFAULT NULL,
    status          SMALLINT DEFAULT 0,
    error_message   TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    date_send       DATETIME DEFAULT NULL,
    fk_user_creat   INTEGER DEFAULT NULL,
    entity          INTEGER DEFAULT 1
) ENGINE=InnoDB;

-- Index for send history table
ALTER TABLE llx_zonajob_send_history ADD INDEX idx_zonajob_send_history_fk_commande (fk_commande);
ALTER TABLE llx_zonajob_send_history ADD INDEX idx_zonajob_send_history_send_type (send_type);

-- Table for public download tokens of order documents
CREATE TABLE IF NOT EXISTS llx_zonajob_doc_tokens (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    token           VARCHAR(64) NOT NULL,
    fk_commande     INTEGER NOT NULL,
    filename        VARCHAR(255) NOT NULL,
    filepath        VARCHAR(512) NOT NULL,
    date_creation   DATETIME NOT NULL,
    date_expiration DATETIME DEFAULT NULL,
    downloads       INTEGER DEFAULT 0,
    active          TINYINT DEFAULT 1,
    entity          INTEGER DEFAULT 1
) ENGINE=InnoDB;

-- Indexes for doc tokens
ALTER TABLE llx_zonajob_doc_tokens ADD UNIQUE INDEX uk_zonajob_doc_tokens_token (token, entity);
ALTER TABLE llx_zonajob_doc_tokens ADD INDEX idx_zonajob_doc_tokens_fk_commande (fk_commande);
ALTER TABLE llx_zonajob_doc_tokens ADD INDEX idx_zonajob_doc_tokens_date_exp (date_expiration);
