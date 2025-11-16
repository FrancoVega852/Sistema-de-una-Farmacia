<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorCompras.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new Conexion();
$ctl  = new ControladorCompras($conn->conexion);

/* ============================
   PROVEEDORES
============================ */
$proveedoresRes = $ctl->proveedores();
$prov_id = isset($_GET['prov']) ? (int)$_GET['prov'] : null;

/* ============================
   PRODUCTOS
============================ */
$productosRes = $ctl->productos($prov_id);
$productos = [];
while ($p = $productosRes->fetch_assoc()) {
    $productos[] = [
        'id'          => (int)$p['id'],
        'nombre'      => $p['nombre'],
        'presentacion'=> $p['presentacion'] ?? '',
        'precio'      => (float)$p['precio'],
        'stock'       => (int)$p['stock_actual'],
        'min'         => (int)$p['stock_minimo'],
        'categoria'   => $p['categoria'] ?? 'General',
        'asoc'        => (int)($p['asociado'] ?? 0),
        'lote'        => $p['numero_lote'] ?? '',
        'vto'         => $p['fecha_vencimiento'] ?? ''
    ];
}

/* ============================
   MAPA PROVEEDOR → PRODUCTOS
   (opcional, para filtrar catálogo
   según proveedor sugerido)
============================ */
$mapaProveedores = file_exists('mapaProveedores.php')
    ? require 'mapaProveedores.php'
    : [];
?>

<!-- ============================
     MÓDULO COMPRAS FARVEC
     (catálogo + carrito)
