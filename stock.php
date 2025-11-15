<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit(); }
$rol = $_SESSION['usuario_rol'] ?? 'Empleado';

$conn = new Conexion();
$db   = $conn->conexion;
$productoModel = new Producto($db);

$categoriasRes = $db->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");
$res = $productoModel->obtenerProductosConLotes();

$productos = [];
$kpi_total = $kpi_bajo = $kpi_vto30 = $kpi_venc = 0;
$today = new DateTime('today');

if ($res && $res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['id'];
        if (!isset($productos[$pid])) {
            $productos[$pid] = [
                'id'           => $pid,
                'nombre'       => $row['nombre'],
                'precio'       => (float)$row['precio'],
                'stock_actual' => (int)$row['stock_actual'],
                'stock_minimo' => (int)$row['stock_minimo'],
                'categoria'    => $row['categoria'] ?? null,
                'lotes'        => []
            ];
        }
        if (!empty($row['numero_lote'])) {
            $productos[$pid]['lotes'][] = [
                'numero_lote'      => $row['numero_lote'],
                'fecha_vencimiento'=> $row['fecha_vencimiento'],
                'cantidad_actual'  => (int)$row['cantidad_actual']
            ];
        }
    }
}

// KPIs y estado por producto
foreach ($productos as &$p) {
    $kpi_total++;

    if ($p['stock_actual'] <= $p['stock_minimo']) {
        $p['estado_stock'] = 'bajo';
        $kpi_bajo++;
    } else {
        $p['estado_stock'] = 'ok';
    }

    $p['lote_proximo'] = null;
    $p['estado_vto']   = 'sin-lote';

    if (!empty($p['lotes'])) {
        usort($p['lotes'], fn($a,$b)=>strcmp($a['fecha_vencimiento'],$b['fecha_vencimiento']));
        $first = $p['lotes'][0];
        $p['lote_proximo'] = $first;

        $vto = DateTime::createFromFormat('Y-m-d',$first['fecha_vencimiento']);
        if ($vto) {
            if ($vto < $today) {
                $p['estado_vto'] = 'vencido';
                $kpi_venc++;
            } else {
                $diff = (int)$today->diff($vto)->format('%a');
                if ($diff <= 90) {
                    $p['estado_vto'] = 'por-vencer';
                    $kpi_vto30++;
                } else {
                    $p['estado_vto'] = 'ok';
                }
            }
        }
    }
}
unset($p);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock y Lotes - FARVEC</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
  --verde:#16a34a;
  --verdeOsc:#0e9f6e;
  --azul:#2563eb;
  --naranja:#d97706;
  --amarillo:#ca8a04;
  --rojo:#b91c1c;
  --grisFondo:#f3f4f6;
}

/* ===== LAYOUT GENERAL ===== */
*{box-sizing:border-box;}

html,body{
  margin:0;
  padding:0;
  height:100%;
  font-family:'Poppins',sans-serif;
  color:#111827;
}

body{
  background:radial-gradient(circle at top,#e0f2fe 0,#ffffff 40%,#f0fdf4 100%);
  overflow-x:hidden;
}

.bg-pastillas{
  position:fixed;
  inset:0;
  z-index:0;
  pointer-events:none;
  opacity:.2;
  background-image:url("data:image/svg+xml,%3Csvg width='180' height='180' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2316a34a33'%3E%3Cellipse cx='40' cy='40' rx='12' ry='5' transform='rotate(25 40 40)'/%3E%3Cellipse cx='140' cy='120' rx='10' ry='4' transform='rotate(-35 140 120)'/%3E%3Crect x='80' y='90' width='20' height='6' rx='3' transform='rotate(45 80 90)'/%3E%3Ccircle cx='110' cy='50' r='5'/%3E%3Ccircle cx='60' cy='150' r='4'/%3E%3C/g%3E%3C/svg%3E");
  background-size:220px 220px;
  animation:pillsMove 40s linear infinite alternate;
}
@keyframes pillsMove{
  0%{background-position:0 0;}
  100%{background-position:220px 200px;}
}

.app-shell{
  position:relative;
  z-index:1;
  min-height:100vh;
  display:flex;
  flex-direction:column;
}

.app-main{
  width:100%;
  max-width:1200px;
  margin:0 auto;
  padding:1.75rem 1.25rem 2.5rem;
}

/* ===== HEADER SUPERIOR ===== */
.page-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:1rem;
  margin-bottom:1.5rem;
}

