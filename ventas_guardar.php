<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'VentasController.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$ctl  = new VentasController($conn->conexion);

$cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
$usuario_id = (int)$_SESSION['usuario_id'];
$items      = array_values($_POST['prod'] ?? []);

try {
    $venta_id = $ctl->guardar($cliente_id, $usuario_id, $items);
    header("Location: ventas_ver.php?id=".$venta_id);
} catch (Exception $e) {
    header("Location: ventas_listar.php?msg=".urlencode($e->getMessage()));
}
