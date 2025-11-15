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

// Manejo seguro de errores
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset('utf8mb4');

try {
    // 1️⃣ Obtener datos del producto
    $stmtProd = $conexion->prepare("SELECT nombre FROM Producto WHERE id = ? LIMIT 1");
    $stmtProd->bind_param("i", $producto_id);
    $stmtProd->execute();
    $resProd = $stmtProd->get_result();
    $producto = $resProd->fetch_assoc();
    if (!$producto) {
        die("❌ Producto no encontrado.");
    }

    // 2️⃣ Total en lotes (para historial)
    $stmtSum = $conexion->prepare("SELECT COALESCE(SUM(cantidad_actual),0) AS total FROM Lote WHERE producto_id = ?");
    $stmtSum->bind_param("i", $producto_id);
    $stmtSum->execute();
    $totalLotes = $stmtSum->get_result()->fetch_assoc();
    $cantidadEliminada = (int)($totalLotes['total'] ?? 0);

    // 3️⃣ Iniciar transacción
    $conexion->begin_transaction();

    // 3.a) Eliminar lotes asociados
    $stmtDelLotes = $conexion->prepare("DELETE FROM Lote WHERE producto_id = ?");
    $stmtDelLotes->bind_param("i", $producto_id);
    $stmtDelLotes->execute();

    // 3.b) Eliminar producto
    $stmtDelProd = $conexion->prepare("DELETE FROM Producto WHERE id = ?");
    $stmtDelProd->bind_param("i", $producto_id);
    $stmtDelProd->execute();

    // 4️⃣ Confirmar cambios
    $conexion->commit();

    // 5️⃣ Registrar acción en archivo de auditoría (seguimiento)
    $usuario_id = $_SESSION["usuario_id"];
    $usuario_nombre = $_SESSION["usuario_nombre"] ?? 'Desconocido';
    $fecha = date("Y-m-d H:i:s");
    $nombreProd = $producto['nombre'];

    $registro = "[{$fecha}] Usuario #{$usuario_id} ({$usuario_nombre}) eliminó el producto '{$nombreProd}' (ID={$producto_id}), cantidad total eliminada: {$cantidadEliminada}\n";
    
    // Guardar en archivo dentro de /logs (crealo si no existe)
    $logFile = __DIR__ . "/logs/auditoria_eliminaciones.log";
    if (!is_dir(__DIR__ . "/logs")) mkdir(__DIR__ . "/logs", 0777, true);
    file_put_contents($logFile, $registro, FILE_APPEND);

    // 6️⃣ Registrar también en HistorialStock (si la FK lo permite)
    try {
        $tipo    = "Baja";
        $detalle = "Eliminación del producto '{$nombreProd}' y sus lotes asociados";
        $stmtHist = $conexion->prepare("
            INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtHist->bind_param("isis", $producto_id, $tipo, $cantidadEliminada, $detalle);
        $stmtHist->execute();
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1452) throw $e;
    }

    header("Location: Stock.php?msg=eliminado");
    exit();

} catch (mysqli_sql_exception $e) {
    try { $conexion->rollback(); } catch (Throwable $t) {}

    if ((int)$e->getCode() === 1451) {
        die("❌ No se pudo eliminar el producto porque está referenciado por otros registros (ventas/compras/historial).
            <br>💡 Sugerencia: desactívalo en lugar de borrarlo o elimina primero las referencias.");
    }

    die("❌ Error al eliminar el producto. Detalle: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
