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

// Datos
$clientesRes  = $ctl->clientes();
$productosRes = $ctl->productos();

// Convertimos resultados a arrays para JS
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Nueva Venta - Farvec</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --verde:#16a34a; --verde-oscuro:#15803d; --acento:#e85c4a;
  --azul:#2563eb; --bg:#f0fdf4; --white:#fff; --text:#1f2937; --muted:#6b7280; --borde:#e5e7eb;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:Segoe UI,system-ui,-apple-system,sans-serif;
  background:linear-gradient(135deg,#e0f7fa,#f0fdf4);
  color:var(--text); min-height:100vh;
  animation:fadeIn 1s ease;
}

/* Animaciones */
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}

/* Barra superior */
.topbar{
  display:flex;align-items:center;gap:12px;padding:14px 18px;
  background:linear-gradient(90deg,var(--verde-oscuro),var(--verde));
  color:#fff;position:sticky;top:0;z-index:50;
  box-shadow:0 4px 15px rgba(0,0,0,.15);
}
.back{background:#fff;color:var(--verde-oscuro);padding:10px 14px;border-radius:10px;
  cursor:pointer;transition:.2s;border:none;font-weight:600}
.back:hover{transform:translateY(-2px) scale(1.02);box-shadow:0 6px 16px rgba(0,0,0,.2)}
.h1{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:700}

/* Layout */
.wrap{display:grid;grid-template-columns:2fr 1.1fr;gap:20px;padding:20px;max-width:1400px;margin:0 auto}
@media (max-width:1100px){ .wrap{grid-template-columns:1fr} }

/* Panel */
.panel{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);overflow:hidden;animation:slideUp .6s ease}
.panel-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f9fafb;border-bottom:1px solid var(--borde)}
.panel-title{display:flex;align-items:center;gap:8px}
.panel-title h2{margin:0;font-size:18px;color:var(--verde-oscuro)}
.panel-body{padding:16px}

