<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION["usuario_id"])) {
  header("Location: login.php");
  exit();
}

/* ------------------------------ DAO ------------------------------ */
class HistorialDAO {
  private mysqli $db;
  public function __construct(mysqli $db){ $this->db = $db; }

  public function productos(): mysqli_result {
    return $this->db->query("SELECT id, nombre FROM Producto ORDER BY nombre ASC");
  }

  public function movimientos(): array {
    $sql = "SELECT h.id, h.tipo, h.cantidad, h.detalle, h.fecha, p.nombre AS producto
            FROM HistorialStock h
            INNER JOIN Producto p ON p.id=h.producto_id
            ORDER BY h.fecha DESC, h.id DESC";
    return $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
  }
}

/* ------------------------------ INIT ------------------------------ */
$conn = new Conexion();
$dao  = new HistorialDAO($conn->conexion);

$productos = $dao->productos();
$movs      = $dao->movimientos();

/* KPIs */
$totalMov = count($movs);
$entradas = $salidas = 0;
foreach($movs as $m){
  if (in_array($m['tipo'], ['Alta','Compra'])) $entradas += $m['cantidad'];
  if (in_array($m['tipo'], ['Baja','Venta','Vencimiento','Devolución'])) $salidas += $m['cantidad'];
}
$balance = $entradas - $salidas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Historial de Movimientos - FARVEC</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
  --verde:#00a86b; --verdeOsc:#00794f; --verdeClaro:#b6f3da;
  --texto:#0a0a0a; --muted:#5e6b74; --borde:#e5e7eb;
}
body,html{
  height:100%;
  margin:0;
  font-family:"Segoe UI",system-ui;
  background:#f0f0f0;
}
#historial-mod{
  position:relative;
  min-height:100vh;
  width:100%;
  overflow:hidden;
  padding:40px 50px;
  box-sizing:border-box;
  color:var(--texto);
  animation:slideIn .5s ease both;
}
@keyframes slideIn{from{opacity:0;transform:translateX(-40px);}to{opacity:1;transform:none;}}

