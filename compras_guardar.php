<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorCompras.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$conn = new Conexion();
$db   = $conn->conexion;
$ctl  = new ControladorCompras($db);

$isAjax = (isset($_POST['ajax']) && $_POST['ajax']=='1') || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='fetch');

$proveedor = (int)($_POST['proveedor_id'] ?? 0);
$items     = $_POST['items'] ?? [];
$obs       = $_POST['obs'] ?? '';
$usuario   = $_SESSION['usuario_id'];

// ============================
// 1) GUARDAR LA COMPRA
// ============================
$ordenId = $ctl->guardar($proveedor, $usuario, $items, $obs);
$ok = $ordenId > 0;

// ============================
// 2) SI SE GUARDÓ, REGISTRAR EL EGRESO EN MOVIMIENTO
// ============================
if ($ok) {

    // Obtener TOTAL de la compra recién creada
    $st = $db->prepare("SELECT total FROM OrdenCompra WHERE id=? LIMIT 1");
    $st->bind_param("i", $ordenId);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $total = (float)($res['total'] ?? 0);

    // Registrar movimiento de egreso
    $mov = $db->prepare("
        INSERT INTO Movimiento (tipo, monto, descripcion, origen, ref_id)
        VALUES ('Egreso', ?, 'Compra a proveedor', 'Compra', ?)
    ");
    $mov->bind_param("di", $total, $ordenId);
    $mov->execute();
}

// ============================
// 3) RESPUESTA AJAX
// ============================
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'   => $ok,
        'id'   => $ordenId,
        'msg'  => $ok ? 'Compra registrada correctamente' : 'No se pudo registrar la compra'
    ]);
    exit;
}

// ============================
// 4) REDIRECCIÓN NORMAL
// ============================
if ($ok) {
    header("Location: compras_ver.php?id=$ordenId&ok=1");
    exit;
}

header("Location: compras_listar.php?err=1");
exit;
