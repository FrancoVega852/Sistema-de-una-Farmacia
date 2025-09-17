<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["usuario_rol"] !== "Administrador") {
    die("⛔ No tienes permisos para acceder a esta página.");
}

if (!isset($_GET["id"])) {
    die("⚠️ No se especificó el producto a eliminar.");
}

$conn        = new Conexion();
$conexion    = $conn->conexion;
$productoObj = new Producto($conexion);
$loteObj     = new Lote($conexion);

$producto_id = intval($_GET["id"]);

// 1️⃣ Obtener datos del producto
$producto = $conexion->query("SELECT nombre FROM Producto WHERE id=$producto_id")->fetch_assoc();
if (!$producto) {
    die("❌ Producto no encontrado.");
}

// 2️⃣ Obtener cantidad total en lotes (para registrar en historial)
$totalLotes = $conexion->query("SELECT SUM(cantidad_actual) AS total FROM Lote WHERE producto_id=$producto_id")->fetch_assoc();
$cantidadEliminada = $totalLotes['total'] ?? 0;

// 3️⃣ Eliminar lotes asociados
$conexion->query("DELETE FROM Lote WHERE producto_id = $producto_id");

// 4️⃣ Eliminar producto
if ($conexion->query("DELETE FROM Producto WHERE id = $producto_id")) {
    // 5️⃣ Registrar movimiento en historial
    $tipo    = "Baja";
    $detalle = "Eliminación del producto '" . $producto['nombre'] . "' y sus lotes asociados";

    $stmt = $conexion->prepare("INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $producto_id, $tipo, $cantidadEliminada, $detalle);
    $stmt->execute();

    header("Location: Stock.php?msg=eliminado");
    exit();
} else {
    die("❌ Error al eliminar el producto.");
}
?>
