<?php
session_start();
require_once 'Conexion.php';

final class ControladorDashboard {
    private mysqli $db;
    public function __construct(mysqli $db) {
        $this->db = $db;
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: login.php'); exit();
        }
    }

    public function usuario(): array {
        $stmt = $this->db->prepare("SELECT id,nombre,email,rol FROM Usuario WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    /** KPIs principales */
    public function kpis(): array {
        $kpi = [
            'ventas_hoy' => 0.0,
            'tickets_hoy' => 0,
            'ventas_mes' => 0.0,
            'productos' => 0,
            'clientes' => 0
        ];

        $r = $this->db->query("SELECT IFNULL(SUM(total),0) m, COUNT(*) c
                               FROM Venta WHERE DATE(fecha)=CURDATE()");
        if ($r && $row = $r->fetch_assoc()) { $kpi['ventas_hoy'] = (float)$row['m']; $kpi['tickets_hoy'] = (int)$row['c']; }

        $r = $this->db->query("SELECT IFNULL(SUM(total),0) m
                               FROM Venta
                               WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())");
        if ($r && $row = $r->fetch_assoc()) { $kpi['ventas_mes'] = (float)$row['m']; }

        $r = $this->db->query("SELECT COUNT(*) c FROM Producto");
        if ($r && $row = $r->fetch_assoc()) { $kpi['productos'] = (int)$row['c']; }

        $r = $this->db->query("SELECT COUNT(*) c FROM Cliente");
        if ($r && $row = $r->fetch_assoc()) { $kpi['clientes'] = (int)$row['c']; }

        return $kpi;
    }

    /** Alertas: productos en stock mínimo */
    public function alertasStockMinimo(int $limit=6): array {
        $sql = "SELECT id,nombre,stock_actual,stock_minimo
                FROM Producto
                WHERE stock_actual <= stock_minimo
                ORDER BY (stock_actual - stock_minimo) ASC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    /** Alertas: lotes por vencer en X días (default 45) */
    public function alertasPorVencer(int $days=45, int $limit=6): array {
        $sql = "SELECT p.nombre, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
                FROM Lote l
                JOIN Producto p ON p.id=l.producto_id
                WHERE l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY l.fecha_vencimiento ASC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $days, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    /** Serie de ventas últimos 7 días */
    public function serieVentas7d(): array {
        $sql = "SELECT DATE(fecha) d, ROUND(SUM(total),2) t
                FROM Venta
                WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(fecha)
                ORDER BY d";
        $r = $this->db->query($sql);
        $dias = []; $totales = [];
        $map = [];
        if ($r) { while($row=$r->fetch_assoc()){ $map[$row['d']] = (float)$row['t']; } }
        for ($i=6; $i>=0; $i--) {
            $d = (new DateTime())->modify("-$i day")->format('Y-m-d');
            $dias[] = $d;
            $totales[] = $map[$d] ?? 0;
        }
        return ['labels'=>$dias,'data'=>$totales];
    }

    /** Stock por categoría (para gráfico de dona) */
    public function stockPorCategoria(): array {
        $sql = "SELECT IFNULL(c.nombre,'Sin categoría') categoria, SUM(p.stock_actual) total
                FROM Producto p
                LEFT JOIN Categoria c ON c.id=p.categoria_id
                GROUP BY categoria
                ORDER BY total DESC";
        $r = $this->db->query($sql);
        $labels=[]; $data=[];
        if($r){ while($row=$r->fetch_assoc()){ $labels[]=$row['categoria']; $data[]=(int)$row['total']; } }
        return ['labels'=>$labels,'data'=>$data];
    }
}

$conn = new Conexion();
$ctl  = new ControladorDashboard($conn->conexion);
$user = $ctl->usuario();
$kpis = $ctl->kpis();
$low  = $ctl->alertasStockMinimo();
$exp  = $ctl->alertasPorVencer();
$ventas7d = $ctl->serieVentas7d();
$stockCat = $ctl->stockPorCategoria();

$alertCount = count($low) + count($exp);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>FARVEC • Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<style>
:root{
  /* 🎨 Paleta TURQUESA para farmacéutico */
  --verde:#00bcd4; --verde-osc:#0097a7;
  --gris:#f4f4f4; --blanco:#fff; --acento:#e85c4a; --tinta:#1f2937;
}

/* RESET + Altura total */
*{box-sizing:border-box}
html,body{height:100%;min-height:100vh;}
/* Fondo base que CUBRE todo - 💧 turquesa */
html{
  background: linear-gradient(150deg,#e3faff,#ffffff,#e3fdff);
  background-attachment: fixed;
}
body{
  margin:0;font-family:"Segoe UI",system-ui,-apple-system,Arial,sans-serif;
  background: transparent;color:var(--tinta);
  min-height:100vh;position:relative;overflow-x:hidden;
}

/* 💊 Capa de PASTILLAS que cubre TODO (detrás) - turquesa */
body::before{
  content:""; position:fixed; inset:0; z-index:-1;
  background-image:
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'%3E%3Cg fill='%2300bcd41a'%3E%3Cellipse cx='36' cy='40' rx='14' ry='5' transform='rotate(20 36 40)'/%3E%3Cellipse cx='170' cy='160' rx='12' ry='4' transform='rotate(-25 170 160)'/%3E%3Cellipse cx='110' cy='90' rx='10' ry='3.5' transform='rotate(12 110 90)'/%3E%3Ccircle cx='70' cy='120' r='3'/%3E%3C/g%3E%3C/svg%3E");
  background-size: 220px 220px;
  background-repeat: repeat;
  animation: pillsDrift 60s linear infinite;
  opacity:.95;
}
@keyframes pillsDrift { from{background-position:0 0;} to{background-position: 600px 480px;} }

/* 🌈 Topbar con gradiente animado - turquesa */
.topbar{
  position:sticky;top:0;z-index:20;
  background: linear-gradient(90deg,#0097a7,#00bcd4,#4dd0e1);
  background-size: 200% 200%;
  animation: gradientTopbar 10s ease infinite, slideDown .6s ease-out both;
  color:var(--blanco);display:flex;align-items:center;gap:16px;justify-content:space-between;
  padding:10px 18px;box-shadow:0 2px 12px rgba(0,0,0,.22);
}
@keyframes gradientTopbar{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.brand{display:flex;align-items:center;gap:10px}
.brand img{height:34px;width:34px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.2));animation:pulse 2s ease-in-out infinite}
.brand .title{font-weight:800;letter-spacing:.6px}
.nav{display:flex;gap:4px}
.nav > li{list-style:none;position:relative}
.nav > li > a{
  display:block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:600;
  transition:all .3s ease;
}
.nav > li > a:hover{background:rgba(255,255,255,.15);transform:translateY(-2px)}
/* Dropdown suave */
.dropdown{
  position:absolute;top:46px;left:0;background:#fff;min-width:240px;border-radius:12px;
  box-shadow:0 12px 24px rgba(0,0,0,.15);padding:8px;display:none;opacity:0;transform:translateY(8px);
  transition:opacity .25s ease, transform .25s ease;
}
.nav > li:hover .dropdown{display:block;opacity:1;transform:translateY(0)}
.dropdown a{
  display:flex;gap:8px;align-items:center;padding:10px;border-radius:8px;color:#222;text-decoration:none;
  transition:transform .2s ease, background .2s ease;
}
.dropdown a:hover{background:#e0f7fa;transform:translateX(4px)}
.alert-badge{
  background:#ff4757;color:#fff;min-width:20px;height:20px;border-radius:10px;padding:0 6px;
  font-size:12px;display:inline-flex;align-items:center;justify-content:center;margin-left:6px;
  animation:badgeBounce 2s infinite;
}
@keyframes badgeBounce{0%,20%,50%,80%,100%{transform:translateY(0)}40%{transform:translateY(-3px)}60%{transform:translateY(-2px)}}
.userbox{display:flex;align-items:center;gap:10px;font-weight:600}

/* WRAPPER */
.wrapper{padding:18px;max-width:1400px;margin:0 auto;animation:fadeInContent .7s ease-out}

/* GREETING + SEARCH + CTA */
.toolbar{
  display:grid;grid-template-columns:1fr auto auto;gap:14px;align-items:center;margin-bottom:14px;
  animation:slideInLeft .6s ease-out both;
}
.greeting h2{margin:0 0 4px 0;color:var(--verde-osc)}
.greeting small{color:#6b7280}
.search{
  display:flex;align-items:center;background:#fff;border-radius:10px;padding:8px 12px;
  box-shadow:0 2px 8px rgba(0,0,0,.08);transition:all .25s ease;
}
.search:focus-within{box-shadow:0 8px 20px rgba(0,151,167,.25);transform:translateY(-1px)}
.search i{color:#9aa4af;margin-right:8px;transition:color .25s ease}
.search:focus-within i{color:var(--verde)}
.search input{border:0;outline:0;font-size:14px;width:260px}
.quick{display:flex;gap:10px}
.btn{
  background:var(--verde);color:#fff;border:0;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;
  box-shadow:0 6px 14px rgba(0,188,212,.25);transition:all .25s ease;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(0,188,212,.35)}
.btn.outline{background:#fff;color:var(--verde);border:2px solid var(--verde)}
.btn.red{background:var(--acento);box-shadow:0 6px 14px rgba(232,92,74,.25)}

/* GRID */
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}

/* KPI CARDS */
.kpi{
  grid-column:span 3;background:linear-gradient(180deg,#ffffff 0%, #f7fbf8 100%);
  border-radius:16px;padding:16px;box-shadow:0 10px 22px rgba(0,0,0,.08);
  display:flex;align-items:center;gap:12px;position:relative;overflow:hidden;
  transition:all .3s ease;animation:fadeInUp .5s ease-out both;
}
.kpi:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.12)}
.kpi .icon{
  width:46px;height:46px;border-radius:12px;background:rgba(0,151,167,.12);display:flex;align-items:center;justify-content:center;color:var(--verde);
  transition:all .25s ease;
}
.kpi:hover .icon{transform:scale(1.1);background:rgba(0,151,167,.2)}
.kpi h3{margin:2px 0 0 0;font-size:22px}
.kpi small{color:#6b7280}
.kpi::after{content:"";position:absolute;right:-20px;bottom:-20px;width:120px;height:120px;border-radius:50%;
background:radial-gradient( rgba(0,151,167,.08), transparent 60% );animation:pulse 3s ease-in-out infinite}

/* PANELS */
.panel{
  grid-column:span 6;background:#fff;border-radius:16px;box-shadow:0 10px 22px rgba(0,0,0,.08);padding:14px 16px;
  transition:all .3s ease;animation:fadeInUp .6s ease-out both;
}
.panel:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.15)}
.panel h4{margin:0 0 10px 0;color:var(--verde-osc)}
.panel .sub{color:#6b7280;margin-bottom:10px}

/* TABLES */
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px;transition:background-color .25s ease}
.table th{color:#334155;background:#f8fafc}
.table tr:hover td{background:#f9fefb}
.tag{padding:4px 8px;border-radius:30px;font-size:12px;font-weight:700;animation:fadeIn .5s ease}
.tag.danger{background:#ffe8e6;color:#b42318}
.tag.warn{background:#fff6e5;color:#b15e00}

/* SHORTCUTS */
.shortcuts{grid-column:span 12;display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.short{
  background:#fff;border-radius:16px;padding:18px;text-align:center;box-shadow:0 8px 18px rgba(0,0,0,.07);
  cursor:pointer;transition:all .3s ease;animation:fadeInUp .8s ease-out both;
}
.short i{color:var(--verde);font-size:30px;margin-bottom:8px;transition:all .25s ease}
.short:hover{transform:translateY(-6px);box-shadow:0 20px 35px rgba(0,0,0,.15)}
.short:hover i{transform:scale(1.12);color:var(--verde-osc)}
.short a{color:inherit;text-decoration:none;font-weight:700}

/* FOOTER */
.footer{margin-top:18px;color:#6b7280;text-align:center;animation:fadeIn 1s ease-out}

/* ANIMACIONES */
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
@keyframes fadeInContent{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:none}}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-30px)}to{opacity:1;transform:none}}
@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}

/* Stagger */
.kpi:nth-child(1){animation-delay:.05s}
.kpi:nth-child(2){animation-delay:.1s}
.kpi:nth-child(3){animation-delay:.15s}
.kpi:nth-child(4){animation-delay:.2s}
.panel:nth-child(1){animation-delay:.1s}
.panel:nth-child(2){animation-delay:.2s}
.panel:nth-child(3){animation-delay:.3s}
.panel:nth-child(4){animation-delay:.4s}
.short:nth-child(1){animation-delay:.1s}
.short:nth-child(2){animation-delay:.15s}
.short:nth-child(3){animation-delay:.2s}
.short:nth-child(4){animation-delay:.25s}
.short:nth-child(5){animation-delay:.3s}
.short:nth-child(6){animation-delay:.35s}
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <div class="brand">
    <img src="Logo.png" alt="FARVEC">
    <div class="title">FARVEC</div>
  </div>

  <ul class="nav">
    <!-- 🔁 Inicio apunta al menú del farmacéutico -->
    <li><a href="menu_farmaceutico.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
    <li>
      <a href="#"><i class="fa-solid fa-database"></i> Registros</a>
      <div class="dropdown">
        <a href="stock.php"><i class="fa-solid fa-capsules"></i> Stock y Lotes</a>
        <a href="Historial.php"><i class="fa-solid fa-clipboard-list"></i> Historial de Stock</a>
        <a href="ventas.php"><i class="fa-solid fa-cash-register"></i> Nueva Venta</a>
        <a href="ventas_listar.php"><i class="fa-solid fa-list"></i> Listado de Ventas</a>
        <!-- ❌ Compras eliminado para farmacéutico -->
        <a href="clientes_listar.php"><i class="fa-solid fa-users"></i> Clientes</a>
        <a href="reportes.php"><i class="fa-solid fa-chart-line"></i> Reportes</a>
      </div>
    </li>

    <li>
      <a href="#"><i class="fa-solid fa-bell"></i> Alertas
        <?php if ($alertCount>0): ?><span class="alert-badge"><?= $alertCount ?></span><?php endif; ?>
      </a>
      <div class="dropdown">
        <a href="#alert-min"><i class="fa-solid fa-triangle-exclamation"></i> Stock en mínimo (<?= count($low) ?>)</a>
        <a href="#alert-vto"><i class="fa-solid fa-hourglass-half"></i> Próximos a vencer (<?= count($exp) ?>)</a>
      </div>
    </li>
    <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Salir</a></li>
  </ul>

  <div class="userbox">
    <i class="fa-solid fa-user-circle"></i>
    <?= htmlspecialchars($user['nombre'] ?? '') ?> (<?= htmlspecialchars($user['rol'] ?? '') ?>)
  </div>
</header>

<!-- MAIN -->
<main class="wrapper">

  <!-- Toolbar -->
  <section class="toolbar">
    <div class="greeting">
      <h2>Bienvenido, <?= htmlspecialchars($user['nombre']) ?> 👋</h2>
      <small>Sistema de gestión farmacéutica · Todo en un solo lugar</small>
    </div>
    <div class="search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" placeholder="Buscar productos, clientes o ventas (Ctrl+/)" id="q">
    </div>
    <div class="quick">
      <button class="btn" onclick="location.href='ventas.php'"><i class="fa-solid fa-plus"></i> Nueva venta</button>
      <button class="btn outline" onclick="location.href='stock.php'"><i class="fa-solid fa-boxes-stacked"></i> Ver stock</button>
    </div>
  </section>

  <!-- KPIs -->
  <section class="grid">
    <div class="kpi">
      <div class="icon"><i class="fa-solid fa-dollar-sign"></i></div>
      <div>
        <small>Ventas de hoy</small>
        <h3>$<?= number_format($kpis['ventas_hoy'],2,',','.') ?></h3>
      </div>
    </div>
    <div class="kpi">
      <div class="icon"><i class="fa-solid fa-receipt"></i></div>
      <div>
        <small>Tickets de hoy</small>
        <h3><?= (int)$kpis['tickets_hoy'] ?></h3>
      </div>
    </div>
    <div class="kpi">
      <div class="icon"><i class="fa-solid fa-calendar-check"></i></div>
      <div>
        <small>Ventas del mes</small>
        <h3>$<?= number_format($kpis['ventas_mes'],2,',','.') ?></h3>
      </div>
    </div>
    <div class="kpi">
      <div class="icon"><i class="fa-solid fa-pills"></i></div>
      <div>
        <small>Productos</small>
        <h3><?= (int)$kpis['productos'] ?></h3>
      </div>
    </div>
  </section>

  <!-- Gráficos + Alertas -->
  <section class="grid">
    <div class="panel" style="grid-column:span 7">
      <h4>Ventas últimos 7 días</h4>
      <div class="sub">Tendencia de facturación diaria</div>
      <canvas id="chartLine" height="140"></canvas>
    </div>

    <div class="panel" style="grid-column:span 5">
      <h4>Stock por categoría</h4>
      <div class="sub">Distribución actual de unidades</div>
      <canvas id="chartDonut" height="140"></canvas>
    </div>

    <div class="panel" id="alert-min" style="grid-column:span 6">
      <h4><i class="fa-solid fa-triangle-exclamation" style="color:#b42318"></i> Stock en mínimo</h4>
      <table class="table">
        <thead><tr><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Estado</th></tr></thead>
        <tbody>
          <?php if(!$low): ?>
            <tr><td colspan="4">Sin alertas de stock mínimo 👌</td></tr>
          <?php else: foreach($low as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['nombre']) ?></td>
              <td><?= (int)$p['stock_actual'] ?></td>
              <td><?= (int)$p['stock_minimo'] ?></td>
              <?php
                $estado = 'Reponer';
                $cls = 'danger';
              ?>
              <td><span class="tag <?= $cls ?>"><?= $estado ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="panel" id="alert-vto" style="grid-column:span 6">
      <h4><i class="fa-solid fa-hourglass-half" style="color:#b15e00"></i> Próximos a vencer</h4>
      <table class="table">
        <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th>Cant.</th><th>Estado</th></tr></thead>
        <tbody>
          <?php if(!$exp): ?>
            <tr><td colspan="5">Sin vencimientos próximos 👌</td></tr>
          <?php else: foreach($exp as $l):
            $estado = (new DateTime($l['fecha_vencimiento'])) < (new DateTime()) ? 'Vencido' : 'Próximo';
            $cls = $estado==='Vencido' ? 'danger' : 'warn';
          ?>
            <tr>
              <td><?= htmlspecialchars($l['nombre']) ?></td>
              <td><?= htmlspecialchars($l['numero_lote']) ?></td>
              <td><?= htmlspecialchars($l['fecha_vencimiento']) ?></td>
              <td><?= (int)$l['cantidad_actual'] ?></td>
              <td><span class="tag <?= $cls ?>"><?= $estado ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Accesos rápidos (sin Compras) -->
  <section class="shortcuts">
    <div class="short"><a href="stock.php"><i class="fa-solid fa-capsules"></i><div>Stock y Lotes</div></a></div>
    <div class="short"><a href="Historial.php"><i class="fa-solid fa-clipboard-list"></i><div>Historial</div></a></div>
    <div class="short"><a href="ventas.php"><i class="fa-solid fa-cash-register"></i><div>Nueva venta</div></a></div>
    <div class="short"><a href="ventas_listar.php"><i class="fa-solid fa-list"></i><div>Listado de ventas</div></a></div>
    <div class="short"><a href="clientes_listar.php"><i class="fa-solid fa-users"></i><div>Clientes</div></a></div>
    <div class="short"><a href="reportes.php"><i class="fa-solid fa-chart-line"></i><div>Reportes</div></a></div>
  </section>

  <div class="footer">© <?= date('Y') ?> FARVEC · Sistema de Gestión de Farmacia</div>
</main>

<script>
/* Acceso rápido a búsqueda */
document.addEventListener('keydown', e=>{
  if((e.ctrlKey||e.metaKey) && e.key=='/'){
    e.preventDefault();
    const q = document.getElementById('q');
    if(q) q.focus();
  }
});

/* Micro-interacción en topbar */
document.querySelectorAll('.nav > li > a').forEach(a=>{
  a.addEventListener('mouseenter', ()=> a.style.filter = 'brightness(1.06)');
  a.addEventListener('mouseleave', ()=> a.style.filter = '');
});

/* Line chart: ventas 7d (paleta turquesa) */
const lineCtx = document.getElementById('chartLine');
new Chart(lineCtx, {
  type:'line',
  data:{
    labels: <?= json_encode($ventas7d['labels']) ?>,
    datasets:[{
      label:'$',
      data: <?= json_encode($ventas7d['data']) ?>,
      fill:true,
      borderColor:'#00bcd4',
      backgroundColor:'rgba(0,188,212,.15)',
      tension:.35,
      pointRadius:3
    }]
  },
  options:{
    plugins:{legend:{display:false}},
    scales:{y:{beginAtZero:true}}
  }
});

/* Donut: stock por categoría (turquesa a celeste) */
const donutCtx = document.getElementById('chartDonut');
new Chart(donutCtx, {
  type:'doughnut',
  data:{
    labels: <?= json_encode($stockCat['labels']) ?>,
    datasets:[{
      data: <?= json_encode($stockCat['data']) ?>,
      backgroundColor:['#00bcd4','#26c6da','#4dd0e1','#80deea','#b2ebf2','#e0f7fa']
    }]
  },
  options:{ plugins:{legend:{position:'bottom'}} }
});
</script>
</body>
</html>
