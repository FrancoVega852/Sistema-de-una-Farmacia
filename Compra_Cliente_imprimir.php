<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new Conexion();
$db   = $conn->conexion;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { exit("Compra no encontrada."); }

// CABECERA
$sql = "SELECT v.id, v.fecha, v.total, v.estado,
               c.nombre AS cli_nombre, c.apellido AS cli_apellido,
               c.nroDocumento,
               u.nombre AS usuario
        FROM Venta v
        LEFT JOIN Cliente c ON c.id = v.cliente_id
        INNER JOIN Usuario u ON u.id = v.usuario_id
        WHERE v.id = ?";
$st = $db->prepare($sql);
$st->bind_param("i", $id);
$st->execute();
$venta = $st->get_result()->fetch_assoc();
if (!$venta) { exit("Compra no encontrada."); }

// DETALLE
$sqlDet = "SELECT d.cantidad, d.precio_unitario, d.subtotal,
                  p.nombre AS producto
           FROM DetalleVenta d
           INNER JOIN Producto p ON p.id = d.producto_id
           WHERE d.venta_id = ?";
$st = $db->prepare($sqlDet);
$st->bind_param("i", $id);
$st->execute();
$detalle = $st->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comprobante Compra #<?= $venta['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui, -apple-system, BlinkMacSystemFont,"Segoe UI",sans-serif;
     margin:0;padding:10px;}
.ticket{max-width:600px;margin:0 auto;border:1px solid #ddd;padding:15px;
        border-radius:10px;}
h1{font-size:18px;margin:0 0 10px;text-align:center;}
h2{font-size:14px;margin:10px 0;}
table{width:100%;border-collapse:collapse;font-size:12px;}
th,td{padding:4px;border-bottom:1px solid #e5e7eb;}
th{text-align:left;background:#f3f4f6;}
.text-right{text-align:right;}
.footer{text-align:center;font-size:11px;margin-top:8px;}
</style>
</head>
<body onload="window.print();">
<div class="ticket">
  <h1>FARVEC - Comprobante de Compra</h1>

  <h2>Datos de la operación</h2>
  <p><strong>N° Compra:</strong> <?= $venta['id'] ?></p>
  <p><strong>Fecha:</strong> <?= date("d/m/Y H:i", strtotime($venta['fecha'])) ?></p>
  <p><strong>Usuario:</strong> <?= htmlspecialchars($venta['usuario']) ?></p>
  <p><strong>Cliente:</strong>
    <?= $venta['cli_nombre']
          ? htmlspecialchars($venta['cli_nombre'].' '.$venta['cli_apellido'])
          : 'Consumidor Final'; ?>
    <?php if($venta['nroDocumento']): ?>
      (<?= htmlspecialchars($venta['nroDocumento']) ?>)
    <?php endif; ?>
  </p>
  <p><strong>Estado:</strong> <?= $venta['estado'] ?></p>

  <h2>Detalle</h2>
  <table>
    <thead>
      <tr>
        <th>Producto</th>
        <th class="text-right">Cant.</th>
        <th class="text-right">P. Unit.</th>
        <th class="text-right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php while($d = $detalle->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($d['producto']) ?></td>
        <td class="text-right"><?= (int)$d['cantidad'] ?></td>
        <td class="text-right">
          $<?= number_format($d['precio_unitario'],2,',','.') ?>
        </td>
        <td class="text-right">
          $<?= number_format($d['subtotal'],2,',','.') ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <h2 style="text-align:right;">Total: $<?= number_format($venta['total'],2,',','.') ?></h2>

  <div class="footer">
    Gracias por su compra.
  </div>
</div>
</body>
</html>