.page-title{
  display:flex;
  align-items:center;
  gap:.75rem;
}
.page-title-icon{
  width:42px;
  height:42px;
  border-radius:1.2rem;
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  display:flex;
  align-items:center;
  justify-content:center;
  color:#ecfdf5;
  box-shadow:0 10px 25px rgba(22,163,74,0.4);
}
.page-title-text h1{
  margin:0;
  font-size:1.4rem;
  font-weight:700;
  color:#064e3b;
}
.page-title-text span{
  font-size:.8rem;
  color:#6b7280;
}

.page-actions{
  display:flex;
  gap:.5rem;
  flex-wrap:wrap;
}

.btn{
  border:none;
  border-radius:.7rem;
  padding:.55rem .9rem;
  font-size:.9rem;
  font-weight:600;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:.4rem;
  text-decoration:none;
  transition:all .2s ease;
  white-space:nowrap;
}
.btn i{font-size:.9rem;}
.btn-primary{
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  color:#ecfdf5;
  box-shadow:0 8px 22px rgba(5,150,105,0.35);
}
.btn-primary:hover{
  transform:translateY(-1px);
  box-shadow:0 10px 26px rgba(5,150,105,0.45);
}
.btn-ghost{
  background:#ffffff;
  border:1px solid #d1d5db;
  color:#374151;
}
.btn-ghost:hover{
  background:#f9fafb;
}

@media(max-width:720px){
  .page-header{
    flex-direction:column;
    align-items:flex-start;
  }
  .page-actions{
    width:100%;
    justify-content:flex-start;
  }
}

/* ===== TARJETAS KPI ===== */
.kpi-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
  gap:1rem;
  margin-bottom:1.4rem;
}

.kpi-card{
  position:relative;
  border-radius:1.1rem;
  padding:1rem .95rem;
  color:#f9fafb;
  overflow:hidden;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:.75rem;
}
.kpi-card::after{
  content:"";
  position:absolute;
  inset:0;
  background:radial-gradient(circle at top right,rgba(255,255,255,.35),transparent 55%);
  opacity:.9;
  pointer-events:none;
}
.kpi-main{
  position:relative;
  z-index:1;
}
.kpi-main .label{
  font-size:.78rem;
  text-transform:uppercase;
  letter-spacing:.04em;
  opacity:.9;
}
.kpi-main .value{
  font-size:1.6rem;
  font-weight:800;
}
.kpi-main .sub{
  font-size:.75rem;
  opacity:.9;
}
.kpi-icon{
  position:relative;
  z-index:1;
  width:38px;
  height:38px;
  border-radius:1rem;
  background:rgba(15,23,42,.22);
  display:flex;
  align-items:center;
  justify-content:center;
}
.kpi-icon i{font-size:1.2rem;}

