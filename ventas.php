<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'VentasController.php';

$conn = new Conexion();
$ctl  = new VentasController($conn->conexion);
$clientes  = $ctl->clientes();
$productos = $ctl->productos();
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><title>Nueva Venta - Farvec</title>
<link rel="stylesheet" href="estilos.css">
</head><body>
<a href="ventas_listar.php" class="btn-volver">⬅ Volver al Listado</a>
<h1>🛒 Nueva Venta</h1>

<form method="POST" action="ventas_guardar.php" class="card">
  <label>Cliente:</label>
  <select name="cliente_id">
    <option value="">Consumidor Final</option>
    <?php while($c=$clientes->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>"><?= $c['nombre'].' '.$c['apellido'] ?></option>
    <?php endwhile; ?>
  </select>

  <h2>Productos</h2>
  <?php while($p=$productos->fetch_assoc()): ?>
    <div class="fila-producto">
      <input type="checkbox" name="prod[<?= $p['id'] ?>][id]" value="<?= $p['id'] ?>">
      <?= htmlspecialchars($p['nombre']) ?> - $<?= number_format($p['precio'],2) ?>
      (Stock: <?= (int)$p['stock_actual'] ?>)
      <input type="number" name="prod[<?= $p['id'] ?>][cant]" min="1" placeholder="Cantidad">
    </div>
  <?php endwhile; ?>

  <button type="submit" class="btn-add">💾 Registrar Venta</button>
</form>
</body></html>
