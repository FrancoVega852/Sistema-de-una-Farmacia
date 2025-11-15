<?php
require_once "Conexion.php";
$conn = new Conexion();
$db = $conn->conexion;

$prov = intval($_GET['proveedor_id']);

$sql = "
SELECT 
    p.id,
    p.nombre,
    p.categoria_id,
    c.nombre AS categoria_nombre,
    pp.precio_costo,
    pp.codigo_proveedor,
    (
        SELECT presentacion_id FROM PresentacionProducto 
        WHERE producto_id = p.id LIMIT 1
    ) AS presentacion_id
FROM ProveedorProducto pp
INNER JOIN Producto p ON p.id = pp.producto_id
LEFT JOIN Categoria c ON c.id = p.categoria_id
WHERE pp.proveedor_id = $prov
ORDER BY c.nombre, p.nombre
";

$res = $db->query($sql);

$out = [];
while($row=$res->fetch_assoc()){
    $out[$row["categoria_nombre"]][] = $row;
}

echo json_encode($out);
