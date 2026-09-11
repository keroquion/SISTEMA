<?php
// ==========================================================
// PETULAP SALES CRM - CONEXIÓN A BASE DE DATOS
// Soporta MySQL nativo y fallback auto-instalable
// ==========================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Credenciales de producción Petulap
$host = 'localhost';
$db_user = 'petumjvq_petumjvq';
$db_pass = 'gu39hMsxVWDMhYP';
$db_name = 'petumjvq_pruebas';

// Si existe config.php de la app principal, usar sus constantes
$main_config = __DIR__ . '/../../website_files/api/config.php';
if (file_exists($main_config)) {
    @include_once $main_config;
    if (defined('DB_HOST')) $host = DB_HOST;
    if (defined('DB_USER')) $db_user = DB_USER;
    if (defined('DB_PASS')) $db_pass = DB_PASS;
    if (defined('DB_NAME')) $db_name = DB_NAME;
}

$conn = null;
try {
    // Conexión MySQLi con reporte silencioso de excepciones
    mysqli_report(MYSQLI_REPORT_STRICT);
    $conn = @new mysqli($host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");

    // Auto-crear tablas si no existen
    $check_table = @$conn->query("SHOW TABLES LIKE 'crm_leads'");
    if ($check_table && $check_table->num_rows === 0) {
        $schema_file = __DIR__ . '/schema.sql';
        if (file_exists($schema_file)) {
            $sql = file_get_contents($schema_file);
            $conn->multi_query($sql);
            while ($conn->more_results() && $conn->next_result()) {;}
        }
    } else {
        // Auto-migración v1.3.0 de columnas nuevas
        @$conn->query("ALTER TABLE crm_leads ADD COLUMN prioridad_compra VARCHAR(20) DEFAULT 'NORMAL'");
        @$conn->query("ALTER TABLE crm_leads ADD COLUMN en_bolsa_rescate TINYINT(1) DEFAULT 0");
        @$conn->query("ALTER TABLE crm_leads ADD COLUMN ultimo_contacto_vendedor DATETIME DEFAULT CURRENT_TIMESTAMP");
        @$conn->query("ALTER TABLE crm_leads ADD COLUMN proxima_llamada_hora DATETIME DEFAULT NULL");
        @$conn->query("ALTER TABLE crm_promociones ADD COLUMN unidades_reservadas INT DEFAULT 0");
        @$conn->query("CREATE TABLE IF NOT EXISTS crm_llamadas_registro (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lead_id INT NOT NULL,
            vendedor_id INT DEFAULT 1,
            resultado VARCHAR(50) NOT NULL,
            notas TEXT DEFAULT NULL,
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_llamada_lead (lead_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Exception $e) {
    // Si falla MySQL local, usar SQLite local en storage/ para que funcione sin configuración
    $storage_dir = __DIR__ . '/storage';
    if (!is_dir($storage_dir)) {
        @mkdir($storage_dir, 0777, true);
    }
    $sqlite_file = $storage_dir . '/crm_sales.sqlite';
    try {
        $pdo = new PDO('sqlite:' . $sqlite_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Inicializar esquema SQLite si es nuevo
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS crm_leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telefono TEXT NOT NULL,
                nombre TEXT NOT NULL,
                etapa TEXT DEFAULT 'NUEVO',
                temperatura TEXT DEFAULT 'VERDE',
                vendedor_id INTEGER DEFAULT 1,
                modelo_interes_id INTEGER DEFAULT NULL,
                modelo_interes_texto TEXT DEFAULT NULL,
                presupuesto_aprox REAL DEFAULT NULL,
                origen_lead TEXT DEFAULT 'WHATSAPP',
                sede_preferida TEXT DEFAULT 'YANAHUARA',
                ultimo_mensaje_texto TEXT DEFAULT NULL,
                ultimo_mensaje_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                ultimo_mensaje_emisor TEXT DEFAULT 'CLIENTE',
                motivo_perdida TEXT DEFAULT NULL,
                notas TEXT DEFAULT NULL,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS crm_promociones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                categoria TEXT NOT NULL,
                marca TEXT NOT NULL,
                modelo TEXT NOT NULL,
                procesador TEXT,
                ram TEXT,
                almacenamiento TEXT,
                pantalla TEXT,
                precio_regular REAL,
                precio_promo REAL NOT NULL,
                stock_disponible INTEGER DEFAULT 1,
                nota_stock TEXT,
                descripcion_comercial TEXT,
                activo INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS crm_agendamientos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                vendedor_id INTEGER DEFAULT 1,
                tipo TEXT NOT NULL,
                fecha_hora DATETIME NOT NULL,
                modelo_laptop TEXT,
                estado TEXT DEFAULT 'PENDIENTE',
                notas TEXT,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $conn = $pdo; // Usar PDO SQLite
    } catch (Exception $sqle) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo conectar a la base de datos: ' . $e->getMessage()]);
        exit;
    }
}

// Función helper unificada para responder JSON
function json_resp($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
