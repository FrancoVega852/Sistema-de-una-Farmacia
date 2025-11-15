<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorClientes.php';

if (!isset($_SESSION['usuario_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'No auth']); exit; }

header('Content-Type: application/json; charset=utf-8');

$conn = new Conexion();
$ctl  = new ControladorClientes($conn->conexion);

try {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    // Guardar (tu método ya soporta insert/update)
    $ok = $ctl->guardar($id, $_POST);
    if (!$ok) throw new Exception('No se pudo guardar');

    // Si fue inserción, obtener el ID generado
    if ($id === null) {
        $id = $conn->conexion->insert_id;
    }

    // Traer datos limpios para pintar la fila
    $cli = $ctl->obtener($id);
    if (!$cli) throw new Exception('No se pudo leer el cliente guardado');

    echo json_encode(['ok'=>true,'id'=>$id,'cliente'=>$cli]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
