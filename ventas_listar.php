<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'ControladorVentas.php';

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$conn = new Conexion();
$ctl  = new ControladorVentas($conn->conexion);
$res  = $ctl->listar();

// Normalizamos a array
$VENTAS = [];
while ($v = $res->fetch_assoc()) {
  $VENTAS[] = [
    'id'      => (int)$v['id'],
    'cliente' => $v['cliente'] ?: 'Consumidor Final',
    'usuario' => $v['usuario'],
    'total'   => (float)$v['total'],
    'fecha'   => $v['fecha'],
    'estado'  => $v['estado'],
  ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Listado de Ventas - Farvec</title>
<style>
:root{
  --verde:#16a34a; --verde-osc:#15803d;
  --azul:#2563eb; --naranja:#f59e0b; --rojo:#dc2626;
  --bg:#f0fdf4; --card:#ffffff; --borde:#e5e7eb;
  --texto:#111827; --muted:#6b7280;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:Segoe UI,system-ui,-apple-system,sans-serif;
  background:linear-gradient(135deg,#e0f7fa,#f0fdf4);
  color:var(--texto);
  min-height:100vh;
  animation:fadeIn .8s ease;
}
a,button{cursor:pointer}
.container{max-width:1300px;margin:0 auto;padding:16px}

/* Animaciones */
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.03)}}

/* Top */
.top{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.btn-volver{background:var(--verde-osc);color:#fff;border:0;border-radius:10px;padding:10px 14px;
  box-shadow:0 6px 18px rgba(0,0,0,.08);text-decoration:none;transition:.2s}
.btn-volver:hover{animation:pulse .5s}
.title{display:flex;align-items:center;gap:12px}
.title h1{font-size:26px;margin:0;color:var(--verde-osc)}
.tag{font-size:13px;color:var(--muted)}
.btn.primary{background:var(--verde);color:#fff;border:none;padding:10px 14px;border-radius:10px;font-weight:600;transition:.2s}
.btn.primary:hover{opacity:.9;transform:translateY(-1px)}

/* KPIs */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}
@media(max-width:1100px){.kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.kpis{grid-template-columns:1fr}}
.kpi{border-radius:14px;padding:16px;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.08);animation:fadeIn .6s}
.kpi .lbl{font-size:13px;opacity:.9}
.kpi .val{font-size:22px;font-weight:700}
.kpi.green{background:var(--verde)}
.kpi.blue{background:var(--azul)}
.kpi.orange{background:var(--naranja)}
.kpi.red{background:var(--rojo)}