.kpi-prod{background:linear-gradient(135deg,var(--azul),#38bdf8);}
.kpi-low{background:linear-gradient(135deg,var(--naranja),#f97316);}
.kpi-vto{background:linear-gradient(135deg,var(--amarillo),#facc15);color:#111827;}
.kpi-exp{background:linear-gradient(135deg,var(--rojo),#ef4444);}

/* ===== PANEL PRINCIPAL ===== */
.panel{
  background:rgba(255,255,255,0.95);
  border-radius:1.2rem;
  border:1px solid rgba(209,213,219,.7);
  box-shadow:0 18px 50px rgba(15,23,42,.08);
  padding:1.2rem 1.3rem 1.4rem;
}

/* ===== FILTROS ===== */
.filters{
  display:flex;
  flex-wrap:wrap;
  gap:.7rem;
  align-items:center;
  margin-bottom:1rem;
  border-bottom:1px solid #e5e7eb;
  padding-bottom:.75rem;
}

.filter-group{
  display:flex;
  align-items:center;
  gap:.4rem;
  padding:.45rem .6rem;
  border-radius:.75rem;
  background:#f9fafb;
  border:1px solid #e5e7eb;
}
.filter-group i{
  font-size:.85rem;
  color:#6b7280;
}

.filter-input,
.filter-select{
  border:none;
  outline:none;
  background:transparent;
  font-size:.85rem;
  color:#111827;
  min-width:120px;
}

.filter-toggle{
  display:flex;
  align-items:center;
  gap:.3rem;
  font-size:.8rem;
  padding:.35rem .6rem;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#ffffff;
  cursor:pointer;
}
.filter-toggle input{accent-color:var(--verde);}

/* ===== TABLA (CORREGIDA) ===== */
.table-container{
  margin-top:.6rem;
  border-radius:.9rem;
  border:1px solid #e5e7eb;
  overflow-x:auto; /* permite scroll en pantallas chicas sin cortar nada */
}

/* grid proporcional, no se corta ni se monta */
.table-header,
.table-row{
  display:grid;
  grid-template-columns:
    60px      /* ID */
    1.5fr     /* Producto */
    1fr       /* Categoría */
    0.9fr     /* Precio */
    0.9fr     /* Stock */
    0.8fr     /* Mínimo */
    1fr       /* Lote próximo */
    1fr       /* Vencimiento */
    0.7fr     /* Cant */
    1.7fr;    /* Acciones */
  column-gap:.65rem;
  align-items:center;
  padding:.55rem .9rem;
  min-width:1200px; /* evita que se compacte demasiado */
}

.table-header{
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  color:#ecfdf5;
  font-size:.82rem;
  font-weight:600;
}

.table-row{
  font-size:.83rem;
  background:#ffffff;
  border-top:1px solid #e5e7eb;
}
.table-row:nth-child(even){
  background:#f9fafb;
}
.table-row:hover{
  background:#ecfdf5;
}

.cell-id{font-weight:600;color:#6b7280;}
.cell-name{font-weight:600;color:#111827;}
.cell-cat{color:#4b5563;}
.cell-price,
.cell-stock,
.cell-minimo,
.cell-lote,
.cell-vto,
.cell-cant{
  font-variant-numeric:tabular-nums;
}

/* TAGS */
.tag{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:.15rem .55rem;
  border-radius:999px;
  font-size:.7rem;
  font-weight:600;
  margin-left:.25rem;
}
.tag.ok{background:#dcfce7;color:#166534;}
.tag.low{background:#fef3c7;color:#92400e;}
.tag.vto{background:#fef9c3;color:#a16207;}
.tag.exp{background:#fee2e2;color:#991b1b;}
.tag.neutro{background:#e5e7eb;color:#374151;}

/* ACCIONES */
.row-actions{
  display:flex;
  flex-wrap:wrap;
  gap:.35rem;
  justify-content:flex-start;
}
.pill{
  border:none;
  border-radius:999px;
  padding:.3rem .6rem;
  font-size:.76rem;
  font-weight:600;
  display:inline-flex;
  align-items:center;
  gap:.25rem;
  cursor:pointer;
  text-decoration:none;
  color:#f9fafb;
  transition:.18s ease;
}
.pill i{font-size:.78rem;}
.pill:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 14px rgba(15,23,42,.25);
}
.pill-lotes{background:var(--azul);}
.pill-hist{background:#0e9f6e;}
.pill-edit{background:#d97706;}
.pill-del{background:#b91c1c;}

/* ===== MODAL LOTES ===== */
.modal-overlay{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,.45);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
}
.modal-overlay.open{display:flex;}

.modal-card{
  width:min(620px,95%);
  max-height:80vh;
  background:#ffffff;
  border-radius:1rem;
  box-shadow:0 22px 55px rgba(15,23,42,.4);
  overflow:hidden;
  display:flex;
  flex-direction:column;
}
.modal-header{
  padding:.8rem 1rem;
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  color:#ecfdf5;
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-size:.9rem;
}
.modal-header-title{
  display:flex;align-items:center;gap:.4rem;
}
.modal-header-title i{font-size:.95rem;}
.modal-header-close{
  cursor:pointer;
  font-size:1.1rem;
}

.modal-body{
  padding:.8rem 1rem 1rem;
  background:#f9fafb;
}
.modal-body table{
  width:100%;
  border-collapse:collapse;
  font-size:.82rem;
}
.modal-body th,
.modal-body td{
  padding:.4rem .5rem;
}
.modal-body thead th{
  background:#e5f9f0;
  text-align:left;
  border-bottom:1px solid #d1d5db;
}
.modal-body tbody td{
  border-top:1px solid #e5e7eb;
}

/* ===== MODAL AGREGAR PRODUCTO ===== */
.modal-add-overlay{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,.5);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9998;
}
.modal-add-panel{
  width:min(940px,95%);
  max-height:92vh;
  border-radius:1.2rem;
  background:#f9fafb;
  box-shadow:0 24px 60px rgba(15,23,42,.45);
  overflow:hidden;
  display:flex;
  flex-direction:column;
}
.modal-add-header{
  padding:.8rem 1.1rem;
  background:linear-gradient(135deg,var(--verdeOsc),var(--verde));
  color:#ecfdf5;
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-size:.95rem;
}
.modal-add-header-left{
  display:flex;
  align-items:center;
  gap:.45rem;
}
.modal-add-header-left i{font-size:1rem;}
.modal-add-close{
  background:none;
  border:none;
  color:#fee2e2;
  font-size:1.2rem;
  cursor:pointer;
}
.modal-add-body{
  padding:1rem 1.1rem 1.2rem;
  overflow:auto;
}
.modal-add-loading{
  text-align:center;
  padding:3rem 1rem;
  color:#065f46;
}
.modal-add-loading i{
  font-size:2.4rem;
  margin-bottom:.5rem;
}

/* Fila recién agregada destacada */
@keyframes highlightNew{
  0%{background:#bbf7d0;}
  100%{background:inherit;}
}
.row-new{
  animation:highlightNew 1.6s ease-out;
}

@media(max-width:720px){
  .app-main{padding:1.25rem .75rem 2.3rem;}
}
</style>
</head>
<body>
<div class="bg-pastillas"></div>

<div class="app-shell">
  <main class="app-main">

    <!-- HEADER -->
    <div class="page-header">
      <div class="page-title">
        <div class="page-title-icon">
          <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="page-title-text">
          <h1>Stock y Lotes</h1>
          <span>Control de inventario, vencimientos y trazabilidad por producto</span>
        </div>
      </div>

      <div class="page-actions">
        <a href="Menu.php" class="btn btn-ghost">
          <i class="fa-solid fa-arrow-left"></i> Volver al menú
        </a>

        <button class="btn btn-ghost" onclick="window.print()">
          <i class="fa-solid fa-print"></i> Imprimir listado
        </button>

        <?php if($rol==='Administrador'): ?>
        <button class="btn btn-primary" id="btnAbrirAgregar">
          <i class="fa-solid fa-circle-plus"></i> Nuevo producto / lote
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- KPIs -->
    <section class="kpi-grid">
      <article class="kpi-card kpi-prod">
        <div class="kpi-main">
          <div class="label">Productos en catálogo</div>
          <div class="value"><?= $kpi_total ?></div>
          <div class="sub">Total de presentaciones activas en stock</div>
        </div>
        <div class="kpi-icon">
          <i class="fa-solid fa-pills"></i>
        </div>
      </article>

      <article class="kpi-card kpi-low">
        <div class="kpi-main">
          <div class="label">Stock bajo</div>
          <div class="value"><?= $kpi_bajo ?></div>
          <div class="sub">Productos en o por debajo del mínimo definido</div>
        </div>
        <div class="kpi-icon">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
      </article>

      <article class="kpi-card kpi-vto">
        <div class="kpi-main">
          <div class="label">Por vencer ≤ 90 días</div>
          <div class="value"><?= $kpi_vto30 ?></div>
          <div class="sub">Lotes que requieren atención próxima</div>
        </div>
        <div class="kpi-icon">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
      </article>

      <article class="kpi-card kpi-exp">
        <div class="kpi-main">
          <div class="label">Vencidos</div>
          <div class="value"><?= $kpi_venc ?></div>
          <div class="sub">Lotes vencidos que deben gestionarse</div>
        </div>
        <div class="kpi-icon">
          <i class="fa-solid fa-skull-crossbones"></i>
        </div>
      </article>
    </section>

    <!-- PANEL PRINCIPAL -->
    <section class="panel">

      <!-- FILTROS -->
      <div class="filters">
        <div class="filter-group">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input id="q" class="filter-input" type="search" placeholder="Buscar por nombre de producto...">
        </div>

        <div class="filter-group">
          <i class="fa-solid fa-tags"></i>
          <select id="cat" class="filter-select">
            <option value="">Todas las categorías</option>
            <?php
            $cats2 = $db->query("SELECT id,nombre FROM Categoria ORDER BY nombre ASC");
            while($c=$cats2->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($c['nombre']) ?>">
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <label class="filter-toggle">
          <input id="f-bajo" type="checkbox"> Stock bajo
        </label>
        <label class="filter-toggle">
          <input id="f-vto" type="checkbox"> Por vencer
        </label>
        <label class="filter-toggle">
          <input id="f-venc" type="checkbox"> Vencidos
        </label>
      </div>

      <!-- TABLA -->
      <div class="table-container">
        <div class="table-header">
          <div>ID</div>
          <div>Producto</div>
          <div>Categoría</div>
          <div>Precio</div>
          <div>Stock</div>
          <div>Mínimo</div>
          <div>Lote próximo</div>
          <div>Vencimiento</div>
          <div>Cant.</div>
          <div>Acciones</div>
        </div>

        <div id="tabla-body">
          <?php foreach($productos as $p):
            $cat   = $p['categoria'] ?? '-';
            $l     = $p['lote_proximo'];
            $estadoVto = $p['estado_vto'];

            $tagVto = [
              'ok'        => '<span class="tag ok">Ok</span>',
              'por-vencer'=> '<span class="tag vto">Por vencer</span>',
              'vencido'   => '<span class="tag exp">Vencido</span>',
              'sin-lote'  => '<span class="tag neutro">Sin lote</span>',
            ][$estadoVto];

            $stockTag = ($p['estado_stock']==='bajo')
              ? '<span class="tag low">Bajo</span>'
              : '<span class="tag ok">Ok</span>';
          ?>
          <div class="table-row">
            <div class="cell-id"><?= $p['id'] ?></div>

            <div class="cell-name">
              <?= htmlspecialchars($p['nombre']) ?>
            </div>

            <div class="cell-cat">
              <?= htmlspecialchars($cat) ?>
            </div>

            <div class="cell-price">
              $<?= number_format($p['precio'],2) ?>
            </div>

            <div class="cell-stock">
              <?= (int)$p['stock_actual'] ?> <?= $stockTag ?>
            </div>

            <div class="cell-minimo">
              <?= (int)$p['stock_minimo'] ?>
            </div>

            <div class="cell-lote">
              <?= $l ? htmlspecialchars($l['numero_lote']) : '-' ?>
            </div>

            <div class="cell-vto">
              <?= $l ? htmlspecialchars($l['fecha_vencimiento']) : '-' ?>
              <?= $tagVto ?>
            </div>

            <div class="cell-cant">
              <?= $l ? (int)$l['cantidad_actual'] : '-' ?>
            </div>

            <div class="row-actions">
              <button class="pill pill-lotes"
                      onclick='verLotes(<?= json_encode($p['lotes']) ?>, <?= json_encode($p['nombre']) ?>)'>
                <i class="fa-solid fa-layer-group"></i> Lotes
              </button>

              <a class="pill pill-hist" href="Historial.php?producto_id=<?= $p['id'] ?>">
                <i class="fa-solid fa-chart-line"></i> Historial
              </a>

              <?php if($rol==='Administrador' || $rol==='Farmaceutico'): ?>
              <a class="pill pill-edit" href="stock_editar.php?id=<?= $p['id'] ?>">
                <i class="fa-solid fa-pen"></i> Editar
              </a>
              <?php endif; ?>

              <?php if($rol==='Administrador'): ?>
              <button class="pill pill-del"
                      onclick="confirmEliminar(<?= $p['id'] ?>,'<?= htmlspecialchars($p['nombre']) ?>')">
                <i class="fa-solid fa-trash"></i> Eliminar
              </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </main>
</div>

<!-- MODAL LOTES -->
<div class="modal-overlay" id="modalLotes">
  <div class="modal-card">
    <div class="modal-header">
      <div class="modal-header-title">
        <i class="fa-solid fa-layer-group"></i>
        <span id="modal-title">Lotes</span>
      </div>
      <div class="modal-header-close" onclick="cerrarModalLotes()">
        <i class="fa-solid fa-xmark"></i>
      </div>
    </div>
    <div class="modal-body">
      <table>
        <thead>
          <tr>
            <th>Lote</th>
            <th>Vencimiento</th>
            <th>Cantidad</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody id="modal-body-lotes"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL AGREGAR PRODUCTO -->
<div class="modal-add-overlay" id="modalAgregar">
  <div class="modal-add-panel">
    <div class="modal-add-header">
      <div class="modal-add-header-left">
        <i class="fa-solid fa-circle-plus"></i>
        <span>Nuevo producto / lote / proveedor</span>
      </div>
      <button class="modal-add-close" id="cerrarModalAgregar">
        <i class="fa-solid fa-circle-xmark"></i>
      </button>
    </div>
    <div class="modal-add-body">
      <div class="modal-add-loading" id="contenidoAgregar">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <p>Cargando formulario de alta...</p>
      </div>
    </div>
  </div>
</div>

<script>
// === Modal LOTES ===
function verLotes(lotes, nombre){
  const overlay = document.getElementById('modalLotes');
  const title   = document.getElementById('modal-title');
  const tbody   = document.getElementById('modal-body-lotes');

  title.textContent = "Lotes de: " + nombre;
  tbody.innerHTML = '';

  if (!lotes || !lotes.length) {
    tbody.innerHTML = '<tr><td colspan="4">Este producto no tiene lotes cargados.</td></tr>';
  } else {
    const today = new Date();
    lotes.forEach(l => {
      const vto = new Date(l.fecha_vencimiento);
      let estado = '<span class="tag ok">Ok</span>';

      if (vto < today){
        estado = '<span class="tag exp">Vencido</span>';
      } else {
        const diff = Math.round((vto - today)/(1000*60*60*24));
        if (diff <= 90) estado = '<span class="tag vto">Por vencer</span>';
      }

      tbody.innerHTML += `
        <tr>
          <td>${l.numero_lote}</td>
          <td>${l.fecha_vencimiento}</td>
          <td>${l.cantidad_actual}</td>
          <td>${estado}</td>
        </tr>`;
    });
  }
  overlay.classList.add('open');
}

function cerrarModalLotes(){
  document.getElementById('modalLotes').classList.remove('open');
}

// === FILTROS ===
(function initFiltros(){
  const q      = document.getElementById('q');
  const cat    = document.getElementById('cat');
  const fBajo  = document.getElementById('f-bajo');
  const fVto   = document.getElementById('f-vto');
  const fVenc  = document.getElementById('f-venc');
  const body   = document.getElementById('tabla-body');
  const rows   = Array.from(body.children);

  const norm = s => (s||'').toString().trim().toLowerCase();

  function applyFilter(){
    const qVal     = norm(q.value);
    const catVal   = norm(cat.value);
    const wantBajo = fBajo.checked;
    const wantVto  = fVto.checked;
    const wantVenc = fVenc.checked;

    rows.forEach(row => {
      const cells = row.children;
      const nombre    = norm(cells[1]?.textContent);
      const categoria = norm(cells[2]?.textContent);
      const stockTxt  = norm(cells[4]?.textContent);
      const vtoTxt    = norm(cells[7]?.textContent);

      // texto
      if (qVal && !nombre.includes(qVal)){ row.style.display='none'; return; }
      // categoria
      if (catVal && categoria !== catVal){ row.style.display='none'; return; }
      // stock bajo
      if (wantBajo && !stockTxt.includes('bajo')){ row.style.display='none'; return; }

      if (wantVto || wantVenc){
        const isPorVencer = vtoTxt.includes('por vencer');
        const isVencido   = vtoTxt.includes('vencido');
        let match = true;
        if (wantVto && !isPorVencer) match = false;
        if (wantVenc && !isVencido)  match = false;
        if (!match){ row.style.display='none'; return; }
      }

      row.style.display='';
    });
  }

  q.addEventListener('input', applyFilter);
  cat.addEventListener('change', applyFilter);
  fBajo.addEventListener('change', applyFilter);
  fVto.addEventListener('change', applyFilter);
  fVenc.addEventListener('change', applyFilter);
})();

// === MODAL AGREGAR: cargar formulario por fetch ===
(function initModalAgregar(){
  const btn   = document.getElementById('btnAbrirAgregar');
  const modal = document.getElementById('modalAgregar');
  const box   = document.getElementById('contenidoAgregar');
  const btnClose = document.getElementById('cerrarModalAgregar');

  if (!btn) return;

  btn.addEventListener('click', () => {
    modal.style.display = 'flex';
    box.innerHTML = `
      <div class="modal-add-loading">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <p>Cargando formulario de alta...</p>
      </div>`;

    fetch('stock_agregar.php?ajax=1')
      .then(r => r.text())
      .then(html => {
        const parser = new DOMParser();
        const doc    = parser.parseFromString(html,'text/html');
        const content = doc.body.innerHTML;

        box.innerHTML = content;

        // ejecutar scripts embebidos de stock_agregar.php
        doc.querySelectorAll('script').forEach(oldScript => {
          const s = document.createElement('script');
          if (oldScript.src) {
            s.src = oldScript.src;
          } else {
            s.textContent = oldScript.textContent;
          }
          box.appendChild(s);
        });
      })
      .catch(() => {
        box.innerHTML = `
          <div class="modal-add-loading">
            <p>No se pudo cargar el formulario. Intentá nuevamente.</p>
          </div>`;
      });
  });

  btnClose.addEventListener('click', () => {
    modal.style.display = 'none';
  });

  modal.addEventListener('click', e => {
    if (e.target === modal) {
      modal.style.display = 'none';
    }
  });
})();

// === Escuchar productos nuevos (evento desde stock_agregar.php) ===
window.addEventListener('producto-agregado', (e) => {
  const p = e.detail;
  if (!p) return;

  const body = document.getElementById('tabla-body');

  const div = document.createElement('div');
  div.className = 'table-row row-new';
  div.innerHTML = `
    <div class="cell-id">${p.id}</div>
    <div class="cell-name">${p.nombre}</div>
    <div class="cell-cat">${p.categoria}</div>
    <div class="cell-price">$${parseFloat(p.precio).toFixed(2)}</div>
    <div class="cell-stock">
      ${p.stock_actual}
      <span class="tag ok">Ok</span>
    </div>
    <div class="cell-minimo">${p.stock_minimo}</div>
    <div class="cell-lote">${p.numero_lote}</div>
    <div class="cell-vto">
      ${p.fecha_vencimiento}
      <span class="tag vto">Por vencer</span>
    </div>
    <div class="cell-cant">${p.cantidad_inicial}</div>
    <div class="row-actions">
      <button class="pill pill-lotes"
        onclick='verLotes([], "${p.nombre.replace(/"/g,"&quot;")}")'>
        <i class="fa-solid fa-layer-group"></i> Lotes
      </button>
      <a class="pill pill-hist" href="Historial.php?producto_id=${p.id}">
        <i class="fa-solid fa-chart-line"></i> Historial
      </a>
      <a class="pill pill-edit" href="stock_editar.php?id=${p.id}">
        <i class="fa-solid fa-pen"></i> Editar
      </a>
    </div>
  `;

  body.prepend(div);

  const modal = document.getElementById('modalAgregar');
  if (modal) modal.style.display = 'none';
});
</script>

</body>
</html>