/* Filtros */
.filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.input, select{padding:10px 12px;border:1px solid var(--borde);border-radius:10px;outline:none;min-width:200px;transition:.3s}
.input:focus, select:focus{border-color:var(--verde);box-shadow:0 0 0 3px rgba(22,163,74,.15)}
.chip{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border-radius:999px;border:1px solid var(--borde);background:#fff;cursor:pointer;transition:.3s}
.chip:hover{background:#f0fdf4}

/* Grid productos */
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media (max-width:1200px){ .grid{grid-template-columns:repeat(2,1fr)} }
@media (max-width:680px){ .grid{grid-template-columns:1fr} }

.card{border:1px solid var(--borde);border-radius:14px;padding:14px;display:flex;flex-direction:column;gap:10px;
  transition:.25s;background:#fff;min-height:160px;position:relative}
.card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,.12)}
.card h4{margin:0;font-size:16px;font-weight:600}
.price{font-weight:700;color:var(--azul);font-size:15px}
.meta{font-size:12px;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;border:1px solid var(--borde);font-size:11px}
.badge.green{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.badge.red{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.controls{display:flex;gap:8px;margin-top:auto}
.qty{width:74px}
.btn-add{flex:1;background:var(--verde);color:#fff;border:none;border-radius:10px;padding:10px 12px;cursor:pointer;transition:.3s;font-weight:600}
.btn-add:hover{animation:pulse 1s infinite}
.btn-add:disabled{background:#9ca3af;cursor:not-allowed}

/* Carrito */
.tot{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.select{min-width:220px}
.cart{width:100%;border-collapse:collapse}
.cart th,.cart td{padding:10px;border-bottom:1px solid var(--borde);font-size:14px;text-align:left}
.cart th{background:#dcfce7;color:#065f46;font-weight:600}
.qty-row{display:flex;align-items:center;gap:6px}
.small{font-size:12px;color:var(--muted)}
.right{margin-left:auto}
.actions{display:flex;gap:8px;margin-top:12px}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--borde);background:#fff;cursor:pointer;transition:.2s;font-weight:600}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.1)}
.btn.primary{background:var(--verde);color:#fff;border-color:var(--verde)}
.btn.warn{background:#f59e0b;color:#fff;border-color:#f59e0b}
.btn.danger{background:#b91c1c;color:#fff;border-color:#b91c1c}
.btn:disabled{opacity:.6;cursor:not-allowed}

/* Resumen */
.summary{background:#ecfdf5;border:1px dashed #86efac;border-radius:14px;padding:14px;margin-top:10px;animation:fadeIn .6s ease}
.summary div{display:flex;justify-content:space-between;margin:6px 0}
.summary strong{font-weight:700}
#s-total{background:linear-gradient(90deg,#16a34a,#22c55e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800}

/* Footer */
.footer{margin-top:20px;padding:10px;text-align:center;color:var(--muted);font-size:12px}
</style>
</head>
<body>

<!-- TOP -->
<div class="topbar">
  <a href="ventas_listar.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Volver</button></a>
  <div class="h1"><i class="fa-solid fa-cash-register"></i> Nueva Venta</div>
</div>

<div class="wrap">

  <!-- CATÁLOGO -->
  <section class="panel">
    <div class="panel-header">
      <div class="panel-title"><i class="fa-solid fa-capsules" style="color:var(--verde-oscuro)"></i><h2>Catálogo</h2></div>
      <div class="small">Productos disponibles: <strong><?= count($productos) ?></strong></div>
    </div>
    <div class="panel-body">
      <div class="filters">
        <input id="q" class="input" type="search" placeholder="Buscar por nombre…">
        <select id="f-cat">
          <option value="">Todas las categorías</option>
          <?php
            $cats = array_values(array_unique(array_map(fn($r)=>$r['categoria'],$productos)));
            sort($cats);
            foreach($cats as $cat) echo '<option>'.htmlspecialchars($cat).'</option>';
          ?>
        </select>
        <label class="chip"><input id="f-stock" type="checkbox"> Solo con stock</label>
        <label class="chip"><input id="f-receta" type="checkbox"> Requiere receta</label>
      </div>
      <div id="grid" class="grid"></div>
    </div>
  </section>

  <!-- CARRITO -->
  <section class="panel">
    <div class="panel-header">
      <div class="panel-title"><i class="fa-solid fa-basket-shopping" style="color:var(--verde-oscuro)"></i><h2>Carrito</h2></div>
      <div class="small right">Todos los importes incluyen IVA</div>
    </div>
    <div class="panel-body">
      <div class="tot">
        <select id="cliente" class="select">
          <option value="">Consumidor Final</option>
          <?php foreach($clientes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="pago" class="select">
          <option value="Efectivo">Efectivo</option>
          <option value="Tarjeta">Tarjeta</option>
          <option value="Cuenta Corriente">Cuenta Corriente</option>
        </select>
        <input id="nota" class="input" style="flex:1" placeholder="Nota en ticket (opcional)">
      </div>
      <div style="overflow:auto; max-height:420px; border:1px solid var(--borde); border-radius:12px">
        <table class="cart" id="cart">
          <thead>
            <tr>
              <th>Producto</th><th>Precio</th><th>Cantidad</th><th>Stock</th><th>Subtotal</th><th>Acción</th>
            </tr>
          </thead>
          <tbody id="cart-body">
            <tr id="empty-row"><td colspan="6" class="small" style="text-align:center;padding:20px">Agrega productos desde el catálogo ➜</td></tr>
          </tbody>
        </table>
      </div>
      <div class="summary">
        <div><span>Subtotal</span><strong id="s-subtotal">$0,00</strong></div>
        <div><span>IVA (21%)</span><strong id="s-iva">$0,00</strong></div>
        <div><span>Descuento</span><input id="descuento" type="number" value="0" min="0" max="100" class="input" style="width:90px"> %</div>
        <div style="border-top:1px dashed #86efac; margin-top:6px; padding-top:6px">
          <span>Total</span><strong id="s-total" style="font-size:20px">$0,00</strong>
        </div>
      </div>
      <div class="actions">
        <button class="btn warn" type="button" onclick="limpiarCarrito()"><i class="fa-solid fa-rotate-left"></i> Vaciar</button>
        <button class="btn" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
        <form id="venta-form" method="POST" action="ventas_guardar.php" style="margin-left:auto;display:flex;gap:8px">
          <input type="hidden" name="cliente_id" id="cliente_id">
          <div id="hidden-lines"></div>
          <button id="btn-registrar" class="btn primary" disabled><i class="fa-solid fa-floppy-disk"></i> Registrar Venta</button>
        </form>
      </div>
    </div>
  </section>
</div>

<div class="footer">Farvec POS • <?= date('Y') ?></div>

<script>
const CATALOGO = <?php echo json_encode($productos, JSON_UNESCAPED_UNICODE); ?>;
const fmt = n => n.toLocaleString('es-AR',{minimumFractionDigits:2, maximumFractionDigits:2});
const $  = sel => document.querySelector(sel);

const grid = $('#grid'), q=$('#q'), fcat=$('#f-cat'), fstock=$('#f-stock'), freceta=$('#f-receta');
const cartBody = $('#cart-body'), emptyRow = $('#empty-row'), hiddenLines = $('#hidden-lines'), btnRegistrar = $('#btn-registrar');
const form = $('#venta-form'), clienteSel = $('#cliente'), clienteId = $('#cliente_id'), descuento = $('#descuento');
let CART = {};

function renderCatalog(){
  const term=(q.value||'').toLowerCase().trim(), cat=fcat.value;
  const onlyStock=fstock.checked, onlyReceta=freceta.checked;
  grid.innerHTML='';
  CATALOGO
    .filter(p=>(!term||p.nombre.toLowerCase().includes(term)))
    .filter(p=>(!cat||p.categoria===cat))
    .filter(p=>(!onlyStock||p.stock>0))
    .filter(p=>(!onlyReceta||p.requiere_receta===1))
    .forEach(p=>{
      const disabled=p.stock<=0, receta=p.requiere_receta===1?`<span class="badge red"><i class="fa-solid fa-prescription"></i> Receta</span>`:'';
      const node=document.createElement('div');
      node.className='card';
      node.innerHTML=`
        <h4>${p.nombre}</h4>
        <div class="meta"><span class="badge"><i class="fa-solid fa-tag"></i> ${p.categoria}</span>
        <span class="badge green"><i class="fa-solid fa-box"></i> Stock: ${p.stock}</span>${receta}</div>
        <div class="price">$${fmt(p.precio)}</div>
        <div class="controls">
          <input type="number" min="1" value="1" class="input qty" id="qty-${p.id}">
          <button class="btn-add" ${disabled?'disabled':''} onclick="addToCart(${p.id})"><i class="fa-solid fa-plus"></i> Añadir</button>
        </div>`;
      grid.appendChild(node);
    });
  if(!grid.children.length){grid.innerHTML='<div class="card" style="grid-column:1/-1;text-align:center;color:#6b7280">No hay productos que coincidan.</div>';}
}
[q,fcat,fstock,freceta].forEach(el=>el.addEventListener('input',renderCatalog));renderCatalog();

function addToCart(id){
  const p=CATALOGO.find(x=>x.id===id);if(!p)return;
  let qtyEl=document.querySelector(`#qty-${id}`);let qty=Math.max(1,parseInt(qtyEl?.value||'1',10));
  if(qty>p.stock)qty=p.stock;
  if(CART[id])CART[id].qty=Math.min(p.stock,CART[id].qty+qty);else CART[id]={...p,qty};
  renderCart();qtyEl.value=1;
}
function removeFromCart(id){delete CART[id];renderCart();}
function setQty(id,q){const p=CART[id];if(!p)return;q=Math.max(1,Math.min(p.stock,parseInt(q||'1',10)));p.qty=q;renderCart(false);}
function renderCart(updateHidden=true){
  emptyRow.style.display=Object.keys(CART).length?'none':'';cartBody.querySelectorAll('tr[data-id]').forEach(tr=>tr.remove());
  let subtotal=0;
  Object.values(CART).forEach(p=>{
    const sub=p.qty*p.precio;subtotal+=sub;
    const tr=document.createElement('tr');tr.dataset.id=p.id;
    tr.innerHTML=`<td>${p.nombre}</td><td>$${fmt(p.precio)}</td>
      <td><div class="qty-row">
        <button class="btn" type="button" onclick="setQty(${p.id},${p.qty-1})">-</button>
        <input class="input" style="width:70px;text-align:center" value="${p.qty}" oninput="setQty(${p.id},this.value)">
        <button class="btn" type="button" onclick="setQty(${p.id},${p.qty+1})">+</button></div></td>
      <td>${p.stock}</td><td>$${fmt(sub)}</td>
      <td><button class="btn danger" type="button" onclick="removeFromCart(${p.id})"><i class="fa-solid fa-trash"></i></button></td>`;
    cartBody.appendChild(tr);
  });
  const iva=subtotal*0.21, desc=Math.min(100,Math.max(0,parseFloat(descuento.value||'0'))), total=subtotal*(1-desc/100);
  $('#s-subtotal').textContent=`$${fmt(subtotal)}`;$('#s-iva').textContent=`$${fmt(iva)}`;$('#s-total').textContent=`$${fmt(total)}`;
  if(updateHidden){hiddenLines.innerHTML='';Object.values(CART).forEach(p=>{hiddenLines.innerHTML+=`<input type="hidden" name="productos[${p.id}][id]" value="${p.id}"><input type="hidden" name="productos[${p.id}][cantidad]" value="${p.qty}">`;});}
  btnRegistrar.disabled=Object.keys(CART).length===0;
}
function limpiarCarrito(){CART={};renderCart();}
form.addEventListener('submit',()=>{clienteId.value=clienteSel.value;});
descuento.addEventListener('input',()=>renderCart(false));
</script>
</body>
</html>
