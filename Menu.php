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
        // normalizamos días faltantes
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
  --verde:#008f4c; --verde-osc:#006837; --gris:#f4f4f4; --blanco:#fff; --acento:#e85c4a; --tinta:#1f2937;
}
/* RESET */
*{box-sizing:border-box}
html,body{height:100%;min-height:100vh;}
html{background:linear-gradient(135deg, #eafdf3, #ffffff);background-attachment:fixed;}
body{
  margin:0;font-family:"Segoe UI",system-ui,-apple-system,Arial,sans-serif;
  background:transparent;color:var(--tinta);
  min-height:100vh;position:relative;
}

/* Animación de fondo sutil con partículas */
html::before {
  content:'';position:fixed;top:0;left:0;width:100%;height:100vh;
  background-image:radial-gradient(circle at 25% 25%, rgba(0,143,76,0.08) 0%, transparent 50%),
                   radial-gradient(circle at 75% 75%, rgba(0,143,76,0.05) 0%, transparent 50%),
                   radial-gradient(circle at 50% 50%, rgba(0,143,76,0.03) 0%, transparent 50%);
  background-size:800px 800px, 600px 600px, 400px 400px;
  background-position:0px 0px, 200px 200px, 400px 0px;
  animation:floatingBg 20s ease-in-out infinite;z-index:-1;
}

@keyframes floatingBg {
  0%, 100% { transform:translate(0,0) rotate(0deg); }
  33% { transform:translate(-20px,10px) rotate(0.5deg); }
  66% { transform:translate(20px,-10px) rotate(-0.5deg); }
}

/* TOPBAR */
.topbar{
  position:sticky;top:0;z-index:20;background:var(--verde);
  color:var(--blanco);display:flex;align-items:center;gap:16px;justify-content:space-between;
  padding:10px 18px;box-shadow:0 2px 8px rgba(0,0,0,.18);
  animation:slideDown 0.6s ease-out;
}
.brand{display:flex;align-items:center;gap:10px}
.brand img{
  height:34px;width:34px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.2));
  animation:pulse 2s ease-in-out infinite;
}
.brand .title{font-weight:800;letter-spacing:.6px}
.nav{display:flex;gap:4px}
.nav > li{list-style:none;position:relative}
.nav > li > a{
  display:block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:600;
  transition:all 0.3s ease;
}
.nav > li > a:hover{background:rgba(255,255,255,.12);transform:translateY(-1px);}
/* dropdown */
.dropdown{position:absolute;top:46px;left:0;background:var(--blanco);min-width:240px;border-radius:12px;
  box-shadow:0 12px 24px rgba(0,0,0,.15);padding:8px;display:none;animation:fadeInUp .3s ease;transform:translateY(10px);opacity:0;}
