<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'VentasController.php';

$conn = new Conexion();
$ctl  = new VentasController($conn->conexion);
$ventas = $ctl->listar();
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><title>Listado de Ventas - Farvec</title>
<link rel="stylesheet" href="estilos.css">
</head><body>
<a href="menu.php" class="btn-volver">⬅ Volver al Menú</a>
<h1>🧾 Listado de Ventas</h1>
<a href="ventas.php" class="btn-add">+ Registrar Venta</a>

<table>
  <thead><tr>
    <th>ID</th><th>Cliente</th><th>Usuario</th>
    <th>Total</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
  </tr></thead>
  <tbody>
  <?php if ($ventas->num_rows): while($v=$ventas->fetch_assoc()): ?>
    <tr>
      <td><?= $v['id'] ?></td>
      <td><?= $v['cliente'] ?? 'Consumidor Final' ?></td>
      <td><?= $v['usuario'] ?></td>
      <td>$<?= number_format($v['total'],2) ?></td>
      <td><?= $v['fecha'] ?></td>
      <td><?= $v['estado'] ?></td>
      <td><a class="btn btn-editar" href="ventas_ver.php?id=<?= $v['id'] ?>">Ver</a></td>
    </tr>
  <?php endwhile; else: ?>
    <tr><td colspan="7">No hay ventas registradas.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</body></html>
