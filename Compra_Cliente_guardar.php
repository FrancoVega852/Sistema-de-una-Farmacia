<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'ControladorVentas.php';

if (!isset($_SESSION['usuario_id'])) {
  exit("<div class='panel'><h3>Error: sesión expirada.</h3></div>");
}

$conn = new Conexion();
$ctl  = new ControladorVentas($conn->conexion);

$clientesRes  = $ctl->clientes();
$productosRes = $ctl->productos();

$clientes = [];
while ($c = $clientesRes->fetch_assoc()) { $clientes[] = $c; }

$productos = [];
while ($p = $productosRes->fetch_assoc()) {
  $productos[] = [
    'id' => (int)$p['id'],
    'nombre' => $p['nombre'],
    'precio' => (float)$p['precio'],
    'stock'  => (int)$p['stock_actual'],
    'categoria' => $p['categoria'] ?? 'General',
    'requiere_receta' => (int)($p['requiere_receta'] ?? 0),
  ];
}

/* ======================================================
   NUEVO: traer TODAS las categorías desde la BD real
====================================================== */
$categoriasRes = $conn->conexion->query("SELECT nombre FROM Categoria ORDER BY nombre ASC");
$categorias = [];
while ($cat = $categoriasRes->fetch_assoc()) {
  $categorias[] = $cat['nombre'];
}
?>

<style>
/* ===============================
   NUEVA COMPRA – FARVEC STYLE
   (CLON DE ventas.php)
   =============================== */
#nv-root *{box-sizing:border-box;font-family:"Inter","Segoe UI",system-ui,Arial}

#nv-root{
  animation:fade .35s ease;
}

