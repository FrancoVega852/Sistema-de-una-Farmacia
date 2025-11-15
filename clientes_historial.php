<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) { exit('Sesión expirada'); }

$conn = new Conexion();
$db   = $conn->conexion;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { exit('<p>Cliente inválido.</p>'); }

// Ventas del cliente (ajustá nombres de columnas si difieren)
$sql = "SELECT v.id, v.fecha, v.total, COALESCE(v.estado,'') estado
        FROM Venta v
        WHERE v.cliente_id = ?
        ORDER BY v.fecha DESC";
$st  = $db->prepare($sql);
$st->bind_param('i',$id);
$st->execute();
$res = $st->get_result();

if (!$res || $res->num_rows===0) {
  exit('<p>Sin registros de compras.</p>');
}
?>
<table class="table table-sm">
  <thead>
    <tr>
      <th>ID Venta</th>
      <th>Fecha</th>
      <th>Estado</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <?php while($r=$res->fetch_assoc()): ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['fecha']) ?></td>
        <td><?= htmlspecialchars($r['estado']) ?></td>
        <td>$<?= number_format((float)$r['total'],2,',','.') ?></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
