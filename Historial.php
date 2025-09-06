<?php
session_start();
include 'Conexion.php';

// Verificar sesión
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$conn = new Conexion();
$conexion = $conn->conexion;

// Filtros
$filtroProducto = $_GET['producto_id'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

$sql = "SELECT h.id, h.tipo, h.cantidad, h.detalle, h.fecha, p.nombre AS producto
        FROM HistorialStock h
        JOIN Producto p ON h.producto_id = p.id
        WHERE 1=1";

if (!empty($filtroProducto)) {
    $sql .= " AND p.id = " . intval($filtroProducto);
}
if (!empty($filtroTipo)) {
    $sql .= " AND h.tipo = '" . $conexion->real_escape_string($filtroTipo) . "'";
}

$sql .= " ORDER BY h.fecha DESC";

$resultado = $conexion->query($sql);

// Traer lista de productos para el filtro
$productos = $conexion->query("SELECT id, nombre FROM Producto ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Movimientos - Farvec</title>
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
    }
    body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--gris); padding: 20px; }
    h1 { color: var(--verde-oscuro); margin-bottom: 20px; }
    table {
      width: 100%; border-collapse: collapse; background: var(--blanco);
      border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-top: 20px;
    }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: var(--verde); color: var(--blanco); }
    tr:hover { background: #f1f1f1; }
    .filtros {
      background: var(--blanco);
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    select, button {
      padding: 8px;
      border-radius: 6px;
      border: 1px solid #ccc;
      margin-right: 10px;
    }
    button {
      background: var(--acento);
      color: var(--blanco);
      border: none;
      cursor: pointer;
      font-weight: bold;
    }
    button:hover { background: #d94c3c; }
    .btn-menu {
      background: #006837;
      color: white;
      padding: 10px 15px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-bottom: 20px;
      display: inline-block;
    }
    .btn-menu:hover { background: #009f4c; transform: scale(1.05); }
  </style>
</head>
<body>
  <a href="Menu.php" class="btn-menu">⬅️ Volver al Menú</a>
  <h1>📊 Historial de Movimientos de Stock</h1>

  <form method="GET" class="filtros">
    <label for="producto_id">Producto:</label>
    <select name="producto_id" id="producto_id">
      <option value="">Todos</option>
      <?php while ($p = $productos->fetch_assoc()): ?>
        <option value="<?= $p['id'] ?>" <?= ($filtroProducto == $p['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['nombre']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label for="tipo">Tipo:</label>
    <select name="tipo" id="tipo">
      <option value="">Todos</option>
      <option value="Alta" <?= ($filtroTipo == 'Alta') ? 'selected' : '' ?>>Alta</option>
      <option value="Baja" <?= ($filtroTipo == 'Baja') ? 'selected' : '' ?>>Baja</option>
      <option value="Venta" <?= ($filtroTipo == 'Venta') ? 'selected' : '' ?>>Venta</option>
      <option value="Compra" <?= ($filtroTipo == 'Compra') ? 'selected' : '' ?>>Compra</option>
      <option value="Devolución" <?= ($filtroTipo == 'Devolución') ? 'selected' : '' ?>>Devolución</option>
      <option value="Vencimiento" <?= ($filtroTipo == 'Vencimiento') ? 'selected' : '' ?>>Vencimiento</option>
    </select>

    <button type="submit">Filtrar</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Producto</th>
        <th>Tipo</th>
        <th>Cantidad</th>
        <th>Detalle</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($resultado->num_rows > 0): ?>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
          <tr>
            <td><?= $fila['id'] ?></td>
            <td><?= htmlspecialchars($fila['producto']) ?></td>
            <td><?= htmlspecialchars($fila['tipo']) ?></td>
            <td><?= htmlspecialchars($fila['cantidad']) ?></td>
            <td><?= htmlspecialchars($fila['detalle'] ?? '-') ?></td>
            <td><?= htmlspecialchars($fila['fecha']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="6">No hay movimientos registrados</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
