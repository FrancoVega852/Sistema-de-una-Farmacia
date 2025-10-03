<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["usuario_rol"] ?? '') !== "Administrador") {
    die("⛔ No tienes permisos para acceder a esta página.");
}

if (!isset($_GET["id"])) {
    die("⚠️ No se especificó el producto a eliminar.");
}

$producto_id = (int)$_GET["id"];

$conn        = new Conexion();
$conexion    = $conn->conexion;
$productoObj = new Producto($conexion);
$loteObj     = new Lote($conexion);

// Opcional pero útil para manejo con excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset('utf8mb4');

try {
    // 1) Obtener datos del producto (nombre)
    $stmtProd = $conexion->prepare("SELECT nombre FROM Producto WHERE id = ? LIMIT 1");
    $stmtProd->bind_param("i", $producto_id);
    $stmtProd->execute();
    $resProd = $stmtProd->get_result();
    $producto = $resProd->fetch_assoc();
    if (!$producto) {
        die("❌ Producto no encontrado.");
    }

    // 2) Total en lotes (para historial)
    $stmtSum = $conexion->prepare("SELECT COALESCE(SUM(cantidad_actual),0) AS total FROM Lote WHERE producto_id = ?");
    $stmtSum->bind_param("i", $producto_id);
    $stmtSum->execute();
    $totalLotes = $stmtSum->get_result()->fetch_assoc();
    $cantidadEliminada = (int)($totalLotes['total'] ?? 0);

    // 3) Transacción: borrar lotes + borrar producto (ambos o ninguno)
    $conexion->begin_transaction();

    // 3.a) Eliminar lotes asociados
    $stmtDelLotes = $conexion->prepare("DELETE FROM Lote WHERE producto_id = ?");
    $stmtDelLotes->bind_param("i", $producto_id);
    $stmtDelLotes->execute();

    // 3.b) Eliminar producto
    $stmtDelProd = $conexion->prepare("DELETE FROM Producto WHERE id = ?");
    $stmtDelProd->bind_param("i", $producto_id);
    $stmtDelProd->execute();

    // Si llegó acá sin excepción, confirmar cambios
    $conexion->commit();

    // 4) Registrar movimiento en historial (fuera de la transacción)
    //    Si tu HistorialStock tiene FK a Producto (RESTRICT), este insert puede fallar (1452).
    //    Lo capturamos y lo ignoramos para no romper el flujo.
    try {
        $tipo    = "Baja";
        $detalle = "Eliminación del producto '" . $producto['nombre'] . "' y sus lotes asociados";

        $stmtHist = $conexion->prepare("
            INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtHist->bind_param("isis", $producto_id, $tipo, $cantidadEliminada, $detalle);
        $stmtHist->execute();
    } catch (mysqli_sql_exception $e) {
        // 1452 = Cannot add or update a child row: a foreign key constraint fails
        if ((int)$e->getCode() !== 1452) {
            // Si es otro error, relanzar
            throw $e;
        }
        // Si es 1452, solo ignoramos el historial (el borrado ya fue exitoso)
    }

    header("Location: Stock.php?msg=eliminado");
    exit();

} catch (mysqli_sql_exception $e) {
    // Si algo falló durante la transacción, revertimos
    if ($conexion->errno) {
        // Si la transacción estaba abierta, intentar rollback
        try { $conexion->rollback(); } catch (Throwable $t) {}
    }

    // 1451 = Cannot delete or update a parent row: a foreign key constraint fails
    if ((int)$e->getCode() === 1451) {
        // Caso típico: el producto está referenciado por ventas/compras, etc.
        // No borramos nada (lotes quedan intactos por el rollback).
        die("❌ No se pudo eliminar el producto porque está referenciado por otros registros (ventas/compras/historial).
            <br>💡 Sugerencia: desactívalo en lugar de borrarlo (baja lógica) o elimina primero las referencias.");
    }

    // Otro error
    die("❌ Error al eliminar el producto. Detalle: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
