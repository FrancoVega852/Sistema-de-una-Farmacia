<?php
session_start();
include 'Conexion.php';

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_rol"] !== "Administrador") {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"])) {
    die("⚠️ No se especificó el producto a eliminar.");
}

$conn = new Conexion();
$conexion = $conn->conexion;

$producto_id = intval($_GET["id"]);

// Obtenemos datos del producto antes de eliminarlo
$producto = $conexion->query("SELECT nombre, stock_actual FROM Producto WHERE id=$producto_id")->fetch_assoc();

// Obtenemos cantidad total de lotes para registrar en historial
$totalLotes = $conexion->query("SELECT SUM(cantidad_actual) AS total FROM Lote WHERE producto_id=$producto_id")->fetch_assoc();
$cantidadEliminada = $totalLotes['total'] ?? 0;

// Primero eliminamos los lotes asociados
$conexion->query("DELETE FROM Lote WHERE producto_id = $producto_id");

// Luego eliminamos el producto
if ($conexion->query("DELETE FROM Producto WHERE id = $producto_id")) {
    // Registrar movimiento en historial
    $tipo = "Baja";
    $detalle = "Eliminación del producto '" . $producto['nombre'] . "'";
    $stmt = $conexion->prepare("INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $producto_id, $tipo, $cantidadEliminada, $detalle);
    $stmt->execute();

    header("Location: Stock.php?msg=eliminado");
    exit();
} else {
    die("❌ Error al eliminar el producto.");
}
