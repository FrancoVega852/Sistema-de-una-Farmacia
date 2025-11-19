<?php
/*******************************************************
 * FARVEC • Portal Cliente (2025)
 * Menú lateral optimizado + Panel de gestión integrado
 *******************************************************/
session_start();
require_once 'Conexion.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*==========================
=   CONTROLADOR (PHP)      =
==========================*/
final class FarvecProCliente {
  private mysqli $db;
  public function __construct(mysqli $db) {
    $this->db = $db;
    if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit(); }
    $this->db->set_charset('utf8mb4');
  }

  public function usuario(): array {
    $st = $this->db->prepare("SELECT id,nombre,email,rol FROM Usuario WHERE id=? LIMIT 1");
    $st->bind_param("i", $_SESSION['usuario_id']);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: [];
  }

  /*==============================
  =            KPIs              =
  ==============================*/
  public function kpis(): array {
    // NOTA: Son KPIs globales del sistema, no por cliente,
    // pero se mantiene el diseño del dashboard.
    $k=['ventas_hoy'=>0.0,'tickets_hoy'=>0,'ventas_mes'=>0.0,'productos'=>0,'clientes'=>0,'proveedores'=>0];
    $r=$this->db->query("SELECT IFNULL(SUM(total),0) m, COUNT(*) c FROM Venta WHERE DATE(fecha)=CURDATE()");
    if($r && $row=$r->fetch_assoc()){ $k['ventas_hoy']=(float)$row['m']; $k['tickets_hoy']=(int)$row['c']; }
    $r=$this->db->query("SELECT IFNULL(SUM(total),0) m FROM Venta WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE())");
    if($r && $row=$r->fetch_assoc()){ $k['ventas_mes']=(float)$row['m']; }
    foreach(['Producto'=>'productos','Cliente'=>'clientes','Proveedor'=>'proveedores'] as $t=>$a){
      try{
        $r=$this->db->query("SELECT COUNT(*) c FROM $t");
        if($r && $row=$r->fetch_assoc()) $k[$a]=(int)$row['c'];
      }catch(\Throwable $e){}
    }
    return $k;
  }

  /*==============================
  =            ALERTAS           =
  ==============================*/
  // Para mantener el diseño, dejamos estas funciones,
  // aunque en un portal real de cliente podrían ocultarse.
  public function stockMinimo(int $limit=12): array {
    try{
      $sql="SELECT id,nombre,stock_actual,stock_minimo
            FROM Producto
            WHERE stock_actual<=stock_minimo
            ORDER BY (stock_actual-stock_minimo) ASC
            LIMIT ?";
      $st=$this->db->prepare($sql); $st->bind_param("i",$limit); $st->execute();
      return $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }catch(\Throwable $e){ return []; }
  }

  public function lotesPorVencer(int $days=60, int $limit=12): array {
    try{
      $sql="SELECT p.nombre, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
            FROM Lote l JOIN Producto p ON p.id=l.producto_id
            WHERE l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY l.fecha_vencimiento ASC
            LIMIT ?";
      $st=$this->db->prepare($sql); $st->bind_param("ii",$days,$limit); $st->execute();
      return $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }catch(\Throwable $e){ return []; }
  }

  public function recetasPendientes(int $limit=12): array {
    // Si no usás Receta con clientes, esto volverá vacío, pero no rompe el diseño.
    try{
      $r=$this->db->query("SELECT id,paciente AS nombre,medico,estado,fecha
                           FROM Receta
                           WHERE estado IN('Pendiente','En revisión')
                           ORDER BY fecha DESC
                           LIMIT $limit");
      if($r && $r->num_rows>0) return $r->fetch_all(MYSQLI_ASSOC);
    }catch(\Throwable $e){}
    return [];
  }

  /*==============================
  =        SERIES / GRÁFICOS     =
  ==============================*/
  public function serieVentas7d(): array {
    $sql="SELECT DATE(fecha)d,ROUND(SUM(total),2)t
          FROM Venta
          WHERE fecha>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)
          GROUP BY DATE(fecha)
          ORDER BY d";
    $r=$this->db->query($sql); $map=[];
    if($r){ while($row=$r->fetch_assoc()) $map[$row['d']]=(float)$row['t']; }
    $labs=[]; $dat=[];
    for($i=6;$i>=0;$i--){
      $d=(new DateTime())->modify("-$i day")->format('Y-m-d');
      $labs[]=$d;
      $dat[]=$map[$d]??0;
    }
    return ['labels'=>$labs,'data'=>$dat];
  }

  public function stockPorCategoria(): array {
    try{
      $sql="SELECT IFNULL(c.nombre,'Sin categoría') cat, SUM(p.stock_actual) tot
            FROM Producto p
            LEFT JOIN Categoria c ON c.id=p.categoria_id
            GROUP BY cat
            ORDER BY tot DESC";
    $r=$this->db->query($sql);
      $L=[]; $D=[];
      if($r){ while($x=$r->fetch_assoc()){ $L[]=$x['cat']; $D[]=(int)$x['tot']; } }
      return ['labels'=>$L,'data'=>$D];
    }catch(\Throwable $e){ return ['labels'=>[],'data'=>[]]; }
  }

  /*==============================
  =      EXTRAS PROFESIONALES    =
  ==============================*/
  public function resumenFinanzas(): array {
    $v=0.0; $c=0.0;
    try{
      $r=$this->db->query("SELECT IFNULL(SUM(total),0)m
                           FROM Venta
                           WHERE YEAR(fecha)=YEAR(CURDATE())
                             AND MONTH(fecha)=MONTH(CURDATE())");
      if($r&&$row=$r->fetch_assoc()) $v=(float)$row['m'];
    }catch(\Throwable $e){}
    try{
      $r=$this->db->query("SELECT IFNULL(SUM(total),0)m
                           FROM Compra
                           WHERE YEAR(fecha)=YEAR(CURDATE())
                             AND MONTH(fecha)=MONTH(CURDATE())");
      if($r&&$row=$r->fetch_assoc()) $c=(float)$row['m'];
    }catch(\Throwable $e){}
    $m=max(0,$v-$c);
    $ratio=$v>0?round(($m/$v)*100,1):0;
    return ['ventas'=>$v,'compras'=>$c,'margen'=>$m,'ratio'=>$ratio];
  }

  public function topProductos(int $limit=6): array {
    try{
      $sql="SELECT p.nombre, SUM(d.cantidad)cant, ROUND(SUM(d.subtotal),2) total
            FROM DetalleVenta d
            JOIN Producto p ON p.id=d.producto_id
            JOIN Venta v ON v.id=d.venta_id
            WHERE YEAR(v.fecha)=YEAR(CURDATE())
              AND MONTH(v.fecha)=MONTH(CURDATE())
            GROUP BY p.id,p.nombre
            ORDER BY cant DESC
            LIMIT ?";
      $st=$this->db->prepare($sql); $st->bind_param("i",$limit); $st->execute();
      return $st->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }catch(\Throwable $e){ return []; }
  }

  public function actividad(int $limit=8): array {
    try{
      $r=$this->db->query("SELECT CONCAT('Usuario ',nombre) evento,
                                  email detalle,
                                  NOW() fecha
                           FROM Usuario
                           ORDER BY id DESC
                           LIMIT $limit");
      return $r?$r->fetch_all(MYSQLI_ASSOC):[];
    }catch(\Throwable $e){ return []; }
  }

  public function tareas(): array {
    $t=[];
    foreach($this->stockMinimo(3) as $p){
      $t[]=[
        'icon'=>'fa-boxes-stacked',
        'txt'=>"Reponer: {$p['nombre']} (queda {$p['stock_actual']})",
        'pri'=>'alta'
      ];
    }
    foreach($this->lotesPorVencer(60,2) as $l){
      $t[]=[
        'icon'=>'fa-hourglass-half',
        'txt'=>"Revisar lote {$l['numero_lote']} de {$l['nombre']} (vence {$l['fecha_vencimiento']})",
        'pri'=>'media'
      ];
    }
    if(!$t) $t[]=['icon'=>'fa-face-smile','txt'=>'Sin tareas por ahora.','pri'=>'baja'];
    return $t;
  }

  public function notifs(): array {
    $n=[];
    foreach($this->stockMinimo(8) as $p){
      $n[]=[
        'tipo'=>'stock','color'=>'danger','icon'=>'fa-triangle-exclamation',
        'titulo'=>"Stock crítico: {$p['nombre']}",
        'detalle'=>"Actual: {$p['stock_actual']} · Mín.: {$p['stock_minimo']}",
        'href'=>'stock.php'
      ];
    }
    foreach($this->lotesPorVencer(60,8) as $l){
      $estado=(new DateTime($l['fecha_vencimiento']))<(new DateTime())?'Vencido':'Próximo';
      $n[]=[
        'tipo'=>'vto',
        'color'=>$estado==='Vencido'?'danger':'warn',
        'icon'=>'fa-hourglass-half',
        'titulo'=>"$estado · {$l['nombre']} (Lote {$l['numero_lote']})",
        'detalle'=>"Vence: {$l['fecha_vencimiento']} · Cant.: {$l['cantidad_actual']}",
        'href'=>'stock.php#alert-vto'
      ];
    }
    foreach($this->recetasPendientes(5) as $r){
      $n[]=[
        'tipo'=>'receta','color'=>'ok','icon'=>'fa-file-prescription',
        'titulo'=>"Receta pendiente: {$r['nombre']}",
        'detalle'=>"Fecha: {$r['fecha']} · Estado: {$r['estado']}",
        'href'=>'ventas_listar.php'
      ];
    }
    $n[]=[
      'tipo'=>'sistema','color'=>'info','icon'=>'fa-circle-info',
      'titulo'=>'Copia de seguridad',
      'detalle'=>'Última copia: hace 2 días.',
      'href'=>'#'
    ];
    return $n;
  }
}

/*==========================
=   BOOTSTRAP DE DATOS     =
==========================*/
$conn = new Conexion();
$ctl  = new FarvecProCliente($conn->conexion);
$user = $ctl->usuario();

/* Enforce: solo CLIENTE puede entrar acá */
if (($user['rol'] ?? '') !== 'Cliente') {
  header('Location: menu.php');
  exit();
}

$kpis = $ctl->kpis();
$serie = $ctl->serieVentas7d();
$stockCat = $ctl->stockPorCategoria();
$low  = $ctl->stockMinimo();
$vto  = $ctl->lotesPorVencer();
$rec  = $ctl->recetasPendientes();
$fin  = $ctl->resumenFinanzas();
$top  = $ctl->topProductos();
$act  = $ctl->actividad();
$tasks= $ctl->tareas();
$notifs=$ctl->notifs();
$alertCount = count($low)+count($vto)+count($rec);

function nfmt($n,$d=2){ return number_format((float)$n,$d,',','.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>FARVEC • Cliente</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<style>
:root{
  --verde:#00a86b; --verde-osc:#00794f;
  --ink:#212b36; --muted:#5e6b74; --white:#ffffff;
  --bg:#f6f9f8;
  --panel:#ffffff; --panel-2:#f8fbfa; --panel-3:#f1f5f4;
  --border:#e7eceb; --border-strong:#d5dddb;
  --shadow:0 8px 22px rgba(17,24,39,.08);
  --ok:#0a7e56; --ok-bg:#0a7e5614;
  --warn:#b17012; --warn-bg:#b1701214;
  --danger:#b93142; --danger-bg:#b9314214;
  --info:#1662c2; --info-bg:#1662c214;
  --side:264px; --side-mini:88px;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;
  color:var(--ink);
  background:var(--bg);
  overflow-x:hidden;
}
.bg-layer{
  position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.25;
  background-image:
    radial-gradient(800px 400px at 80% -20%, #d7f3ea 0%, transparent 60%),
    radial-gradient(700px 340px at 10% 0%, #e6f7f1 0%, transparent 50%);
}

/* TOPBAR */
.topbar{
  position:sticky;top:0;z-index:70;
  display:flex;align-items:center;justify-content:center;
  padding:12px 16px;
  background:linear-gradient(90deg,#00794f 0%,#00a86b 100%);
  color:#fff; box-shadow:0 2px 8px rgba(0,0,0,.18);
}
.top-actions,.right-actions{
  position:absolute; top:50%; transform:translateY(-50%);
  display:flex; gap:8px; align-items:center;
}
.top-actions{ left:12px } .right-actions{ right:12px }
.topbar .btn{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.12);color:#fff;
  border:none;padding:9px 12px;border-radius:999px;
  font-weight:600;text-decoration:none;font-size:13px;
  transition:.2s;
}
.topbar .btn:hover{ background:rgba(255,255,255,.22); transform:translateY(-1px) }
.brand-plate{
  display:flex; align-items:center; gap:12px;
  background:rgba(255,255,255,.18);
  padding:8px 14px;border-radius:999px;
  box-shadow:0 6px 18px rgba(0,0,0,.15);
  backdrop-filter:blur(6px);
}
.brand-plate img{ width:34px; height:34px }
.brand-plate .title{ font-weight:800; letter-spacing:.3px;font-size:14px }

/* LAYOUT */
.app{
  display:grid;
  grid-template-columns:var(--side) 1fr;
  min-height:calc(100vh - 64px);
  transition:grid-template-columns .25s ease;
}

/* SIDEBAR */
.sidebar{
  background:linear-gradient(180deg,#00794f 0%,#00a86b 100%);
  border-right:1px solid #006c43;
  padding:14px 10px;
  color:#fff;
  box-shadow:8px 0 22px rgba(17,24,39,.18);
  position:relative;
}
.sidebar-inner{display:flex;flex-direction:column;height:100%}
.sidebar-main{
  flex:1;
  overflow-y:auto;
  padding-right:2px;
}
.sidebar .mobile-toggle{
  display:none;position:absolute;top:10px;right:10px;width:34px;height:34px;
  border-radius:10px;border:1px solid rgba(255,255,255,.3);
  background:rgba(255,255,255,.08);color:#fff;
  align-items:center;justify-content:center;cursor:pointer;
}
.sidebar .header{
  display:flex;align-items:center;gap:10px;
  padding:4px 8px 10px;
}
.sidebar .header .logo{
  width:32px;height:32px;border-radius:9px;
  background:rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
}
.sidebar .header .title{font-weight:800;font-size:14px}
.sidebar .group{
  color:rgba(255,255,255,.7);
  font-size:11px;
  margin:10px 14px 4px;
  letter-spacing:.12em;
  text-transform:uppercase;
}
.nav{list-style:none;padding:0 6px;margin:0}
.nav a{
  display:flex;align-items:center;gap:10px;
  color:#fff;text-decoration:none;
  padding:9px 10px;border-radius:12px;margin:3px 0;
  font-size:14px;
  transition:background .18s ease, transform .18s ease;
}
.nav a .icon{width:22px;display:inline-grid;place-items:center}
.nav a .text{flex:1}
.nav a .caret{font-size:11px;color:rgba(255,255,255,.7);transition:transform .18s}
.nav a:hover{background:rgba(255,255,255,.18);transform:translateX(3px)}
.nav li.open > a{background:rgba(255,255,255,.22)}
.nav li.open > a .caret{transform:rotate(90deg)}
.submenu{display:none;padding-left:30px;padding-bottom:4px}
.submenu a{padding:6px 10px;font-size:13px;opacity:.95}
.submenu a i{margin-right:6px;font-size:12px}
.nav li.open > .submenu{display:block}

/* botón colapsar */
#collapse{
  position:absolute;right:-12px;top:74px;width:26px;height:26px;border-radius:50%;
  background:#fff;color:#00794f;border:1px solid var(--border-strong);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;cursor:pointer;box-shadow:0 8px 22px rgba(0,0,0,.25);
}

/* Sidebar colapsado */
.app.collapsed{grid-template-columns:var(--side-mini) 1fr}
.app.collapsed .sidebar .text,
.app.collapsed .sidebar .group{display:none}
.app.collapsed #collapse i{transform:rotate(180deg)}
.app.collapsed .submenu{
  position:absolute;left:var(--side-mini);top:auto;
  background:linear-gradient(180deg,#00794f 0%,#00a86b 100%);
  border-radius:12px;
  box-shadow:0 8px 20px rgba(0,0,0,.35);
  padding:8px;
  min-width:180px;
}

/* PANEL GESTIÓN EN SIDEBAR (DENTRO DEL SCROLL) */
.sidebar-panel-gestion{
  border-top:1px solid rgba(255,255,255,.25);
  margin-top:10px;
  padding:10px 8px 6px;
}
.sidebar-panel-gestion-title{
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.12em;
  color:rgba(255,255,255,.8);
  margin:0 6px 6px;
}
.tabs-header.vertical{display:flex;flex-direction:column;gap:8px}
.tab-btn{
  border-radius:999px;border:1px solid rgba(255,255,255,.30);
  padding:6px 10px;font-size:12px;cursor:pointer;
  background:rgba(255,255,255,.08);color:#e0fff3;
  display:flex;align-items:center;gap:6px;
  text-align:left;
}
.tab-btn i{font-size:14px}
.tab-btn.active{
  background:#ffffff;
  color:#00794f;
  border-color:#ffffff;
}

/* Off-canvas móvil */
.offcanvas-backdrop{display:none}
@media (max-width:1024px){
  .app{grid-template-columns:1fr}
  .sidebar{
    position:fixed;inset:64px auto 0 0;width:var(--side);
    transform:translateX(-110%);transition:transform .25s ease;
  }
  body.menu-open .sidebar{transform:translateX(0)}
  .sidebar .mobile-toggle{display:flex}
  #collapse{display:none}
  .offcanvas-backdrop{
    position:fixed;inset:64px 0 0 0;background:rgba(0,0,0,.35);z-index:50;display:none;
  }
  body.menu-open .offcanvas-backdrop{display:block}
}

/* MAIN */
.main{padding:16px}
.tools{display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:12px}
.search{
  display:flex;align-items:center;gap:8px;
  background:var(--panel);border:1px solid var(--border);
  border-radius:999px;padding:9px 14px;box-shadow:var(--shadow)
}
.search input{border:0;outline:0;background:transparent;width:100%;font-size:14px}

.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
.kpi{
  grid-column:span 3;
  background:var(--panel);border:1px solid var(--border);
  border-radius:16px;padding:12px 12px;
  box-shadow:var(--shadow);display:flex;align-items:center;gap:10px;
}
.kpi .ico{
  width:40px;height:40px;border-radius:12px;
  background:#e5fbf2;color:#0a7e56;
  display:flex;align-items:center;justify-content:center;
}
.kpi small{color:var(--muted);font-size:12px}
.kpi h3{margin:2px 0 0;font-size:18px}

.panel{
  background:var(--panel);border:1px solid var(--border);
  border-radius:16px;padding:12px;box-shadow:var(--shadow);
}
.panel h4{margin:0 0 6px;font-size:15px}
.sub{color:var(--muted);font-size:12px;margin-bottom:4px}
.table{width:100%;border-collapse:collapse;font-size:13px}
.table th,.table td{padding:7px 8px;border-bottom:1px solid var(--border)}
.table th{background:#f5f7f6;text-align:left}
.tag{padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700}
.tag.ok{color:#0b7f55;background:var(--ok-bg)}
.tag.warn{color:#8a5e13;background:var(--warn-bg)}
.tag.danger{color:#9c2c38;background:var(--danger-bg)}
.tag.info{color:#0d3b87;background:var(--info-bg)}

.cards3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.card{background:var(--panel-2);border-radius:14px;border:1px solid var(--border);padding:10px}
.footer{color:#66777a;text-align:center;margin-top:14px;font-size:12px}

/* PANELES DEL PANEL DE GESTIÓN (MAIN) */
.tab-pane{display:none;animation:fade .25s ease}
.tab-pane.active{display:block}

/* Modal & toast */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.45);
  display:none;align-items:center;justify-content:center;z-index:100
}
.modal{
  width:min(940px,94vw);background:var(--panel);
  border:1px solid var(--border);border-radius:16px;
  padding:14px;box-shadow:0 20px 70px rgba(0,0,0,.35)
}
.modal header{display:flex;align-items:center;justify-content:space-between;gap:10px}
.modal .list{max-height:64vh;overflow:auto;padding-right:6px}
.notice{
  display:flex;gap:12px;align-items:flex-start;
  padding:10px;border-radius:12px;
  border:1px solid var(--border);background:var(--panel-2);margin:8px 0
}
.notice .actions{margin-left:auto;display:flex;gap:6px}
.notice .actions a{
  color:#0b1320;text-decoration:none;
  padding:4px 8px;border-radius:10px;
  border:1px solid var(--border);font-size:12px
}
.notice.ok{border-left:3px solid var(--ok)}
.notice.warn{border-left:3px solid var(--warn)}
.notice.danger{border-left:3px solid var(--danger)}
.notice.info{border-left:3px solid var(--info)}

.toast{
  position:fixed;right:14px;bottom:14px;
  background:var(--panel);border:1px solid var(--border);
  padding:8px 12px;border-radius:12px;
  box-shadow:var(--shadow);display:none;font-size:13px
}
.toast.show{display:block;animation:fade 3s ease forwards}

@media (max-width:1100px){
  .grid{grid-template-columns:repeat(6,1fr)}
  .kpi{grid-column:span 3}
}
@media (max-width:760px){
  .grid{grid-template-columns:repeat(2,1fr)}
  .kpi{grid-column:span 2}
  .panel{grid-column:span 2!important}
  .cards3{grid-template-columns:1fr}
}
@keyframes fade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="bg-layer"></div>

<header class="topbar">
  <div class="top-actions">
    <button id="btnMobileMenu" class="btn" title="Menú"><i class="fa-solid fa-bars"></i></button>
    <!-- IMPORTANTE: Home del cliente apunta a ESTE menú -->
    <a href="menu_cliente.php" class="btn" title="Inicio"><i class="fa-solid fa-house"></i></a>

    <a href="#" id="btnAlerts" class="btn" title="Alertas"><i class="fa-solid fa-bell"></i>
      <span class="badge" style="background:#ffd166;color:#053;border-radius:10px;padding:0 6px;font-weight:900;margin-left:4px;"><?= $alertCount ?></span>
    </a>
  </div>

  <div class="brand-plate">
    <img src="Logo.png" alt="FARVEC">
    <div class="title">FARVEC • Portal de Cliente</div>
  </div>

  <div class="right-actions">
    <a href="logout.php" class="btn" title="Salir"><i class="fa-solid fa-right-from-bracket"></i></a>
    <span style="margin-left:6px;font-weight:700;font-size:13px">
      <?= htmlspecialchars($user['nombre']??'') ?> (<?= htmlspecialchars($user['rol']??'') ?>)
    </span>
  </div>
</header>

<div class="offcanvas-backdrop" id="backdrop"></div>

<div class="app" id="app">
  <!-- SIDEBAR + PANEL DE GESTIÓN DENTRO DEL SCROLL -->
  <aside class="sidebar" id="sidebar">
    <button id="btnCloseMobile" class="mobile-toggle"><i class="fa-solid fa-xmark"></i></button>

    <div class="sidebar-inner">
      <div class="sidebar-main">
        <div class="header">
          <div class="logo"><i class="fa-solid fa-user"></i></div>
          <div class="title">Cliente</div>
          <div id="collapse"><i class="fa-solid fa-angles-left"></i></div>
        </div>

      <!-- COMPRAS (PORTAL CLIENTE) -->
<div class="group">Compras</div>
<ul class="nav">
  <li>
    <a href="#" class="has-sub">
      <span class="icon"><i class="fa-solid fa-cart-shopping"></i></span>
      <span class="text">Compras</span>
      <i class="fa-solid fa-chevron-right caret"></i>
    </a>
    <div class="submenu">
      <!-- Nueva compra (se carga dinámicamente con AJAX) -->
      <a href="Compra_Cliente_guardar.php">
        <i class="fa-solid fa-bag-shopping"></i> Nueva compra
      </a>

      <!-- Historial de compras (del usuario logueado) -->
      <a href="Compra_Cliente_listar.php">
        <i class="fa-solid fa-file-invoice"></i> Mis compras
      </a>
    </div>
  </li>
</ul>


        <!-- HERRAMIENTAS (Sólo soporte visible) -->
        <div class="group">Herramientas</div>
        <ul class="nav">
          <li>
            

        <!-- PANEL DE GESTIÓN (SE MUEVE CON EL MENÚ) -->
        <div class="sidebar-panel-gestion">
          <div class="sidebar-panel-gestion-title">Panel de gestión</div>
          <div class="tabs-header vertical">
            <button class="tab-btn active" data-tab="finanzas"><i class="fa-solid fa-sack-dollar"></i> Resumen</button>
            <button class="tab-btn" data-tab="top"><i class="fa-solid fa-ranking-star"></i> Top productos</button>
            <button class="tab-btn" data-tab="actividad"><i class="fa-solid fa-user-clock"></i> Actividad</button>
            <button class="tab-btn" data-tab="tareas"><i class="fa-solid fa-list-check"></i> Tareas</button>
            <button class="tab-btn" data-tab="sistema"><i class="fa-solid fa-gears"></i> Sistema</button>
          </div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <!-- BUSCADOR -->
    <section class="tools">
      <div class="search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input id="q" placeholder="Buscar productos o compras (Ctrl+/)">
      </div>
    </section>

    <!-- KPIs -->
    <section class="grid fade-in">
      <div class="kpi"><div class="ico"><i class="fa-solid fa-dollar-sign"></i></div><div><small>Ventas de hoy</small><h3>$<?= nfmt($kpis['ventas_hoy']) ?></h3></div></div>
      <div class="kpi"><div class="ico"><i class="fa-solid fa-receipt"></i></div><div><small>Tickets de hoy</small><h3><?= (int)$kpis['tickets_hoy'] ?></h3></div></div>
      <div class="kpi"><div class="ico"><i class="fa-solid fa-calendar-check"></i></div><div><small>Ventas del mes</small><h3>$<?= nfmt($kpis['ventas_mes']) ?></h3></div></div>
      <div class="kpi"><div class="ico"><i class="fa-solid fa-pills"></i></div><div><small>Productos</small><h3><?= (int)$kpis['productos'] ?></h3></div></div>
    </section>

<?php
$stSal = $conn->conexion->query("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo='Ingreso' THEN monto END),0) -
        COALESCE(SUM(CASE WHEN tipo='Egreso' THEN monto END),0) AS saldo
    FROM Movimiento
");
$saldoActual = 0;
if ($stSal && $rowSal = $stSal->fetch_assoc()) {
    $saldoActual = (float)$rowSal['saldo'];
}
?>

<div class="kpi">
  <div class="ico"><i class="fa-solid fa-piggy-bank"></i></div>
  <div>
    <small>Saldo actual</small>
    <h3>$<?= nfmt($saldoActual) ?></h3>
  </div>
</div>

    <!-- GRÁFICOS -->
    <section class="grid" style="margin-top:12px">
      <div class="panel" style="grid-column:span 7">
        <h4>Ventas últimos 7 días</h4>
        <div class="sub">Tendencia de facturación diaria</div>
        <canvas id="line" height="130"></canvas>
      </div>
      <div class="panel" style="grid-column:span 5">
        <h4>Stock por categoría</h4>
        <div class="sub">Distribución actual de unidades</div>
        <canvas id="donut" height="130"></canvas>
      </div>
    </section>

    <!-- ALERTAS STOCK / VTO -->
    <section class="grid" style="margin-top:12px">
      <div class="panel" style="grid-column:span 6">
        <h4><i class="fa-solid fa-triangle-exclamation" style="color:#b93142"></i> Stock en mínimo (<?= count($low) ?>)</h4>
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
      <div class="panel" style="grid-column:span 6">
        <h4><i class="fa-solid fa-hourglass-half" style="color:#b17012"></i> Próximos a vencer (<?= count($vto) ?>)</h4>
        <table class="table">
          <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th>Cant.</th><th>Estado</th></tr></thead>
          <tbody>
          <?php if(!$vto): ?>
            <tr><td colspan="5">Sin vencimientos próximos 👌</td></tr>
          <?php else: foreach($vto as $l):
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

    <!-- PANEL DE GESTIÓN (CONTENIDO CONTROLADO POR EL MENÚ) -->
    <section class="panel" style="margin-top:12px">
      <h4>Panel de gestión</h4>
      <div class="sub">Información clave en un solo lugar (cambiá de pestaña desde el menú lateral)</div>

      <!-- FINANZAS / RESUMEN -->
      <div class="tab-pane active" id="tab-finanzas">
        <div class="cards3" style="grid-template-columns:repeat(3,1fr)">
          <div class="card">
            <strong>Ventas mes</strong>
            <div style="font-size:20px;color:#0b7f55;margin-top:4px">$<?= nfmt($fin['ventas']) ?></div>
          </div>
          <div class="card">
            <strong>Compras mes</strong>
            <div style="font-size:20px;color:#b93142;margin-top:4px">$<?= nfmt($fin['compras']) ?></div>
          </div>
          <div class="card">
            <strong>Margen</strong>
            <div style="font-size:20px;color:#0b7f55;margin-top:4px">$<?= nfmt($fin['margen']) ?> (<?= $fin['ratio'] ?>%)</div>
          </div>
        </div>
      </div>

      <!-- TOP PRODUCTOS -->
      <div class="tab-pane" id="tab-top">
        <table class="table">
          <thead><tr><th>Producto</th><th>Cant.</th><th>Total</th></tr></thead>
          <tbody>
          <?php if(!$top): ?>
            <tr><td colspan="3">Aún sin ranking (o falta DetalleVenta).</td></tr>
          <?php else: foreach($top as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['nombre']) ?></td>
              <td><?= (int)$t['cant'] ?></td>
              <td>$<?= nfmt($t['total']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- ACTIVIDAD -->
      <div class="tab-pane" id="tab-actividad">
        <div class="cards3" style="grid-template-columns:repeat(3,1fr)">
          <?php if(!$act): ?>
            <div class="card">Sin actividad registrada.</div>
          <?php else: foreach($act as $a): ?>
            <div class="card">
              <div style="font-size:13px;font-weight:700">
                <i class="fa-solid fa-user" style="color:#0b7f55;margin-right:4px"></i>
                <?= htmlspecialchars($a['evento']) ?>
              </div>
              <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($a['detalle']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- TAREAS -->
      <div class="tab-pane" id="tab-tareas">
        <div class="cards3">
          <?php foreach($tasks as $t): $c=$t['pri']==='alta'?'#b93142':($t['pri']==='media'?'#b17012':'#0b7f55'); ?>
            <div class="card" style="display:flex;gap:8px;align-items:flex-start">
              <i class="fa-solid <?= $t['icon'] ?>" style="color:<?= $c ?>;margin-top:2px"></i>
              <div style="font-size:13px"><?= htmlspecialchars($t['txt']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- SISTEMA -->
      <div class="tab-pane" id="tab-sistema">
        <div class="cards3">
          <div class="card">
            <strong>Hora del sistema</strong>
            <div id="clock" style="font-size:20px;margin-top:4px">--:--</div>
          </div>
          <a class="card" href="ventas.php" style="text-decoration:none;color:inherit">
            <strong><i class="fa-solid fa-cart-plus"></i> Hacer una compra</strong>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Iniciá un nuevo pedido.</div>
          </a>
          <a class="card" href="ventas_listar.php" style="text-decoration:none;color:inherit">
            <strong><i class="fa-solid fa-file-invoice"></i> Ver mis compras</strong>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Revisar historial de compras.</div>
          </a>
        </div>
      </div>
    </section>

    <div class="footer">© <?= date('Y') ?> FARVEC · Portal Cliente</div>
  </main>
</div>

<!-- MODAL ALERTAS -->
<div class="overlay" id="alertsModal" aria-hidden="true">
  <div class="modal">
    <header>
      <h3>Notificaciones y alertas</h3>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="tag danger">Stock (<?= count($low) ?>)</span>
        <span class="tag warn">Vencimientos (<?= count($vto) ?>)</span>
        <span class="tag ok">Recetas (<?= count($rec) ?>)</span>
        <span class="tag info">Sistema</span>
        <a href="#" id="closeAlerts" class="btn" style="background:#00794f;color:#fff;border:none;border-radius:999px;padding:5px 10px;font-size:12px"><i class="fa-solid fa-xmark"></i> Cerrar</a>
      </div>
    </header>
    <div class="list">
      <?php if(!$notifs): ?>
        <div class="notice info">
          <i class="fa-solid fa-circle-info"></i>
          <div><strong>Sin notificaciones</strong><br><small>No hay novedades por ahora.</small></div>
          <div class="actions"><a href="#" onclick="return false;">OK</a></div>
        </div>
      <?php else: foreach($notifs as $n): ?>
        <div class="notice <?= htmlspecialchars($n['color']) ?>">
          <i class="fa-solid <?= htmlspecialchars($n['icon']) ?>"></i>
          <div><strong><?= htmlspecialchars($n['titulo']) ?></strong><br><small><?= htmlspecialchars($n['detalle']) ?></small></div>
          <div class="actions">
            <?php if($n['tipo']==='stock'): ?>
              <a href="stock.php"><i class="fa-solid fa-eye"></i> Ver stock</a>
            <?php elseif($n['tipo']==='vto'): ?>
              <a href="stock.php#alert-vto"><i class="fa-solid fa-eye"></i> Ver lotes</a>
            <?php elseif($n['tipo']==='receta'): ?>
              <a href="ventas_listar.php"><i class="fa-solid fa-eye"></i> Ver recetas</a>
            <?php else: ?>
              <a href="<?= htmlspecialchars($n['href']) ?>"><i class="fa-solid fa-arrow-right"></i> Abrir</a>
            <?php endif; ?>
            <a href="#" class="btnMark"><i class="fa-regular fa-circle-check"></i> Marcar leída</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">Listo</div>

<script>
const $=s=>document.querySelector(s), $$=s=>document.querySelectorAll(s);

/* Reloj */
setInterval(()=>{ const d=new Date(); const el=$("#clock"); if(el) el.textContent=d.toLocaleTimeString(); },1000);

/* Charts */
new Chart($("#line"),{
  type:'line',
  data:{labels: <?= json_encode($serie['labels']) ?>,
        datasets:[{data: <?= json_encode($serie['data']) ?>,
                   label:'$',
                   borderColor:'#00794f',
                   backgroundColor:'rgba(0,121,79,.12)',
                   tension:.35,fill:true,pointRadius:3}]},
  options:{plugins:{legend:{display:false}},
           scales:{x:{grid:{color:'rgba(0,0,0,.05)'}},
                   y:{grid:{color:'rgba(0,0,0,.05)'},beginAtZero:true}}}
});
new Chart($("#donut"),{
  type:'doughnut',
  data:{labels: <?= json_encode($stockCat['labels']) ?>,
        datasets:[{data: <?= json_encode($stockCat['data']) ?>,
                   backgroundColor:['#00a86b','#1bd48f','#55f3c2','#a0f7dc','#d7fff1','#7ee6c1','#34caaa','#2bb086']}]},
  options:{plugins:{legend:{position:'bottom',labels:{color:getComputedStyle(document.documentElement).getPropertyValue('--ink'),font:{size:11}}}}}
});

/* Sidebar: submenús */
$$(".has-sub").forEach(link=>{
  link.addEventListener("click",e=>{
    e.preventDefault();
    const li = link.parentElement;
    li.classList.toggle("open");
  });
});

/* Colapsar sidebar */
const app=$("#app");
$("#collapse").addEventListener('click',()=>{ app.classList.toggle('collapsed'); });

/* Off-canvas móvil */
const btnMobile=$("#btnMobileMenu"), btnCloseMobile=$("#btnCloseMobile"), backdrop=$("#backdrop");
function openMenu(){ document.body.classList.add('menu-open'); }
function closeMenu(){ document.body.classList.remove('menu-open'); }
btnMobile.addEventListener('click',openMenu);
btnCloseMobile.addEventListener('click',closeMenu);
backdrop.addEventListener('click',closeMenu);
window.addEventListener('keydown',e=>{ if(e.key==='Escape') closeMenu(); });

/* Búsqueda rápida */
document.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='/'){ e.preventDefault(); $("#q").focus(); }
});

/* Modal alertas */
const modal=$("#alertsModal");
$("#btnAlerts").addEventListener('click',e=>{ e.preventDefault(); modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); });
$("#closeAlerts").addEventListener('click',e=>{ e.preventDefault(); modal.style.display='none'; modal.setAttribute('aria-hidden','true'); });
modal.addEventListener('click',e=>{ if(e.target===modal){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }});
document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&modal.style.display==='flex'){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }});
$$('.btnMark').forEach(b=>b.addEventListener('click',e=>{
  e.preventDefault();
  const n=e.target.closest('.notice');
  if(n){ n.style.opacity=.4; n.style.filter='grayscale(1)'; showToast('Notificación marcada como leída'); }
}));

/* Tabs Panel de gestión controladas desde el menú lateral */
const tabBtns=$$('.tab-btn');
const panes={
  finanzas:$("#tab-finanzas"),
  top:$("#tab-top"),
  actividad:$("#tab-actividad"),
  tareas:$("#tab-tareas"),
  sistema:$("#tab-sistema")
};
tabBtns.forEach(btn=>{
  btn.addEventListener('click',()=>{
    const tab=btn.dataset.tab;
    tabBtns.forEach(b=>b.classList.remove('active'));
    Object.values(panes).forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    const pane=panes[tab]; if(pane) pane.classList.add('active');
  });
});

/* Toast */
function showToast(msg){
  const t=$("#toast");
  t.textContent=msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2200);
}

/* =======================================================
   FARVEC PRO – NAVEGACIÓN DINÁMICA A → B (2025)
   ======================================================= */

const mainContainer = document.querySelector('main.main');

/**
 * reverse = false  → entra desde la derecha (A → B)
 * reverse = true   → entra desde la izquierda (B → A)
 */
async function cargarModulo(url, title, opts = {}) {
  const { wrapTitle = true, reverse = false } = opts;

  // Dirección de salida
  const salida = reverse ? '40px' : '-40px';
  // Dirección de entrada
  const entrada = reverse ? '-40px' : '40px';

  // ANIMACIÓN DE SALIDA
  mainContainer.style.transition = 'transform .25s ease, opacity .25s ease';
  mainContainer.style.transform = `translateX(${salida})`;
  mainContainer.style.opacity = '0';

  setTimeout(async () => {
    try {
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error('No se pudo cargar el módulo.');
      const html = await res.text();

      // Insertar contenido
      if (wrapTitle) {
        mainContainer.innerHTML = `
          <section class="module-dynamic">
            <h2 style="color:#00794f;font-weight:800;margin-bottom:12px">${title}</h2>
            ${html}
          </section>
        `;
      } else {
        mainContainer.innerHTML = `
          <section class="module-dynamic">
            ${html}
          </section>
        `;
      }

      // Re-ejecutar scripts de la vista cargada
      const scripts = mainContainer.querySelectorAll('script');
      scripts.forEach(old => {
        const s = document.createElement('script');
        if (old.src) s.src = old.src;
        else s.textContent = old.textContent;
        old.parentNode.replaceChild(s, old);
      });

      // Re-init especial del stock si existe (no aplica mucho a cliente, pero no molesta)
      if (window.initStockYLotes) {
        window.__stockInit = false;
        window.initStockYLotes();
      }

      // ANIMACIÓN DE ENTRADA
      mainContainer.style.transition = 'transform .35s ease, opacity .35s ease';
      mainContainer.style.transform = `translateX(${entrada})`;

      setTimeout(() => {
        mainContainer.style.transform = 'translateX(0)';
        mainContainer.style.opacity = '1';
      }, 10);

      document.title = "FARVEC • " + title;
      closeMenu();

    } catch (err) {
      mainContainer.innerHTML = `
        <div class="panel" style="padding:20px;border-radius:16px;">
          <h3 style="color:#b93142"><i class="fa-solid fa-triangle-exclamation"></i> Error al cargar el módulo</h3>
          <p>${err.message}</p>
        </div>`;
      mainContainer.style.transform = 'translateX(0)';
      mainContainer.style.opacity = '1';
    }
  }, 250);
}

/* 1) Interceptar clicks del menú lateral (links .php) */
document.querySelectorAll('.sidebar a[href$=".php"]').forEach(link => {
  const url = link.getAttribute('href');
  if (!url) return;

  // No interceptamos logout ni el propio menú de cliente
  if (/logout\.php/i.test(url)) return;
  if (/menu_cliente\.php/i.test(url)) return;

  link.addEventListener('click', async e => {
    const isModal = link.hasAttribute('data-modal');
    if (isModal) return;

    e.preventDefault();
    const title = link.textContent.trim() || 'Módulo';
    cargarModulo(url, title, { wrapTitle: true });
  });
});

/* 2) Capturar botones internos .btn-module (dentro de los módulos cargados) */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.btn-module');
  if (!btn) return;

  e.preventDefault();
  const url = btn.dataset.href || btn.getAttribute('href');
  if (!url) return;

  const title = btn.dataset.title || btn.textContent.trim() || 'Módulo';
  cargarModulo(url, title, { wrapTitle: false });
});

/* Estilos dinámicos del contenedor */
const dynStyle = document.createElement('style');
dynStyle.textContent = `
  .module-dynamic {
    background:#ffffff;
    border:1px solid #e7eceb;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,121,79,.1);
    padding:20px;
    min-height:70vh;
    animation:slideIn .4s cubic-bezier(.25,.46,.45,.94);
  }
  @keyframes slideIn {
    from { transform:translateX(-35px); opacity:0; }
    to   { transform:translateX(0); opacity:1; }
  }
`;
document.head.appendChild(dynStyle);
</script>
</body>
</html>
