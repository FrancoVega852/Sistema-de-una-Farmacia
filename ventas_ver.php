<?php
session_start();
require_once 'Conexion.php';
if (!isset($_GET['id'])) { header("Location: ventas_listar.php"); exit(); }

$conn = new Conexion();
$db   = $conn->conexion;
$id   = (int)$_GET['id'];

$st = $db->prepare("SELECT v.id, v.fecha, v.total, v.estado,
                           c.nombre AS cliente, c.apellido, u.nombre AS usuario
                    FROM Venta v
                    LEFT JOIN Cliente c ON v.cliente_id = c.id
                    INNER JOIN Usuario u ON v.usuario_id = u.id
                    WHERE v.id=?");
$st->bind_param("i",$id); $st->execute();
$venta = $st->get_result()->fetch_assoc();

$st = $db->prepare("SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre AS producto
                    FROM DetalleVenta d INNER JOIN Producto p ON d.producto_id=p.id
                    WHERE d.venta_id=?");
$st->bind_param("i",$id); $st->execute();
$det = $st->get_result();
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><title>Detalle Venta #<?= $venta['id'] ?></title>
<link rel="stylesheet" href="estilos.css">
</head><body>
<a href="ventas_listar.php" class="btn-volver">⬅ Volver</a>
<h1>🧾 Detalle de Venta #<?= $venta['id'] ?></h1>

<div class="card">
  <p><strong>Cliente:</strong> <?= $venta['cliente'] ? $venta['cliente'].' '.$venta['apellido'] : 'Consumidor Final' ?></p>
  <p><strong>Usuario:</strong> <?= $venta['usuario'] ?></p>
  <p><strong>Fecha:</strong> <?= $venta['fecha'] ?></p>
  <p><strong>Estado:</strong> <?= $venta['estado'] ?></p>
</div>

<table>
  <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>
  <tbody>
  <?php while($d=$det->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($d['producto']) ?></td>
      <td><?= (int)$d['cantidad'] ?></td>
      <td>$<?= number_format($d['precio_unitario'],2) ?></td>
      <td>$<?= number_format($d['subtotal'],2) ?></td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>

<p class="card"><strong>TOTAL:</strong> $<?= number_format($venta['total'],2) ?></p>
</body></html>
