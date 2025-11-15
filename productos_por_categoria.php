<?php
require_once "Conexion.php";
$conn = new Conexion();
$db = $conn->conexion;

$cat = intval($_GET['categoria_id']);

$sql = "
SELECT 
    p.*,
    pv.razonSocial AS proveedor_nombre,
    pp.precio_costo,
    pp.codigo_proveedor,
    (
        SELECT presentacion_id 
        FROM PresentacionProducto 
        WHERE producto_id = p.id LIMIT 1
    ) AS presentacion_id
FROM Producto p
LEFT JOIN ProveedorProducto pp ON pp.producto_id = p.id
LEFT JOIN Proveedor pv ON pv.id = pp.proveedor_id
WHERE p.categoria_id = $cat
ORDER BY p.nombre ASC
";
$res = $db->query($sql);

$data = [];
while($row=$res->fetch_assoc()) $data[] = $row;

echo json_encode($data);
