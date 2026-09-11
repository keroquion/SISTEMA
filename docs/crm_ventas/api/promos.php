<?php
// ==========================================================
// PETULAP SALES CRM - CATÁLOGO DE PROMOCIONES Y OFERTAS
// Permite consultar y alimentar las promociones del archivo CSV
// ==========================================================

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'list';

// Catálogo predeterminado obtenido del CSV de promociones
$promos_seed = [
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad T14',
        'procesador' => 'Intel Core i5',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" FHD Antirreflejo',
        'precio_regular' => 1350.00,
        'precio_promo' => 1190.00,
        'stock_disponible' => 4,
        'nota_stock' => 'Stock limitado promo',
        'descripcion_comercial' => 'Gama ejecutiva ultraduradera estándar militar. Teclado ergonómico legendario.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Dell',
        'modelo' => 'Latitude 7490',
        'procesador' => 'Intel Core i5 8th Gen',
        'ram' => '8 GB / 16 GB',
        'almacenamiento' => '256 GB SSD',
        'pantalla' => '14" HD/FHD',
        'precio_regular' => 1300.00,
        'precio_promo' => 1200.00,
        'stock_disponible' => 3,
        'nota_stock' => 'Promo junio',
        'descripcion_comercial' => 'Chasis de fibra de carbono ligero, ideal para oficina y universidad.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Dell',
        'modelo' => 'Latitude 5410 Táctil',
        'procesador' => 'Intel Core i5 10th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '256 GB SSD',
        'pantalla' => '14" FHD Pantalla Táctil',
        'precio_regular' => 1350.00,
        'precio_promo' => 1190.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Pantalla táctil disponible',
        'descripcion_comercial' => 'Versatilidad táctil ejecutiva con procesador de décima generación.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad L14',
        'procesador' => 'Intel Core i5 10th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" FHD',
        'precio_regular' => 1500.00,
        'precio_promo' => 1390.00,
        'stock_disponible' => 3,
        'nota_stock' => 'Excelente autonomía',
        'descripcion_comercial' => 'Capacidad de 512GB SSD y 16GB de RAM para multitarea pesada.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Dell',
        'modelo' => 'Vostro / Latitude 3420',
        'procesador' => 'Intel Core i5 11th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '256 GB SSD',
        'pantalla' => '14" Micro-Borde',
        'precio_regular' => 1550.00,
        'precio_promo' => 1390.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Solo 2 unidades',
        'descripcion_comercial' => 'Procesador Intel de 11va generación con gráficos Iris Xe.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad T14s Slim',
        'procesador' => 'Intel Core i5 11th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" IPS FHD',
        'precio_regular' => 1600.00,
        'precio_promo' => 1450.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Chasis ultradelgado T14s',
        'descripcion_comercial' => 'Versión slim más liviana y potente de la serie T.'
    ],
    [
        'categoria' => 'LAPTOP_MEDIA',
        'marca' => 'Dell',
        'modelo' => 'Latitude 7300',
        'procesador' => 'Intel Core i7 8th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '256 GB SSD',
        'pantalla' => '13.3" FHD Compacta',
        'precio_regular' => 1250.00,
        'precio_promo' => 1100.00,
        'stock_disponible' => 1,
        'nota_stock' => '1 stock único',
        'descripcion_comercial' => 'Portabilidad extrema en 13 pulgadas con potencia Core i7.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'HP',
        'modelo' => 'ProBook 440 G8',
        'procesador' => 'Intel Core i7 11th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" FHD IPS',
        'precio_regular' => 1990.00,
        'precio_promo' => 1790.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Acabado en aluminio plateado',
        'descripcion_comercial' => 'Diseño refinado en aluminio para directores y profesionales.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'Dell',
        'modelo' => 'Latitude 5510',
        'procesador' => 'Intel Core i7 10th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '15.6" con Teclado Numérico',
        'precio_regular' => 2200.00,
        'precio_promo' => 1999.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Teclado numérico completo',
        'descripcion_comercial' => 'Pantalla amplia de 15.6" con teclado numérico para contabilidad y análisis.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'Dell',
        'modelo' => 'Latitude 3520',
        'procesador' => 'Intel Core i7 11th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '15.6" FHD',
        'precio_regular' => 2600.00,
        'precio_promo' => 2390.00,
        'stock_disponible' => 2,
        'nota_stock' => '11va generación i7',
        'descripcion_comercial' => 'Gran potencia y velocidad con gráficos Iris Xe en pantalla grande.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'HP',
        'modelo' => 'EliteBook / ProBook 440 G8 Max',
        'procesador' => 'Intel Core i7 11th Gen',
        'ram' => '32 GB RAM',
        'almacenamiento' => '1 TB NVMe SSD',
        'pantalla' => '14" FHD IPS',
        'precio_regular' => 2850.00,
        'precio_promo' => 2600.00,
        'stock_disponible' => 1,
        'nota_stock' => '32GB RAM + 1 Tera SSD',
        'descripcion_comercial' => 'Capacidad máxima para bases de datos, virtualización y programación.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'HP',
        'modelo' => 'ProBook 450 G9',
        'procesador' => 'Intel Core i7 12th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '15.6" FHD',
        'precio_regular' => 2990.00,
        'precio_promo' => 2700.00,
        'stock_disponible' => 2,
        'nota_stock' => '12va Generación (10 núcleos)',
        'descripcion_comercial' => 'Procesador de 12va generación con arquitectura híbrida de alta potencia.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad Carbon X1 Gen 10',
        'procesador' => 'Intel Core i7 10th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" 2K/FHD Ultraligera (1.1 kg)',
        'precio_regular' => 2800.00,
        'precio_promo' => 2550.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Fibra de carbono real',
        'descripcion_comercial' => 'La laptop ejecutiva más prestigiosa del mundo. Menos de 1.1 kg de peso.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad Carbon X1 Gen 11',
        'procesador' => 'Intel Core i7 11th Gen',
        'ram' => '16 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => '14" WUXGA Antirreflejo',
        'precio_regular' => 3300.00,
        'precio_promo' => 2990.00,
        'stock_disponible' => 1,
        'nota_stock' => 'Gen 11 (512GB SSD)',
        'descripcion_comercial' => 'Máxima exclusividad corporativa y rendimiento de élite.'
    ],
    [
        'categoria' => 'LAPTOP_EJECUTIVA',
        'marca' => 'Apple',
        'modelo' => 'MacBook Air / Pro',
        'procesador' => 'Apple Silicon / Core i5',
        'ram' => '8 GB / 16 GB',
        'almacenamiento' => '256 GB SSD',
        'pantalla' => 'Retina Display True Tone',
        'precio_regular' => 2350.00,
        'precio_promo' => 2099.00,
        'stock_disponible' => 1,
        'nota_stock' => 'Ecosistema Apple',
        'descripcion_comercial' => 'Diseño icónico unibody en aluminio, teclado Magic Keyboard y pantalla Retina.'
    ],
    [
        'categoria' => 'WORKSTATION',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad P17 Workstation',
        'procesador' => 'Intel Core i7 / Xeon Workstation',
        'ram' => '32 GB RAM',
        'almacenamiento' => '1 TB NVMe SSD',
        'pantalla' => '17.3" FHD Calibrada',
        'precio_regular' => 4900.00,
        'precio_promo' => 4490.00,
        'stock_disponible' => 1,
        'nota_stock' => 'Para arquitectura y render',
        'descripcion_comercial' => 'Estación de trabajo pesada con pantalla de 17.3" para AutoCAD, Revit y 3D.'
    ],
    [
        'categoria' => 'WORKSTATION',
        'marca' => 'Petulap Pro',
        'modelo' => 'CPU Workstation Catálogo #11',
        'procesador' => 'Workstation Pro Gaming & Render',
        'ram' => '16 GB / 32 GB',
        'almacenamiento' => '512 GB SSD',
        'pantalla' => 'Torre Workstation',
        'precio_regular' => 2200.00,
        'precio_promo' => 1999.00,
        'stock_disponible' => 3,
        'nota_stock' => 'Solo 3 unidades',
        'descripcion_comercial' => 'Torre de ingeniería profesional y gaming competitivo.'
    ],
    [
        'categoria' => 'WORKSTATION',
        'marca' => 'Petulap Pro',
        'modelo' => 'CPU Workstation Catálogo #12',
        'procesador' => 'Workstation Extreme',
        'ram' => '32 GB',
        'almacenamiento' => '1 TB SSD',
        'pantalla' => 'Torre Workstation',
        'precio_regular' => 3300.00,
        'precio_promo' => 2999.00,
        'stock_disponible' => 3,
        'nota_stock' => 'Solo 3 unidades',
        'descripcion_comercial' => 'Rendimiento industrial para cálculo estructural y renderizado intensivo.'
    ],
    [
        'categoria' => 'WORKSTATION',
        'marca' => 'Petulap Pro',
        'modelo' => 'CPU Workstation Catálogo #13',
        'procesador' => 'Workstation Ultra Power',
        'ram' => '32 GB (+S/190 por 64GB)',
        'almacenamiento' => '1 TB SSD',
        'pantalla' => 'Torre Workstation',
        'precio_regular' => 4100.00,
        'precio_promo' => 3799.00,
        'stock_disponible' => 3,
        'nota_stock' => 'Solo 3 unidades (+S/190 x 32GB extra)',
        'descripcion_comercial' => 'Tope de gama para estudios de arquitectura y desarrollo de software.'
    ],
    [
        'categoria' => 'ACCESORIO',
        'marca' => 'Epson / ViewSonic',
        'modelo' => 'Proyector Cañón Multimedia',
        'procesador' => 'HDMI / VGA / USB',
        'ram' => 'Alta Luminosidad',
        'almacenamiento' => 'N/A',
        'pantalla' => 'Proyección hasta 300"',
        'precio_regular' => 850.00,
        'precio_promo' => 700.00,
        'stock_disponible' => 2,
        'nota_stock' => 'Ideal para capacitaciones y aulas',
        'descripcion_comercial' => 'Proyector de alta luminosidad para salas de reuniones y colegios.'
    ]
];

if ($action === 'list') {
    foreach ($promos_seed as &$p) {
        $p['unidades_reservadas'] = $p['unidades_reservadas'] ?? 0;
        $p['stock_real'] = max(0, $p['stock_disponible'] - $p['unidades_reservadas']);
    }
    json_resp(['success' => true, 'total' => count($promos_seed), 'data' => $promos_seed]);
}

json_resp(['success' => false, 'error' => 'Acción no reconocida'], 400);
