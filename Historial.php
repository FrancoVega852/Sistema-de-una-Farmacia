<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION["usuario_id"])) {
  header("Location: login.php");
  exit();
}

/* ------------------------------
   Data Access Object
--------------------------------*/
class HistorialDAO {
  private mysqli $db;
  public function __construct(mysqli $db){ $this->db = $db; }

  public function productos(): mysqli_result {
    return $this->db->query("SELECT id, nombre FROM Producto ORDER BY nombre");
  }

  // Movimientos con filtros seguros
  public function movimientos(?int $producto_id, ?string $tipo, ?string $desde, ?string $hasta, ?string $q): array {
    $sql = "SELECT h.id, h.tipo, h.cantidad, h.detalle, h.fecha, p.nombre AS producto
            FROM HistorialStock h
            INNER JOIN Producto p ON p.id=h.producto_id
            WHERE 1=1";
    $params = []; $types = "";

    if ($producto_id) { $sql .= " AND p.id = ?"; $params[] = $producto_id; $types .= "i"; }

    if ($tipo) {
      // Whitelist de tipos
      $permitidos = ['Alta','Baja','Venta','Compra','Devolución','Vencimiento'];
      if (in_array($tipo, $permitidos, true)) {
        $sql .= " AND h.tipo = ?";
        $params[] = $tipo; $types .= "s";
      }
    }

    if ($desde) { $sql .= " AND DATE(h.fecha) >= ?"; $params[] = $desde; $types .= "s"; }
    if ($hasta) { $sql .= " AND DATE(h.fecha) <= ?"; $params[] = $hasta; $types .= "s"; }

    if ($q) { $sql .= " AND (h.detalle LIKE CONCAT('%', ?, '%') OR p.nombre LIKE CONCAT('%', ?, '%'))";
      $params[] = $q; $params[] = $q; $types .= "ss"; }

    $sql .= " ORDER BY h.fecha DESC, h.id DESC";

    $stmt = $this->db->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    return $rows;
  }
}

/* ------------------------------
   Inicialización + Filtros
--------------------------------*/
$conn = new Conexion();
$dao  = new HistorialDAO($conn->conexion);

$producto_id = isset($_GET['producto_id']) && $_GET['producto_id']!=="" ? (int)$_GET['producto_id'] : null;
$tipo        = $_GET['tipo']   ?? null;
$desde       = $_GET['desde']  ?? null;
$hasta       = $_GET['hasta']  ?? null;
$q           = trim($_GET['q'] ?? "");

// Datos
$productos  = $dao->productos();
$movs       = $dao->movimientos($producto_id, $tipo, $desde, $hasta, $q);