============================ -->
<div id="m-compras">
  <style>
    /* ——— Colores del menú FARVEC ——— */
    #m-compras{
      --verde:#00794f;
      --verde2:#00a86b;
      --borde:#e5e7eb;
      --txt:#1f2937;
      --mut:#6b7280;
      --panel:#ffffff;
      --bg:#f6f9f8;
      --ok:#0a7e56;
      --err:#b93142;
    }
    #m-compras *{
      box-sizing:border-box;
      font-family:"Inter","Segoe UI",system-ui,Arial;
    }
    #m-compras .wrap{
      display:grid;
      grid-template-columns:2fr 1.2fr;
      gap:16px;
    }
    @media(max-width:1100px){
      #m-compras .wrap{grid-template-columns:1fr}
    }
    #m-compras .card{
      background:var(--panel);
      border:1px solid var(--borde);
      border-radius:14px;
      box-shadow:0 6px 18px rgba(0,0,0,.06);
      overflow:hidden;
    }
    #m-compras .card-h{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:12px 14px;
      border-bottom:1px solid var(--borde);
      background:#f8fbfa;
      color:var(--verde);
    }
    #m-compras .card-b{padding:14px}

    /* Filtros */
    #m-compras .filters{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:10px;
      align-items:center;
    }
    #m-compras .input,
    #m-compras select{
      border:1px solid var(--borde);
      border-radius:10px;
      padding:8px 12px;
      font-size:14px;
      outline:none;
    }
    #m-compras .input:focus,
    #m-compras select:focus{
      border-color:var(--verde);
      box-shadow:0 0 0 2px #00794f25;
    }

    /* Catálogo */
    #m-compras .grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
      gap:12px;
    }
    #m-compras .prod{
      border:1px solid var(--borde);
      border-radius:12px;
      padding:12px;
      background:#fff;
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    #m-compras .prod h4{
      margin:0;
      font-size:16px;
      color:var(--txt);
    }
    #m-compras .badges{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
    }
    #m-compras .badge{
      display:inline-flex;
      gap:6px;
      align-items:center;
      font-size:12px;
      border:1px solid var(--borde);
      border-radius:999px;
      padding:3px 8px;
    }
    #m-compras .b-stock{
      background:#ecfdf5;
      color:#065f46;
      border-color:#a7f3d0;
    }
    #m-compras .b-min{
      background:#fff7ed;
      color:#b45309;
      border-color:#fed7aa;
    }
    #m-compras .btn{
      border:none;
      border-radius:10px;
      padding:9px 12px;
      font-weight:700;
      cursor:pointer;
    }
    #m-compras .btn.primary{
      background:linear-gradient(90deg,var(--verde),var(--verde2));
      color:#fff;
    }
    #m-compras .btn.primary:disabled{
      filter:grayscale(1);
      opacity:.7;
      cursor:not-allowed;
    }

    /* Carrito */
    #m-compras .table{
      width:100%;
      border-collapse:collapse;
      font-size:14px;
    }
    #m-compras .table th,
    #m-compras .table td{
      padding:10px;
      border-bottom:1px solid var(--borde);
    }
    #m-compras .table th{
      background:#ecfdf5;
      color:#064e3b;
      text-align:left;
    }
    #m-compras .kpis{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:10px;
      margin-bottom:10px;
    }
    #m-compras .kpi{
      background:#f8fafc;
      border:1px dashed #d1d5db;
      border-radius:10px;
      padding:8px 10px;
    }

    /* Toasts */
    #m-compras .toast{
      position:fixed;
      right:16px;
      bottom:16px;
      padding:12px 16px;
      border-radius:12px;
      color:#fff;
      font-weight:800;
      box-shadow:0 10px 26px rgba(0,0,0,.18);
      opacity:0;
      transform:translateY(12px);
      pointer-events:none;
      transition:.25s;
      z-index:9999;
    }
    #m-compras .toast.show{
      opacity:1;
      transform:translateY(0);
    }
    #m-compras .toast.ok{
      background:linear-gradient(90deg,#0a7e56,#00a86b);
    }
    #m-compras .toast.err{
      background:linear-gradient(90deg,#b93142,#e05262);
    }
  </style>

  <div class="wrap">
    <!-- ============================
         CATÁLOGO
    ============================= -->
    <section class="card">
      <div class="card-h">
        <strong>Catálogo</strong>

        <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--mut)">
          <!-- BOTÓN: ABRIR MÓDULO AGREGAR PRODUCTO -->
          <button class="btn primary btn-add-producto">
            <i class="fa-solid fa-plus"></i> Agregar Producto
          </button>

          <span>
            Proveedor:
            <select id="c-prov">
              <option value="">(Todos)</option>
              <?php while($pr=$proveedoresRes->fetch_assoc()): ?>
                <option value="<?= $pr['id'] ?>" <?= ($prov_id == $pr['id'] ? 'selected' : '') ?>>
                  <?= htmlspecialchars($pr['razonSocial']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </span>
        </div>
      </div>

      <div class="card-b">
        <div class="filters">
          <input id="c-q" class="input" type="search" placeholder="Buscar producto…">
          <select id="c-cat" class="input">
            <option value="">Todas las categorías</option>
            <?php
              $cats = array_values(array_unique(array_map(fn($r)=>$r['categoria'],$productos)));
              sort($cats);
              foreach($cats as $c){
                  echo '<option>'.htmlspecialchars($c).'</option>';
              }
            ?>
          </select>
          <label style="font-size:13px;color:var(--mut)">
            <input id="c-asoc" type="checkbox" style="accent-color:var(--verde)"> Asociados
          </label>
          <label style="font-size:13px;color:var(--mut)">
            <input id="c-min" type="checkbox" style="accent-color:var(--verde)"> Stock ≤ mínimo
          </label>
        </div>

        <div id="c-grid" class="grid"></div>
      </div>
    </section>

    <!-- ============================
         CARRITO
    ============================= -->
    <section class="card">
      <div class="card-h">
        <strong>Carrito</strong>
        <span style="font-size:13px;color:var(--mut)">Costo + lote/vto opcionales</span>
      </div>

      <div class="card-b">
        <div class="kpis">
          <div class="kpi">
            <div style="font-size:12px;color:var(--mut)">Proveedor</div>
            <div id="c-prov-name">
              <em><?= $prov_id ? '' : 'Sin seleccionar' ?></em>
            </div>
          </div>
          <div class="kpi">
            <div style="font-size:12px;color:var(--mut)">Obs.</div>
            <input id="c-obs" class="input" placeholder="Observaciones (opcional)">
          </div>
        </div>

        <div style="overflow:auto;max-height:420px;border:1px solid var(--borde);border-radius:10px">
          <table class="table">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Presentación</th>
                <th>Categoría</th>
                <th>Precio Base</th>
                <th>Cant</th>
                <th>Costo</th>
                <th>Lote</th>
                <th>Venc.</th>
                <th>Subtotal</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="c-cart">
              <tr id="c-empty">
                <td colspan="10" style="text-align:center;color:#6b7280;padding:14px">
                  Agrega productos desde el catálogo ➜
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="display:flex;gap:10px;align-items:center;justify-content:flex-end;margin-top:10px">
          <div style="font-size:14px;color:var(--mut)">Total:</div>
          <div id="c-total" style="font-weight:800;font-size:18px">$0,00</div>

          <form id="c-form" method="POST" action="compras_guardar.php" style="display:flex;gap:8px">
            <input type="hidden" name="proveedor_id" id="c-h-prov">
            <input type="hidden" name="obs"          id="c-h-obs">
            <div id="c-h-lines"></div>

            <button id="c-save" class="btn primary" disabled>
              <i class="fa-solid fa-floppy-disk"></i> Registrar Compra
            </button>
          </form>
        </div>
      </div>
    </section>
  </div>

  <!-- Toast genérico (añadir / error / etc.) -->
  <div id="c-toast" class="toast"></div>

  <script>
  (function(){
    const ROOT   = document.querySelector('#m-compras');
    const $      = sel => ROOT.querySelector(sel);
    const fmt    = n => Number(n||0).toLocaleString('es-AR',
                      {minimumFractionDigits:2,maximumFractionDigits:2});

    const CATALOGO  = <?= json_encode($productos, JSON_UNESCAPED_UNICODE) ?>;
    const MAPA_PROV = <?= json_encode($mapaProveedores, JSON_UNESCAPED_UNICODE) ?>;
    const PROV_ID   = <?= $prov_id ?? 'null' ?>;

    // Controles principales
    const selProv  = $('#c-prov');
    const provName = $('#c-prov-name');
    const hidProv  = $('#c-h-prov');

    const grid  = $('#c-grid');
    const q     = $('#c-q');
    const fcat  = $('#c-cat');
    const fasoc = $('#c-asoc');
    const fmin  = $('#c-min');

    if (selProv.value) {
      provName.textContent = selProv.options[selProv.selectedIndex].text;
      hidProv.value = selProv.value;
    }

    selProv.addEventListener('change', () => {
      provName.textContent = selProv.options[selProv.selectedIndex]?.text || "(Todos)";
      hidProv.value = selProv.value;
      renderCatalog();
    });

    function showToast(type,msg){
      const t = $('#c-toast');
      t.className = 'toast ' + (type === 'err' ? 'err' : 'ok');
      t.textContent = msg || 'Listo';
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 2200);
    }

    function card(p){
      const div = document.createElement('div');
      div.className='prod';
      div.innerHTML = `
        <h4>${p.nombre}</h4>
        <div class="badges">
          <span class="badge b-stock"><i class="fa-solid fa-box"></i> Stock: ${p.stock}</span>
          <span class="badge b-min"><i class="fa-solid fa-gauge-high"></i> Min: ${p.min}</span>
          ${p.lote ? `<span class="badge"><i class="fa-solid fa-hashtag"></i> ${p.lote}</span>` : ''}
          ${p.vto  ? `<span class="badge"><i class="fa-regular fa-calendar"></i> ${p.vto}</span>` : ''}
        </div>
        <div style="font-size:13px;color:#374151">Precio: $${fmt(p.precio)}</div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:auto">
          <input id="qty-${p.id}" class="input" type="number" min="1" value="1" style="width:80px">
          <input id="cost-${p.id}" class="input" type="number" step="0.01" min="0" placeholder="Costo" style="width:110px">
          <button class="btn primary" data-add="${p.id}">
            <i class="fa-solid fa-plus"></i> Añadir
          </button>
        </div>`;
      return div;
    }

    function renderCatalog(){
      const term    = (q.value||'').toLowerCase().trim();
      const cat     = fcat.value;
      const onlyAsoc= fasoc.checked;
      const onlyMin = fmin.checked;

      grid.innerHTML = '';
      let list = [...CATALOGO];

      if (selProv.value && MAPA_PROV && MAPA_PROV[selProv.value]) {
        const ids = MAPA_PROV[selProv.value];
        list = list.filter(p => ids.includes(p.id));
      }

      list = list
        .filter(p => !term || p.nombre.toLowerCase().includes(term))
        .filter(p => !cat  || p.categoria === cat)
        .filter(p => !onlyAsoc || p.asoc === 1)
        .filter(p => !onlyMin  || p.stock <= p.min);

      if (!list.length) {
        grid.innerHTML = '<div style="text-align:center;color:#6b7280;padding:16px">No hay productos para mostrar.</div>';
        return;
      }
      list.forEach(p => grid.appendChild(card(p)));
    }

    [q,fcat,fasoc,fmin].forEach(el => el.addEventListener('input', renderCatalog));
    renderCatalog();

    // ============================
    // CARRITO
    // ============================
    const cartBody = $('#c-cart');
    const emptyRow = $('#c-empty');
    const hidLines = $('#c-h-lines');
    const btnSave  = $('#c-save');
    const totalLbl = $('#c-total');
    const obsInp   = $('#c-obs');
    const form     = $('#c-form');

    let CART = {};

    grid.addEventListener('click', e => {
      const btn = e.target.closest('button[data-add]');
      if (!btn) return;

      const id = btn.getAttribute('data-add');
      const p  = CATALOGO.find(x => x.id === Number(id));
      if (!p) return;

      const qtyEl  = ROOT.querySelector('#qty-'+p.id);
      const costEl = ROOT.querySelector('#cost-'+p.id);

      const qy = Math.max(1, parseInt((qtyEl?.value)||'1',10));
      const cs = Math.max(0, parseFloat((costEl?.value)||'0'));

      if (!CART[p.id]) {
        CART[p.id] = {
          id:    p.id,
          nombre:p.nombre,
          presentacion:p.presentacion,
          categoria:p.categoria,
          precio:p.precio,
          cant: qy,
          costo: cs>0 ? cs : p.precio,
          lote: p.lote || '',
          vto:  p.vto  || ''
        };
      } else {
        CART[p.id].cant += qy;
        if (cs>0) CART[p.id].costo = cs;
      }
      renderCart();
      showToast('ok','Añadido al carrito');
    });

    function inputCell(w,val,on){
      return `<input class="input" style="width:${w}px" value="${val}" oninput="${on}">`;
    }

    function renderCart(){
      emptyRow.style.display = Object.keys(CART).length ? 'none' : '';

      cartBody.querySelectorAll('tr[data-id]').forEach(tr => tr.remove());

      Object.values(CART).forEach(p => {
        const tr = document.createElement('tr');
        tr.dataset.id = p.id;
        const sub = p.cant * (p.costo||0);

        tr.innerHTML = `
          <td>${p.nombre}</td>
          <td>${p.presentacion||''}</td>
          <td>${p.categoria||''}</td>
          <td>$${fmt(p.precio||0)}</td>
          <td>${inputCell(70,p.cant,`mCompras_set(${p.id},'cant',this.value)`)}</td>
          <td>${inputCell(90,p.costo||'',`mCompras_set(${p.id},'costo',this.value)`)}</td>
          <td>${inputCell(90,p.lote||'',`mCompras_set(${p.id},'lote',this.value)`)}</td>
          <td>
            <input class="input" style="width:110px" type="date"
                   value="${p.vto||''}"
                   oninput="mCompras_set(${p.id},'vto',this.value)">
          </td>
          <td>$${fmt(sub)}</td>
          <td>
            <button class="btn" style="background:#f59e0b;color:#fff"
                    onclick="mCompras_del(${p.id})">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </td>`;
        cartBody.appendChild(tr);
      });
      renderTotals(true);
    }

    window.mCompras_set = (id,field,value)=>{
      const r = CART[id];
      if (!r) return;

      if (field === 'cant')  r.cant  = Math.max(1,parseInt(value||'1',10));
      if (field === 'costo') r.costo = Math.max(0,parseFloat(value||'0'));
      if (field === 'lote')  r.lote  = value;
      if (field === 'vto')   r.vto   = value;
      renderTotals(false);
    };

    window.mCompras_del = (id)=>{
      delete CART[id];
      renderCart();
    };

    function renderTotals(updateHidden){
      let total = 0;
      Object.values(CART).forEach(p => total += p.cant*(p.costo||0));
      totalLbl.textContent = `$${fmt(total)}`;

      btnSave.disabled = !selProv.value || Object.keys(CART).length===0 || total<=0;

      if (updateHidden) {
        $('#c-h-obs').value = obsInp.value;
        hidProv.value       = selProv.value;
        hidLines.innerHTML  = '';
        Object.values(CART).forEach(p=>{
          hidLines.insertAdjacentHTML('beforeend',`
            <input type="hidden" name="items[${p.id}][id]"    value="${p.id}">
            <input type="hidden" name="items[${p.id}][cant]"  value="${p.cant}">
            <input type="hidden" name="items[${p.id}][costo]" value="${p.costo}">
            <input type="hidden" name="items[${p.id}][lote]"  value="${p.lote||''}">
            <input type="hidden" name="items[${p.id}][vto]"   value="${p.vto||''}">
          `);
        });
      }
    }

    form.addEventListener('submit',()=>{
      $('#c-h-obs').value = obsInp.value;
    });

    // ===============================
    // ABRIR FORMULARIO DE AGREGAR PRODUCTO EN VENTANA
    // ===============================
    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".btn-add-producto");
      if (!btn) return;

      e.preventDefault();

      // Si estamos dentro del Dashboard con menú dinámico
      if (typeof cargarModulo === "function") {
        cargarModulo("stock_agregar.php", "Agregar Producto", {
          wrapTitle: false,
          reverse: false
        });
      } else {
        // Fallback: navegación normal
        window.location.href = "stock_agregar.php";
      }
    });

    // ===============================
    // TOAST "COMPRA REGISTRADA" (opcional)
    // si compras_guardar.php redirige a compras.php?ok=1
    // ===============================
    const params = new URLSearchParams(window.location.search);
    if (params.get('ok') === '1') {
      showToast('ok','Compra registrada con éxito');
    }

  })();
  </script>
</div>