/* ===== HEADER ===== */
.header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:1.5rem;
}
.header h2{
  margin:0;
  color:var(--verdeOsc);
  font-weight:800;
  text-shadow:0 0 6px #8df5ca;
}
.btn{
  border:none;
  border-radius:12px;
  padding:10px 16px;
  font-weight:600;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.btn-primary{
  background:linear-gradient(90deg,var(--verdeOsc),var(--verde));
  color:#fff;
  box-shadow:0 6px 18px rgba(0,121,79,.25);
  transition:.25s;
}
.btn-primary:hover{transform:translateY(-2px);filter:brightness(1.05);}

/* ===== KPIs ===== */
.kpis{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:1rem;
  margin-bottom:1.5rem;
}
.kpi{
  display:flex;
  align-items:center;
  gap:14px;
  background:linear-gradient(180deg,#ffffff,#f0fff7);
  border:1px solid #cceedd;
  border-radius:16px;
  padding:18px;
  box-shadow:0 6px 18px rgba(0,0,0,.08);
}
.kpi i{
  font-size:26px;
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  color:#fff;
  padding:14px;
  border-radius:12px;
  box-shadow:0 5px 15px rgba(0,121,79,.3);
}
.kpi .info p{margin:0;font-size:13px;color:#374151;}
.kpi .info h4{margin:0;font-weight:800;color:var(--texto);}

/* ===== FILTROS ===== */
.filters{
  display:flex;
  flex-wrap:wrap;
  gap:.6rem;
  margin-bottom:1rem;
}
.filters input,.filters select{
  padding:.6rem .8rem;
  border-radius:.6rem;
  border:1px solid #cceedd;
  background:#fff;
}
.filters .btn-primary{padding:.6rem 1rem;}

/* ===== TABLA ===== */
.panel{
  background:#fff;
  border-radius:18px;
  box-shadow:0 6px 18px rgba(0,0,0,.07);
  padding:1rem;
}
table{
  width:100%;
  border-collapse:collapse;
}
thead th{
  background:var(--verdeClaro);
  color:#064e3b;
  text-align:center;
  padding:10px;
}
td{
  padding:10px;
  text-align:center;
  border-top:1px solid #e5e7eb;
}
tr:hover{background:#e8fff1;}

/* ===== BOTÓN IMPRIMIR ===== */
.btn-float{
  margin-top:20px;
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:var(--verde);
  color:#fff;
  border:none;
  border-radius:999px;
  padding:10px 14px;
  box-shadow:0 10px 24px rgba(0,121,79,.30);
  cursor:pointer;
  transition:.25s;
}
.btn-float:hover{transform:translateY(-2px);}
</style>
</head>
<body>

<div id="historial-mod">
  <div class="header">
    <h2><i class="fa-solid fa-clipboard-list"></i> Historial de Movimientos</h2>
    <button class="btn btn-primary" onclick="location.href='Menu.php'">
      <i class="fa-solid fa-arrow-left"></i> Volver al Menú
    </button>
  </div>

  <div class="kpis">
    <div class="kpi"><i class="fa-solid fa-database"></i><div class="info"><p>Total Movimientos</p><h4><?= $totalMov ?></h4></div></div>
    <div class="kpi"><i class="fa-solid fa-box-open"></i><div class="info"><p>Entradas</p><h4><?= $entradas ?></h4></div></div>
    <div class="kpi"><i class="fa-solid fa-dolly"></i><div class="info"><p>Salidas</p><h4><?= $salidas ?></h4></div></div>
    <div class="kpi"><i class="fa-solid fa-scale-balanced"></i><div class="info"><p>Balance</p><h4 style="color:<?= $balance>=0?'#065f46':'#991b1b' ?>"><?= $balance ?></h4></div></div>
  </div>

  <!-- Filtros con búsqueda instantánea -->
  <div class="filters">
    <input type="text" id="buscar" placeholder="Buscar detalle / producto...">
    <select id="producto">
      <option value="">Todos los productos</option>
      <?php while($p=$productos->fetch_assoc()): ?>
      <option value="<?= htmlspecialchars($p['nombre']) ?>"><?= htmlspecialchars($p['nombre']) ?></option>
      <?php endwhile; ?>
    </select>
    <select id="tipo">
      <option value="">Todos los tipos</option>
      <?php foreach(['Alta','Baja','Venta','Compra','Devolución','Vencimiento'] as $t): ?>
      <option><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" id="desde">
    <input type="date" id="hasta">
  </div>

  <div class="panel">
    <table id="tabla">
      <thead>
        <tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Detalle</th><th>Fecha</th></tr>
      </thead>
      <tbody>
        <?php if(!$movs): ?>
          <tr><td colspan="6">No hay movimientos registrados</td></tr>
        <?php else: foreach($movs as $m): ?>
          <tr>
            <td><?= $m['id'] ?></td>
            <td><?= htmlspecialchars($m['producto']) ?></td>
            <td><?= htmlspecialchars($m['tipo']) ?></td>
            <td><?= $m['cantidad'] ?></td>
            <td><?= htmlspecialchars($m['detalle']) ?></td>
            <td><?= $m['fecha'] ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <button class="btn-float" onclick="window.print()">
    <i class="fa-solid fa-print"></i> Imprimir
  </button>
</div>

<script>
const q=document.getElementById('buscar'),
      producto=document.getElementById('producto'),
      tipo=document.getElementById('tipo'),
      desde=document.getElementById('desde'),
      hasta=document.getElementById('hasta'),
      filas=[...document.querySelectorAll('#tabla tbody tr')];

function norm(s){return (s||'').toLowerCase();}
function toDate(v){return v?new Date(v):null;}

function filtrar(){
  const qv=norm(q.value), pv=norm(producto.value), tv=norm(tipo.value);
  const d1=toDate(desde.value), d2=toDate(hasta.value);
  filas.forEach(r=>{
    const [id,prod,tip,cant,det,fecha]=[...r.children].map(td=>norm(td.textContent));
    const f=new Date(fecha.replace(/-/g,'/'));
    let ok=true;
    if(qv && !prod.includes(qv) && !det.includes(qv)) ok=false;
    if(pv && prod!==pv) ok=false;
    if(tv && tip!==tv) ok=false;
    if(d1 && f<d1) ok=false;
    if(d2 && f>d2) ok=false;
    r.style.display=ok?'':'none';
  });
}
[q,producto,tipo,desde,hasta].forEach(el=>el.addEventListener('input',filtrar));
</script>
</body>
</html>
