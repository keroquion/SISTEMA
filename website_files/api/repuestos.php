<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header('HTTP/1.1 401 Unauthorized'); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false, 'msg'=>'No autorizado']); 
    exit; 
}
$userId = (int)$_SESSION['user_id'];
$userTipo = $_SESSION['user_tipo'] ?? 'tecnico';
$userName = $_SESSION['user_nombre'] ?? 'Sistema';
session_write_close();

header('Content-Type: application/json; charset=utf-8');
require_once "config.php";
require_once "courier_ia.php";
$db = getDB();
$action = $_GET["action"] ?? "list";

// Helper: Garantizar existencia y actualización de la tabla repuestos y permisos
$ensureTableRepuestos = function() use ($db) {
    $sql = "
        CREATE TABLE IF NOT EXISTS repuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            pn VARCHAR(100) DEFAULT NULL,
            categoria VARCHAR(100) DEFAULT 'GENERAL',
            stock INT DEFAULT 0,
            stock_minimo INT DEFAULT 1,
            precio DECIMAL(10,2) DEFAULT 0.00,
            ubicacion VARCHAR(100) DEFAULT 'TALLER',
            notas TEXT DEFAULT NULL,
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (categoria),
            INDEX (pn),
            INDEX (stock)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->query($sql);

    // Verificar y agregar columnas si la tabla ya existía con esquema previo
    $cols = [];
    $colRes = $db->query("SHOW COLUMNS FROM repuestos");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) $cols[] = $c['Field'];
    }
    if (!in_array('categoria', $cols)) { @$db->query("ALTER TABLE repuestos ADD COLUMN categoria VARCHAR(100) DEFAULT 'GENERAL'"); }
    if (!in_array('stock_minimo', $cols)) { @$db->query("ALTER TABLE repuestos ADD COLUMN stock_minimo INT DEFAULT 1"); }
    if (!in_array('ubicacion', $cols)) { @$db->query("ALTER TABLE repuestos ADD COLUMN ubicacion VARCHAR(100) DEFAULT 'TALLER'"); }

    // Auto-seed si la tabla está vacía para que la interfaz nunca esté vacía
    $cntRes = $db->query("SELECT COUNT(*) as c FROM repuestos");
    $cnt = 0;
    if ($cntRes) {
        $row = $cntRes->fetch_assoc();
        $cnt = (int)($row['c'] ?? 0);
    }
    if ($cnt === 0) {
        $seedItems = [
            ['Pantalla 15.6 LED Slim 30 Pines (FHD 1920x1080)', 'B156HAN02.1', 'Pantallas', 3, 1, 220.00, 'Estante A-1', 'Panel IPS mate compatible con Lenovo, Dell, Asus, Acer.'],
            ['Pantalla 14.0 LED Slim 30 Pines (HD/FHD)', 'N140BGA-EA4', 'Pantallas', 2, 1, 195.00, 'Estante A-2', 'Conector 30 pines inferior derecho sin brackets.'],
            ['Teclado Lenovo ThinkPad E14 / T480 Español', '01HX044', 'Teclados', 3, 1, 85.00, 'Estante B-1', 'Con trackpoint y retroiluminación compatible Gen 1-2.'],
            ['Teclado HP 15-DY / 15-EF Español con Marco', 'L63579-161', 'Teclados', 2, 1, 75.00, 'Estante B-2', 'Distribución LA negro mate con teclado numérico.'],
            ['Cargador Universal Laptop 65W USB-C (20V 3.25A)', 'PD-65W-TYPEC', 'Cargadores', 5, 2, 65.00, 'Gaveta C-1', 'Protocolo PD 3.0 para Lenovo, Dell, HP y MacBook.'],
            ['Cargador HP Punta Azul 19.5V 3.33A 65W Original', 'PPP009L-E', 'Cargadores', 4, 2, 60.00, 'Gaveta C-2', 'Conector 4.5x3.0mm pin central para HP Pavilion/ProBook.'],
            ['Batería Interna Dell Inspiron 5570 / 3580 (42Wh)', 'WDX0R', 'Baterías', 2, 1, 130.00, 'Gaveta D-1', 'Batería de polímero de litio 11.4V 3 celdas.'],
            ['Batería Lenovo ThinkPad 24Wh Externa 6 celdas', '01AV423', 'Baterías', 2, 1, 125.00, 'Gaveta D-2', 'Compatible T470, T480, T570, T580.'],
            ['Pasta Térmica Alto Rendimiento Arctic MX-4 (4g)', 'ACTCP00002B', 'Insumos', 8, 2, 35.00, 'Mesa Taller', 'Conductividad térmica 8.5 W/mK sin curado eléctrico.'],
            ['Cable Flex de Video eDP 30 Pines Universal 25cm', 'FLEX-EDP-30P', 'Flex / Cables', 3, 1, 45.00, 'Gaveta E-1', 'Repuesto para pantallas de 30 pines 60Hz.']
        ];
        $stmtSeed = $db->prepare("INSERT INTO repuestos (nombre, pn, categoria, stock, stock_minimo, precio, ubicacion, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmtSeed) {
            foreach ($seedItems as $si) {
                $stmtSeed->bind_param("sssiidss", $si[0], $si[1], $si[2], $si[3], $si[4], $si[5], $si[6], $si[7]);
                $stmtSeed->execute();
            }
            $stmtSeed->close();
        }
    }

    // Auto-asegurar repuestos.html y pedidos_repuestos.html en roles_config para que check_auth.js no bloquee navegación
    $resRoles = $db->query("SELECT id, rol, modulos_permitidos FROM roles_config");
    if ($resRoles) {
        while ($rRow = $resRoles->fetch_assoc()) {
            $mods = json_decode($rRow['modulos_permitidos'], true) ?? [];
            $changed = false;
            if (!in_array('repuestos.html', $mods)) {
                $mods[] = 'repuestos.html';
                $changed = true;
            }
            if (!in_array('pedidos_repuestos.html', $mods)) {
                $mods[] = 'pedidos_repuestos.html';
                $changed = true;
            }
            if ($changed) {
                $modsJson = json_encode(array_values(array_unique($mods)));
                $updStmt = $db->prepare("UPDATE roles_config SET modulos_permitidos = ? WHERE id = ?");
                if ($updStmt) {
                    $updStmt->bind_param("si", $modsJson, $rRow['id']);
                    $updStmt->execute();
                    $updStmt->close();
                }
            }
        }
    }
};

