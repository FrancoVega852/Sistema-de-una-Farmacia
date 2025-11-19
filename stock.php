<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}
$rol = $_SESSION['usuario_rol'] ?? 'Empleado';

$conn = new Conexion();
$db   = $conn->conexion;
$productoModel = new Producto($db);

/* ============================
   KPIs DE COMPRAS (REEMPLAZA A LOS DE PRODUCTOS)
============================ */
$sqlKpi = "
    SELECT 
        COUNT(*) AS total,
        SUM(estado = 'pendiente') AS pendientes,
        SUM(estado = 'recibida') + SUM(estado = 'pagada') AS recibidas,
        SUM(estado = 'cancelada') AS canceladas
    FROM OrdenCompra
";
$resKpi = $db->query($sqlKpi)->fetch_assoc();

$kpi_total  = (int)$resKpi['total'];
$kpi_pend   = (int)$resKpi['pendientes'];
$kpi_recib  = (int)$resKpi['recibidas'];
$kpi_cancel = (int)$resKpi['canceladas'];

/* ============================
   LISTADO DE COMPRAS
============================ */
$sqlCompras = "SELECT oc.id, oc.fecha, oc.total, oc.estado,
                      pr.razonSocial AS proveedor, 
                      u.nombre       AS usuario
               FROM OrdenCompra oc
               INNER JOIN Proveedor pr ON pr.id = oc.proveedor_id
               INNER JOIN Usuario   u ON u.id = oc.usuario_id
               ORDER BY oc.fecha DESC";
$resCompras = $db->query($sqlCompras);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
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
}

/* ====== FONDO ====== */
html,body{margin:0;padding:0;height:100%;font-family:'Poppins',sans-serif;}
body{
  background:#ffffff;
  color:#1f2937;
  overflow-x:hidden;
}
.bg-pastillas{
  position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.25;
  background-image:url("data:image/svg+xml,%3Csvg width='180' height='180' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2316a34a33'%3E%3Cellipse cx='40' cy='40' rx='12' ry='5' transform='rotate(25 40 40)'/%3E%3Cellipse cx='140' cy='120' rx='10' ry='4' transform='rotate(-35 140 120)'/%3E%3Crect x='80' y='90' width='20' height='6' rx='3' transform='rotate(45 80 90)'/%3E%3Ccircle cx='110' cy='50' r='5'/%3E%3Ccircle cx='60' cy='150' r='4'/%3E%3C/g%3E%3C/svg%3E");
  background-size:200px 200px;
  animation:pillsMove 40s linear infinite alternate;
}
@keyframes pillsMove{0%{background-position:0 0}100%{background-position:220px 200px}}

main{
  position:relative;z-index:1;
  width:100%;
  padding:2rem 3rem;
  box-sizing:border-box;
  animation:fadeIn .7s ease both;
}
@keyframes fadeIn{0%{opacity:0;transform:translateY(20px);}100%{opacity:1;transform:translateY(0);}}

/* ====== HEADER ====== */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem;}
.header h1{margin:0;font-weight:700;color:var(--verdeOsc);}
.btn-back{background:linear-gradient(90deg,var(--verdeOsc),var(--verde));color:#fff;
  padding:.6rem 1rem;border:none;border-radius:.75rem;text-decoration:none;font-weight:600;
  transition:.2s;}
.btn-back:hover{opacity:.9;transform:translateY(-1px);}

