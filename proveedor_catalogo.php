<?php
require_once "Conexion.php";

if (!isset($_GET['proveedor_id'])) {
    echo json_encode([]);
    exit;
}

$prov = intval($_GET['proveedor_id']);

$conn = new Conexion();
$db   = $conn->conexion;

$sql = "
SELECT 
    p.id,
    p.nombre,
    p.precio,
    p.stock_minimo,
    p.requiere_receta,
    p.categoria_id,
    pp.precio_costo,
    pp.codigo_proveedor,
    (
        SELECT presentacion_id 
        FROM PresentacionProducto 
        WHERE producto_id = p.id 
        LIMIT 1
    ) AS presentacion_id
FROM ProveedorProducto pp
INNER JOIN Producto p ON p.id = pp.producto_id
WHERE pp.proveedor_id = $prov
ORDER BY p.nombre ASC
";

$res = $db->query($sql);

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
