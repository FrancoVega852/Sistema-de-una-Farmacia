<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';  // usa tu clase actual

// ---- Seguridad de sesión
if (!isset($_SESSION['usuario_id'])) {
  header('Location: login.php');
  exit();
}
$rol = $_SESSION['usuario_rol'] ?? 'Empleado';

// ---- Conexión y modelos
$conn = new Conexion();
$db   = $conn->conexion;
$productoModel = new Producto($db);

// ---- Traer categorías (para filtro)
$categoriasRes = $db->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");

// ---- Traer productos + lotes
$res = $productoModel->obtenerProductosConLotes();

// ---- Agrupar y calcular
$productos = []; $kpi_total=$kpi_bajo=$kpi_vto30=$kpi_venc=0;
if ($res && $res instanceof mysqli_result) {
  while ($row = $res->fetch_assoc()) {
    $pid = (int)$row['id'];
    if (!isset($productos[$pid])) {
      $productos[$pid] = [
        'id'=>$pid,
        'nombre'=>$row['nombre'],
        'precio'=>(float)$row['precio'],
        'stock_actual'=>(int)$row['stock_actual'],
        'stock_minimo'=>(int)$row['stock_minimo'],
        'categoria'=>$row['categoria'] ?? null,
        'lotes'=>[]
      ];
    }
    if (!empty($row['numero_lote'])) {
      $productos[$pid]['lotes'][] = [
        'numero_lote'=>$row['numero_lote'],
        'fecha_vencimiento'=>$row['fecha_vencimiento'],
        'cantidad_actual'=>(int)$row['cantidad_actual'],
      ];
    }
  }
}

