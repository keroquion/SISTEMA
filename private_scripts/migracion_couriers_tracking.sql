-- ============================================================================
-- MIGRACIÓN DDL: TABLA GUIAS_ENVIO (RASTREO AUTOMATIZADO DE COURIERS)
-- Petulap SST v1.6.0
-- Compatible con Cruz del Sur Cargo (API v4) y Shalom Express (API Microservicios)
-- ============================================================================

CREATE TABLE IF NOT EXISTS guias_envio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NULL,
    numero_referencia VARCHAR(50) NOT NULL,
    courier ENUM('CRUZ_DEL_SUR', 'SHALOM') NOT NULL,
    numero_guia_orden VARCHAR(50) NOT NULL,
    codigo_seguridad VARCHAR(20) NULL,
    ose_id VARCHAR(50) NULL,
    estado_courier VARCHAR(50) NOT NULL DEFAULT 'REGISTRADO',
    ultimo_mensaje TEXT NULL,
    origen VARCHAR(150) NULL,
    destino VARCHAR(150) NULL,
    agencia_destino VARCHAR(150) NULL,
    remitente VARCHAR(150) NULL,
    remitente_doc VARCHAR(50) NULL,
    destinatario VARCHAR(150) NULL,
    destinatario_doc VARCHAR(50) NULL,
    fecha_emision VARCHAR(50) NULL,
    fecha_entrega_courier VARCHAR(50) NULL,
    importe DECIMAL(10,2) DEFAULT 0.00,
    peso_kg DECIMAL(10,2) DEFAULT 0.00,
    raw_data_json MEDIUMTEXT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ge_ticket (ticket_id),
    INDEX idx_ge_referencia (numero_referencia),
    INDEX idx_ge_courier (courier),
    INDEX idx_ge_guia (numero_guia_orden),
    INDEX idx_ge_estado (estado_courier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
