<?php
require_once 'Conexion.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "<h2>Error de sesión</h2>";
    return;
}

$conn = new Conexion();
$db   = $conn->conexion;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>cargarModulo('Compra_Cliente_listar.php');</script>";
    return;
}

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

if (!$venta) {
    echo "<script>cargarModulo('Compra_Cliente_listar.php');</script>";
    return;
}

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

<!-- ENCABEZADO + BOTONES -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;gap:10px;flex-wrap:wrap;">

  <div style="display:flex;gap:10px;align-items:center;">
    <button class="btn-module btn-back"
            data-href="Compra_Cliente_listar.php"
            style="background:#00794f;color:#fff;padding:8px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:600;">
      <i class="fa-solid fa-arrow-left"></i> Volver
    </button>

    <h1 style="margin:0;font-size:22px;font-weight:700;color:#0b1320;">
      Compra #<?= $venta['id'] ?>
    </h1>
  </div>

  <div style="display:flex;gap:10px;">

    <button onclick="window.open('Compra_Cliente_imprimir.php?id=<?= $venta['id'] ?>','_blank')"
            style="background:#1662c2;color:#fff;padding:8px 14px;border-radius:10px;border:0;font-weight:600;cursor:pointer;">
      <i class="fa-solid fa-print"></i> Imprimir
    </button>

    <button class="btn-module"
            data-href="Compra_Cliente_listar.php"
            style="background:#6b7280;color:#fff;padding:8px 14px;border-radius:10px;border:0;font-weight:600;cursor:pointer;">
      <i class="fa-solid fa-list"></i> Mis compras
    </button>

  </div>

</div>

<!-- INFORMACIÓN GENERAL -->
<div style="background:#fff;border-radius:16px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.12);margin-bottom:18px;">
  <h2 style="margin-top:0;font-size:18px;">Información general</h2>

  <p><strong>Fecha:</strong> <?= date("d/m/Y H:i", strtotime($venta['fecha'])) ?></p>
  <p><strong>Usuario:</strong> <?= htmlspecialchars($venta['usuario']) ?></p>

  <p><strong>Cliente:</strong>
    <?= $venta['cli_nombre']
          ? htmlspecialchars($venta['cli_nombre'].' '.$venta['cli_apellido'])
          : 'Consumidor Final'; ?>
    <?php if ($venta['nroDocumento']): ?>
      (<?= htmlspecialchars($venta['nroDocumento']) ?>)
    <?php endif; ?>
  </p>

  <p><strong>Total:</strong>
    $<?= number_format($venta['total'],2,',','.') ?>
  </p>

  <p>
    <strong>Estado:</strong>
    <?php if($venta['estado']==='Pagada'): ?>
      <span style="background:#d1fae5;color:#047857;padding:4px 10px;border-radius:20px;font-weight:600;">
        Pagada
      </span>
    <?php else: ?>
      <span style="background:#fee2e2;color:#b91c1c;padding:4px 10px;border-radius:20px;font-weight:600;">
        Pendiente
      </span>
    <?php endif; ?>
  </p>
</div>

<!-- DETALLE -->
<div style="background:#fff;border-radius:16px;padding:18px;box-shadow:0 8px 20px rgba(0,0,0,.12);">

  <h2 style="margin-top:0;font-size:18px;">Detalle de productos</h2>

  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:#f3f4f6;">
        <th style="padding:8px 10px;text-align:left;">Producto</th>
        <th style="padding:8px 10px;text-align:right;">Cant.</th>
        <th style="padding:8px 10px;text-align:right;">Precio</th>
        <th style="padding:8px 10px;text-align:right;">Subtotal</th>
      </tr>
    </thead>
    <tbody>

    <?php while($d = $detalle->fetch_assoc()): ?>
      <tr>
        <td style="padding:8px 10px;"><?= htmlspecialchars($d['producto']) ?></td>
        <td style="padding:8px 10px;text-align:right;"><?= (int)$d['cantidad'] ?></td>
        <td style="padding:8px 10px;text-align:right;">
          $<?= number_format($d['precio_unitario'],2,',','.') ?>
        </td>
        <td style="padding:8px 10px;text-align:right;">
          $<?= number_format($d['subtotal'],2,',','.') ?>
        </td>
      </tr>
    <?php endwhile; ?>

    </tbody>
  </table>

</div>

<script>
document.querySelectorAll(".btn-module").forEach(btn => {
  btn.onclick = () => cargarModulo(btn.dataset.href);
});
</script>
