-- Copyright (C) 2026 DocSig Module
--
-- Claves e índices para llx_docsig_ratelimit

ALTER TABLE llx_docsig_ratelimit ADD INDEX idx_docsig_ratelimit_identifier (identifier, identifier_type);
ALTER TABLE llx_docsig_ratelimit ADD INDEX idx_docsig_ratelimit_action (action_type);
ALTER TABLE llx_docsig_ratelimit ADD INDEX idx_docsig_ratelimit_window (window_start, window_end);
ALTER TABLE llx_docsig_ratelimit ADD INDEX idx_docsig_ratelimit_blocked (is_blocked);
ALTER TABLE llx_docsig_ratelimit ADD UNIQUE INDEX uk_docsig_ratelimit (identifier, identifier_type, action_type, window_start);
