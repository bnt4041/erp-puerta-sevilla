-- Copyright (C) 2024 PuertaSevilla
--
-- Diccionario: Formas de Pago
--

CREATE TABLE IF NOT EXISTS llx_c_psv_forma_pago (
    rowid integer AUTO_INCREMENT PRIMARY KEY,
    code varchar(16) NOT NULL,
    label varchar(128) NOT NULL,
    active tinyint DEFAULT 1 NOT NULL
) ENGINE=innodb;

DELETE FROM llx_c_psv_forma_pago;

INSERT INTO llx_c_psv_forma_pago (code, label, active) VALUES
('efectivo', 'Efectivo', 1),
('transferencia', 'Transferencia', 1),
('domiciliacion', 'Domiciliación', 1),
('tarjeta', 'Tarjeta', 1),
('cheque', 'Cheque', 1);
