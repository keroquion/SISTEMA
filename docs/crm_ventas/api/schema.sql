-- ==========================================================
-- PETULAP SALES CRM - SCHEMA INDEPENDIENTE
-- Base de datos para CRM de Ventas WhatsApp y Seguimiento
-- ==========================================================

CREATE TABLE IF NOT EXISTS `crm_vendedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `telefono` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `rol` ENUM('ASESOR', 'SUPERVISOR', 'ADMIN') DEFAULT 'ASESOR',
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_promociones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `categoria` ENUM('LAPTOP_MEDIA', 'LAPTOP_EJECUTIVA', 'WORKSTATION', 'ACCESORIO') NOT NULL,
  `marca` VARCHAR(50) NOT NULL,
  `modelo` VARCHAR(100) NOT NULL,
  `procesador` VARCHAR(50) DEFAULT NULL,
  `ram` VARCHAR(20) DEFAULT NULL,
  `almacenamiento` VARCHAR(50) DEFAULT NULL,
  `pantalla` VARCHAR(50) DEFAULT NULL,
  `precio_regular` DECIMAL(10,2) DEFAULT NULL,
  `precio_promo` DECIMAL(10,2) NOT NULL,
  `stock_disponible` INT DEFAULT 1,
  `nota_stock` VARCHAR(100) DEFAULT NULL, -- Ej: 'Solo 2 unidades', '1 stock'
  `descripcion_comercial` TEXT DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_leads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `telefono` VARCHAR(30) NOT NULL, -- Número WhatsApp (ej: 51987654321)
  `nombre` VARCHAR(120) NOT NULL,
  `etapa` ENUM('NUEVO', 'ASESORIA', 'COTIZADO', 'VISITA_SEPARADO', 'GANADO', 'PERDIDO') DEFAULT 'NUEVO',
  `temperatura` ENUM('VERDE', 'AMBAR', 'ROJO') DEFAULT 'VERDE',
  `vendedor_id` INT DEFAULT NULL,
  `modelo_interes_id` INT DEFAULT NULL,
  `modelo_interes_texto` VARCHAR(150) DEFAULT NULL,
  `presupuesto_aprox` DECIMAL(10,2) DEFAULT NULL,
  `origen_lead` ENUM('WHATSAPP', 'FACEBOOK_ADS', 'TIKTOK', 'TIENDA_YANAHUARA', 'TIENDA_CAYMA', 'RECOMENDACION') DEFAULT 'WHATSAPP',
  `sede_preferida` ENUM('YANAHUARA', 'CAYMA', 'ENVIO_PROVINCIA') DEFAULT 'YANAHUARA',
  `ultimo_mensaje_texto` TEXT DEFAULT NULL,
  `ultimo_mensaje_hora` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ultimo_mensaje_emisor` ENUM('CLIENTE', 'PETULAP') DEFAULT 'CLIENTE',
  `motivo_perdida` VARCHAR(255) DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_telefono` (`telefono`),
  INDEX `idx_etapa` (`etapa`),
  INDEX `idx_temperatura` (`temperatura`),
  INDEX `idx_vendedor` (`vendedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_seguimientos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lead_id` INT NOT NULL,
  `vendedor_id` INT DEFAULT NULL,
  `tipo_accion` ENUM('MENSAJE_ENVIADO', 'RESPUESTA_CLIENTE', 'COTIZACION', 'LLAMADA', 'VISITA_TIENDA', 'CAMBIO_ETAPA', 'AUTO_SLA') NOT NULL,
  `detalle` TEXT NOT NULL,
  `fecha` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`lead_id`) REFERENCES `crm_leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserción inicial de Vendedores de muestra
INSERT INTO `crm_vendedores` (`nombre`, `telefono`, `rol`) VALUES
('Asesor General', '51983396137', 'SUPERVISOR'),
('Ventas Yanahuara', '51942770228', 'ASESOR'),
('Ventas Cayma', '51983396137', 'ASESOR')
ON DUPLICATE KEY UPDATE `nombre`=`nombre`;