/* Panel filtros */
.panel{background:var(--card);border:1px solid var(--borde);border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,.06);overflow:hidden}
.panel-h{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;border-bottom:1px solid var(--borde);padding:12px}
.h-left{display:flex;flex-wrap:wrap;gap:8px}
.input,select{padding:10px 12px;border:1px solid var(--borde);border-radius:10px;outline:none}
.input:focus,select:focus{border-color:var(--verde)}
.chip{display:inline-flex;gap:6px;align-items:center;padding:8px 10px;border:1px solid var(--borde);border-radius:999px;background:#fff}
.chip input{accent-color:var(--verde)}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--borde);background:#fff;transition:.2s}
.btn:hover{transform:translateY(-1px)}
.btn.warn{background:var(--naranja);color:#fff;border:none}
.btn.red{background:var(--rojo);color:#fff;border:none}

/* Tabla */
.table-wrap{overflow:auto;max-height:60vh}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid var(--borde);font-size:14px}
thead th{background:#f0fdf4;color:#064e3b;position:sticky;top:0;z-index:1}
tbody tr:nth-child(odd){background:#fff}
tbody tr:nth-child(even){background:#f9fafb}
tbody tr:hover{background:#ecfdf5}
.right{text-align:right}
.center{text-align:center}
th.sort{cursor:pointer}

/* Badges */
.badge{padding:6px 10px;border-radius:999px;font-size:12px;display:inline-block}
.b-ok{background:#dcfce7;color:#065f46}
.b-pend{background:#fef9c3;color:#92400e}
.b-cancel{background:#fee2e2;color:#991b1b}

/* Acciones */
.row-actions{display:flex;gap:8px;justify-content:center}
.action{border:1px solid var(--borde);background:#fff;border-radius:8px;padding:6px 10px;text-decoration:none;font-size:13px}
.action:hover{background:#f0fdf4}
.action.view{color:var(--verde-osc)}
.action.print{color:var(--muted)}

/* Paginación */
.pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;padding:10px;border-top:1px solid var(--borde);background:#fafafa}
.select-sm{height:38px}
.footer{margin-top:16px;text-align:center;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div class="container">

  <!-- Top -->
  <div class="top">
    <a class="btn-volver" href="menu.php">⬅ Volver al Menú</a>
    <div class="title"><h1>Listado de Ventas</h1><span class="tag">Gestión • búsqueda • métricas • exportación</span></div>
    <a class="btn primary" style="margin-left:auto" href="ventas.php">+ Registrar Venta</a>
  </div>

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi green"><div class="lbl">Ventas (filtro)</div><div id="k-cant" class="val">0</div></div>
    <div class="kpi blue"><div class="lbl">Importe total (filtro)</div><div id="k-total" class="val">$0,00</div></div>
    <div class="kpi orange"><div class="lbl">Ticket promedio</div><div id="k-avg" class="val">$0,00</div></div>
    <div class="kpi red"><div class="lbl">Hoy</div><div id="k-hoy" class="val">$0,00</div></div>
  </div>

  <!-- Panel -->
  <div class="panel">
    <div class="panel-h">
      <div class="h-left">
        <input id="q" class="input" type="search" placeholder="Buscar cliente / usuario / ID" />
        <select id="estado">
          <option value="">Todos los estados</option>
          <option>Pagada</option>
          <option>Pendiente</option>
          <option>Cancelada</option>
        </select>
        <input id="desde" class="input" type="date" />
        <input id="hasta" class="input" type="date" />
        <label class="chip"><input id="solo-hoy" type="checkbox"> Solo hoy</label>
      </div>
      <div class="h-right" style="display:flex;gap:8px">
        <button id="btn-csv" class="btn">⬇ Exportar CSV</button>
        <button id="btn-clear" class="btn warn">↺ Limpiar filtros</button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="table-wrap">
      <table id="tbl">
        <thead>
          <tr>
            <th class="center"><input id="chk-all" type="checkbox"></th>
            <th class="sort" data-k="id">ID</th>
            <th class="sort" data-k="cliente">Cliente</th>
            <th class="sort" data-k="usuario">Usuario</th>
            <th class="sort right" data-k="total">Total</th>
            <th class="sort" data-k="fecha">Fecha</th>
            <th class="sort center" data-k="estado">Estado</th>
            <th class="center">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="pager">
      <span class="tag" id="range">0–0 de 0</span>
      <select id="perPage" class="select-sm">
        <option value="10">10 por página</option>
        <option value="25">25 por página</option>
        <option value="50">50 por página</option>
      </select>
      <button id="prev" class="btn">◀</button>
      <button id="next" class="btn">▶</button>
      <button id="btn-multi-print" class="btn">🖨 Imprimir</button>
      <button id="btn-multi-del" class="btn red">✖ Anular</button>
    </div>
  </div>

  <div class="footer">Farvec • Panel de ventas • <?= date('Y') ?></div>
</div>

<script>
const SALES = <?php echo json_encode($VENTAS, JSON_UNESCAPED_UNICODE); ?>;
const $=s=>document.querySelector(s);const $$=s=>document.querySelectorAll(s);
const money=n=>n.toLocaleString('es-AR',{style:'currency',currency:'ARS'});
const fmtFecha=s=>new Intl.DateTimeFormat('es-AR',{dateStyle:'short',timeStyle:'short'}).format(new Date(s.replace(' ','T')));

let q='',estado='',page=1,perPage=10,sortK='fecha',sortDir='desc';let checked=new Set();
const qEl=$('#q'),estadoEl=$('#estado'),tbody=$('#tbody'),rangeEl=$('#range'),perEl=$('#perPage');

[qEl,estadoEl,perEl].forEach(el=>el.addEventListener('input',()=>{q=qEl.value.toLowerCase();estado=estadoEl.value;perPage=parseInt(perEl.value,10)||10;page=1;render()}));
$$('th.sort').forEach(th=>th.addEventListener('click',()=>{const k=th.dataset.k;sortK===k?sortDir=(sortDir==='asc'?'desc':'asc'):(sortK=k,sortDir='asc');render()}));

function filtered(){let rows=SALES.slice();if(q)rows=rows.filter(r=>r.cliente.toLowerCase().includes(q)||r.usuario.toLowerCase().includes(q)||String(r.id).includes(q));
if(estado)rows=rows.filter(r=>r.estado===estado);rows.sort((a,b)=>{let s=0;if(sortK==='total'||sortK==='id')s=a[sortK]-b[sortK];else if(sortK==='fecha')s=a.fecha.localeCompare(b.fecha);else s=String(a[sortK]).localeCompare(String(b[sortK]));return sortDir==='asc'?s:-s});return rows;}
function renderKPIs(rows){$('#k-cant').textContent=rows.length;$('#k-total').textContent=money(rows.reduce((a,b)=>a+b.total,0));$('#k-avg').textContent=money(rows.length?rows.reduce((a,b)=>a+b.total,0)/rows.length:0);$('#k-hoy').textContent=money(rows.filter(r=>r.fecha.slice(0,10)===new Date().toISOString().slice(0,10)).reduce((a,b)=>a+b.total,0));}
function renderRows(rows){tbody.innerHTML='';if(!rows.length){tbody.innerHTML='<tr><td colspan="8" class="center" style="color:#6b7280;padding:18px">No hay resultados</td></tr>';return;}
rows.forEach(r=>{tbody.innerHTML+=`<tr><td class="center"><input type="checkbox"></td><td>${r.id}</td><td>${r.cliente}</td><td>${r.usuario}</td><td class="right">${money(r.total)}</td><td>${fmtFecha(r.fecha)}</td><td class="center">${r.estado==='Pagada'?'<span class="badge b-ok">Pagada</span>':r.estado==='Pendiente'?'<span class="badge b-pend">Pendiente</span>':'<span class="badge b-cancel">'+r.estado+'</span>'}</td><td class="row-actions"><a class="action view" href="ventas_ver.php?id=${r.id}">Ver</a><a class="action print" href="ventas_ver.php?id=${r.id}&print=1" target="_blank">Imp</a></td></tr>`});}
function render(){const rows=filtered();renderKPIs(rows);const total=rows.length;const pages=Math.ceil(total/perPage)||1;if(page>pages)page=pages;const ini=(page-1)*perPage;renderRows(rows.slice(ini,ini+perPage));rangeEl.textContent=`${total?ini+1:0}–${Math.min(ini+perPage,total)} de ${total}`;}
render();
</script>
</body>
</html>