.nav > li:hover .dropdown{display:block;animation:fadeInUp .3s ease forwards;}
.dropdown a{display:flex;gap:8px;align-items:center;padding:10px;border-radius:8px;color:var(--tinta);text-decoration:none;transition:all 0.3s ease;}
.dropdown a:hover{background:#f5f9f6;transform:translateX(4px);}
.alert-badge{
  background:#ff4757;color:#fff;min-width:20px;height:20px;border-radius:10px;padding:0 6px;
  font-size:12px;display:inline-flex;align-items:center;justify-content:center;margin-left:6px;
  animation:bounce 2s infinite;
}
.userbox{display:flex;align-items:center;gap:10px;font-weight:600}

/* WRAPPER */
.wrapper{padding:18px;max-width:1400px;margin:0 auto;animation:fadeInContent 0.8s ease-out;}

/* GREETING + SEARCH + CTA */
.toolbar{display:grid;grid-template-columns:1fr auto auto;gap:14px;align-items:center;margin-bottom:14px;animation:slideInLeft 0.8s ease-out;}
.greeting h2{margin:0 0 4px 0;color:var(--verde-osc)}
.greeting small{color:#6b7280}
.search{
  display:flex;align-items:center;background:#fff;border-radius:10px;padding:8px 12px;
  box-shadow:0 2px 8px rgba(0,0,0,.08);transition:all 0.3s ease;
}
.search:focus-within{box-shadow:0 4px 16px rgba(0,143,76,.15);transform:translateY(-1px);}
.search i{color:#9aa4af;margin-right:8px;transition:color 0.3s ease;}
.search:focus-within i{color:var(--verde);}
.search input{border:0;outline:0;font-size:14px;width:260px}
.quick{display:flex;gap:10px}
.btn{
  background:var(--verde);color:#fff;border:0;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer;
  box-shadow:0 6px 14px rgba(0,143,76,.25);transition:all .3s ease;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(0,143,76,.35);}
.btn.outline{background:#fff;color:var(--verde);border:2px solid var(--verde)}
.btn.red{background:var(--acento);box-shadow:0 6px 14px rgba(232,92,74,.25)}

/* GRID */
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}

/* KPI CARDS */
.kpi{
  grid-column:span 3;background:linear-gradient(180deg,#ffffff 0%, #f7fbf8 100%);
  border-radius:16px;padding:16px;box-shadow:0 10px 22px rgba(0,0,0,.08);
  display:flex;align-items:center;gap:12px;position:relative;overflow:hidden;
  transition:all 0.4s ease;animation:fadeInUp 0.6s ease-out;
}
.kpi:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.12);}
.kpi .icon{
  width:46px;height:46px;border-radius:12px;background:rgba(0,143,76,.12);display:flex;align-items:center;justify-content:center;color:var(--verde);
  transition:all 0.3s ease;
}
.kpi:hover .icon{transform:scale(1.1);background:rgba(0,143,76,.2);}
.kpi h3{margin:2px 0 0 0;font-size:22px}
.kpi small{color:#6b7280}
.kpi::after{content:"";position:absolute;right:-20px;bottom:-20px;width:120px;height:120px;border-radius:50%;
background:radial-gradient( rgba(0,143,76,.08), transparent 60% );animation:pulse 3s ease-in-out infinite;}

/* PANELS */
.panel{
  grid-column:span 6;background:#fff;border-radius:16px;box-shadow:0 10px 22px rgba(0,0,0,.08);padding:14px 16px;
  transition:all 0.4s ease;animation:fadeInUp 0.8s ease-out;
}
.panel:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.15);}
.panel h4{margin:0 0 10px 0;color:var(--verde-osc)}
.panel .sub{color:#6b7280;margin-bottom:10px}

/* TABLES */
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px;transition:background-color 0.3s ease;}
.table th{color:#334155;background:#f8fafc}
.table tr:hover td{background:#f8fafc;}
.tag{padding:4px 8px;border-radius:30px;font-size:12px;font-weight:700;animation:fadeIn 0.5s ease;}
.tag.danger{background:#ffe8e6;color:#b42318}
.tag.warn{background:#fff6e5;color:#b15e00}

/* SHORTCUTS (Accesos rápidos) */
.shortcuts{grid-column:span 12;display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.short{
  background:#fff;border-radius:16px;padding:18px;text-align:center;box-shadow:0 8px 18px rgba(0,0,0,.07);
  cursor:pointer;transition:all .4s ease;animation:fadeInUp 1s ease-out;
}
.short i{color:var(--verde);font-size:30px;margin-bottom:8px;transition:all 0.3s ease;}
.short:hover{transform:translateY(-6px);box-shadow:0 20px 35px rgba(0,0,0,.15);}
.short:hover i{transform:scale(1.2);color:var(--verde-osc);}
.short a{color:inherit;text-decoration:none;font-weight:700}

/* FOOTER */
.footer{margin-top:18px;color:#6b7280;text-align:center;animation:fadeIn 1.2s ease-out;}

/* ANIMATIONS */
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
@keyframes fadeInContent{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:none}}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-30px)}to{opacity:1;transform:none}}
@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
@keyframes bounce{0%,20%,50%,80%,100%{transform:translateY(0)}40%{transform:translateY(-3px)}60%{transform:translateY(-2px)}}

/* Animación de entrada para elementos */
.kpi:nth-child(1){animation-delay:0.1s;}
.kpi:nth-child(2){animation-delay:0.2s;}
.kpi:nth-child(3){animation-delay:0.3s;}
.kpi:nth-child(4){animation-delay:0.4s;}

.panel:nth-child(1){animation-delay:0.2s;}
.panel:nth-child(2){animation-delay:0.4s;}
.panel:nth-child(3){animation-delay:0.6s;}
.panel:nth-child(4){animation-delay:0.8s;}

.short:nth-child(1){animation-delay:0.2s;}
.short:nth-child(2){animation-delay:0.3s;}
.short:nth-child(3){animation-delay:0.4s;}
.short:nth-child(4){animation-delay:0.5s;}
.short:nth-child(5){animation-delay:0.6s;}
.short:nth-child(6){animation-delay:0.7s;}
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
    <li><a href="Menu.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
    <li>
      <a href="#"><i class="fa-solid fa-database"></i> Registros</a>
      <div class="dropdown">
        <a href="stock.php"><i class="fa-solid fa-capsules"></i> Stock y Lotes</a>
        <a href="Historial.php"><i class="fa-solid fa-clipboard-list"></i> Historial de Stock</a>
        <a href="ventas.php"><i class="fa-solid fa-cash-register"></i> Nueva Venta</a>
        <a href="ventas_listar.php"><i class="fa-solid fa-list"></i> Listado de Ventas</a>
        <a href="clientes.php"><i class="fa-solid fa-users"></i> Clientes</a>
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
              <td><span class="tag danger">Reponer</span></td>
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

  <!-- Accesos rápidos -->
  <section class="shortcuts">
    <div class="short"><a href="stock.php"><i class="fa-solid fa-capsules"></i><div>Stock y Lotes</div></a></div>
    <div class="short"><a href="Historial.php"><i class="fa-solid fa-clipboard-list"></i><div>Historial</div></a></div>
    <div class="short"><a href="ventas.php"><i class="fa-solid fa-cash-register"></i><div>Nueva venta</div></a></div>
    <div class="short"><a href="ventas_listar.php"><i class="fa-solid fa-list"></i><div>Listado de ventas</div></a></div>
    <div class="short"><a href="clientes.php"><i class="fa-solid fa-users"></i><div>Clientes</div></a></div>
    <div class="short"><a href="reportes.php"><i class="fa-solid fa-chart-line"></i><div>Reportes</div></a></div>
  </section>

  <div class="footer">© <?= date('Y') ?> FARVEC · Sistema de Gestión de Farmacia</div>
</main>

<script>
/* accesos búsqueda rápida */
document.addEventListener('keydown', e=>{
  if((e.ctrlKey||e.metaKey) && e.key==='/'){ e.preventDefault(); document.getElementById('q').focus(); }
});

/* Line chart: ventas 7d */
const lineCtx = document.getElementById('chartLine');
new Chart(lineCtx, {
  type:'line',
  data:{
    labels: <?= json_encode($ventas7d['labels']) ?>,
    datasets:[{
      label:'$',
      data: <?= json_encode($ventas7d['data']) ?>,
      fill:true,
      borderColor:'#008f4c',
      backgroundColor:'rgba(0,143,76,.15)',
      tension:.35,
      pointRadius:3
    }]
  },
  options:{
    plugins:{legend:{display:false}},
    scales:{y:{beginAtZero:true}}
  }
});

/* Donut: stock por categoría */
const donutCtx = document.getElementById('chartDonut');
new Chart(donutCtx, {
  type:'doughnut',
  data:{
    labels: <?= json_encode($stockCat['labels']) ?>,
    datasets:[{ data: <?= json_encode($stockCat['data']) ?>,
      backgroundColor:['#008f4c','#00b36a','#5cd08f','#9be0b7','#d7f3e5','#b6ffd5']
    }]
  },
  options:{ plugins:{legend:{position:'bottom'}} }
});
</script>
</body>
</html>