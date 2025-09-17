<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

class Historial {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function obtenerMovimientos($producto_id = null, $tipo = null) {
        $sql = "SELECT h.id, h.tipo, h.cantidad, h.detalle, h.fecha, p.nombre AS producto
                FROM HistorialStock h
                JOIN Producto p ON h.producto_id = p.id
                WHERE 1=1";

        if (!empty($producto_id)) {
            $sql .= " AND p.id = " . intval($producto_id);
        }
        if (!empty($tipo)) {
            $sql .= " AND h.tipo = '" . $this->conn->real_escape_string($tipo) . "'";
        }

        $sql .= " ORDER BY h.fecha DESC";
        return $this->conn->query($sql);
    }

    public function obtenerProductos() {
        return $this->conn->query("SELECT id, nombre FROM Producto ORDER BY nombre");
    }
}

// ✅ Inicializar
$conn      = new Conexion();
$historial = new Historial($conn->conexion);

// Filtros
$filtroProducto = $_GET['producto_id'] ?? '';
$filtroTipo     = $_GET['tipo'] ?? '';

$resultado = $historial->obtenerMovimientos($filtroProducto, $filtroTipo);
$productos = $historial->obtenerProductos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Movimientos - Farvec</title>
  <link rel="stylesheet" href="estilos.css">
  <style>
    table {
      width: 100%; border-collapse: collapse; background: #fff;
      border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #008f4c; color: #fff; }
    tr:hover { background: #f1f1f1; }
    .filtros {
      background: #fff; padding: 15px; margin-bottom: 20px;
      border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .btn-volver {
      background: #006837; color: #fff; padding: 10px 15px;
      border-radius: 6px; text-decoration: none; font-weight: bold;
      margin-bottom: 20px; display: inline-block;
    }
    .btn-volver:hover { background: #009f4c; }
  </style>
</head>
<body>
  <a href="Menu.php" class="btn-volver">⬅ Volver al Menú</a>
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
        <tr><td colspan="6">No hay movimientos registrados</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
