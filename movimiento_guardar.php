<?php
session_start();
require_once "Conexion.php";

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sesión expirada']);
    exit;
}

if (!isset($_POST['tipo'], $_POST['monto'], $_POST['descripcion'])) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan datos']);
    exit;
}

$tipo = $_POST['tipo']; // Ingreso | Egreso
$monto = (float) $_POST['monto'];
$desc  = trim($_POST['descripcion']);
$usuario = $_SESSION['usuario_id'];

if ($monto <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'El monto debe ser mayor a cero']);
    exit;
}

$conn = new Conexion();
$db = $conn->conexion;

$sql = "INSERT INTO movimientos (tipo, monto, descripcion, fecha, usuario_id)
        VALUES (?, ?, ?, NOW(), ?)";

$st = $db->prepare($sql);
$st->bind_param("sdsi", $tipo, $monto, $desc, $usuario);

if ($st->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Movimiento registrado']);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar']);
}
