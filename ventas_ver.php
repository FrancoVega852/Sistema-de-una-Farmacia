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
$st->bind_param("i",$id);
$st->execute();
$venta = $st->get_result()->fetch_assoc();

$st = $db->prepare("SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre AS producto
                    FROM DetalleVenta d
                    INNER JOIN Producto p ON d.producto_id=p.id
                    WHERE d.venta_id=?");
$st->bind_param("i",$id);
$st->execute();
$res = $st->get_result();

$items = [];
$itemsCount = 0;
$unitsCount = 0;
while ($row = $res->fetch_assoc()) {
  $items[] = $row;
  $itemsCount++;
  $unitsCount += (int)$row['cantidad'];
}

// Desglose estimado (visual)
$subtotalEst = $venta['total'] > 0 ? round($venta['total'] / 1.21, 2) : 0;
$ivaEst      = $venta['total'] - $subtotalEst;

$clienteNom = $venta['cliente'] ? ($venta['cliente'].' '.$venta['apellido']) : 'Consumidor Final';
$estado = $venta['estado'] ?? 'Pendiente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle de Venta #<?= (int)$venta['id'] ?> - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
  --verde:#008f4c; --verde-oscuro:#006837; --acento:#e85c4a;
  --bg:#f3f6f4; --card:#ffffff; --text:#1f2937; --muted:#6b7280; --borde:#e5e7eb;
  --ok:#16a34a; --warn:#f59e0b; --danger:#dc2626;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:Segoe UI,system-ui,-apple-system,sans-serif;
  background:linear-gradient(180deg,#f4faf6 0%, #eef5f1 35%, #f7f8f9 100%);
  color:var(--text);
  animation:fadeIn .4s ease;
}
.container{max-width:1200px;margin:0 auto;padding:14px}

/* Topbar */
.topbar{display:flex;align-items:center;gap:12px;padding:10px 0}
.btn{border:1px solid var(--borde); background:#fff; color:#111; padding:9px 12px; border-radius:10px; cursor:pointer}
.btn i{margin-right:6px}
.btn.primary{background:var(--verde); border-color:var(--verde); color:#fff}
.btn.warn{background:var(--warn); border-color:var(--warn); color:#fff}
.btn.ghost{background:#fff}
.btn:hover{filter:brightness(.98)}
.title{
  display:flex; align-items:center; gap:10px; font-size:22px; color:#0f5132; margin-left:6px
}
.badge{
  display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px;
  font-size:12px; border:1px solid var(--borde); background:#fff; color:#111
}
.badge.ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.badge.warn{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
.badge.danger{background:#fef2f2;border-color:#fecaca;color:#991b1b}

/* Layout */
.grid{display:grid; grid-template-columns: 1.6fr .9fr; gap:14px}
@media (max-width:1024px){ .grid{grid-template-columns:1fr} }

.card{
  background:var(--card); border:1px solid var(--borde); border-radius:14px;
  box-shadow:0 10px 24px rgba(0,0,0,.06);
  overflow:hidden; animation:rise .35s ease
}
.card-header{display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px; border-bottom:1px solid var(--borde)}
.card-body{padding:16px}

/* Resumen */
.kpis{display:grid; grid-template-columns:repeat(4,1fr); gap:10px}
@media (max-width:700px){ .kpis{grid-template-columns:repeat(2,1fr)} }
.kpi{
  padding:14px; background:#f8fafc; border:1px dashed var(--borde); border-radius:12px
}
.kpi .label{color:var(--muted); font-size:12px}
.kpi .value{font-weight:700; font-size:18px; margin-top:4px}
.kpi .hint{font-size:11px; color:var(--muted)}

/* Info venta */
.info{display:grid; grid-template-columns:repeat(2,1fr); gap:10px}
@media (max-width:700px){ .info{grid-template-columns:1fr} }
.info .row{display:flex; gap:10px; align-items:center; background:#f9fafb; padding:12px; border-radius:10px; border:1px solid var(--borde)}
.info .lbl{font-weight:600; min-width:100px; color:#0f5132}
.info .val{color:#111}

/* Tabla items */
.table{width:100%; border-collapse:collapse}
.table th,.table td{padding:12px; border-bottom:1px solid var(--borde); text-align:left}
.table th{background:#eaf7ef; color:#064e3b; font-weight:700}
.table tfoot td{background:#f9fafb; font-weight:700}
.text-right{text-align:right}

/* Acciones */
.actions{display:flex; gap:8px; flex-wrap:wrap}

/* Print */
@media print{
  .topbar, .actions, .right-card{display:none !important}
  body{background:#fff}
  .card{box-shadow:none; border-color:#ddd}
  .container{max-width:100%; padding:0}
}

/* Animaciones */
@keyframes fadeIn{from{opacity:0} to{opacity:1}}
@keyframes rise{from{transform:translateY(6px); opacity:0} to{transform:none; opacity:1}}
</style>
</head>
<body>
<div class="container">

  <!-- HEADER -->
  <div class="topbar">
    <a href="ventas_listar.php" class="btn"><i class="fa-solid fa-arrow-left"></i>Volver</a>
    <div class="title"><i class="fa-solid fa-receipt" style="color:#006837"></i>
      <strong>Detalle de Venta #<?= (int)$venta['id'] ?></strong>
    </div>
    <span style="margin-left:auto"></span>
    <?php
      $cls = $estado==='Pagada' ? 'ok' : ($estado==='Pendiente' ? 'warn' : 'danger');
    ?>
    <span class="badge <?= $cls ?>"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($estado) ?></span>
  </div>

  <div class="grid">

    <!-- IZQUIERDA: INFO + ITEMS -->
    <section class="card">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-circle-info" style="color:#006837"></i>
          <strong>Datos de la venta</strong>
        </div>
        <div class="actions">
          <button class="btn ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
          <a class="btn" href="ventas.php"><i class="fa-solid fa-plus"></i> Nueva venta</a>
        </div>
      </div>
      <div class="card-body">

        <!-- KPIs -->
        <div class="kpis" style="margin-bottom:12px">
          <div class="kpi">
            <div class="label">Total venta</div>
            <div class="value">$<?= number_format((float)$venta['total'],2) ?></div>
            <div class="hint">Importe final</div>
          </div>
          <div class="kpi">
            <div class="label">Ítems</div>
            <div class="value"><?= (int)$itemsCount ?></div>
            <div class="hint">Productos diferentes</div>
          </div>
          <div class="kpi">
            <div class="label">Unidades</div>
            <div class="value"><?= (int)$unitsCount ?></div>
            <div class="hint">Cantidades totales</div>
          </div>
          <div class="kpi">
            <div class="label">Fecha</div>
            <div class="value"><?= htmlspecialchars($venta['fecha']) ?></div>
            <div class="hint">Usuario: <?= htmlspecialchars($venta['usuario']) ?></div>
          </div>
        </div>

        <!-- INFO -->
        <div class="info" style="margin-bottom:12px">
          <div class="row"><div class="lbl"><i class="fa-solid fa-user"></i> Cliente</div>
            <div class="val"><?= htmlspecialchars($clienteNom) ?></div></div>
          <div class="row"><div class="lbl"><i class="fa-solid fa-id-card-clip"></i> N° Venta</div>
            <div class="val">#<?= (int)$venta['id'] ?></div></div>
        </div>

        <!-- DESGLOSE (visual) -->
        <div class="info" style="margin-bottom:12px">
          <div class="row"><div class="lbl"><i class="fa-solid fa-money-bill-wave"></i> Subtotal (est.)</div>
            <div class="val">$<?= number_format($subtotalEst,2) ?></div></div>
          <div class="row"><div class="lbl"><i class="fa-solid fa-percent"></i> IVA 21% (est.)</div>
            <div class="val">$<?= number_format($ivaEst,2) ?></div></div>
        </div>

        <!-- ITEMS -->
        <div class="card" style="border:none; box-shadow:none">
          <div class="card-header" style="border-radius:12px 12px 0 0">
            <div style="display:flex;align-items:center;gap:8px"><i class="fa-solid fa-basket-shopping" style="color:#006837"></i><strong>Productos</strong></div>
          </div>
          <div class="card-body" style="padding:0">
            <table class="table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th class="text-right" style="width:120px">Cantidad</th>
                  <th class="text-right" style="width:140px">Precio</th>
                  <th class="text-right" style="width:150px">Subtotal</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($items as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['producto']) ?></td>
                  <td class="text-right"><?= (int)$d['cantidad'] ?></td>
                  <td class="text-right">$<?= number_format((float)$d['precio_unitario'],2) ?></td>
                  <td class="text-right">$<?= number_format((float)$d['subtotal'],2) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="3" class="text-right">TOTAL</td>
                  <td class="text-right">$<?= number_format((float)$venta['total'],2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>
    </section>

    <!-- DERECHA: ACCIONES RÁPIDAS / NOTAS -->
    <aside class="card right-card">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-toolbox" style="color:#006837"></i>
          <strong>Acciones rápidas</strong>
        </div>
      </div>
      <div class="card-body">
        <div class="actions" style="margin-bottom:10px">
          <button class="btn primary" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</button>
          <a class="btn" href="ventas.php"><i class="fa-solid fa-plus"></i> Nueva Venta</a>
          <a class="btn ghost" href="ventas_listar.php"><i class="fa-solid fa-list"></i> Listado</a>
        </div>

        <div class="kpi" style="margin-top:8px">
          <div class="label">Observación</div>
          <div class="hint">Si necesitás mostrar un código en el comprobante, podés imprimir esta vista en PDF. El desglose de IVA mostrado es sólo orientativo.</div>
        </div>
      </div>
    </aside>

  </div><!-- grid -->
</div><!-- container -->

</body>
</html>
