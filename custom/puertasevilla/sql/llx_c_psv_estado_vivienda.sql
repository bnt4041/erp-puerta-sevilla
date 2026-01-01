-- Copyright (C) 2024 PuertaSevilla
--
-- Diccionario: Estados de Vivienda
--

CREATE TABLE IF NOT EXISTS llx_c_psv_estado_vivienda (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    code varchar(16) NOT NULL,
    label varchar(128) NOT NULL,
    active tinyint DEFAULT 1 NOT NULL
) ENGINE=innodb;

DELETE FROM llx_c_psv_estado_vivienda;

INSERT INTO llx_c_psv_estado_vivienda (code, label, active) VALUES
('ocupada', 'Ocupada', 1),
('vacia', 'Vacía', 1),
('reforma', 'En Reforma', 1),
('baja', 'Baja', 1);
