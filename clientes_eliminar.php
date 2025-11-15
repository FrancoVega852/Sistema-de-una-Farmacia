<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorClientes.php';

if (!isset($_SESSION['usuario_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No auth']); exit; }

header('Content-Type: application/json; charset=utf-8');

$conn = new Conexion();
$ctl  = new ControladorClientes($conn->conexion);

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id<=0) throw new Exception('ID inválido');

    $ok = $ctl->eliminar($id);
    if (!$ok) throw new Exception('No se pudo eliminar');

    echo json_encode(['ok'=>true,'id'=>$id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
