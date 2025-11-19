<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: Compra_Cliente_listar.php");
    exit();
}

$conn = new Conexion();
$db   = $conn->conexion;

// Traemos la venta (compra del cliente)
$st = $db->prepare("SELECT id, total, estado FROM Venta WHERE id = ?");
$st->bind_param("i", $id);
$st->execute();
$venta = $st->get_result()->fetch_assoc();

if (!$venta) {
    header("Location: Compra_Cliente_listar.php");
    exit();
}

$db->begin_transaction();
try {

    // Si estaba pagada, registramos EGRESO para anular el ingreso
    if ($venta['estado'] === 'Pagada') {

        // Buscamos si ya existe un ingreso por esta venta/compra
        $chk = $db->prepare("SELECT id FROM Movimiento
                             WHERE origen='Venta' AND ref_id=? AND tipo='Ingreso'
                             LIMIT 1");
        $chk->bind_param("i", $id);
        $chk->execute();
        $tieneIngreso = $chk->get_result()->num_rows > 0;

        if ($tieneIngreso) {
            $desc = "Anulación compra #".$id;
            $mov = $db->prepare(
                "INSERT INTO Movimiento (tipo, monto, descripcion, origen, ref_id)
                 VALUES ('Egreso', ?, ?, 'Venta', ?)"
            );
            $monto = (float)$venta['total'];
            $mov->bind_param("dsi", $monto, $desc, $id);
            $mov->execute();
        }
    }

    // Actualizamos estado a Pendiente (o luego podés usar 'Anulada')
    $nuevoEstado = 'Pendiente';
    $up = $db->prepare("UPDATE Venta SET estado = ? WHERE id = ?");
    $up->bind_param("si", $nuevoEstado, $id);
    $up->execute();

    $db->commit();
    header("Location: Compra_Cliente_listar.php?msg=anulada");
    exit();

} catch (Exception $e) {
    $db->rollback();
    header("Location: Compra_Cliente_listar.php?error=1");
    exit();
}
