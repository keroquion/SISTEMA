-- ==============================================================================
-- SCRIPT DE MIGRACIÓN: ÍNDICES COMPUESTOS DE ALTA ESCALABILIDAD (FASE 1)
-- Proyecto: Petulap SST
-- Fecha: Septiembre 2026
-- Objetivo: Acelerar consultas de Kanban, inventario, auditoría y notificaciones,
--           reduciendo escaneos secuenciales (Full Table Scan) y el tiempo de
--           retención de conexiones concurrentes en MySQL.
-- ==============================================================================

USE `petumjvq_pruebas`;

-- Procedimiento auxiliar para agregar índices solo si no existen previamente
DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_add_index_if_not_exists`$$
CREATE PROCEDURE `sp_add_index_if_not_exists`(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;
    SELECT COUNT(1) INTO v_count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table
      AND index_name = p_index;

    IF v_count = 0 THEN
        SET @s = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Índice ', p_index, ' agregado exitosamente a ', p_table) AS Resultado;
    ELSE
        SELECT CONCAT('El índice ', p_index, ' ya existe en ', p_table) AS Resultado;
    END IF;
END$$
DELIMITER ;

-- ------------------------------------------------------------------------------
-- 1. Optimización del Tablero Kanban (mis_ordenes.html y soporte.html)
-- Acelera el filtrado por técnico asignado y estado, ordenando por ID descendente.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('soporte_tecnico', 'idx_tecnico_estado_id', 'tecnico_id, estado, id');

-- ------------------------------------------------------------------------------
-- 2. Optimización de Historial de Clientes (consulta.html y soporte.html)
-- Acelera la búsqueda de tickets por cliente y fecha de ingreso.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('soporte_tecnico', 'idx_cliente_fecha', 'cliente_id, fecha_ingreso');

-- ------------------------------------------------------------------------------
-- 3. Optimización de Trazabilidad y Auditoría (historial_cambios)
-- Evita escaneos de millones de registros al auditar una entidad por fecha.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('historial_cambios', 'idx_tabla_reg_fecha', 'tabla, registro_id, fecha_registro');

-- ------------------------------------------------------------------------------
-- 4. Optimización de Catálogo e Inventario Rápido (equipos)
-- Acelera búsquedas por número de serie exacto o código de inventario.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('equipos', 'idx_serie_codigo', 'numero_serie, codigo_inventario');

-- ------------------------------------------------------------------------------
-- 5. Optimización de Gestión de Garantías de Proveedor (garantias.php)
-- Acelera el filtrado por estado y fecha de reclamo para alertas operativas.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('garantias_proveedor', 'idx_estado_fecha', 'estado, fecha_reclamo');

-- ------------------------------------------------------------------------------
-- 6. Optimización de Alertas y Notificaciones (notificaciones.php)
-- Acelera la consulta de avisos no leídos por usuario y cron de garantías.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('notificaciones', 'idx_usuario_leido_fecha', 'usuario_id, leido, fecha');

-- ------------------------------------------------------------------------------
-- 7. Optimización de Triaje y Lotes (lotes.php y lotes_equipos)
-- Acelera el conteo y listado de equipos por estado de triaje dentro de un lote.
-- ------------------------------------------------------------------------------
CALL sp_add_index_if_not_exists('lotes_equipos', 'idx_lote_triaje', 'lote_id, estado_triaje');

-- Limpieza del procedimiento auxiliar
DROP PROCEDURE IF EXISTS `sp_add_index_if_not_exists`;

-- Confirmación de índices creados
SELECT table_name, index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columnas
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND index_name LIKE 'idx_%'
GROUP BY table_name, index_name;
