<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorCompras.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$conn = new Conexion();
$ctl  = new ControladorCompras($conn->conexion);

$isAjax = (isset($_POST['ajax']) && $_POST['ajax']=='1') || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='fetch');

$proveedor = (int)($_POST['proveedor_id'] ?? 0);
$items     = $_POST['items'] ?? [];
$obs       = $_POST['obs'] ?? '';
$usuario   = $_SESSION['usuario_id'];

// Guardar compra -> devuelve el ID de la OrdenCompra
$ordenId = $ctl->guardar($proveedor, $usuario, $items, $obs);

$ok = $ordenId > 0;

// Si es AJAX devolvemos JSON
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'   => $ok,
        'id'   => $ordenId,
        'msg'  => $ok ? 'Compra registrada correctamente' : 'No se pudo registrar la compra'
    ]);
    exit;
}

// Redirección normal
if ($ok) {
    header("Location: compras_ver.php?id=$ordenId&ok=1");
    exit;
}

header("Location: compras_listar.php?err=1");
exit;
