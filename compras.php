<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorCompras.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$ctl  = new ControladorCompras($conn->conexion);

$proveedoresRes = $ctl->proveedores();
$prov_id = isset($_GET['prov']) ? (int)$_GET['prov'] : null;
$productosRes  = $ctl->productos($prov_id);
$productos = [];
while($p=$productosRes->fetch_assoc()){
  $productos[] = [
    'id'=>(int)$p['id'],
    'nombre'=>$p['nombre'],
    'presentacion'=>$p['presentacion'] ?? '',
    'precio'=>(float)$p['precio'],
    'stock'=>(int)$p['stock_actual'],
    'min'=>(int)$p['stock_minimo'],
    'categoria'=>$p['categoria'] ?? 'General',
    'asoc'=>(int)$p['asociado'],
    'lote'=>$p['numero_lote'] ?? '',
    'vto'=>$p['fecha_vencimiento'] ?? ''
  ];
}
$sug = $ctl->sugerencias();   // RF04.03
$top = $ctl->topVendidos();   // RF04.04
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva Compra - Farvec</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{ --verde:#008f4c; --osc:#006837; --borde:#e5e7eb; --muted:#6b7280; --txt:#1f2937; --acento:#f97316 }
*{box-sizing:border-box}
body{margin:0;font-family:Segoe UI,system-ui,Arial;background:linear-gradient(180deg,#f3faf6 0%, #eef5f1 18%, #f5f7f8 100%); color:var(--txt)}
.top{display:flex;gap:12px;align-items:center;padding:14px 18px;background:#fff;border-bottom:1px solid var(--borde)}
.top .h1{font-size:20px;color:var(--osc);font-weight:800}
.back{background:var(--osc);color:#fff;border:0;border-radius:10px;padding:10px 14px;cursor:pointer}
.wrap{display:grid;grid-template-columns:2fr 1.2fr;gap:14px;padding:14px;max-width:1400px;margin:0 auto}
@media (max-width:1100px){.wrap{grid-template-columns:1fr}}
.card{background:#fff;border:1px solid var(--borde);border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.06);overflow:hidden}
.card-h{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid var(--borde)}
.card-b{padding:12px}
.input, select{border:1px solid var(--borde);border-radius:10px;padding:10px 12px;outline:none}
.filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media (max-width:1200px){.grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:640px){.grid{grid-template-columns:1fr}}
.product{border:1px solid var(--borde);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px}
.product h4{margin:0}
.badge{border:1px solid var(--borde);border-radius:999px;padding:3px 8px;font-size:11px;color:#334155;display:inline-flex;gap:6px;align-items:center}
.badge.green{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
.badge.orange{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--borde);background:#fff;cursor:pointer}
.btn.primary{background:var(--verde);border-color:var(--verde);color:#fff}
.btn.warn{background:#f59e0b;color:#fff;border-color:#f59e0b}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid var(--borde);font-size:14px;text-align:left}
.table th{background:#f0fdf4;color:#064e3b}
.small{font-size:12px;color:var(--muted)}
.right{margin-left:auto}
.kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:10px}
.kpi{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:10px}
</style>
</head>
<body>

<div class="top">
  <a href="Menu.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Menú</button></a>
  <div class="h1"><i class="fa-solid fa-truck"></i> Nueva Compra</div>
  <div class="right"></div>
</div>

<div class="wrap">

  <!-- Catálogo -->
  <section class="card">
    <div class="card-h">
      <div><strong>Catálogo</strong></div>
      <div class="small">
        Proveedor:
        <select id="prov" onchange="cambiarProveedor()">
          <option value="">(Todos)</option>
          <?php while($pr=$proveedoresRes->fetch_assoc()): ?>
            <option value="<?= $pr['id'] ?>" <?= ($prov_id==$pr['id']?'selected':'') ?>>
              <?= htmlspecialchars($pr['razonSocial']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>
    <div class="card-b">
      <div class="filters">
        <input id="q" class="input" type="search" placeholder="Buscar producto…">
        <select id="f-cat" class="input">
          <option value="">Todas las categorías</option>
          <?php
            $cats = array_values(array_unique(array_map(fn($r)=>$r['categoria'],$productos)));
            sort($cats);
            foreach($cats as $c) echo '<option>'.htmlspecialchars($c).'</option>';
          ?>
        </select>
        <label class="badge"><input id="f-asoc" type="checkbox" style="accent-color:#008f4c"> Solo asociados al proveedor</label>
        <label class="badge"><input id="f-min" type="checkbox" style="accent-color:#008f4c"> Stock ≤ mínimo</label>
      </div>

      <div id="grid" class="grid"></div>
    </div>
  </section>

  <!-- Carrito -->
  <section class="card">
    <div class="card-h"><strong>Carrito</strong><span class="small">Costo + lote/vencimiento opcionales</span></div>
    <div class="card-b">

      <div class="kpis">
        <div class="kpi"><div class="small">Proveedor seleccionado</div>
          <div id="prov-name"><em><?= $prov_id ? '' : 'Sin seleccionar' ?></em></div></div>
        <div class="kpi"><div class="small">Obs.</div>
          <input id="obs" class="input" placeholder="Observaciones (opcional)"></div>
      </div>

      <div style="overflow:auto;max-height:430px;border:1px solid var(--borde);border-radius:12px">
        <table class="table" id="cart">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Presentación</th>
              <th>Categoría</th>
              <th>Precio Base</th>
              <th style="width:90px">Cant</th>
              <th style="width:110px">Costo</th>
              <th style="width:130px">Lote</th>
              <th style="width:140px">Venc.</th>
              <th style="width:110px">Subtotal</th>
              <th style="width:80px">Acción</th>
            </tr>
          </thead>
          <tbody id="cart-body">
            <tr id="empty"><td colspan="10" class="small" style="text-align:center;padding:16px">Agrega productos desde el catálogo ➜</td></tr>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:8px;align-items:center;margin-top:10px">
        <div class="right"></div>
        <div class="small">Total</div>
        <div id="total" style="font-weight:800">$0,00</div>
        <form id="f" method="POST" action="compras_guardar.php" style="display:flex;gap:8px">
          <input type="hidden" name="proveedor_id" id="hid-proveedor">
          <input type="hidden" name="obs" id="hid-obs">
          <div id="hid-lines"></div>
          <button id="btn-save" class="btn primary" disabled><i class="fa-solid fa-floppy-disk"></i> Registrar Compra</button>
        </form>
      </div>

    </div>
  </section>

</div>

<script>
// Datos desde PHP
const CATALOGO = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
const fmt = n => n.toLocaleString('es-AR',{minimumFractionDigits:2, maximumFractionDigits:2});
const $  = s => document.querySelector(s);

// proveedor
const provSel = $('#prov'); const provName = $('#prov-name'); const hidProv = $('#hid-proveedor');
function cambiarProveedor(){ const v = provSel.value; location.href='compras.php'+(v?`?prov=${v}`:''); }
if (provSel.value) { provName.textContent = provSel.options[provSel.selectedIndex].text; hidProv.value = provSel.value; }

// filtros catálogo
const grid = $('#grid'), q=$('#q'), fcat=$('#f-cat'), fasoc=$('#f-asoc'), fmin=$('#f-min');

function renderCatalog(){
  const term=(q.value||'').toLowerCase().trim(), cat=fcat.value, onlyAsoc=fasoc.checked, onlyMin=fmin.checked;
  grid.innerHTML='';
  CATALOGO
    .filter(p => (!term || p.nombre.toLowerCase().includes(term)))
    .filter(p => (!cat || p.categoria===cat))
    .filter(p => (!onlyAsoc || p.asoc===1))
    .filter(p => (!onlyMin || p.stock<=p.min))
    .forEach(p=>{
      const n = document.createElement('div'); n.className='product';
      n.innerHTML=`
        <h4>${p.nombre} <span style="font-size:12px;color:#6b7280">(${p.presentacion||''})</span></h4>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="badge"><i class="fa-solid fa-tag"></i> ${p.categoria}</span>
          <span class="badge green"><i class="fa-solid fa-box"></i> Stock: ${p.stock}</span>
          <span class="badge orange"><i class="fa-solid fa-gauge-high"></i> Min: ${p.min}</span>
        </div>
        <div style="font-size:13px;color:#374151">Precio: $${fmt(p.precio)} ${p.lote?`| Lote: ${p.lote}`:''} ${p.vto?`| Vto: ${p.vto}`:''}</div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:auto">
          <input id="qty-${p.id}" class="input" type="number" min="1" value="1" style="width:90px">
          <input id="cost-${p.id}" class="input" type="number" step="0.01" min="0" placeholder="Costo" style="width:120px">
          <button class="btn primary" onclick="addToCart(${p.id})"><i class="fa-solid fa-plus"></i> Añadir</button>
        </div>`;
      grid.appendChild(n);
    });
  if(!grid.children.length){
    grid.innerHTML = `<div class="product" style="grid-column:1/-1;text-align:center;color:#6b7280">
      No hay productos con ese filtro.</div>`;
  }
}
[q,fcat,fasoc,fmin].forEach(e=>e.addEventListener('input', renderCatalog)); renderCatalog();

// carrito
let CART = {};
const cartBody = $('#cart-body'); const emptyRow = $('#empty'); const hidLines = $('#hid-lines'); const btnSave = $('#btn-save');

function addToCart(id){
  const p = CATALOGO.find(x=>x.id===id); if(!p) return;
  let q = parseInt(document.querySelector(`#qty-${id}`)?.value||'1',10); q = Math.max(1,q);
  let c = parseFloat(document.querySelector(`#cost-${id}`)?.value||'0'); c = Math.max(0,c);
  if(!CART[id]) {
    CART[id] = {
      id:p.id, nombre:p.nombre, presentacion:p.presentacion, categoria:p.categoria,
      precio:p.precio, cant:q, costo: c>0?c:p.precio,
      lote:p.lote||'', vto:p.vto||''
    };
  } else {
    CART[id].cant += q;
    if(c>0) CART[id].costo = c;
  }
  renderCart();
}

function removeFromCart(id){ delete CART[id]; renderCart(); }

function setField(id, field, value){
  const r = CART[id]; if(!r) return;
  if(field==='cant'){ r.cant = Math.max(1, parseInt(value||'1',10)); }
  else if(field==='costo'){ r.costo = Math.max(0, parseFloat(value||'0')); }
  else if(field==='lote'){ r.lote = value; }
  else if(field==='vto'){ r.vto = value; }
  renderTotals(false);
}

function renderCart(){
  emptyRow.style.display = Object.keys(CART).length ? 'none' : '';
  cartBody.querySelectorAll('tr[data-id]').forEach(tr=>tr.remove());
  Object.values(CART).forEach(p=>{
    const tr = document.createElement('tr'); tr.dataset.id = p.id;
    const sub = p.cant * (p.costo||0);
    tr.innerHTML = `
      <td>${p.nombre}</td>
      <td>${p.presentacion||''}</td>
      <td>${p.categoria||''}</td>
      <td>$${fmt(p.precio||0)}</td>
      <td><input class="input" style="width:80px" type="number" min="1" value="${p.cant}" oninput="setField(${p.id},'cant',this.value)"></td>
      <td><input class="input" style="width:100px" type="number" step="0.01" min="0" value="${p.costo||''}" placeholder="Costo" oninput="setField(${p.id},'costo',this.value)"></td>
      <td><input class="input" style="width:100px" value="${p.lote||''}" placeholder="Lote" oninput="setField(${p.id},'lote',this.value)"></td>
      <td><input class="input" style="width:120px" type="date" value="${p.vto||''}" oninput="setField(${p.id},'vto',this.value)"></td>
      <td>$${fmt(sub)}</td>
      <td><button class="btn warn" type="button" onclick="removeFromCart(${p.id})"><i class="fa-solid fa-xmark"></i></button></td>`;
    cartBody.appendChild(tr);
  });
  renderTotals(true);
}

function renderTotals(updateHidden){
  let total = 0; Object.values(CART).forEach(p=> total += p.cant * (p.costo||0) );
  document.getElementById('total').textContent = `$${fmt(total)}`;
  btnSave.disabled = !provSel.value || Object.keys(CART).length===0 || total<=0;
  if(updateHidden){
    const obs = document.getElementById('obs').value;
    document.getElementById('hid-obs').value = obs;
    hidProv.value = provSel.value;
    hidLines.innerHTML='';
    Object.values(CART).forEach(p=>{
      hidLines.insertAdjacentHTML('beforeend', `
        <input type="hidden" name="items[${p.id}][id]" value="${p.id}">
        <input type="hidden" name="items[${p.id}][cant]" value="${p.cant}">
        <input type="hidden" name="items[${p.id}][costo]" value="${p.costo}">
        <input type="hidden" name="items[${p.id}][lote]" value="${p.lote||''}">
        <input type="hidden" name="items[${p.id}][vto]" value="${p.vto||''}">
      `);
    });
  }
}

document.getElementById('f').addEventListener('submit', ()=>{
  document.getElementById('hid-obs').value = document.getElementById('obs').value;
});
</script>
</body>
</html>
