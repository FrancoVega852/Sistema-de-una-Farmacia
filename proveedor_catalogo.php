<?php
session_start();
header("Content-Type: application/json");

require_once "Conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([]);
    exit;
}

$proveedor_id = isset($_GET["proveedor_id"]) ? (int)$_GET["proveedor_id"] : 0;
if ($proveedor_id <= 0) {
    echo json_encode([]);
    exit;
}

$conn = new Conexion();
$db   = $conn->conexion;

/*
   Trae productos que ese proveedor ya vende, con:
   - nombre
   - precio_costo
   - codigo_proveedor
   - categoria_id
   - stock_minimo
   - requiere_receta
   - una presentación principal (la de menor id)
*/
$sql = "
    SELECT 
        p.id,
        p.nombre,
        p.stock_minimo,
        p.categoria_id,
        p.requiere_receta,
        pp.precio_costo,
        pp.codigo_proveedor,
        MIN(ppres.presentacion_id) AS presentacion_id
    FROM ProveedorProducto pp
    JOIN Producto p        ON p.id = pp.producto_id
    LEFT JOIN PresentacionProducto ppres ON ppres.producto_id = p.id
    WHERE pp.proveedor_id = ?
    GROUP BY 
        p.id, p.nombre, p.stock_minimo, p.categoria_id, 
        p.requiere_receta, pp.precio_costo, pp.codigo_proveedor
    ORDER BY p.nombre ASC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $proveedor_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        "producto_id"      => (int)$row["id"],
        "nombre"           => $row["nombre"],
        "precio_costo"     => (float)$row["precio_costo"],
        "codigo_proveedor" => $row["codigo_proveedor"],
        "stock_minimo"     => (int)$row["stock_minimo"],
        "categoria_id"     => $row["categoria_id"] !== null ? (int)$row["categoria_id"] : null,
        "presentacion_id"  => $row["presentacion_id"] !== null ? (int)$row["presentacion_id"] : null,
        "requiere_receta"  => (int)$row["requiere_receta"]
    ];
}

echo json_encode($items);
exit;
?>