// ---- KPIs y estados
$today = new DateTime('today');
foreach ($productos as &$p) {
  $kpi_total++;
  if ($p['stock_actual'] <= $p['stock_minimo']) {
    $p['estado_stock']='bajo'; $kpi_bajo++;
  } else $p['estado_stock']='ok';

  $p['lote_proximo']=null; $p['estado_vto']='sin-lote';
  if (!empty($p['lotes'])) {
    usort($p['lotes'],fn($a,$b)=>strcmp($a['fecha_vencimiento'],$b['fecha_vencimiento']));
    $first=$p['lotes'][0]; $p['lote_proximo']=$first;
    $vto=DateTime::createFromFormat('Y-m-d',$first['fecha_vencimiento']);
    if ($vto) {
      if ($vto<$today){$p['estado_vto']='vencido';$kpi_venc++;}
      else{
        $diff=(int)$today->diff($vto)->format('%a');
        if($diff<=30){$p['estado_vto']='por-vencer';$kpi_vto30++;}
        else $p['estado_vto']='ok';
      }
    }
  }
}
unset($p);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Stock y Lotes - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --verde:#008f4c; --verde-oscuro:#006837; --acento:#e85c4a;
  --blanco:#fff; --gris:#f5f7f8; --texto:#1f2937; --gris-borde:#e5e7eb;
  --azul:#2563eb; --naranja:#f59e0b; --amarillo:#eab308; --rojo:#dc2626;
}
*{box-sizing:border-box}
html,body{height:100%;min-height:100vh;}
html{background:linear-gradient(135deg, #f7fafc, #eefdf5);background-attachment:fixed;}
body{margin:0;font-family:Segoe UI,system-ui,-apple-system,sans-serif;background:transparent;color:var(--texto);}

/* Fondo animado */
html::before {
  content:'';position:fixed;top:0;left:0;width:100%;height:100vh;
  background-image:radial-gradient(circle at 20% 80%, rgba(0,143,76,0.05) 0%, transparent 50%),
                   radial-gradient(circle at 80% 20%, rgba(37,99,235,0.05) 0%, transparent 50%);
  background-size:600px 600px, 700px 700px;
  background-position:0px 0px, 300px 300px;
  animation:floatingBg 25s ease-in-out infinite;z-index:-1;
}
@keyframes floatingBg {
  0%,100%{transform:translate(0,0)}
  33%{transform:translate(30px,-15px)}
  66%{transform:translate(-20px,20px)}
}

.container{max-width:1200px;margin:auto;padding:20px}

/* Top bar */
.topbar{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.back{background:var(--verde-oscuro);color:#fff;padding:10px 14px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.08);text-decoration:none}
.title{display:flex;align-items:center;gap:12px}
.title i{color:var(--verde-oscuro);font-size:26px}
.title h1{margin:0;font-size:28px;color:#0f5132}

/* KPI CARDS */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:16px 0}
.kpi{border-radius:14px;padding:16px;display:flex;align-items:center;gap:12px;color:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12);animation:fadeInUp .8s ease both}
.kpi i{font-size:22px}
.kpi h3{margin:0;font-size:14px;opacity:.9}
.kpi span{font-size:20px;font-weight:800}
.kpi.prod{background:var(--azul);}
.kpi.low{background:var(--naranja);}
.kpi.vto{background:var(--amarillo);color:#000;}
.kpi.exp{background:var(--rojo);}

/* Toolbar */
.toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid var(--gris-borde);border-radius:14px;padding:12px;margin-bottom:14px}
.input,select{padding:10px;border:1px solid var(--gris-borde);border-radius:10px}
.actions{margin-left:auto;display:flex;gap:8px}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--gris-borde);background:#fff;cursor:pointer;transition:all .2s ease}
.btn.primary{background:var(--verde);color:#fff;border:none}
.btn:hover{transform:scale(1.05);box-shadow:0 4px 10px rgba(0,0,0,.1)}

/* Tabla */
.table-wrap{margin-top:14px;background:#fff;border:1px solid var(--gris-borde);border-radius:14px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,.08)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px;font-size:14px;text-align:left;border-bottom:1px solid var(--gris-borde)}
th{background:var(--verde-oscuro);color:#fff}

/* Filas del inventario */
tbody tr{
  background:#fff;
  transition:all .25s ease-in-out;
  animation:fadeInUp .6s ease both;
}
tbody tr:nth-child(even){background:#f9fafb;}
tbody tr:hover{
  background:#f0fdf4;
  transform:scale(1.01);
  box-shadow:0 4px 12px rgba(0,0,0,.12);
  z-index:1;
}

/* Animaciones */
@keyframes fadeInUp {
  from {opacity:0; transform:translateY(10px);}
  to {opacity:1; transform:translateY(0);}
}

/* Tags */
.tag{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid #e5e7eb;animation:fadeIn .5s ease}
.tag.low{background:#fff7ed;color:#c2410c}
.tag.ok{background:#ecfeff;color:#0369a1}
.tag.exp{background:#fee2e2;color:#991b1b}
.tag.vto{background:#fef9c3;color:#a16207}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* Row actions */
.row-actions{display:flex;gap:8px;flex-wrap:wrap}
.pill{padding:6px 10px;border-radius:999px;font-size:12px;border:none;cursor:pointer;transition:all .25s ease}
.pill.green{background:#2563eb;color:#fff}
.pill.blue{background:#059669;color:#fff}
.pill.red{background:#ef4444;color:#fff}
.pill:hover{transform:scale(1.05);box-shadow:0 2px 8px rgba(0,0,0,.15)}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:20px}
.modal.open{display:flex}
.modal-card{background:#fff;max-width:720px;width:100%;border-radius:14px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.25);animation:fadeInUp .4s ease}
.modal-header{display:flex;justify-content:space-between;align-items:center;background:var(--verde-oscuro);color:#fff;padding:12px 16px}
.modal-body{padding:16px}
</style>
</head>
<body>
<div class="container">

  <!-- Top bar -->
  <div class="topbar">
    <a class="back" href="Menu.php"><i class="fa-solid fa-arrow-left"></i> Volver al Menú</a>
    <div class="title">
      <i class="fa-solid fa-boxes-stacked"></i>
      <h1>Gestión de Stock y Lotes</h1>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi prod"><i class="fa-solid fa-pills"></i><div><h3>Productos</h3><span><?= $kpi_total ?></span></div></div>
    <div class="kpi low"><i class="fa-solid fa-triangle-exclamation"></i><div><h3>Stock bajo</h3><span><?= $kpi_bajo ?></span></div></div>
    <div class="kpi vto"><i class="fa-solid fa-hourglass-half"></i><div><h3>Por vencer ≤ 30 días</h3><span><?= $kpi_vto30 ?></span></div></div>
    <div class="kpi exp"><i class="fa-solid fa-skull-crossbones"></i><div><h3>Vencidos</h3><span><?= $kpi_venc ?></span></div></div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <i class="fa-solid fa-magnifying-glass" style="color:#6b7280"></i>
    <input id="q" class="input" type="search" placeholder="Buscar por nombre...">
    <select id="cat">
      <option value="">Todas las categorías</option>
      <?php while($c = $categoriasRes->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endwhile; ?>
    </select>

    <label><input id="f-bajo" type="checkbox"> Stock bajo</label>
    <label><input id="f-vto" type="checkbox"> Por vencer</label>
    <label><input id="f-venc" type="checkbox"> Vencidos</label>

    <div class="actions">
      <?php if ($rol === 'Administrador'): ?>
        <a href="stock_agregar.php" class="btn primary"><i class="fa-solid fa-plus"></i> Agregar</a>
      <?php endif; ?>
      <button class="btn" id="btnCsv"><i class="fa-solid fa-file-export"></i> CSV</button>
      <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>
  </div>

  <!-- Tabla -->
  <div class="table-wrap">
    <table id="tabla">
      <thead>
        <tr>
          <th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th>
          <th>Stock</th><th>Stock Mín.</th><th>Lote</th><th>Vencimiento</th><th>Cant. Lote</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($productos as $p): 
          $cat=$p['categoria']??'-'; $l=$p['lote_proximo']; $estadoVto=$p['estado_vto'];
          $tagVto=['ok'=>'<span class="tag ok">Ok</span>','por-vencer'=>'<span class="tag vto">Por vencer</span>','vencido'=>'<span class="tag exp">Vencido</span>','sin-lote'=>'<span class="tag">Sin lote</span>'][$estadoVto];
          $stockTag=($p['estado_stock']==='bajo')?'<span class="tag low">Bajo</span>':'<span class="tag ok">Ok</span>';
        ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= htmlspecialchars($p['nombre']) ?></td>
          <td><?= htmlspecialchars($cat) ?></td>
          <td>$<?= number_format($p['precio'],2) ?></td>
          <td><?= (int)$p['stock_actual'] ?> <?= $stockTag ?></td>
          <td><?= (int)$p['stock_minimo'] ?></td>
          <td><?= $l ? htmlspecialchars($l['numero_lote']) : '-' ?></td>
          <td><?= $l ? htmlspecialchars($l['fecha_vencimiento']) : '-' ?> <?= $tagVto ?></td>
          <td><?= $l ? (int)$l['cantidad_actual'] : '-' ?></td>
          <td class="row-actions">
            <button class="pill" onclick='verLotes(<?= json_encode($p['lotes']) ?>, <?= json_encode($p['nombre']) ?>)'><i class="fa-solid fa-layer-group"></i> Lotes</button>
            <a class="pill green" href="Historial.php?producto_id=<?= $p['id'] ?>"><i class="fa-solid fa-chart-line"></i> Historial</a>
            <?php if ($rol==='Administrador'||$rol==='Farmaceutico'): ?>
              <a class="pill blue" href="stock_editar.php?id=<?= $p['id'] ?>"><i class="fa-solid fa-pen"></i> Editar</a>
            <?php endif; ?>
            <?php if ($rol==='Administrador'): ?>
              <button class="pill red" onclick="confirmEliminar(<?= $p['id'] ?>,'<?= htmlspecialchars($p['nombre']) ?>')"><i class="fa-solid fa-trash"></i> Eliminar</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal" id="modal">
  <div class="modal-card">
    <div class="modal-header">
      <div id="modal-title">Lotes</div>
      <div onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></div>
    </div>
    <div class="modal-body">
      <table style="width:100%">
        <thead><tr><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>Estado</th></tr></thead>
        <tbody id="modal-body"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
function verLotes(lotes,nombre){
  const modal=document.getElementById('modal');const tbody=document.getElementById('modal-body');
  document.getElementById('modal-title').textContent=`Lotes de: ${nombre}`;tbody.innerHTML='';
  if(!lotes||lotes.length===0){tbody.innerHTML='<tr><td colspan="4">Sin lotes</td></tr>'}
  else{lotes.forEach(l=>{
    let tag='<span class="tag ok">Ok</span>';const today=new Date();const vto=new Date(l.fecha_vencimiento);
    if(vto<today) tag='<span class="tag exp">Vencido</span>'; else{const diff=Math.round((vto-today)/(1000*60*60*24));if(diff<=30) tag='<span class="tag vto">Por vencer</span>'}
    tbody.innerHTML+=`<tr><td>${l.numero_lote}</td><td>${l.fecha_vencimiento}</td><td>${l.cantidad_actual}</td><td>${tag}</td></tr>`;
  })}
  modal.classList.add('open');
}
function cerrarModal(){document.getElementById('modal').classList.remove('open')}
function confirmEliminar(id,nombre){if(confirm(`¿Eliminar ${nombre}?`))window.location=`stock_eliminar.php?id=${id}`}

// ====== FILTRO ======
document.addEventListener('DOMContentLoaded', () => {
  const q      = document.getElementById('q');
  const cat    = document.getElementById('cat');
  const fBajo  = document.getElementById('f-bajo');
  const fVto   = document.getElementById('f-vto');
  const fVenc  = document.getElementById('f-venc');
  const tbody  = document.querySelector('#tabla tbody');
  const rows   = Array.from(tbody.querySelectorAll('tr'));

  const cellText = (row, idx) =>
    (row.children[idx]?.textContent || '').trim().toLowerCase();

  function applyFilter() {
    const qVal    = (q.value || '').trim().toLowerCase();
    const catVal  = (cat.value || '').trim().toLowerCase();
    const wantBajo = fBajo.checked;
    const wantVto  = fVto.checked;
    const wantVenc = fVenc.checked;

    rows.forEach(row => {
      const nombre   = cellText(row, 1);
      const categoria= cellText(row, 2);
      const stockTxt = cellText(row, 4);
      const vtoTxt   = cellText(row, 7);

      if (qVal && !nombre.includes(qVal)) { row.style.display='none'; return; }
      if (catVal && categoria !== catVal) { row.style.display='none'; return; }
      if (wantBajo && !stockTxt.includes('bajo')) { row.style.display='none'; return; }
      if (wantVto || wantVenc) {
        const isPorVencer = vtoTxt.includes('por vencer');
        const isVencido   = vtoTxt.includes('vencido');
        const matchVto = (wantVto && isPorVencer) || (wantVenc && isVencido);
        if (!matchVto) { row.style.display='none'; return; }
      }
      row.style.display='';
    });

    let visibleIndex = 0;
    rows.forEach(r => {
      if (r.style.display !== 'none') {
        r.style.background = (visibleIndex % 2 === 0) ? '#fff' : '#f9fafb';
        visibleIndex++;
      }
    });
  }

  q.addEventListener('input', applyFilter);
  cat.addEventListener('change', applyFilter);
  fBajo.addEventListener('change', applyFilter);
  fVto.addEventListener('change', applyFilter);
  fVenc.addEventListener('change', applyFilter);

  applyFilter();
});
</script>
</body>
</html>
