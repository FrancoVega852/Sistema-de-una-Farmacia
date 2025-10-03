<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorCompras.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$ctl  = new ControladorCompras($conn->conexion);

$proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
$usuario_id   = (int)$_SESSION['usuario_id'];
$items        = array_values($_POST['items'] ?? []);
$obs          = trim($_POST['obs'] ?? '');

try {
    if ($proveedor_id<=0) throw new Exception("Proveedor inválido.");
    if (empty($items))    throw new Exception("No hay ítems para registrar.");
    // Normalizamos cantidades y costos
    $norm = [];
    foreach($items as $it){
        $norm[] = [
            'id'   => (int)($it['id'] ?? 0),
            'cant' => (int)($it['cant'] ?? 0),
            'costo'=> (float)($it['costo'] ?? 0),
            'lote' => trim($it['lote'] ?? ''),
            'vto'  => trim($it['vto'] ?? '')
        ];
    }
    $oc_id = $ctl->guardar($proveedor_id, $usuario_id, $norm, $obs);
    header("Location: compras_ver.php?id=".$oc_id);
} catch(Exception $e){
    header("Location: compras.php?msg=".urlencode($e->getMessage()));
}
