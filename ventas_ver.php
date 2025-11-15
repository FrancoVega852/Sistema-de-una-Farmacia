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

$subtotalEst = $venta['total'] > 0 ? round($venta['total'] / 1.21, 2) : 0;
$ivaEst      = $venta['total'] - $subtotalEst;
$clienteNom  = $venta['cliente'] ? ($venta['cliente'].' '.$venta['apellido']) : 'Consumidor Final';
$estado      = $venta['estado'] ?? 'Pendiente';
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
  --azul:#2563eb;
  --azul-claro:#3b82f6;
  --borde:#d1d5db;
  --negro:#0a0a0a;
  --card:rgba(255,255,255,0.85);
  --verde:#22c55e;
  --rojo:#ef4444;
  --naranja:#fbbf24;
}

/* ===== Fondo ===== */
html,body{height:100%;min-height:100vh;}
body{
  margin:0;
  font-family:Segoe UI,system-ui,-apple-system,sans-serif;
  background:linear-gradient(180deg,#8dd3ff 0%,#63b3ed 40%,#2563eb 100%);
  color:var(--negro);
  position:relative;
  overflow-x:hidden;
}
.bg-pastillas{
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg width='160' height='160' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff20'%3E%3Cellipse cx='30' cy='30' rx='12' ry='5' transform='rotate(30 30 30)'/%3E%3Cellipse cx='90' cy='25' rx='10' ry='4' transform='rotate(-25 90 25)'/%3E%3Cellipse cx='60' cy='90' rx='8' ry='3.5' transform='rotate(40 60 90)'/%3E%3Crect x='70' y='60' width='16' height='6' rx='3' transform='rotate(45 70 60)'/%3E%3Ccircle cx='40' cy='110' r='5'/%3E%3Ccircle cx='115' cy='100' r='4'/%3E%3C/g%3E%3C/svg%3E");
  background-size:180px 180px;opacity:.6;
  animation:pillsMove 60s linear infinite alternate;
}
@keyframes pillsMove{0%{background-position:0 0}100%{background-position:200px 200px}}

.container{max-width:1200px;margin:0 auto;padding:20px;position:relative;z-index:2}

/* ===== Botones ===== */
.btn{
  border:none;
  border-radius:10px;
  padding:10px 14px;
  font-weight:600;
  cursor:pointer;
  color:#fff;
  text-decoration:none;
  box-shadow:0 3px 10px rgba(0,0,0,.25);
  transition:.2s;
}
.btn.primary{background:linear-gradient(90deg,#2563eb,#3b82f6);}
.btn.ghost{background:rgba(255,255,255,.35);color:#0a0a0a;}
.btn.warn{background:linear-gradient(90deg,#f59e0b,#fbbf24);}
.btn:hover{filter:brightness(1.05);transform:translateY(-1px);}

/* ===== Topbar ===== */
.topbar{
  display:flex;align-items:center;gap:12px;margin-bottom:14px;
}
.title{
  display:flex;align-items:center;gap:10px;
  font-size:22px;color:#fff;text-shadow:0 0 6px #60a5fa;
}
.badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:999px;font-size:12px;
  background:rgba(255,255,255,.3);color:#fff;border:1px solid #ffffff66;
}
.badge.ok{color:#16a34a;}
.badge.warn{color:#f59e0b;}
.badge.danger{color:#ef4444;}

/* ===== Cards ===== */
.grid{display:grid;grid-template-columns:1.6fr .9fr;gap:16px}
@media(max-width:1024px){.grid{grid-template-columns:1fr}}
.card{
  background:var(--card);
  border:1px solid var(--borde);
  border-radius:16px;
  box-shadow:0 8px 25px rgba(0,0,0,.25);
  backdrop-filter:blur(14px);
  overflow:hidden;
}
.card-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 16px;
  border-bottom:1px solid var(--borde);
  background:rgba(255,255,255,.55);
  color:var(--negro);
}
.card-body{padding:18px;color:var(--negro)}

/* ===== KPIs ===== */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px}
@media(max-width:700px){.kpis{grid-template-columns:repeat(2,1fr)}}
.kpi{
  padding:14px;
  border-radius:12px;
  background:rgba(255,255,255,.9);
  border:1px solid rgba(0,0,0,.1);
  color:var(--negro);
}
.kpi .label{font-size:13px;font-weight:600;color:#111;}
.kpi .value{font-size:20px;font-weight:700;color:#000;}
.kpi .hint{font-size:12px;color:#333;}

/* ===== Info ===== */
.info{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:12px}
@media(max-width:700px){.info{grid-template-columns:1fr}}
.info .row{
  display:flex;gap:10px;align-items:center;
  background:rgba(255,255,255,.95);
  padding:12px;border-radius:10px;border:1px solid #d1d5db;
  color:var(--negro);
}
.info .lbl{font-weight:700;color:#111;}
.info .val{color:#000;}

/* ===== Tabla ===== */
.table{width:100%;border-collapse:collapse;color:#111;}
.table th,.table td{padding:12px;border-bottom:1px solid rgba(0,0,0,.1);text-align:left}
.table th{
  background:#2563eb;
  color:#fff;
  font-weight:700;
}
.table tr:hover td{background:rgba(37,99,235,.08);}
.table tfoot td{background:rgba(255,255,255,.8);font-weight:700;color:#000;}
.text-right{text-align:right}
</style>
</head>
<body>
<div class="bg-pastillas"></div>

<div class="container">

  <div class="topbar">
    <a href="ventas_listar.php" class="btn ghost"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <div class="title"><i class="fa-solid fa-receipt"></i><strong>Detalle de Venta #<?= (int)$venta['id'] ?></strong></div>
    <span style="margin-left:auto"></span>
    <?php $cls = $estado==='Pagada' ? 'ok' : ($estado==='Pendiente' ? 'warn' : 'danger'); ?>
    <span class="badge <?= $cls ?>"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($estado) ?></span>
  </div>

  <div class="grid">

    <!-- IZQUIERDA -->
    <section class="card">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-circle-info"></i>
          <strong>Datos de la venta</strong>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> PDF</button>
          <a class="btn primary" href="ventas.php"><i class="fa-solid fa-plus"></i> Nueva venta</a>
        </div>
      </div>
      <div class="card-body">

        <div class="kpis">
          <div class="kpi"><div class="label">Total</div><div class="value">$<?= number_format((float)$venta['total'],2) ?></div><div class="hint">Importe final</div></div>
          <div class="kpi"><div class="label">Ítems</div><div class="value"><?= (int)$itemsCount ?></div><div class="hint">Productos diferentes</div></div>
          <div class="kpi"><div class="label">Unidades</div><div class="value"><?= (int)$unitsCount ?></div><div class="hint">Cantidades totales</div></div>
          <div class="kpi"><div class="label">Fecha</div><div class="value"><?= htmlspecialchars($venta['fecha']) ?></div><div class="hint">Usuario: <?= htmlspecialchars($venta['usuario']) ?></div></div>
        </div>

        <div class="info">
          <div class="row"><div class="lbl"><i class="fa-solid fa-user"></i> Cliente</div><div class="val"><?= htmlspecialchars($clienteNom) ?></div></div>
          <div class="row"><div class="lbl"><i class="fa-solid fa-id-card-clip"></i> N° Venta</div><div class="val">#<?= (int)$venta['id'] ?></div></div>
        </div>

        <div class="info">
          <div class="row"><div class="lbl"><i class="fa-solid fa-money-bill-wave"></i> Subtotal</div><div class="val">$<?= number_format($subtotalEst,2) ?></div></div>
          <div class="row"><div class="lbl"><i class="fa-solid fa-percent"></i> IVA 21%</div><div class="val">$<?= number_format($ivaEst,2) ?></div></div>
        </div>

        <div class="card" style="border:none;box-shadow:none;margin-top:10px;background:rgba(255,255,255,.9)">
          <div class="card-header" style="background:#2563eb;color:#fff"><i class="fa-solid fa-basket-shopping"></i><strong>Productos</strong></div>
          <div class="card-body" style="padding:0">
            <table class="table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th class="text-right">Cantidad</th>
                  <th class="text-right">Precio</th>
                  <th class="text-right">Subtotal</th>
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

    <!-- DERECHA -->
    <aside class="card">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:8px">
          <i class="fa-solid fa-toolbox"></i><strong>Acciones rápidas</strong>
        </div>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:12px">
          <button class="btn primary" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</button>
          <a class="btn ghost" href="ventas.php"><i class="fa-solid fa-plus"></i> Nueva Venta</a>
          <a class="btn ghost" href="ventas_listar.php"><i class="fa-solid fa-list"></i> Listado</a>
        </div>
        <div class="kpi">
          <div class="label">Observación</div>
          <div class="hint">El desglose de IVA mostrado es orientativo. Podés imprimir esta vista como comprobante PDF.</div>
        </div>
      </div>
    </aside>

  </div>
</div>
</body>
</html>
