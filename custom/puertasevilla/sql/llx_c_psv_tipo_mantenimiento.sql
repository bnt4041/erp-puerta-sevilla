-- Copyright (C) 2024 PuertaSevilla
--
-- Diccionario: Tipos de Mantenimiento
--

CREATE TABLE IF NOT EXISTS llx_c_psv_tipo_mantenimiento (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    code varchar(16) NOT NULL,
    label varchar(128) NOT NULL,
    active tinyint DEFAULT 1 NOT NULL
) ENGINE=innodb;

DELETE FROM llx_c_psv_tipo_mantenimiento;

INSERT INTO llx_c_psv_tipo_mantenimiento (code, label, active) VALUES
('urgencia', 'Urgencia', 1),
('suministros', 'Suministros', 1),
('reparacion', 'Reparación', 1),
('limpieza', 'Limpieza', 1),
('revision', 'Revisión', 1),
('otros', 'Otros', 1);
