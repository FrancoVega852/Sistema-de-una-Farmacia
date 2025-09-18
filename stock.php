<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'StockController.php';

$conn = new Conexion();
$ctl  = new StockController($conn->conexion);
$productos = $ctl->productos();
$rol = $ctl->rol();
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><title>Stock y Lotes - Farvec</title>
<link rel="stylesheet" href="estilos.css">
</head><body>
<a href="Menu.php" class="btn-volver">⬅ Volver al Menú</a>
<h1>📦 Gestión de Stock y Lotes</h1>

<?php if ($rol === 'Administrador'): ?>
  <a href="stock_agregar.php" class="btn-add">+ Agregar Producto/Lote</a>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th>
      <th>Stock Actual</th><th>Stock Mínimo</th><th>Lote</th>
      <th>Vencimiento</th><th>Cantidad Lote</th>
      <?php if ($rol === 'Administrador' || $rol === 'Farmaceutico'): ?><th>Acciones</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach($productos as $f): ?>
      <?php $alerta = $ctl->alertaVencimiento($f['fecha_vencimiento'] ?? null); ?>
      <tr>
        <td><?= htmlspecialchars($f['id']) ?></td>
        <td><?= htmlspecialchars($f['nombre']) ?></td>
        <td><?= htmlspecialchars($f['categoria'] ?? '-') ?></td>
        <td>$<?= number_format($f['precio'],2) ?></td>
        <td class="<?= ($f['stock_actual'] <= $f['stock_minimo']) ? 'alerta' : '' ?>">
          <?= (int)$f['stock_actual'] ?>
        </td>
        <td><?= (int)$f['stock_minimo'] ?></td>
        <td><?= htmlspecialchars($f['numero_lote'] ?? '-') ?></td>
        <td class="<?= $alerta ?>"><?= htmlspecialchars($f['fecha_vencimiento'] ?? '-') ?></td>
        <td><?= htmlspecialchars($f['cantidad_actual'] ?? '-') ?></td>
        <?php if ($rol === 'Administrador' || $rol === 'Farmaceutico'): ?>
          <td>
            <a class="btn btn-editar" href="Historial.php?producto_id=<?= $f['id'] ?>">Historial</a>
            <?php if ($rol === 'Administrador'): ?>
              <a class="btn btn-editar" href="stock_editar.php?id=<?= $f['id'] ?>">Editar</a>
              <a class="btn btn-eliminar" href="stock_eliminar.php?id=<?= $f['id'] ?>">Eliminar</a>
            <?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</body></html>