// Helper: Garantizar existencia de la tabla pedidos_repuestos
$ensureTablePedidosRepuestos = function() use ($db) {
    $sql = "
        CREATE TABLE IF NOT EXISTS pedidos_repuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origen_tipo ENUM('CLIENTE', 'GARANTIA', 'LOTE') NOT NULL DEFAULT 'CLIENTE',
            ticket_id INT NULL,
            lote_equipo_id INT NULL,
            numero_referencia VARCHAR(50) NOT NULL,
            cliente_nombre VARCHAR(150) NULL,
            cliente_telefono VARCHAR(50) NULL,
            equipo_descripcion VARCHAR(255) NULL,
            repuesto_nombre VARCHAR(255) NOT NULL,
            repuesto_pn VARCHAR(100) NULL,
            proveedor_nombre VARCHAR(100) NULL,
            courier VARCHAR(100) NULL,
            tracking_number VARCHAR(100) NULL,
            codigo_seguridad VARCHAR(20) NULL,
            fecha_compra DATE NULL,
            fecha_estimada_llegada DATE NULL,
            costo_compra DECIMAL(10,2) DEFAULT 0.00,
            precio_cliente DECIMAL(10,2) DEFAULT 0.00,
            es_garantia TINYINT(1) DEFAULT 0,
            estado_envio ENUM('SOLICITADO', 'EN_TRANSITO', 'RECIBIDO_EN_TALLER', 'INSTALADO') DEFAULT 'SOLICITADO',
            ultimo_estado_courier VARCHAR(150) NULL,
            agencia_destino VARCHAR(150) NULL,
            voucher_foto_url VARCHAR(255) NULL,
            ultimo_rastreo_json MEDIUMTEXT NULL,
            notas_envio TEXT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (ticket_id),
            INDEX (lote_equipo_id),
            INDEX (numero_referencia),
            INDEX (estado_envio),
            INDEX (origen_tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $db->query($sql);

    // Auto-migración no destructiva de columnas si la tabla ya existía
    $colRes = $db->query("SHOW COLUMNS FROM pedidos_repuestos");
    if ($colRes) {
        $existingCols = [];
        while ($r = $colRes->fetch_assoc()) $existingCols[] = $r['Field'];
        if (!in_array('codigo_seguridad', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN codigo_seguridad VARCHAR(20) NULL AFTER tracking_number"); }
        if (!in_array('costo_envio', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN costo_envio DECIMAL(10,2) DEFAULT 0.00 AFTER costo_compra"); }
        if (!in_array('banco_proveedor', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN banco_proveedor VARCHAR(50) NULL AFTER proveedor_nombre"); }
        if (!in_array('cuenta_proveedor', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN cuenta_proveedor VARCHAR(100) NULL AFTER banco_proveedor"); }
        if (!in_array('nro_operacion_pago', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN nro_operacion_pago VARCHAR(100) NULL AFTER cuenta_proveedor"); }
        if (!in_array('fecha_pago_proveedor', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN fecha_pago_proveedor DATETIME NULL AFTER nro_operacion_pago"); }
        if (!in_array('pago_proveedor_estado', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN pago_proveedor_estado ENUM('PENDIENTE', 'PAGADO') DEFAULT 'PENDIENTE' AFTER fecha_pago_proveedor"); }
        if (!in_array('comprobante_pago_url', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN comprobante_pago_url VARCHAR(255) NULL AFTER pago_proveedor_estado"); }
        if (!in_array('ultimo_estado_courier', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN ultimo_estado_courier VARCHAR(150) NULL AFTER estado_envio"); }
        if (!in_array('agencia_destino', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN agencia_destino VARCHAR(150) NULL AFTER ultimo_estado_courier"); }
        if (!in_array('voucher_foto_url', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN voucher_foto_url VARCHAR(255) NULL AFTER agencia_destino"); }
        if (!in_array('ultimo_rastreo_json', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN ultimo_rastreo_json MEDIUMTEXT NULL AFTER voucher_foto_url"); }
        if (!in_array('ultima_alerta_sla', $existingCols)) { @$db->query("ALTER TABLE pedidos_repuestos ADD COLUMN ultima_alerta_sla DATETIME DEFAULT NULL"); }
    }
};

switch ($action) {
    // -------------------------------------------------------------
    // GESTIÓN Y TRAZABILIDAD DE COMPRAS Y ENVÍOS (v1.5.0)
    // -------------------------------------------------------------
    case "tracking_pedidos":
        $ensureTablePedidosRepuestos();

        // 1. Auto-sincronizar órdenes de soporte_tecnico pendientes de repuesto
        $sqlSyncSt = "
            SELECT st.id as ticket_id, st.numero_atencion, st.equipo_descripcion,
                   COALESCE(NULLIF(st.repuesto_nombre, ''), 'Repuesto no especificado') as repuesto_nombre,
                   st.repuesto_pn, st.repuesto_precio, st.en_garantia,
                   st.repuesto_fecha_llegada_aprox,
                   CONCAT(c.nombre, ' ', COALESCE(c.apellido, '')) as cliente_nombre,
                   c.telefono as cliente_telefono
            FROM soporte_tecnico st
            LEFT JOIN personas c ON st.cliente_id = c.id
            WHERE (st.estado = 'ESPERANDO_REPUESTO' OR (st.repuesto_nombre IS NOT NULL AND st.repuesto_nombre != '' AND st.estado NOT IN ('ENTREGADO', 'CANCELADO')))
            AND st.id NOT IN (SELECT ticket_id FROM pedidos_repuestos WHERE ticket_id IS NOT NULL)
        ";
        $resSyncSt = $db->query($sqlSyncSt);
        if ($resSyncSt) {
            $stmtInsSt = $db->prepare("
                INSERT INTO pedidos_repuestos 
                (origen_tipo, ticket_id, numero_referencia, cliente_nombre, cliente_telefono, equipo_descripcion, repuesto_nombre, repuesto_pn, fecha_estimada_llegada, precio_cliente, es_garantia, estado_envio)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SOLICITADO')
            ");
            while ($stRow = $resSyncSt->fetch_assoc()) {
                $origen = (!empty($stRow['en_garantia']) || (float)$stRow['repuesto_precio'] == 0) ? 'GARANTIA' : 'CLIENTE';
                $esGar = ($origen === 'GARANTIA') ? 1 : 0;
                $precioCli = $esGar ? 0.00 : (float)($stRow['repuesto_precio'] ?? 0);
                $fechaEst = !empty($stRow['repuesto_fecha_llegada_aprox']) ? $stRow['repuesto_fecha_llegada_aprox'] : null;
                $tkId = (int)$stRow['ticket_id'];
                $numRef = $stRow['numero_atencion'] ?? 'ST-PENDIENTE';
                $cliNom = trim($stRow['cliente_nombre'] ?? 'Cliente General');
                $cliTel = $stRow['cliente_telefono'] ?? '';
                $eqDesc = $stRow['equipo_descripcion'] ?? 'Laptop de Cliente';
                $repNom = $stRow['repuesto_nombre'];
                $repPn = $stRow['repuesto_pn'] ?? '';

                $stmtInsSt->bind_param("sissssssdss", $origen, $tkId, $numRef, $cliNom, $cliTel, $eqDesc, $repNom, $repPn, $fechaEst, $precioCli, $esGar);
                $stmtInsSt->execute();
            }
            $stmtInsSt->close();
        }

        // 2. Auto-sincronizar laptops de lotes masivos con triaje 'NECESITA_REPUESTO'
        $sqlSyncLe = "
            SELECT le.id as lote_equipo_id, le.lote_id, le.equipo_codigo, le.marca, le.modelo,
                   le.pieza, le.pn, le.falla, l.titulo as lote_titulo
            FROM lote_equipos le
            LEFT JOIN lotes l ON le.lote_id = l.lote_id
            WHERE le.triaje = 'NECESITA_REPUESTO'
            AND le.id NOT IN (SELECT lote_equipo_id FROM pedidos_repuestos WHERE lote_equipo_id IS NOT NULL)
        ";
        $resSyncLe = $db->query($sqlSyncLe);
        if ($resSyncLe) {
            $stmtInsLe = $db->prepare("
                INSERT INTO pedidos_repuestos 
                (origen_tipo, lote_equipo_id, numero_referencia, cliente_nombre, equipo_descripcion, repuesto_nombre, repuesto_pn, notas_envio, estado_envio)
                VALUES ('LOTE', ?, ?, 'Interno Petulap / Taller', ?, ?, ?, ?, 'SOLICITADO')
            ");
            while ($leRow = $resSyncLe->fetch_assoc()) {
                $leId = (int)$leRow['lote_equipo_id'];
                $numRef = ($leRow['lote_id'] ?? 'LOT') . ' / ' . ($leRow['equipo_codigo'] ?? 'S/C');
                $piezaNom = !empty($leRow['pieza']) ? trim($leRow['pieza']) : (!empty($leRow['falla']) ? 'Repuesto: ' . trim($leRow['falla']) : 'Pieza de Lote');
                $eqDesc = trim(($leRow['marca'] ?? '') . ' ' . ($leRow['modelo'] ?? ''));
                $pn = $leRow['pn'] ?? '';
                $notas = "Lote: " . ($leRow['lote_titulo'] ?? 'General') . " | Falla: " . ($leRow['falla'] ?? 'Requiere repuesto');

                $stmtInsLe->bind_param("issssss", $leId, $numRef, $eqDesc, $piezaNom, $pn, $notas);
                $stmtInsLe->execute();
            }
            $stmtInsLe->close();
        }

        // Consultar todos los pedidos ordenados por prioridad y fecha
        $sqlList = "
            SELECT p.*,
                   CASE 
                       WHEN p.estado_envio = 'SOLICITADO' THEN 1
                       WHEN p.estado_envio = 'EN_TRANSITO' THEN 2
                       WHEN p.estado_envio = 'RECIBIDO_EN_TALLER' THEN 3
                       WHEN p.estado_envio = 'INSTALADO' THEN 4
                       ELSE 5
                   END as orden_prioridad,
                   DATEDIFF(p.fecha_estimada_llegada, CURDATE()) as dias_para_llegada
            FROM pedidos_repuestos p
            ORDER BY orden_prioridad ASC, p.fecha_registro DESC
        ";
        $resList = $db->query($sqlList);
        $pedidos = [];
        if ($resList) {
            while ($row = $resList->fetch_assoc()) {
                $pedidos[] = $row;
            }
        }
        echo json_encode(["ok" => true, "data" => $pedidos]);
        break;

    case "guardar_tracking":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $prov = trim($data["proveedor_nombre"] ?? "");
        $courier = trim($data["courier"] ?? "");
        $tracking = trim($data["tracking_number"] ?? "");
        $fCompra = !empty($data["fecha_compra"]) ? $data["fecha_compra"] : null;
        $fLlegada = !empty($data["fecha_estimada_llegada"]) ? $data["fecha_estimada_llegada"] : null;
        $costo = (float)($data["costo_compra"] ?? 0);
        $costoEnvio = isset($data["costo_envio"]) ? (float)$data["costo_envio"] : 0.00;
        $precio = (float)($data["precio_cliente"] ?? 0);
        $esGar = (!empty($data["es_garantia"])) ? 1 : 0;
        $estadoEnvio = trim($data["estado_envio"] ?? "SOLICITADO");
        $notas = trim($data["notas_envio"] ?? "");

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos SET
                proveedor_nombre = ?,
                courier = ?,
                tracking_number = ?,
                fecha_compra = ?,
                fecha_estimada_llegada = ?,
                costo_compra = ?,
                costo_envio = ?,
                precio_cliente = ?,
                es_garantia = ?,
                estado_envio = ?,
                notas_envio = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssssdddissi", $prov, $courier, $tracking, $fCompra, $fLlegada, $costo, $costoEnvio, $precio, $esGar, $estadoEnvio, $notas, $id);
        
        if ($stmt->execute()) {
            // Sincronizar ticket de soporte si aplica
            $stmtTk = $db->prepare("SELECT ticket_id FROM pedidos_repuestos WHERE id = ?");
            $stmtTk->bind_param("i", $id);
            $stmtTk->execute();
            $resTk = $stmtTk->get_result()->fetch_assoc();
            $stmtTk->close();

            if (!empty($resTk['ticket_id'])) {
                $tkId = (int)$resTk['ticket_id'];
                $stmtUpdSt = $db->prepare("
                    UPDATE soporte_tecnico SET
                        repuesto_fecha_llegada_aprox = ?,
                        repuesto_precio = ?,
                        en_garantia = ?
                    WHERE id = ?
                ");
                $stmtUpdSt->bind_param("sdii", $fLlegada, $precio, $esGar, $tkId);
                $stmtUpdSt->execute();
                $stmtUpdSt->close();
            }

            echo json_encode(["ok" => true, "msg" => "Tracking actualizado exitosamente"]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al actualizar: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // ESCANEAR VOUCHER CON IA Y RASTREO AUTOMÁTICO (v1.6.1)
    // -------------------------------------------------------------
    case "escanear_voucher_pedido":
        $ensureTablePedidosRepuestos();
        
        $id = 0;
        $base64 = '';
        $mimeType = 'image/jpeg';

        if (!empty($_FILES['imagen']['tmp_name'])) {
            $id = (int)($_POST['id'] ?? $_POST['pedido_id'] ?? 0);
            $fileTmp = $_FILES['imagen']['tmp_name'];
            $mimeType = mime_content_type($fileTmp) ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($fileTmp));
        } else {
            $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
            $id = (int)($data['id'] ?? $data['pedido_id'] ?? 0);
            $rawB64 = $data['imagen_base64'] ?? '';
            if (preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $rawB64, $matches)) {
                $mimeType = $matches[1];
                $base64 = $matches[2];
            } else {
                $base64 = $rawB64;
            }
        }

        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        if (empty($base64)) {
            echo json_encode(["ok" => false, "msg" => "Fotografía del voucher requerida"]);
            break;
        }

        // 1. Análisis de imagen con IA Gemini Vision
        $iaResult = CourierIAService::analizarVoucherConIA($base64, $mimeType);
        if (!$iaResult['success']) {
            echo json_encode(["ok" => false, "msg" => "Error de análisis IA: " . ($iaResult['message'] ?? 'No se pudo procesar la foto')]);
            break;
        }

        $ia = $iaResult['data'];
        $courier = $ia['courier'] ?? 'DESCONOCIDO';
        $codigo = $ia['codigo_seguimiento'] ?? '';
        $claveSeg = $ia['codigo_seguridad'] ?? null;
        $monto = floatval($ia['monto'] ?? 0);

        if (empty($codigo) || $courier === 'DESCONOCIDO') {
            echo json_encode([
                "ok" => false, 
                "msg" => "La IA no pudo detectar un código de seguimiento válido ni la empresa de transporte en este comprobante.",
                "ia_data" => $ia
            ]);
            break;
        }

        // 2. Rastreo en vivo inmediato con el Courier oficial
        $trackResult = ($courier === 'CRUZ_DEL_SUR')
            ? CourierIAService::trackCruzDelSur($codigo)
            : CourierIAService::trackShalom($codigo, $claveSeg ?? '');

        // 3. Estimación de llegada y estado
        $ultimoEstado = $trackResult['mensaje_estado'] ?? $trackResult['estado_actual'] ?? 'EN TRÁNSITO';
        $agenciaDest = $trackResult['agencia_destino'] ?? $trackResult['destino'] ?? $ia['destino'] ?? '';
        $fLlegadaEst = null;
        $fCompra = date('Y-m-d');

        if (stripos($ultimoEstado, 'entregad') !== false || stripos($ultimoEstado, 'agencia destino') !== false) {
            $fLlegadaEst = date('Y-m-d');
        } else {
            $fLlegadaEst = date('Y-m-d', strtotime('+2 weekdays'));
        }

        $trackJson = json_encode($trackResult, JSON_UNESCAPED_UNICODE);

        $fleteFila = floatval($trackResult['importe'] ?? $monto ?? 0);

        // 4. Actualizar registro en pedidos_repuestos con flete courier descontable
        $stmtUpd = $db->prepare("
            UPDATE pedidos_repuestos SET
                courier = ?,
                tracking_number = ?,
                codigo_seguridad = ?,
                fecha_compra = COALESCE(fecha_compra, ?),
                fecha_estimada_llegada = COALESCE(?, fecha_estimada_llegada),
                costo_envio = CASE WHEN ? > 0 THEN ? ELSE costo_envio END,
                estado_envio = 'EN_TRANSITO',
                ultimo_estado_courier = ?,
                agencia_destino = ?,
                ultimo_rastreo_json = ?
            WHERE id = ?
        ");
        $stmtUpd->bind_param("sssssddsssi", $courier, $codigo, $claveSeg, $fCompra, $fLlegadaEst, $fleteFila, $fleteFila, $ultimoEstado, $agenciaDest, $trackJson, $id);
        $stmtUpd->execute();
        $stmtUpd->close();

        // 5. Sincronizar soporte_tecnico si tiene ticket_id
        $stmtTk = $db->prepare("SELECT ticket_id, numero_referencia FROM pedidos_repuestos WHERE id = ?");
        $stmtTk->bind_param("i", $id);
        $stmtTk->execute();
        $resTk = $stmtTk->get_result()->fetch_assoc();
        $stmtTk->close();

        if (!empty($resTk['ticket_id'])) {
            $tkId = (int)$resTk['ticket_id'];
            $stmtSt = $db->prepare("UPDATE soporte_tecnico SET repuesto_fecha_llegada_aprox = ? WHERE id = ?");
            $stmtSt->bind_param("si", $fLlegadaEst, $tkId);
            $stmtSt->execute();
            $stmtSt->close();
        }

        echo json_encode([
            "ok" => true,
            "msg" => "Voucher procesado exitosamente por IA y tracking registrado",
            "courier" => $courier,
            "tracking_number" => $codigo,
            "ia_data" => $ia,
            "tracking" => $trackResult
        ]);
        break;

    // -------------------------------------------------------------
    // ACTUALIZAR RASTREO EN VIVO 1-CLIC (v1.6.1)
    // -------------------------------------------------------------
    case "actualizar_tracking_en_vivo":
        $ensureTablePedidosRepuestos();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $stmtSel = $db->prepare("SELECT courier, tracking_number, codigo_seguridad FROM pedidos_repuestos WHERE id = ?");
        $stmtSel->bind_param("i", $id);
        $stmtSel->execute();
        $ped = $stmtSel->get_result()->fetch_assoc();
        $stmtSel->close();

        if (!$ped || empty($ped['tracking_number'])) {
            echo json_encode(["ok" => false, "msg" => "El pedido no tiene código de tracking registrado"]);
            break;
        }

        $cType = $ped['courier'];
        $cCode = $ped['tracking_number'];
        $cSec = $ped['codigo_seguridad'] ?? '';

        $trackResult = ($cType === 'CRUZ_DEL_SUR')
            ? CourierIAService::trackCruzDelSur($cCode)
            : CourierIAService::trackShalom($cCode, $cSec);

        if ($trackResult['success']) {
            $ultimoEstado = $trackResult['mensaje_estado'] ?? $trackResult['estado_actual'] ?? 'EN TRÁNSITO';
            $agenciaDest = $trackResult['agencia_destino'] ?? $trackResult['destino'] ?? '';
            $trackJson = json_encode($trackResult, JSON_UNESCAPED_UNICODE);

            $stmtUpd = $db->prepare("
                UPDATE pedidos_repuestos SET
                    ultimo_estado_courier = ?,
                    agencia_destino = ?,
                    ultimo_rastreo_json = ?
                WHERE id = ?
            ");
            $stmtUpd->bind_param("sssi", $ultimoEstado, $agenciaDest, $trackJson, $id);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        echo json_encode([
            "ok" => true,
            "tracking" => $trackResult
        ]);
        break;

    case "marcar_recibido":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos 
            SET estado_envio = 'RECIBIDO_EN_TALLER' 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Consultar datos para sincronización con soporte_tecnico
            $stmtInfo = $db->prepare("SELECT ticket_id, lote_equipo_id, numero_referencia, repuesto_nombre FROM pedidos_repuestos WHERE id = ?");
            $stmtInfo->bind_param("i", $id);
            $stmtInfo->execute();
            $info = $stmtInfo->get_result()->fetch_assoc();
            $stmtInfo->close();

            if (!empty($info['ticket_id'])) {
                $tkId = (int)$info['ticket_id'];
                
                // Actualizar estado del ticket en soporte_tecnico
                $stmtUpdSt = $db->prepare("
                    UPDATE soporte_tecnico 
                    SET estado = 'EN_REPARACION', fecha_llegada_repuesto = NOW() 
                    WHERE id = ? AND estado = 'ESPERANDO_REPUESTO'
                ");
                $stmtUpdSt->bind_param("i", $tkId);
                $stmtUpdSt->execute();
                $stmtUpdSt->close();

                // Registrar en auditoría historial_cambios
                $stmtHist = $db->prepare("
                    INSERT INTO historial_cambios 
                    (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre)
                    VALUES ('soporte_tecnico', ?, ?, 'estado', 'ESPERANDO_REPUESTO', 'EN_REPARACION', ?)
                ");
                $numRef = $info['numero_referencia'] ?? "Ticket #$tkId";
                $stmtHist->bind_param("iss", $tkId, $numRef, $userName);
                $stmtHist->execute();
                $stmtHist->close();

                // Alerta en notificaciones internas
                $stmtNotif = $db->prepare("
                    INSERT INTO notificaciones (usuario_id, titulo, mensaje, link)
                    SELECT tecnico_id, ?, ?, 'soporte.html'
                    FROM soporte_tecnico WHERE id = ? AND tecnico_id IS NOT NULL
                ");
                if ($stmtNotif) {
                    $tituloNotif = "Pieza en Taller: " . ($info['repuesto_nombre'] ?? 'Repuesto');
                    $msgNotif = "El repuesto de la orden {$numRef} fue recibido en taller y pasó a EN REPARACIÓN.";
                    $stmtNotif->bind_param("ssi", $tituloNotif, $msgNotif, $tkId);
                    $stmtNotif->execute();
                    $stmtNotif->close();
                }
            } elseif (!empty($info['lote_equipo_id'])) {
                $leId = (int)$info['lote_equipo_id'];
                $stmtLe = $db->prepare("UPDATE lote_equipos SET triaje = 'FALLA_MENOR', estado_item = 'EN_PROCESO' WHERE id = ?");
                $stmtLe->bind_param("i", $leId);
                $stmtLe->execute();
                $stmtLe->close();
            }

            echo json_encode(["ok" => true, "msg" => "Repuesto recibido en taller. Orden actualizada."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al marcar recepción: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // REGISTRAR PAGO A PROVEEDOR (GERENCIA / TESORERÍA - v1.6.3)
    // -------------------------------------------------------------
    case "registrar_pago_proveedor":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $prov = trim($data["proveedor_nombre"] ?? "");
        $costo = floatval($data["costo_compra"] ?? 0);
        $banco = trim($data["banco_proveedor"] ?? "");
        $cuenta = trim($data["cuenta_proveedor"] ?? "");
        $op = trim($data["nro_operacion_pago"] ?? "");
        $voucherB64 = $data["comprobante_b64"] ?? "";

        $comprobanteUrl = null;
        if (!empty($voucherB64) && strpos($voucherB64, 'data:image') !== false) {
            $dir = __DIR__ . "/../uploads/vouchers_proveedores/";
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $imgParts = explode(";base64,", $voucherB64);
            if (count($imgParts) === 2) {
                $imgData = base64_decode($imgParts[1]);
                $fileName = "pago_prov_{$id}_" . time() . ".jpg";
                if (@file_put_contents($dir . $fileName, $imgData)) {
                    $comprobanteUrl = "uploads/vouchers_proveedores/" . $fileName;
                }
            }
        }

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos SET
                proveedor_nombre = CASE WHEN ? != '' THEN ? ELSE proveedor_nombre END,
                costo_compra = CASE WHEN ? > 0 THEN ? ELSE costo_compra END,
                banco_proveedor = ?,
                cuenta_proveedor = ?,
                nro_operacion_pago = ?,
                fecha_pago_proveedor = NOW(),
                pago_proveedor_estado = 'PAGADO',
                comprobante_pago_url = COALESCE(?, comprobante_pago_url)
            WHERE id = ?
        ");
        $stmt->bind_param("ssddsssssi", $prov, $prov, $costo, $costo, $banco, $cuenta, $op, $comprobanteUrl, $id);

        if ($stmt->execute()) {
            // Historial de auditoría
            $stmtHist = $db->prepare("
                INSERT INTO historial_cambios 
                (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre)
                VALUES ('pedidos_repuestos', ?, (SELECT numero_referencia FROM pedidos_repuestos WHERE id = ?), 'pago_proveedor', 'PENDIENTE', 'PAGADO', ?)
            ");
            if ($stmtHist) {
                $stmtHist->bind_param("iis", $id, $id, $userName);
                $stmtHist->execute();
                $stmtHist->close();
            }

            echo json_encode([
                "ok" => true,
                "msg" => "Pago a proveedor registrado exitosamente",
                "comprobante_url" => $comprobanteUrl
            ]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al registrar pago: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // MARCAR COMPRA LOCAL EN AREQUIPA (v1.6.3)
    // -------------------------------------------------------------
    case "marcar_compra_local":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $prov = trim($data["proveedor_nombre"] ?? "Compuplaza Arequipa / Local");
        $costo = isset($data["costo_compra"]) ? floatval($data["costo_compra"]) : 0;
        $curDate = date('Y-m-d');
        $msgEstado = "Compra local en Arequipa - Entrega estimada hoy";

        $stmt = $db->prepare("
            UPDATE pedidos_repuestos SET
                proveedor_nombre = CASE WHEN ? != '' THEN ? ELSE proveedor_nombre END,
                costo_compra = CASE WHEN ? > 0 THEN ? ELSE costo_compra END,
                courier = 'COMPRA_LOCAL',
                tracking_number = 'LOCAL-AQP',
                costo_envio = 0.00,
                fecha_compra = COALESCE(fecha_compra, ?),
                fecha_estimada_llegada = ?,
                estado_envio = 'EN_TRANSITO',
                ultimo_estado_courier = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssddsssi", $prov, $prov, $costo, $costo, $curDate, $curDate, $msgEstado, $id);

        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "msg" => "Compra local en Arequipa registrada exitosamente"]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // MARCAR INSTALADO Y PROBADO (TÉCNICO / 1-CLIC - v1.6.3)
    // -------------------------------------------------------------
    case "marcar_instalado":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $stmt = $db->prepare("UPDATE pedidos_repuestos SET estado_envio = 'INSTALADO' WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Sincronizar ticket de soporte técnico
            $stmtInfo = $db->prepare("SELECT ticket_id, numero_referencia, repuesto_nombre FROM pedidos_repuestos WHERE id = ?");
            $stmtInfo->bind_param("i", $id);
            $stmtInfo->execute();
            $info = $stmtInfo->get_result()->fetch_assoc();
            $stmtInfo->close();

            if (!empty($info['ticket_id'])) {
                $tkId = (int)$info['ticket_id'];
                $stmtUpdSt = $db->prepare("UPDATE soporte_tecnico SET estado = 'REPARADO', fecha_reparado = NOW() WHERE id = ?");
                $stmtUpdSt->bind_param("i", $tkId);
                $stmtUpdSt->execute();
                $stmtUpdSt->close();

                // Historial de cambios
                $numRef = $info['numero_referencia'] ?? "Ticket #$tkId";
                $stmtHist = $db->prepare("
                    INSERT INTO historial_cambios 
                    (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre)
                    VALUES ('soporte_tecnico', ?, ?, 'estado', 'EN_REPARACION', 'REPARADO', ?)
                ");
                if ($stmtHist) {
                    $stmtHist->bind_param("iss", $tkId, $numRef, $userName);
                    $stmtHist->execute();
                    $stmtHist->close();
                }

                // Notificación a administradores y recepción para coordinar entrega
                $stmtNotif = $db->prepare("
                    INSERT INTO notificaciones (usuario_id, titulo, mensaje, link)
                    SELECT id, ?, ?, 'soporte.html' FROM personas WHERE tipo IN ('admin', 'recepcion') AND estado = 'ACTIVO'
                ");
                if ($stmtNotif) {
                    $titN = "Equipo Listo: " . $numRef;
                    $msgN = "El repuesto {$info['repuesto_nombre']} fue instalado y probado. El equipo pasó a REPARADO y está listo para entrega.";
                    $stmtNotif->bind_param("ss", $titN, $msgN);
                    $stmtNotif->execute();
                    $stmtNotif->close();
                }
            }

            echo json_encode(["ok" => true, "msg" => "Repuesto instalado y probado. Laptop en estado REPARADO."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error: " . $db->error]);
        }
        $stmt->close();
        break;

    // -------------------------------------------------------------
    // FINALIZAR ENTREGA AL CLIENTE CON BALANCE NETO (v1.6.3)
    // -------------------------------------------------------------
    case "finalizar_entrega":
        $ensureTablePedidosRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de pedido inválido"]);
            break;
        }

        $stmtInfo = $db->prepare("SELECT * FROM pedidos_repuestos WHERE id = ?");
        $stmtInfo->bind_param("i", $id);
        $stmtInfo->execute();
        $p = $stmtInfo->get_result()->fetch_assoc();
        $stmtInfo->close();

        if (!$p) {
            echo json_encode(["ok" => false, "msg" => "Pedido no encontrado"]);
            break;
        }

        $pCli = floatval($p['precio_cliente'] ?? 0);
        $cCmp = floatval($p['costo_compra'] ?? 0);
        $cEnv = floatval($p['costo_envio'] ?? 0);
        $gananciaNeta = $pCli - $cCmp - $cEnv;
        $tkId = (int)($p['ticket_id'] ?? 0);
        $numRef = $p['numero_referencia'] ?? "Orden #$id";

        // Marcar soporte técnico como ENTREGADO
        if ($tkId > 0) {
            $stmtSt = $db->prepare("UPDATE soporte_tecnico SET estado = 'ENTREGADO', fecha_entrega = NOW() WHERE id = ?");
            $stmtSt->bind_param("i", $tkId);
            $stmtSt->execute();
            $stmtSt->close();

            $stmtHist = $db->prepare("
                INSERT INTO historial_cambios 
                (tabla_origen, registro_id, numero_referencia, campo_cambiado, valor_anterior, valor_nuevo, usuario_nombre)
                VALUES ('soporte_tecnico', ?, ?, 'estado', 'REPARADO', 'ENTREGADO', ?)
            ");
            if ($stmtHist) {
                $stmtHist->bind_param("iss", $tkId, $numRef, $userName);
                $stmtHist->execute();
                $stmtHist->close();
            }
        }

        // Anotar balance final en notas_envio de pedidos_repuestos y marcar estado ENTREGADO
        $notaCierre = " [ENTREGADO - Margen Neto: S/ " . number_format($gananciaNeta, 2) . "]";
        $stmtUpdPed = $db->prepare("UPDATE pedidos_repuestos SET estado_envio = 'ENTREGADO', notas_envio = CONCAT(COALESCE(notas_envio, ''), ?) WHERE id = ?");
        $stmtUpdPed->bind_param("si", $notaCierre, $id);
        $stmtUpdPed->execute();
        $stmtUpdPed->close();

        echo json_encode([
            "ok" => true,
            "msg" => "Equipo entregado al cliente exitosamente",
            "balance" => [
                "precio_cliente" => $pCli,
                "costo_compra" => $cCmp,
                "costo_envio" => $cEnv,
                "ganancia_neta" => $gananciaNeta
            ]
        ]);
        break;

    // -------------------------------------------------------------
    // ACCIONES DEL CATÁLOGO E INVENTARIO FÍSICO (v1.5.5)
    // -------------------------------------------------------------
    case "resumen":
    case "metricas":
        $ensureTableRepuestos();
        $totalItems = 0;
        $totalStock = 0;
        $stockCritico = 0;
        $valorTotal = 0.0;

        $resKpi = $db->query("
            SELECT 
                COUNT(*) as total_items,
                COALESCE(SUM(stock), 0) as total_stock,
                COALESCE(SUM(CASE WHEN stock <= stock_minimo THEN 1 ELSE 0 END), 0) as stock_critico,
                COALESCE(SUM(stock * precio), 0.0) as valor_total
            FROM repuestos
        ");
        if ($resKpi && ($kpi = $resKpi->fetch_assoc())) {
            $totalItems = (int)$kpi['total_items'];
            $totalStock = (int)$kpi['total_stock'];
            $stockCritico = (int)$kpi['stock_critico'];
            $valorTotal = (float)$kpi['valor_total'];
        }

        // Conteo por categorías
        $catList = [];
        $resCat = $db->query("
            SELECT categoria, COUNT(*) as cantidad, COALESCE(SUM(stock), 0) as stock_cat 
            FROM repuestos 
            GROUP BY categoria 
            ORDER BY categoria ASC
        ");
        if ($resCat) {
            while ($cRow = $resCat->fetch_assoc()) {
                $catList[] = [
                    'categoria' => $cRow['categoria'] ?: 'General',
                    'cantidad' => (int)$cRow['cantidad'],
                    'stock' => (int)$cRow['stock_cat']
                ];
            }
        }

        echo json_encode([
            "ok" => true,
            "data" => [
                "total_items" => $totalItems,
                "total_stock" => $totalStock,
                "stock_critico" => $stockCritico,
                "valor_total" => round($valorTotal, 2),
                "categorias" => $catList,
                "user_tipo" => $userTipo,
                "puede_editar" => ($userTipo === 'admin' || $userTipo === 'gerencia')
            ]
        ]);
        break;

    case "list":
        $ensureTableRepuestos();
        $categoria = trim($_GET["categoria"] ?? "");
        $critico = trim($_GET["critico"] ?? "");

        if (!empty($categoria) && strtoupper($categoria) !== 'TODOS') {
            $stmt = $db->prepare("SELECT * FROM repuestos WHERE categoria = ? ORDER BY nombre ASC");
            $stmt->bind_param("s", $categoria);
            $stmt->execute();
            $result = $stmt->get_result();
        } elseif ($critico === '1') {
            $result = $db->query("SELECT * FROM repuestos WHERE stock <= stock_minimo ORDER BY stock ASC, nombre ASC");
        } else {
            $result = $db->query("SELECT * FROM repuestos ORDER BY nombre ASC");
        }

        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['stock'] = (int)$row['stock'];
                $row['stock_minimo'] = (int)($row['stock_minimo'] ?? 1);
                $row['precio'] = (float)$row['precio'];
                $rows[] = $row;
            }
        }
        echo json_encode([
            "ok" => true, 
            "data" => $rows,
            "puede_editar" => ($userTipo === 'admin' || $userTipo === 'gerencia')
        ]);
        break;

    case "buscar":
        $ensureTableRepuestos();
        $raw_q = trim($_GET["q"] ?? "");
        if ($raw_q === "") {
            $result = $db->query("SELECT * FROM repuestos ORDER BY nombre ASC LIMIT 50");
        } else {
            $q = "%" . $raw_q . "%";
            $stmt = $db->prepare("
                SELECT * FROM repuestos 
                WHERE nombre LIKE ? OR pn LIKE ? OR categoria LIKE ? OR ubicacion LIKE ?
                ORDER BY nombre ASC LIMIT 50
            ");
            $stmt->bind_param("ssss", $q, $q, $q, $q);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['stock'] = (int)$row['stock'];
                $row['stock_minimo'] = (int)($row['stock_minimo'] ?? 1);
                $row['precio'] = (float)$row['precio'];
                $rows[] = $row;
            }
        }
        echo json_encode([
            "ok" => true, 
            "data" => $rows,
            "puede_editar" => ($userTipo === 'admin' || $userTipo === 'gerencia')
        ]);
        break;

    case "crear":
        if ($userTipo !== 'admin' && $userTipo !== 'gerencia') {
            http_response_code(403);
            echo json_encode(["ok" => false, "msg" => "No tienes permisos para registrar repuestos en el catálogo."]);
            break;
        }
        $ensureTableRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $n = trim($data["nombre"] ?? "");
        $pn = trim($data["pn"] ?? "");
        $cat = trim($data["categoria"] ?? "General");
        $s = (int)($data["stock"] ?? 0);
        $sm = (int)($data["stock_minimo"] ?? 1);
        $p = (float)($data["precio"] ?? 0);
        $ub = trim($data["ubicacion"] ?? "Taller");
        $nt = trim($data["notas"] ?? "");

        if (empty($n)) {
            echo json_encode(["ok" => false, "msg" => "El nombre del repuesto es obligatorio."]);
            break;
        }

        $stmt = $db->prepare("INSERT INTO repuestos (nombre, pn, categoria, stock, stock_minimo, precio, ubicacion, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiidss", $n, $pn, $cat, $s, $sm, $p, $ub, $nt);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "id" => $db->insert_id, "msg" => "Repuesto registrado con éxito."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al guardar: " . $db->error]);
        }
        $stmt->close();
        break;

    case "actualizar":
        if ($userTipo !== 'admin' && $userTipo !== 'gerencia') {
            http_response_code(403);
            echo json_encode(["ok" => false, "msg" => "No tienes permisos para modificar repuestos."]);
            break;
        }
        $ensureTableRepuestos();
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $id = (int)($data["id"] ?? 0);
        $n = trim($data["nombre"] ?? "");
        $pn = trim($data["pn"] ?? "");
        $cat = trim($data["categoria"] ?? "General");
        $s = (int)($data["stock"] ?? 0);
        $sm = (int)($data["stock_minimo"] ?? 1);
        $p = (float)($data["precio"] ?? 0);
        $ub = trim($data["ubicacion"] ?? "Taller");
        $nt = trim($data["notas"] ?? "");

        if ($id <= 0 || empty($n)) {
            echo json_encode(["ok" => false, "msg" => "Datos incompletos o ID inválido."]);
            break;
        }

        $stmt = $db->prepare("UPDATE repuestos SET nombre=?, pn=?, categoria=?, stock=?, stock_minimo=?, precio=?, ubicacion=?, notas=? WHERE id=?");
        $stmt->bind_param("sssiidssi", $n, $pn, $cat, $s, $sm, $p, $ub, $nt, $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "msg" => "Repuesto actualizado con éxito."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al actualizar: " . $db->error]);
        }
        $stmt->close();
        break;

    case "eliminar":
        if ($userTipo !== 'admin') {
            http_response_code(403);
            echo json_encode(["ok" => false, "msg" => "Solo los administradores pueden eliminar repuestos del catálogo."]);
            break;
        }
        $id = (int)($_GET["id"] ?? 0);
        if ($id <= 0) {
            echo json_encode(["ok" => false, "msg" => "ID de repuesto inválido."]);
            break;
        }
        $stmt = $db->prepare("DELETE FROM repuestos WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "msg" => "Repuesto eliminado del catálogo."]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error al eliminar: " . $db->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["ok" => false, "msg" => "Acción inválida"]);
}
$db->close();
?>