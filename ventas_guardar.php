<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'ControladorVentas.php';

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$conn = new Conexion();
$ctl  = new ControladorVentas($conn->conexion);

$cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
$usuario_id = (int)$_SESSION['usuario_id'];

// 🔥 Importante: aquí usamos "productos" (como en el formulario)
$raw = $_POST['productos'] ?? [];
$items = [];

foreach ($raw as $p) {
    if (!empty($p['id']) && !empty($p['cantidad'])) {
        $items[] = [
            'id'   => (int)$p['id'],
            'cant' => (int)$p['cantidad']
        ];
    }
}

try {
    $venta_id = $ctl->guardar($cliente_id, $usuario_id, $items);
    header("Location: ventas_ver.php?id=" . $venta_id);
    exit();
} catch (Exception $e) {
    header("Location: ventas_listar.php?msg=" . urlencode($e->getMessage()));
    exit();
}