/* ====== KPIs ====== */
/* Forzar que el contenedor de KPIs no herede display:flex */
.kpis {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.kpi{display:flex;align-items:center;justify-content:space-between;border-radius:1rem;padding:1rem 1.2rem;color:#fff;
  box-shadow:0 6px 20px rgba(0,0,0,.1);}
.kpi i{font-size:1.6rem;}
.kpi.prod{background:var(--azul);}
.kpi.low{background:var(--naranja);}
.kpi.vto{background:var(--amarillo);color:#111;}
.kpi.exp{background:var(--rojo);}
.kpi .info{text-align:right;}
.kpi .n{font-size:1.8rem;font-weight:800;}

/* ====== TOOLBAR ====== */
.toolbar{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;padding:.8rem;background:#fff;
  border:1px solid #e5e7eb;border-radius:1rem;margin-bottom:1rem;
  box-shadow:0 6px 18px rgba(0,0,0,.05);}
.control{display:flex;align-items:center;gap:.5rem;background:#f9fafb;border:1px solid #e5e7eb;padding:.55rem .8rem;border-radius:.8rem;}
.control input{border:0;outline:0;background:transparent;color:#111;}
.cb{display:flex;align-items:center;gap:.35rem;color:#111;}
.actions{margin-left:auto;display:flex;gap:.6rem;}
.btn{border:none;border-radius:.8rem;padding:.6rem .9rem;cursor:pointer;font-weight:600;}
.btn.primary{background:linear-gradient(90deg,var(--verdeOsc),var(--verde));color:#fff;}
.btn.ghost{background:#fff;border:1px solid var(--verde);color:var(--verdeOsc);}
.btn:hover{transform:translateY(-1px);opacity:.9;}

/* ====== TABLA ====== */
.table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:1rem;overflow:hidden;
  box-shadow:0 8px 20px rgba(0,0,0,.05);}
table{width:100%;border-collapse:collapse;}
thead th{background:linear-gradient(90deg,var(--verdeOsc),var(--verde));color:#fff;padding:.9rem;text-align:left;}
tbody td{padding:.9rem;border-top:1px solid #e5e7eb;}
tbody tr:nth-child(even){background:#f9fdfb;}
tbody tr:hover{background:#ecfdf5;}

/* ====== TAGS KPIs ====== */
.tag{padding:.25rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700;}
.tag.ok{background:#d1fae5;color:#065f46;}
.tag.low{background:#fef3c7;color:#92400e;}
.tag.vto{background:#fef9c3;color:#a16207;}
.tag.exp{background:#fee2e2;color:#991b1b;}

/* ====== MODAL LOTES ====== */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:1rem;z-index:20;}
.modal.open{display:flex;}
.modal-card{width:min(760px,95%);background:#fff;border-radius:1rem;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.25);}
.modal-header{background:linear-gradient(90deg,var(--verdeOsc),var(--verde));color:#fff;padding:.9rem 1rem;display:flex;justify-content:space-between;}
.modal-body{padding:1rem;background:#fff;}
.modal-body th{background:#f4fbf7;text-align:left;padding:.6rem;}
.modal-body td{padding:.6rem;border-top:1px solid #e5e7eb;}

/* ====== ACCIONES (pastillas de colores) ====== */
.row-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.pill {
  border: none;
  border-radius: 999px;
  padding: .45rem .75rem;
  color: #fff;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .85rem;
  font-weight: 600;
  transition: all 0.25s ease;
  text-decoration: none;
}

.pill i {
  font-size: .9rem;
}

.pill:hover {
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25);
  opacity: .95;
}

/* Colores individuales */
.pill       { background: #2563eb; }   /* genérico / azul  */
.pill.green { background: #0e9f6e; }   /* verde           */
.pill.blue  { background: #d97706; }   /* naranja         */
.pill.red   { background: #b91c1c; }   /* rojo            }

/* ====== PASTILLAS DE ESTADO PARA COMPRAS ====== */
.estado-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:600;
  border:1px solid #e5e7eb;
  background:#fff;
}
.estado-ok{
  color:#065f46;
  background:#ecfdf5;
  border-color:#bbf7d0;
}
.estado-pending{
  color:#92400e;
  background:#fff7ed;
  border-color:#fed7aa;
}
.estado-cancel{
  color:#991b1b;
  background:#fee2e2;
  border-color:#fecaca;
}

/* FILA VACÍA */
.empty{ text-align:center; color:#6b7280; padding:1.2rem; }

</style>
</head>
<body>
<div class="bg-pastillas"></div>
<main>
  <div class="header">
    <h1><i class="fa-solid fa-boxes-stacked"></i> Stock y Lotes</h1>
    <a class="btn-back" href="Menu.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
  </div>

  <!-- KPIs DE STOCK (SE MANTIENEN) -->
  <div class="kpis">

  <div class="kpi prod">
    <i class="fa-solid fa-clipboard-list"></i>
    <div class="info">
      <div>Total de compras</div>
      <div class="n"><?= $kpi_total ?></div>
    </div>
  </div>

  <div class="kpi low">
    <i class="fa-solid fa-clock"></i>
    <div class="info">
      <div>En espera (Pendientes)</div>
      <div class="n"><?= $kpi_pend ?></div>
    </div>
  </div>

  <div class="kpi vto">
    <i class="fa-solid fa-circle-check"></i>
    <div class="info">
      <div>Recibidas / Pagadas</div>
      <div class="n"><?= $kpi_recib ?></div>
    </div>
  </div>

  <div class="kpi exp">
    <i class="fa-solid fa-ban"></i>
    <div class="info">
      <div>Canceladas</div>
      <div class="n"><?= $kpi_cancel ?></div>
    </div>
  </div>

</div>



  <!-- TOOLBAR AHORA SOLO BUSCA Y ACCIONES -->
  <div class="toolbar">
    <div class="control">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input id="q" type="search" placeholder="Buscar compra (proveedor, usuario, estado, ID)...">
    </div>
    <div class="actions">
      <?php if($rol==='Administrador'): ?>
        <button class="btn primary btn-module-add">
          <i class="fa-solid fa-plus"></i> Agregar
        </button>
      <?php endif; ?>
      <button class="btn ghost" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Imprimir
      </button>
    </div>
  </div>

  <!-- TABLA REEMPLAZADA: LISTADO DE COMPRAS -->
  <div class="table-wrap">
    <table id="tabla">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Proveedor</th>
          <th>Usuario</th>
          <th>Estado</th>
          <th style="width:140px">Fecha</th>
          <th style="width:150px">Total</th>
          <th style="width:160px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if($resCompras && $resCompras->num_rows): 
              while($row = $resCompras->fetch_assoc()):
                  $estado = strtolower($row['estado']);
                  if ($estado === 'recibida' || $estado === 'pagada') {
                      $clsEstado = 'estado-ok';
                  } elseif ($estado === 'pendiente') {
                      $clsEstado = 'estado-pending';
                  } else {
                      $clsEstado = 'estado-cancel';
                  }
        ?>
          <tr>
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['proveedor']) ?></td>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td>
              <span class="estado-pill <?= $clsEstado ?>">
                <i class="fa-solid fa-circle"></i>
                <?= htmlspecialchars($row['estado']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($row['fecha']) ?></td>
            <td>$<?= number_format($row['total'], 2, ',', '.') ?></td>
            <td class="row-actions">
              <button class="pill green" 
                      onclick="location.href='compras_ver.php?id=<?= $row['id'] ?>'">
                <i class="fa-solid fa-eye"></i> Ver
              </button>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr>
            <td colspan="7" class="empty">No hay compras registradas.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- MODAL LOTES (SE MANTIENE POR SI LO USÁS DESPUÉS) -->
<div class="modal" id="modal">
  <div class="modal-card">
    <div class="modal-header">
      <div id="modal-title">Lotes</div>
      <div style="cursor:pointer" onclick="cerrarModal()"><i class="fa-solid fa-xmark"></i></div>
    </div>
    <div class="modal-body">
      <table>
        <thead><tr><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>Estado</th></tr></thead>
        <tbody id="modal-body"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
/* ===== MODAL LOTES (por si luego volvés a mostrar lotes) ===== */
function verLotes(lotes,nombre){
  const modal=document.getElementById('modal');
  const tbody=document.getElementById('modal-body');
  document.getElementById('modal-title').textContent="Lotes de: "+nombre;
  tbody.innerHTML='';
  if(!lotes||!lotes.length){
    tbody.innerHTML='<tr><td colspan="4">Sin lotes</td></tr>';
  } else {
    const today=new Date();
    lotes.forEach(l=>{
      const vto=new Date(l.fecha_vencimiento);
      let tag='<span class="tag ok">Ok</span>';
      if(vto<today) tag='<span class="tag exp">Vencido</span>';
      else{
        const diff=Math.round((vto-today)/(1000*60*60*24));
        if(diff<=90) tag='<span class="tag vto">Por vencer</span>';
      }
      tbody.innerHTML+=`<tr><td>${l.numero_lote}</td><td>${l.fecha_vencimiento}</td><td>${l.cantidad_actual}</td><td>${tag}</td></tr>`;
    });
  }
  modal.classList.add('open');
}
function cerrarModal(){
  document.getElementById('modal').classList.remove('open');
}

/* ===== Filtro simple para la tabla de COMPRAS ===== */
/* (mantengo el nombre initStockYLotes porque lo llama tu Dashboard) */
window.initStockYLotes = function () {
  if (window.__stockInit) return;
  window.__stockInit = true;

  const q     = document.getElementById('q');
  const tbody = document.querySelector('#tabla tbody');
  if (!q || !tbody) return;

  const rows = Array.from(tbody.querySelectorAll('tr'));

  const norm = s => (s || '').toString().trim().toLowerCase();

  function applyFilter() {
    const qVal = norm(q.value);

    rows.forEach(row => {
      const txt = norm(row.textContent);
      if (qVal && !txt.includes(qVal)) {
        row.style.display = 'none';
      } else {
        row.style.display = '';
      }
    });

    // re-zebra solo visibles
    let i = 0;
    rows.forEach(r=>{
      if(r.style.display!=='none'){
        r.style.background = (i % 2 === 0) ? '#ffffff' : '#f9fdfb';
        i++;
      }
    });
  }

  q.addEventListener('input', applyFilter);
  applyFilter();
};

/* ===== Botón "Agregar" (mantengo lógica: agregar producto) ===== */
document.addEventListener("click", function(e){
  const btn = e.target.closest(".btn-module-add");
  if (!btn) return;

  e.preventDefault();

  if (typeof cargarModulo === "function") {
    cargarModulo("compras.php", "Agregar Producto", {
      wrapTitle: false,
      reverse: false
    });
  } else {
    window.location.href = "compras.php";
  }
});
</script>

<script>
/* Funciones de eliminar producto + toast (por si las seguís usando en otro lado) */
function eliminarProducto(id, nombre){
    if(!confirm("¿Eliminar \""+nombre+"\"?")) return;

    fetch("stock_eliminar.php?id="+id, {
        method: "GET",
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(r => r.json())
    .then(res => {
        if(!res.ok){
            alert("❌ Error al eliminar");
            return;
        }

        let row = document.querySelector("tr[data-row-id='"+id+"']");
        if(row){
            row.style.transition = "all .3s ease";
            row.style.opacity = "0";
            row.style.transform = "translateX(-30px)";
            setTimeout(()=> row.remove(), 300);
        }

        toastOK("Producto eliminado correctamente");
    })
    .catch(err => {
        console.error(err);
        alert("❌ Error inesperado");
    });
}

function toastOK(msg){
    let t = document.createElement("div");
    t.className="toast-farvec";
    t.innerHTML = "<i class='fa-solid fa-check'></i> " + msg;
    document.body.appendChild(t);
    setTimeout(()=> t.classList.add("show"),20);
    setTimeout(()=> {
        t.classList.remove("show");
        setTimeout(()=> t.remove(),300);
    }, 2500);
}

const css = document.createElement("style");
css.innerHTML = `
.toast-farvec{
  position:fixed;
  top:20px;
  right:-300px;
  padding:12px 18px;
  background:#16a34a;
  color:white;
  font-weight:600;
  border-radius:10px;
  transition:all .4s ease;
  box-shadow:0 4px 15px rgba(0,0,0,.25);
  z-index:99999;
}
.toast-farvec.show{
  right:20px;
}
`;
document.head.appendChild(css);
</script>

</body>
</html>