// KPIs
$totalMov   = count($movs);
$entradas   = 0; // Alta + Compra (+ Devolución si prefieres considerarla entrada)
$salidas    = 0; // Baja + Venta + Vencimiento (+ Devolución si prefieres salida)
foreach($movs as $m){
  switch($m['tipo']){
    case 'Alta':
    case 'Compra':
      $entradas += (int)$m['cantidad']; break;
    case 'Baja':
    case 'Venta':
    case 'Vencimiento':
      $salidas  += (int)$m['cantidad']; break;
    case 'Devolución':
      // Puedes elegir cómo contabilizarla; aquí la considero salida
      $salidas  += (int)$m['cantidad']; break;
  }
}
$balance = $entradas - $salidas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Historial de Movimientos - Farvec</title>
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
  color:var(--text); min-height:100vh; animation:fade .3s ease
}
.container{max-width:1300px;margin:0 auto;padding:14px}
.topbar{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.btn{border:1px solid var(--borde); background:#fff; color:#111; padding:10px 12px; border-radius:10px; cursor:pointer}
.btn i{margin-right:6px}
.btn.primary{background:var(--verde); border-color:var(--verde); color:#fff}
.btn.ghost{background:#fff}
.btn:hover{filter:brightness(.98)}
.title{display:flex;align-items:center;gap:10px;font-size:22px;color:#0f5132}
.title i{color:var(--verde-oscuro)}

.grid{display:grid; grid-template-columns: 1fr; gap:12px}

/* Card */
.card{background:var(--card); border:1px solid var(--borde); border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.06); overflow:hidden; animation:rise .35s ease}
.card-header{display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--borde)}
.card-body{padding:14px}

/* KPIs */
.kpis{display:grid; grid-template-columns:repeat(4,1fr); gap:10px}
@media (max-width:900px){ .kpis{grid-template-columns:repeat(2,1fr)} }
.kpi{padding:14px; background:#f8fafc; border:1px dashed var(--borde); border-radius:12px}
.kpi .label{color:var(--muted); font-size:12px}
.kpi .value{font-weight:700; font-size:18px; margin-top:4px}
.kpi .hint{font-size:11px; color:var(--muted)}

/* Filtros */
.filters{display:grid; grid-template-columns: 2fr 1.2fr 1.2fr 1.2fr 1fr; gap:8px}
@media (max-width:1000px){ .filters{grid-template-columns:1fr 1fr 1fr 1fr} }
@media (max-width:700px){ .filters{grid-template-columns:1fr 1fr} }

.input, select{padding:10px 12px;border:1px solid var(--borde);border-radius:10px;outline:none;background:#fff}
.actions{display:flex; gap:8px; flex-wrap:wrap; align-items:center}

/* Tabla */
.table{width:100%; border-collapse:collapse}
.table th,.table td{padding:12px; border-bottom:1px solid var(--borde); text-align:left; font-size:14px}
.table th{background:#eaf7ef; color:#064e3b; font-weight:700; position:sticky; top:0}
.table tr:hover{background:#f9fafb}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;border:1px solid var(--borde);font-size:12px;background:#fff}
.b-alta{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.b-compra{background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a}
.b-venta,.b-baja,.b-venc{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.b-devol{background:#fff7ed;border-color:#fed7aa;color:#9a3412}

.footerbar{display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:10px; color:var(--muted); font-size:12px}
.pager{display:flex;align-items:center;gap:6px}
.pager button{padding:6px 10px;border:1px solid var(--borde);background:#fff;border-radius:8px;cursor:pointer}
.pager button[disabled]{opacity:.5;cursor:not-allowed}

/* Print */
@media print{
  .topbar, .card-header .actions, .footerbar {display:none !important}
  .card{box-shadow:none;border-color:#ddd}
  body{background:#fff}
}

/* Anim */
@keyframes fade{from{opacity:0} to{opacity:1}}
@keyframes rise{from{transform:translateY(6px); opacity:0} to{transform:none; opacity:1}}
</style>
</head>
<body>
<div class="container">

  <!-- Header -->
  <div class="topbar">
    <a href="Menu.php" class="btn"><i class="fa-solid fa-arrow-left"></i> Volver al Menú</a>
    <div class="title"><i class="fa-solid fa-clipboard-list"></i><strong>Historial de Movimientos de Stock</strong></div>
  </div>

  <!-- KPIs -->
  <section class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:8px">
        <i class="fa-solid fa-chart-line" style="color:#006837"></i><strong>Resumen</strong>
      </div>
    </div>
    <div class="card-body">
      <div class="kpis">
        <div class="kpi">
          <div class="label">Movimientos</div>
          <div class="value"><?= number_format($totalMov) ?></div>
          <div class="hint">Coinciden con los filtros</div>
        </div>
        <div class="kpi">
          <div class="label">Entradas</div>
          <div class="value"><?= number_format($entradas) ?></div>
          <div class="hint">Alta / Compra</div>
        </div>
        <div class="kpi">
          <div class="label">Salidas</div>
          <div class="value"><?= number_format($salidas) ?></div>
          <div class="hint">Venta / Baja / Venc.</div>
        </div>
        <div class="kpi">
          <div class="label">Balance</div>
          <div class="value" style="color:<?= $balance>=0?'#065f46':'#991b1b' ?>"><?= number_format($balance) ?></div>
          <div class="hint"><?= $balance>=0?'(+ stock)':'(- stock)' ?></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Tabla + Filtros -->
  <section class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:8px">
        <i class="fa-solid fa-database" style="color:#006837"></i><strong>Movimientos</strong>
      </div>
      <div class="actions">
        <button class="btn" onclick="exportCSV()"><i class="fa-solid fa-file-csv"></i> Exportar CSV</button>
        <button class="btn primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
      </div>
    </div>
    <div class="card-body">

      <!-- Filtros -->
      <form class="filters" method="GET">
        <input class="input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar detalle / producto…">
        <select class="input" name="producto_id">
          <option value="">Todos los productos</option>
          <?php while($p=$productos->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $producto_id==$p['id']?'selected':'' ?>>
              <?= htmlspecialchars($p['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <select class="input" name="tipo">
          <option value="">Todos los tipos</option>
          <?php foreach (['Alta','Baja','Venta','Compra','Devolución','Vencimiento'] as $t): ?>
            <option <?= $tipo===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
        <input class="input" type="date" name="desde" value="<?= htmlspecialchars($desde ?? '') ?>" title="Desde">
        <input class="input" type="date" name="hasta" value="<?= htmlspecialchars($hasta ?? '') ?>" title="Hasta">
        <div class="actions">
          <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
          <a class="btn ghost" href="Historial.php"><i class="fa-solid fa-rotate-left"></i> Limpiar</a>
        </div>
      </form>

      <!-- Tabla -->
      <div style="overflow:auto; max-height:60vh; border:1px solid var(--borde); border-radius:12px; margin-top:10px">
        <table class="table" id="tabla">
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th>Producto</th>
              <th style="width:140px">Tipo</th>
              <th style="width:110px">Cantidad</th>
              <th>Detalle</th>
              <th style="width:170px">Fecha</th>
            </tr>
          </thead>
          <tbody id="tbody">
          <?php if ($movs): foreach($movs as $m): 
            $badgeClass = match($m['tipo']){
              'Alta'        => 'b-alta',
              'Compra'      => 'b-compra',
              'Venta'       => 'b-venta',
              'Baja'        => 'b-baja',
              'Vencimiento' => 'b-venc',
              'Devolución'  => 'b-devol',
              default       => ''
            };
          ?>
            <tr>
              <td><?= (int)$m['id'] ?></td>
              <td><?= htmlspecialchars($m['producto']) ?></td>
              <td><span class="badge <?= $badgeClass ?>"><i class="fa-solid fa-tag"></i><?= htmlspecialchars($m['tipo']) ?></span></td>
              <td><?= (int)$m['cantidad'] ?></td>
              <td><?= htmlspecialchars($m['detalle'] ?: '-') ?></td>
              <td><?= htmlspecialchars($m['fecha']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:16px">No hay movimientos con los filtros actuales.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer tabla -->
      <div class="footerbar">
        <div>Mostrando <strong id="range">1–<?= max(0, min(25, $totalMov)) ?></strong> de <strong id="totalRows"><?= $totalMov ?></strong> movimientos</div>
        <div class="actions">
          <label class="small">Filtrado rápido:</label>
          <input id="quick" class="input" placeholder="Escribe para filtrar aquí…">
          <label>Filas:</label>
          <select id="pageSize" class="input" style="width:90px">
            <option>10</option><option selected>25</option><option>50</option><option>100</option>
          </select>
          <div class="pager">
            <button id="prev">&lt;</button>
            <span id="pageInfo">1 / 1</span>
            <button id="next">&gt;</button>
          </div>
        </div>
      </div>

    </div>
  </section>

</div>

<script>
// Paginación + filtro rápido (en cliente)
const tbody = document.getElementById('tbody');
const rows = Array.from(tbody.querySelectorAll('tr'));
const pageSizeSel = document.getElementById('pageSize');
const quick = document.getElementById('quick');
const pageInfo = document.getElementById('pageInfo');
const prev = document.getElementById('prev');
const next = document.getElementById('next');
const range = document.getElementById('range');
const totalRowsEl = document.getElementById('totalRows');

let filtered = rows.slice();
let page = 1;

function applyQuick(){
  const t = (quick.value || '').toLowerCase().trim();
  filtered = rows.filter(r => r.innerText.toLowerCase().includes(t));
  page = 1;
  render();
}
quick.addEventListener('input', applyQuick);

function render(){
  const pageSize = parseInt(pageSizeSel.value,10);
  const total = filtered.length;
  const maxPage = Math.max(1, Math.ceil(total / pageSize));
  if (page > maxPage) page = maxPage;

  // ocultar/mostrar
  rows.forEach(r => r.style.display = 'none');
  const start = (page-1)*pageSize;
  const end   = Math.min(total, start + pageSize);
  filtered.slice(start, end).forEach(r => r.style.display = '');
  pageInfo.textContent = `${page} / ${maxPage}`;
  prev.disabled = (page===1);
  next.disabled = (page===maxPage);
  range.textContent = total ? `${start+1}–${end}` : '0–0';
  totalRowsEl.textContent = total;
}
pageSizeSel.addEventListener('change', ()=>{ page=1; render(); });
prev.addEventListener('click', ()=>{ if(page>1){page--; render();} });
next.addEventListener('click', ()=>{ const ps=parseInt(pageSizeSel.value,10); if(page<Math.ceil(filtered.length/ps)){page++; render();} });

render();

// Exportar CSV de lo actualmente visible (respeta filtro rápido + paginación si quieres todo/visible)
function exportCSV(){
  // Por defecto exportamos TODO lo filtrado (no sólo la página actual)
  const data = filtered.map(tr => Array.from(tr.children).map(td => td.innerText.replace(/\s+/g,' ').trim()));
  const headers = Array.from(document.querySelectorAll('#tabla thead th')).map(th => th.innerText.trim());
  const rowsCsv = [headers].concat(data);
  const csv = rowsCsv.map(r => r.map(cell => `"${cell.replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'historial_movimientos.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}
</script>
</body>
</html>