/* Breadcrumb */
.nv-breadcrumb{
  display:flex;align-items:center;gap:6px;
  font-size:12px;color:#6b7280;
  margin-bottom:6px;
}
.nv-breadcrumb i{color:#00794f}
.nv-breadcrumb .sep{color:#9ca3af}

/* Título */
.nv-title{
  font-size:20px;
  font-weight:800;
  margin-bottom:4px;
  color:#00794f;
}
.nv-sub{
  color:#5e6b74;
  margin-bottom:14px;
  font-size:13px;
}

/* Grid principal */
.nv-grid{
  display:grid;
  grid-template-columns:7fr 5fr;
  gap:14px;
}
@media(max-width:1000px){
  .nv-grid{grid-template-columns:1fr}
}

/* Panel genérico */
.nv-panel{
  background:#ffffff;
  border:1px solid #e7eceb;
  border-radius:16px;
  box-shadow:0 8px 20px rgba(0,121,79,.10);
  padding:14px;
}
.nv-panel h4{
  margin:0 0 4px;
  font-size:15px;
  font-weight:700;
  color:#111827;
}

/* Inputs FARVEC */
.nv-input{
  border:1px solid #e7eceb;
  border-radius:10px;
  padding:8px 10px;
  font-size:13px;
  outline:none;
  width:100%;
  background:#ffffff;
}
.nv-input:focus{
  border-color:#00a86b;
  box-shadow:0 0 0 2px rgba(0,168,107,.15);
}

/* Botones FARVEC */
.nv-btn{
  border-radius:10px;
  border:1px solid transparent;
  padding:7px 11px;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.nv-btn-ghost{
  background:transparent;
  border-color:#d1d5db;
  color:#374151;
}
.nv-btn-danger{
  background:#b93142;
  border-color:#b93142;
  color:#fff;
}
.nv-btn-success{
  background:#00794f;
  border-color:#00794f;
  color:#fff;
}
.nv-btn:disabled{
  opacity:.6;
  cursor:not-allowed;
}
.nv-btn:hover:not(:disabled){
  filter:brightness(1.05);
  transform:translateY(-1px);
}

/* Catálogo */
.nv-filters{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:10px;
}
.nv-chip{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:5px 9px;
  border-radius:999px;
  border:1px solid #e7eceb;
  background:#f3f4f6;
  font-size:12px;
  cursor:pointer;
}
.nv-chip input{margin:0}

.nv-catalogo-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
  gap:10px;
}

.nv-card{
  background:#f8fbfa;
  border:1px solid #e7eceb;
  padding:10px;
  border-radius:14px;
  transition:.25s;
  display:flex;
  flex-direction:column;
  gap:6px;
  min-height:150px;
}
.nv-card:hover{
  transform:translateY(-3px);
  box-shadow:0 6px 16px rgba(0,0,0,.12);
}
.nv-card h4{
  margin:0;
  font-size:14px;
  font-weight:700;
}
.nv-meta{
  font-size:11px;
  color:#6b7280;
  display:flex;
  flex-wrap:wrap;
  gap:4px;
}
.nv-badge{
  display:inline-flex;
  align-items:center;
  gap:4px;
  padding:3px 7px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  font-size:11px;
  background:#fff;
}
.nv-badge.green{color:#047857;background:#ecfdf5;border-color:#a7f3d0}
.nv-badge.red{color:#b91c1c;background:#fef2f2;border-color:#fecaca}
.nv-price{
  color:#0a7e56;
  font-weight:800;
  font-size:14px;
}
.nv-controls{
  margin-top:auto;
  display:flex;
  gap:6px;
}
.nv-qty{
  max-width:80px;
}

/* Paginación */
.nv-pager{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:10px;
  font-size:12px;
  color:#4b5563;
}
.nv-page-buttons{
  display:flex;
  gap:4px;
}
.nv-page-btn{
  min-width:28px;
  height:28px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#fff;
  font-size:12px;
  cursor:pointer;
}
.nv-page-btn.active{
  background:#00794f;
  border-color:#00794f;
  color:#fff;
}

/* Carrito */
.nv-cart-table{
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
.nv-cart-table th{
  background:#f1f5f4;
  border-bottom:1px solid #d5dddb;
  padding:7px 8px;
  text-align:left;
  font-weight:600;
}
.nv-cart-table td{
  border-bottom:1px solid #e7eceb;
  padding:7px 8px;
}
.nv-empty-row{
  text-align:center;
  color:#6b7280;
  font-size:13px;
}

/* Totales */
.nv-summary{
  margin-top:12px;
  border:1px dashed #00794f;
  border-radius:14px;
  padding:10px;
}
.nv-summary .row-line{
  display:flex;
  justify-content:space-between;
  margin:4px 0;
}
.nv-total{
  color:#00794f;
  font-size:19px;
  font-weight:800;
}

/* Toast */
.nv-toast{
  position:fixed;
  right:16px;
  bottom:16px;
  background:linear-gradient(135deg,#00a86b,#16c784);
  color:#fff;
  padding:10px 14px;
  border-radius:12px;
  box-shadow:0 10px 26px rgba(0,0,0,.2);
  font-size:13px;
  font-weight:700;
  opacity:0;
  transform:translateY(10px);
  pointer-events:none;
  transition:.28s ease;
  z-index:999;
}
.nv-toast.show{
  opacity:1;
  transform:translateY(0);
}

/* Modal recibo */
.nv-modal-backdrop{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,.45);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:998;
}
.nv-modal{
  background:#ffffff;
  border-radius:16px;
  border:1px solid #e5e7eb;
  box-shadow:0 20px 60px rgba(15,23,42,.35);
  width:min(520px,94vw);
  max-height:90vh;
  display:flex;
  flex-direction:column;
}
.nv-modal-header{
  padding:10px 14px;
  border-bottom:1px solid #e5e7eb;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.nv-modal-body{
  padding:12px 14px;
  overflow:auto;
}
.nv-modal-footer{
  padding:10px 14px;
  border-top:1px solid #e5e7eb;
  display:flex;
  justify-content:flex-end;
  gap:8px;
}
.nv-receipt-title{
  font-size:14px;
  font-weight:800;
  color:#00794f;
}
.nv-receipt-meta{
  font-size:12px;
  color:#4b5563;
}
.nv-receipt-table{
  width:100%;
  border-collapse:collapse;
  font-size:12px;
  margin-top:8px;
}
.nv-receipt-table th,
.nv-receipt-table td{
  border-bottom:1px solid #e5e7eb;
  padding:4px 6px;
}
.nv-receipt-table th{
  background:#f3f4f6;
  text-align:left;
}

/* Anim */
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
</style>

<div id="nv-root">
  <!-- Toast -->
  <div id="nv-toast" class="nv-toast">Compra registrada con éxito</div>

  <!-- Modal recibo -->
  <div id="nv-modal" class="nv-modal-backdrop" aria-hidden="true">
    <div class="nv-modal">
      <div class="nv-modal-header">
        <div>
          <div class="nv-receipt-title"><i class="fa-solid fa-receipt"></i> Comprobante de compra</div>
          <div class="nv-receipt-meta" id="nv-rec-info"></div>
        </div>
        <button type="button" class="nv-btn nv-btn-ghost" id="nv-rec-close" style="padding:4px 8px;font-size:11px">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="nv-modal-body">
        <table class="nv-receipt-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th style="width:60px">Cant.</th>
              <th style="width:80px">Precio</th>
              <th style="width:90px">Subtotal</th>
            </tr>
          </thead>
          <tbody id="nv-rec-body"></tbody>
        </table>

        <div style="margin-top:8px;border-top:1px dashed #d1d5db;padding-top:6px;font-size:12px">
          <div style="display:flex;justify-content:space-between;">
            <span>Subtotal</span><span id="nv-rec-subtotal">$0,00</span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <span>IVA 21%</span><span id="nv-rec-iva">$0,00</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-weight:800;margin-top:4px">
            <span>Total</span><span id="nv-rec-total" style="color:#00794f">$0,00</span>
          </div>
        </div>
      </div>
      <div class="nv-modal-footer">
        <button type="button" class="nv-btn nv-btn-ghost" id="nv-rec-menu">
          <i class="fa-solid fa-house"></i> Ir al menú
        </button>
        <button type="button" class="nv-btn nv-btn-ghost" id="nv-rec-listado">
          <i class="fa-solid fa-list"></i> Ir a mis compras
        </button>
        <button type="button" class="nv-btn nv-btn-success" id="nv-rec-nueva">
          <i class="fa-solid fa-plus"></i> Nueva compra
        </button>
      </div>
    </div>
  </div>

  <!-- Breadcrumb -->
  <nav class="nv-breadcrumb">
    <span><i class="fa-solid fa-house"></i> Inicio</span>
    <span class="sep">›</span>
    <span>Compras</span>
    <span class="sep">›</span>
    <strong>Nueva compra</strong>
  </nav>

  <div class="nv-title"><i class="fa-solid fa-cart-shopping"></i> Nueva compra</div>
  <div class="nv-sub">Seleccioná productos del catálogo, añadilos al carrito y registrá tu compra.</div>

  <div class="nv-grid">

    <!-- ==========================
         CATÁLOGO
         ========================== -->
    <div class="nv-panel">
      <h4>Catálogo de productos</h4>
      <div style="font-size:12px;color:#6b7280;margin-bottom:8px;">
        Productos disponibles: <strong><?= count($productos) ?></strong>
      </div>

      <div class="nv-filters">
        <input id="nv-search" class="nv-input" placeholder="Buscar por nombre…">

        <!-- SELECT CATEGORÍAS -->
        <select id="nv-cat" class="nv-input" style="max-width:200px">
          <option value="">Todas las categorías</option>
          <?php foreach($categorias as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>">
              <?= htmlspecialchars($cat) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label class="nv-chip">
          <input type="checkbox" id="nv-only-stock">
          Solo con stock
        </label>
        <label class="nv-chip">
          <input type="checkbox" id="nv-only-receta">
          Requiere receta
        </label>
      </div>

      <div class="nv-catalogo-grid" id="nv-grid"></div>
      <div class="nv-pager" id="nv-pager"></div>
    </div>


    <!-- ==========================
         CARRITO
         ========================== -->
    <div class="nv-panel">
      <h4>Carrito</h4>

      <!-- Cliente / forma de pago -->
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
        <div style="flex:1 1 180px">
          <label style="font-size:11px;color:#6b7280;margin-bottom:2px;display:block">Cliente</label>
          <select id="nv-cliente" class="nv-input">
            <option value="">Consumidor final</option>
            <?php foreach($clientes as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1 1 140px">
          <label style="font-size:11px;color:#6b7280;margin-bottom:2px;display:block">Forma de pago</label>
          <select id="nv-pago" class="nv-input">
            <option value="Efectivo">Efectivo</option>
            <option value="Tarjeta">Tarjeta</option>
            <option value="Cuenta Corriente">Cuenta Corriente</option>
          </select>
        </div>
      </div>

      <table class="nv-cart-table">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Cant.</th>
            <th>Precio</th>
            <th>Subtotal</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="nv-cart-body">
          <tr id="nv-empty"><td colspan="5" class="nv-empty-row">Sin productos</td></tr>
        </tbody>
      </table>

      <div class="nv-summary">
        <div class="row-line">
          <span>Subtotal</span><span id="nv-subtotal">$0,00</span>
        </div>
        <div class="row-line">
          <span>IVA 21%</span><span id="nv-iva">$0,00</span>
        </div>
        <div class="row-line" style="border-top:1px dashed #d1d5db;padding-top:4px">
          <strong>Total</strong>
          <strong class="nv-total" id="nv-total">$0,00</strong>
        </div>
      </div>

      <div class="nv-actions" style="margin-top:14px;display:flex;gap:8px;align-items:center">
        <button type="button" class="nv-btn nv-btn-danger" onclick="nvVaciar()">
          <i class="fa-solid fa-trash"></i> Vaciar
        </button>

        <form action="Compra_Cliente_guardar_accion.php" method="POST" id="nv-form" style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <input type="hidden" name="cliente_id" id="nv-cliente-id" value="">
          <div id="nv-hidden"></div>

          <button id="nv-registrar" class="nv-btn nv-btn-success" disabled>
            <i class="fa-solid fa-floppy-disk"></i> Registrar compra
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
/* ===========================================
   CATÁLOGO + CARRITO + PAGINACIÓN + ESTADO
   (CLON DE ventas.php)
   =========================================== */
(function(){
  const CAT = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
  const fmt = n => Number(n||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});

  const LS_KEY_Q    = 'farvec_nv_search_cliente';
  const LS_KEY_CAT  = 'farvec_nv_cat_cliente';
  const LS_KEY_STK  = 'farvec_nv_only_stock_cliente';
  const LS_KEY_REC  = 'farvec_nv_only_receta_cliente';
  const LS_KEY_PAGE = 'farvec_nv_page_cliente';

  const PAGE_SIZE = 8;

  const q       = document.querySelector('#nv-search');
  const selCat  = document.querySelector('#nv-cat');
  const chkStk  = document.querySelector('#nv-only-stock');
  const chkRec  = document.querySelector('#nv-only-receta');
  const grid    = document.querySelector('#nv-grid');
  const pager   = document.querySelector('#nv-pager');
  const cartBody= document.querySelector('#nv-cart-body');
  const emptyRow= document.querySelector('#nv-empty');
  const hidden  = document.querySelector('#nv-hidden');
  const btnReg  = document.querySelector('#nv-registrar');
  const form    = document.querySelector('#nv-form');
  const toast   = document.querySelector('#nv-toast');
  const selCliente = document.querySelector('#nv-cliente');
  const hiddenCliente = document.querySelector('#nv-cliente-id');

  // Modal recibo
  const modal      = document.querySelector('#nv-modal');
  const recInfo    = document.querySelector('#nv-rec-info');
  const recBody    = document.querySelector('#nv-rec-body');
  const recSub     = document.querySelector('#nv-rec-subtotal');
  const recIva     = document.querySelector('#nv-rec-iva');
  const recTotal   = document.querySelector('#nv-rec-total');
  const btnRecClose= document.querySelector('#nv-rec-close');
  const btnRecListado = document.querySelector('#nv-rec-listado');
  const btnRecMenu = document.querySelector('#nv-rec-menu');
  const btnRecNueva= document.querySelector('#nv-rec-nueva');

  let CART = {};
  let currentPage = 1;

  /* ====== Estado ====== */
  function loadState(){
    const sQ   = localStorage.getItem(LS_KEY_Q);
    const sCat = localStorage.getItem(LS_KEY_CAT);
    const sStk = localStorage.getItem(LS_KEY_STK);
    const sRec = localStorage.getItem(LS_KEY_REC);
    const sPag = parseInt(localStorage.getItem(LS_KEY_PAGE)||'1',10);

    if(sQ   !== null) q.value = sQ;
    if(sCat !== null) selCat.value = sCat;
    if(sStk !== null) chkStk.checked = sStk === '1';
    if(sRec !== null) chkRec.checked = sRec === '1';
    currentPage = isNaN(sPag) || sPag<1 ? 1 : sPag;
  }
  function saveState(){
    localStorage.setItem(LS_KEY_Q, q.value || '');
    localStorage.setItem(LS_KEY_CAT, selCat.value || '');
    localStorage.setItem(LS_KEY_STK, chkStk.checked ? '1':'0');
    localStorage.setItem(LS_KEY_REC, chkRec.checked ? '1':'0');
    localStorage.setItem(LS_KEY_PAGE, String(currentPage));
  }

  /* ====== FILTROS + PAGINACIÓN ====== */
  function getFiltered(){
    const term = (q.value || "").toLowerCase().trim();
    const cat  = selCat.value;
    const onlyStk = chkStk.checked;
    const onlyRec = chkRec.checked;
    return CAT
      .filter(p => !term || p.nombre.toLowerCase().includes(term))
      .filter(p => !cat  || p.categoria === cat)
      .filter(p => !onlyStk || p.stock > 0)
      .filter(p => !onlyRec || p.requiere_receta === 1);
  }

  function renderCatalog(){
    const list = getFiltered();
    const total = list.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if(currentPage > totalPages) currentPage = totalPages;
    if(currentPage < 1) currentPage = 1;

    grid.innerHTML = "";
    const start = (currentPage-1)*PAGE_SIZE;
    const slice = list.slice(start, start+PAGE_SIZE);

    if(!slice.length){
      grid.innerHTML = '<div style="grid-column:1/-1;padding:14px;text-align:center;color:#6b7280;font-size:13px">No hay productos que coincidan con el filtro.</div>';
    }else{
      slice.forEach(p=>{
        const receta = p.requiere_receta===1 ? `<span class="nv-badge red"><i class="fa-solid fa-prescription-bottle-medical"></i> Receta</span>` : '';
        const disabled = p.stock<=0 ? 'disabled' : '';
        const card = document.createElement('div');
        card.className = 'nv-card';
        card.innerHTML = `
          <h4>${p.nombre}</h4>
          <div class="nv-meta">
            <span class="nv-badge"><i class="fa-solid fa-tag"></i> ${p.categoria}</span>
            <span class="nv-badge green"><i class="fa-solid fa-box"></i> Stock: ${p.stock}</span>
            ${receta}
          </div>
          <div class="nv-price">$${fmt(p.precio)}</div>
          <div class="nv-controls">
            <input type="number" min="1" value="1" class="nv-input nv-qty" id="nv-qty-${p.id}">
            <button class="nv-btn nv-btn-success" ${disabled} data-add="${p.id}">
              <i class="fa-solid fa-plus"></i> Añadir
            </button>
          </div>
        `;
        grid.appendChild(card);
      });
    }

    renderPager(totalPages, total);
    saveState();
  }

  function renderPager(totalPages, totalItems){
    if(totalPages<=1){
      pager.innerHTML = '';
      return;
    }
    const from = (currentPage-1)*PAGE_SIZE + 1;
    const to   = Math.min(currentPage*PAGE_SIZE, totalItems);

    let html = `
      <div style="font-size:12px">Mostrando ${from}–${to} de ${totalItems}</div>
      <div class="nv-page-buttons">
        <button type="button" class="nv-page-btn" data-page="prev" ${currentPage===1?'disabled':''}>&laquo;</button>
    `;

    const maxBtns = 5;
    let start = Math.max(1, currentPage - 2);
    let end   = Math.min(totalPages, start + maxBtns - 1);
    if(end - start + 1 < maxBtns){
      start = Math.max(1, end - maxBtns + 1);
    }
    for(let p=start;p<=end;p++){
      html += `<button type="button" class="nv-page-btn ${p===currentPage?'active':''}" data-page="${p}">${p}</button>`;
    }

    html += `
        <button type="button" class="nv-page-btn" data-page="next" ${currentPage===totalPages?'disabled':''}>&raquo;</button>
      </div>
    `;
    pager.innerHTML = html;

    pager.querySelectorAll('.nv-page-btn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const v = btn.getAttribute('data-page');
        if(v==='prev' && currentPage>1){ currentPage--; }
        else if(v==='next' && currentPage<totalPages){ currentPage++; }
        else if(!isNaN(parseInt(v,10))){ currentPage = parseInt(v,10); }
        renderCatalog();
      });
    });
  }

  [q, selCat, chkStk, chkRec].forEach(el=>{
    el.addEventListener('input', ()=>{
      currentPage = 1;
      renderCatalog();
    });
  });

  // Delegar clicks "Añadir"
  grid.addEventListener('click',e=>{
    const btn = e.target.closest('button[data-add]');
    if(!btn) return;
    const id = parseInt(btn.getAttribute('data-add'),10);
    const prod = CAT.find(x=>x.id===id);
    if(!prod) return;
    const qtyEl = document.querySelector(`#nv-qty-${id}`);
    let qv = parseInt(qtyEl?.value || '1',10);
    if(isNaN(qv) || qv<1) qv=1;
    if(qv>prod.stock) qv = prod.stock;

    if(CART[id]) CART[id].cant = Math.min(prod.stock, CART[id].cant + qv);
    else CART[id] = {...prod, cant:qv};

    if(qtyEl) qtyEl.value = 1;
    renderCart();
  });

  /* ============ CARRITO ============ */
  function nvRemove(id){
    delete CART[id];
    renderCart();
  }
  window.nvRemove = nvRemove;

  function renderCart(){
    cartBody.innerHTML = "";
    const ids = Object.keys(CART);
    if(!ids.length){
      emptyRow.style.display = "";
      cartBody.appendChild(emptyRow);
      document.querySelector("#nv-subtotal").textContent = "$0,00";
      document.querySelector("#nv-iva").textContent      = "$0,00";
      document.querySelector("#nv-total").textContent    = "$0,00";
      hidden.innerHTML = "";
      btnReg.disabled = true;
      return;
    }
    emptyRow.style.display = "none";

    let subtotal = 0;
    ids.forEach(id=>{
      const p = CART[id];
      const sub = p.cant * p.precio;
      subtotal += sub;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${p.nombre}</td>
        <td>${p.cant}</td>
        <td>$${fmt(p.precio)}</td>
        <td>$${fmt(sub)}</td>
        <td>
          <button type="button" class="nv-btn nv-btn-ghost" style="padding:4px 8px;font-size:11px" onclick="nvRemove(${p.id})">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </td>
      `;
      cartBody.appendChild(tr);
    });

    const iva   = subtotal * 0.21;
    const total = subtotal + iva;
    document.querySelector("#nv-subtotal").textContent = "$" + fmt(subtotal);
    document.querySelector("#nv-iva").textContent      = "$" + fmt(iva);
    document.querySelector("#nv-total").textContent    = "$" + fmt(total);

    hidden.innerHTML = "";
    ids.forEach(id=>{
      const p = CART[id];
      hidden.insertAdjacentHTML('beforeend',`
        <input type="hidden" name="productos[${p.id}][id]" value="${p.id}">
        <input type="hidden" name="productos[${p.id}][cantidad]" value="${p.cant}">`);
    });

    btnReg.disabled = ids.length === 0;
  }

  function nvVaciar(){
    CART = {};
    renderCart();
  }
  window.nvVaciar = nvVaciar;

  /* ============ TOAST ============ */
  function showToast(msg){
    if(!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(()=>toast.classList.remove('show'),2200);
  }

  /* ============ MODAL RECIBO ============ */
  function openModal(){
    if(!modal) return;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
  }
  function closeModal(){
    if(!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  }

  if(btnRecClose) btnRecClose.addEventListener('click', closeModal);
  if(modal) modal.addEventListener('click',e=>{
    if(e.target === modal) closeModal();
  });

  if(btnRecNueva){
    btnRecNueva.addEventListener('click',()=>{
      closeModal();
      nvVaciar();
      showToast('Lista para una nueva compra');
    });
  }

  if(btnRecListado){
    btnRecListado.addEventListener('click',()=>{
      closeModal();
      const link = document.querySelector('.sidebar a[href="Compra_Cliente_listar.php"]');
      if(link) link.click();
      else window.location.href = 'Compra_Cliente_listar.php';
    });
  }

  if(btnRecMenu){
    btnRecMenu.addEventListener('click',()=>{
      closeModal();
      const linkHome = document.querySelector('.top-actions a[href="menu_cliente.php"]');
      if(linkHome) linkHome.click();
      else window.location.href = 'menu_cliente.php';
    });
  }

  /* ============ ENVÍO AJAX DE LA COMPRA ============ */
  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    if(btnReg.disabled) return;

    // Setear cliente_id oculto
    hiddenCliente.value = selCliente.value || '';

    // Snapshot de datos para el recibo (antes de vaciar)
    const ids = Object.keys(CART);
    if(!ids.length) return;

    let subtotal = 0;
    const itemsRecibo = ids.map(id=>{
      const p = CART[id];
      const sub = p.cant * p.precio;
      subtotal += sub;
      return {
        nombre: p.nombre,
        cant: p.cant,
        precio: p.precio,
        subtotal: sub
      };
    });
    const iva = subtotal * 0.21;
    const total = subtotal + iva;
    const clienteNombre = selCliente.value 
      ? selCliente.options[selCliente.selectedIndex].textContent
      : 'Consumidor final';

    btnReg.disabled = true;
    showToast('Registrando compra…');

    try{
      const fd = new FormData(form);
      const res = await fetch('Compra_Cliente_guardar_accion.php', {
        method:'POST',
        body: fd,
        headers:{ 'X-Requested-With':'XMLHttpRequest' }
      });

      let data = null;
      const ct = res.headers.get('content-type') || '';
      if(ct.includes('application/json')){
        data = await res.json();
      }

      if(!res.ok || (data && data.ok === false)){
        throw new Error(data && data.msg ? data.msg : 'No se pudo registrar la compra.');
      }

      // Compra OK → armar recibo
      const compraId = data && data.venta_id ? data.venta_id : '—';
      const ahora = new Date();
      const fechaStr = ahora.toLocaleString('es-AR');

      if(recInfo){
        recInfo.textContent = `Compra #${compraId} · ${fechaStr} · Cliente: ${clienteNombre}`;
      }
      if(recBody){
        recBody.innerHTML = '';
        itemsRecibo.forEach(it=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${it.nombre}</td>
            <td>${it.cant}</td>
            <td>$${fmt(it.precio)}</td>
            <td>$${fmt(it.subtotal)}</td>
          `;
          recBody.appendChild(tr);
        });
      }
      if(recSub)  recSub.textContent  = "$" + fmt(subtotal);
      if(recIva)  recIva.textContent  = "$" + fmt(iva);
      if(recTotal)recTotal.textContent= "$" + fmt(total);

      showToast('Compra registrada con éxito');
      openModal();

    }catch(err){
      console.error(err);
      showToast('Error al registrar la compra');
      alert('Error al registrar la compra: ' + (err.message || 'desconocido'));
    }finally{
      btnReg.disabled = false;
    }
  });

  // Init
  loadState();
  renderCatalog();
})();
</script>
