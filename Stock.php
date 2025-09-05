<?php
session_start();
include 'Conexion.php';
include 'Producto.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$rol = $_SESSION["usuario_rol"];
$conn = new Conexion();
$conexion = $conn->conexion;

$productoObj = new Producto($conexion);
$productos = $productoObj->obtenerProductosConLotes();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Stock y Lotes - Farvec</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
    }
    body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--gris); padding: 20px; }
    h1 { color: var(--verde-oscuro); }
    .btn {
      display: inline-block; padding: 10px 15px; border-radius: 6px;
      text-decoration: none; font-weight: bold; transition: 0.3s;
    }
    .btn:hover { transform: scale(1.05); }
    .btn-add { background: var(--acento); color: var(--blanco); margin-bottom: 20px; }
    .btn-add:hover { background: #d94c3c; }
    table {
      width: 100%; border-collapse: collapse; background: var(--blanco);
      border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: var(--verde); color: var(--blanco); }
    tr:hover { background: #f1f1f1; }
    .alerta { color: var(--acento); font-weight: bold; }
  </style>
</head>
<body>
  <h1>📦 Gestión de Stock y Lotes</h1>

  <?php if ($rol === 'Administrador'): ?>
    <a href="stock_agregar.php" class="btn btn-add"><i class="fa-solid fa-plus"></i> Agregar Producto/Lote</a>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Producto</th>
        <th>Categoría</th>
        <th>Precio</th>
        <th>Stock Actual</th>
        <th>Stock Mínimo</th>
        <th>Lote</th>
        <th>Vencimiento</th>
        <th>Cantidad Lote</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $productos->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($fila['id']) ?></td>
          <td><?= htmlspecialchars($fila['nombre']) ?></td>
          <td><?= htmlspecialchars($fila['categoria'] ?? '-') ?></td>
          <td>$<?= number_format($fila['precio'], 2) ?></td>
          <td class="<?= ($fila['stock_actual'] <= $fila['stock_minimo']) ? 'alerta' : '' ?>">
            <?= htmlspecialchars($fila['stock_actual']) ?>
          </td>
          <td><?= htmlspecialchars($fila['stock_minimo']) ?></td>
          <td><?= htmlspecialchars($fila['numero_lote'] ?? '-') ?></td>
          <td><?= htmlspecialchars($fila['fecha_vencimiento'] ?? '-') ?></td>
          <td><?= htmlspecialchars($fila['cantidad_actual'] ?? '-') ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
