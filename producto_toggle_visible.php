<?php
/*******************************************************
 * FARVEC • Toggle visibilidad producto (AJAX)
 *******************************************************/
session_start();
require_once 'Conexion.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo "ERROR: Sesión expirada";
  exit();
}

$rol = $_SESSION['usuario_rol'] ?? 'Empleado';
if (!in_array($rol, ['Administrador','Farmaceutico'])) {
  http_response_code(403);
  echo "ERROR: Sin permisos";
  exit();
}

$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$visible = isset($_POST['visible']) ? (int)$_POST['visible'] : 0;

if ($id <= 0) {
  http_response_code(400);
  echo "ERROR: ID inválido";
  exit();
}

$conn = new Conexion();
$db   = $conn->conexion;
$db->set_charset('utf8mb4');

$st = $db->prepare("UPDATE Producto SET visible_cliente = ? WHERE id = ?");
$st->bind_param("ii", $visible, $id);
$st->execute();

echo "OK";
