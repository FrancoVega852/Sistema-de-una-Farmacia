<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$db = $conn->conexion;

// --- Reporte de Ventas (últimos 15 días) ---
$ventas = $db->query("
SELECT DATE(fecha) AS dia, SUM(total) AS total, COUNT(*) AS tickets
FROM Venta
WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
GROUP BY DATE(fecha)
ORDER BY dia ASC
")->fetch_all(MYSQLI_ASSOC);

// --- Top 10 Productos ---
$top = $db->query("
SELECT p.nombre, c.nombre AS categoria, SUM(dv.cantidad) AS unidades, SUM(dv.subtotal) AS total
FROM DetalleVenta dv
JOIN Producto p ON p.id = dv.producto_id
LEFT JOIN Categoria c ON c.id = p.categoria_id
GROUP BY p.id
ORDER BY unidades DESC
LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// --- Stock Bajo ---
$stockBajo = $db->query("
SELECT id, nombre, stock_actual, stock_minimo
FROM Producto
WHERE stock_actual <= stock_minimo
ORDER BY stock_actual ASC
LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// --- Lotes por vencer (90 días) ---
$vencimientos = $db->query("
SELECT p.nombre, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
FROM Lote l
JOIN Producto p ON p.id=l.producto_id
WHERE l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
ORDER BY l.fecha_vencimiento ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-report">
  <div class="header-actions">
    <button type="button" class="btn-verde" onclick="location.href='Menu.php'">
      <i class="fa-solid fa-arrow-left"></i> Volver al Menú
    </button>
    <h2 class="fw-bold"><i class="fa-solid fa-chart-line"></i> Reportes Generales</h2>
  </div>

  <!-- TARJETAS RESUMEN -->
  <div class="resumen">
    <div class="card-resumen"><i class="fa-solid fa-sack-dollar"></i>
      <div><p>Total Vendido (15 días)</p>
      <h4>$<?= number_format(array_sum(array_column($ventas,'total')),2,',','.') ?></h4></div>
    </div>
    <div class="card-resumen"><i class="fa-solid fa-receipt"></i>
      <div><p>Tickets Emitidos</p>
      <h4><?= array_sum(array_column($ventas,'tickets')) ?></h4></div>
    </div>
    <div class="card-resumen"><i class="fa-solid fa-boxes-stacked"></i>
      <div><p>Productos con Stock Bajo</p>
      <h4><?= count($stockBajo) ?></h4></div>
    </div>
    <div class="card-resumen"><i class="fa-solid fa-hourglass-half"></i>
      <div><p>Lotes por Vencer</p>
      <h4><?= count($vencimientos) ?></h4></div>
    </div>
  </div>

  <!-- 📈 Ventas -->
  <section>
    <h4 class="titulo-seccion"><i class="fa-solid fa-chart-column"></i> Ventas de los últimos 15 días</h4>
    <canvas id="ventasChart" height="100"></canvas>
  </section>

  <!-- 🏆 Top Productos -->
  <section>
    <h4 class="titulo-seccion"><i class="fa-solid fa-pills"></i> Top 10 Productos Más Vendidos</h4>
    <canvas id="topChart" height="120"></canvas>
  </section>

  <!-- ⚠️ Alertas -->
  <section>
    <h4 class="titulo-seccion"><i class="fa-solid fa-triangle-exclamation"></i> Alertas de Stock y Vencimientos</h4>
    <div class="row">
      <div class="col-md-6">
        <h6 class="text-danger"><i class="fa-solid fa-box"></i> Stock Bajo</h6>
        <table class="table table-sm table-hover align-middle shadow-sm rounded">
          <thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th></tr></thead>
          <tbody>
            <?php if(!$stockBajo): ?><tr><td colspan="3">Sin alertas 👌</td></tr><?php endif; ?>
            <?php foreach($stockBajo as $p): ?>
            <tr><td><?= $p['nombre'] ?></td><td><?= $p['stock_actual'] ?></td><td><?= $p['stock_minimo'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="col-md-6">
        <h6 class="text-warning"><i class="fa-solid fa-hourglass-half"></i> Próximos a Vencer</h6>
        <table class="table table-sm table-hover align-middle shadow-sm rounded">
          <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th>Cant.</th></tr></thead>
          <tbody>
            <?php if(!$vencimientos): ?><tr><td colspan="4">Sin vencimientos próximos 👌</td></tr><?php endif; ?>
            <?php foreach($vencimientos as $l): ?>
            <tr><td><?= $l['nombre'] ?></td><td><?= $l['numero_lote'] ?></td><td><?= $l['fecha_vencimiento'] ?></td><td><?= $l['cantidad_actual'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <div class="text-center mt-4 text-muted">
    FARVEC • Reportes del Sistema · <?= date('Y') ?>
  </div>
</div>

<!-- 🖨️ Botón imprimir flotante -->
<button class="btn-float-print" onclick="window.print()"><i class="fa-solid fa-print"></i></button>

<style>
:root{
  --verde:#00a86b;
  --verde-osc:#00794f;
  --verde-claro:#b6f3da;
  --texto:#0a0a0a;
}
html,body{
  height:100%;margin:0;
  font-family:"Segoe UI",system-ui;
  background:linear-gradient(180deg,#eafff3,#d9fbe8,#bdf5d8);
  background-size:400% 400%;
  animation:flowBg 15s ease-in-out infinite;
}
@keyframes flowBg{
  0%{background-position:0% 50%;}
  50%{background-position:100% 50%;}
  100%{background-position:0% 50%;}
}

.container-report{
  position:relative;
  z-index:1;
  max-width:1250px;
  margin:50px auto;
  padding:36px;
  background:rgba(255,255,255,0.9);
  border-radius:24px;
  box-shadow:0 15px 40px rgba(0,0,0,.1);
  animation:slideIn .6s cubic-bezier(.25,.46,.45,.94);
}
@keyframes slideIn{
  0%{opacity:0;transform:translateX(-30px);}
  100%{opacity:1;transform:translateX(0);}
}

.header-actions{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;position:relative;
}
.header-actions h2{
  position:absolute;left:50%;transform:translateX(-50%);
  margin:0;color:var(--verde-osc);
  text-shadow:0 0 5px #6fe0b6;
}
.btn-verde{
  background:linear-gradient(90deg,var(--verde-osc),var(--verde));
  color:#fff;border:none;padding:10px 18px;border-radius:10px;font-weight:600;
  box-shadow:0 6px 14px rgba(0,121,79,.25);
  transition:all .25s ease;
}
.btn-verde:hover{transform:translateY(-2px);filter:brightness(1.05);}

/* === TARJETAS === */
.resumen{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
  gap:16px;margin-bottom:30px;
}
.card-resumen{
  background:linear-gradient(180deg,#ffffff,#e4fff2);
  border:1px solid #cceedd;
  border-radius:18px;
  padding:18px;
  display:flex;align-items:center;gap:14px;
  box-shadow:0 6px 20px rgba(0,0,0,.08);
  transition:all .3s ease;
}
.card-resumen:hover{
  transform:translateY(-5px);
  box-shadow:0 12px 30px rgba(0,0,0,.1);
}
.card-resumen i{
  font-size:30px;
  background:linear-gradient(135deg,var(--verde-osc),var(--verde));
  color:white;
  padding:14px;
  border-radius:12px;
  box-shadow:0 5px 15px rgba(0,121,79,.3);
}
.card-resumen p{margin:0;font-size:13px;color:#374151;}
.card-resumen h4{margin:0;font-weight:800;color:var(--texto);}

/* === SECCIONES === */
.titulo-seccion{
  color:var(--verde-osc);
  margin-bottom:1rem;
  font-weight:700;
}
.table th,.table td{text-align:center;vertical-align:middle;color:var(--texto);}
.table-hover tbody tr:hover{background:#e8fff1;}
.table thead{background:var(--verde-claro);color:#064e3b;}
section{margin-bottom:35px;animation:fadeIn .6s ease both;}
@keyframes fadeIn{from{opacity:0;transform:translateY(15px);}to{opacity:1;transform:none;}}

/* === BOTÓN IMPRIMIR === */
.btn-float-print{
  position:fixed;
  bottom:25px;right:30px;
  background:var(--verde);
  color:white;border:none;
  border-radius:50%;
  width:55px;height:55px;
  box-shadow:0 10px 25px rgba(0,121,79,.35);
  cursor:pointer;
  transition:.3s;
  z-index:5;
}
.btn-float-print:hover{transform:scale(1.1);background:var(--verde-osc);}
.btn-float-print i{font-size:22px;}

:root{
  --verde:#00a86b;
  --verde-osc:#00794f;
  --verde-claro:#b6f3da;
  --texto:#0a0a0a;
}

/* Fondo general neutro para integrarse al dashboard */
html,body{
  height:100%;
  margin:0;
  font-family:"Segoe UI",system-ui;
  background:#f8faf9; /* elimina el recuadro verde visible */
}

/* Contenedor con fondo animado interno */
.container-report{
  position:relative;
  z-index:1;
  max-width:1250px;
  margin:50px auto;
  padding:36px;
  background:linear-gradient(180deg,#ffffff 0%,#f2fff7 100%);
  border-radius:24px;
  box-shadow:0 10px 25px rgba(0,0,0,.08);
  animation:slideIn .6s cubic-bezier(.25,.46,.45,.94);
  overflow:hidden;
}
.container-report::before{
  content:"";
  position:absolute;
  inset:0;
  background:radial-gradient(circle at top right,rgba(0,168,107,0.08),transparent 70%),
             radial-gradient(circle at bottom left,rgba(0,121,79,0.06),transparent 70%);
  z-index:0;
}
.container-report > *{position:relative;z-index:1;}

@keyframes slideIn{
  0%{opacity:0;transform:translateX(-30px);}
  100%{opacity:1;transform:translateX(0);}
}

/* Header y botones */
.header-actions{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:25px;
  position:relative;
}
.header-actions h2{
  position:absolute;
  left:50%;
  transform:translateX(-50%);
  margin:0;
  color:var(--verde-osc);
  text-shadow:0 0 5px #6fe0b6;
}
.btn-verde{
  background:linear-gradient(90deg,var(--verde-osc),var(--verde));
  color:#fff;
  border:none;
  padding:10px 18px;
  border-radius:10px;
  font-weight:600;
  box-shadow:0 6px 14px rgba(0,121,79,.25);
  transition:all .25s ease;
}
.btn-verde:hover{transform:translateY(-2px);filter:brightness(1.05);}

/* Tarjetas KPI */
.resumen{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
  gap:16px;
  margin-bottom:30px;
}
.card-resumen{
  background:linear-gradient(180deg,#ffffff,#e9fff1);
  border:1px solid #cceedd;
  border-radius:18px;
  padding:18px;
  display:flex;
  align-items:center;
  gap:14px;
  box-shadow:0 6px 20px rgba(0,0,0,.08);
  transition:all .3s ease;
}
.card-resumen:hover{
  transform:translateY(-5px);
  box-shadow:0 12px 30px rgba(0,0,0,.1);
}
.card-resumen i{
  font-size:30px;
  background:linear-gradient(135deg,var(--verde-osc),var(--verde));
  color:white;
  padding:14px;
  border-radius:12px;
  box-shadow:0 5px 15px rgba(0,121,79,.3);
}
.card-resumen p{margin:0;font-size:13px;color:#374151;}
.card-resumen h4{margin:0;font-weight:800;color:var(--texto);}

/* Tablas */
.table th,.table td{text-align:center;vertical-align:middle;color:var(--texto);}
.table-hover tbody tr:hover{background:#e8fff1;}
.table thead{background:var(--verde-claro);color:#064e3b;}

/* Botón imprimir */
.btn-float-print{
  position:fixed;
  bottom:25px;right:30px;
  background:var(--verde);
  color:white;border:none;
  border-radius:50%;
  width:55px;height:55px;
  box-shadow:0 10px 25px rgba(0,121,79,.35);
  cursor:pointer;
  transition:.3s;
  z-index:5;
}
.btn-float-print:hover{transform:scale(1.1);background:var(--verde-osc);}
.btn-float-print i{font-size:22px;}

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<script>
const ventasData = {
  labels: <?= json_encode(array_column($ventas,'dia')) ?>,
  datasets: [{
    label: 'Ventas ($)',
    data: <?= json_encode(array_column($ventas,'total')) ?>,
    backgroundColor: 'rgba(0,168,107,0.3)',
    borderColor: '#00794f',
    borderWidth: 2,
    tension: 0.3,
    fill: true
  }]
};
new Chart(document.getElementById('ventasChart'), {
  type:'line',
  data:ventasData,
  options:{scales:{y:{beginAtZero:true}},
  plugins:{legend:{labels:{color:'#0a0a0a'}}}}
});

const topData = {
  labels: <?= json_encode(array_column($top,'nombre')) ?>,
  datasets: [{
    label: 'Unidades Vendidas',
    data: <?= json_encode(array_column($top,'unidades')) ?>,
    backgroundColor: 'rgba(0,121,79,0.6)',
    borderColor: '#00794f',
    borderWidth: 1
  }]
};
new Chart(document.getElementById('topChart'), {
  type:'bar',
  data:topData,
  options:{
    indexAxis:'y',
    scales:{
      x:{beginAtZero:true,ticks:{color:'#0a0a0a'}},
      y:{ticks:{color:'#0a0a0a'}}
    },
    plugins:{legend:{labels:{color:'#0a0a0a'}}}
  }
});
</script>
