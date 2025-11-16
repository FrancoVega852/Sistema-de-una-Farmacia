<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) { exit("ERROR-LOGIN"); }
if (!in_array($_SESSION["usuario_rol"], ["Administrador", "Farmaceutico"])) { exit("ERROR-PERMS"); }

$conn = new Conexion();
$db   = $conn->conexion;

/* ============================
   CATEGORÍAS
============================ */
$categoriasRes = $db->query("SELECT id,nombre FROM Categoria ORDER BY nombre ASC");

/* ============================
   PROVEEDORES
============================ */
$proveedoresRes = $db->query("SELECT id, razonSocial FROM Proveedor ORDER BY razonSocial ASC");

/* ============================
   PRESENTACIONES
============================ */
$presentacionesRes = $db->query("SELECT id, nombre, unidad_medida FROM Presentacion WHERE activo = 1 ORDER BY nombre ASC");

$categorias = [];
while ($c = $categoriasRes->fetch_assoc()) $categorias[] = $c;

$proveedores = [];
while ($p = $proveedoresRes->fetch_assoc()) $proveedores[] = $p;

$presentaciones = [];
while ($pr = $presentacionesRes->fetch_assoc()) $presentaciones[] = $pr;
?>

<!-- ==========================================================
     BOTÓN VOLVER
========================================================== -->
<button id="btnVolverStock" class="btn-back-farvec">
  <i class="fa-solid fa-arrow-left"></i> Volver al Stock
</button>

<!-- ==========================================================
     FORMULARIO COMPLETO
========================================================== -->
<div class="form-modal module-dynamic">
    <h2 class="title-modal">
        <i class="fa-solid fa-circle-plus"></i> Agregar producto, lote y proveedor
    </h2>

    <div class="form-grid">

        <!-- ============================
             BLOQUE: PRODUCTO
        ============================ -->
        <div class="form-box">
            <h3>Datos del producto</h3>

            <label>Nombre</label>
            <input type="text" id="p_nombre" placeholder="Ej: Ibuprofeno 400mg">

            <label>Precio de venta</label>
            <input type="number" id="p_precio" step="0.01" placeholder="1500">

            <label>Stock mínimo</label>
            <input type="number" id="p_minimo" placeholder="10">

            <label>Categoría</label>
            <select id="p_categoria">
                <option value="">Seleccione...</option>
                <?php foreach($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Presentación principal</label>
            <select id="p_presentacion">
                <option value="">Seleccione...</option>
                <?php foreach($presentaciones as $pr): ?>
                    <option value="<?= $pr['id'] ?>">
                        <?= htmlspecialchars($pr['nombre']) ?>
                        <?php if ($pr['unidad_medida']): ?>
                            (<?= htmlspecialchars($pr['unidad_medida']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="check-line">
                <input type="checkbox" id="p_receta"> Requiere receta
            </label>
        </div>

        <!-- ============================
             BLOQUE: LOTE + PROVEEDOR
        ============================ -->
        <div class="form-box">
            <h3>Datos del lote y proveedor</h3>

            <label>Número de lote</label>
            <input type="text" id="l_lote">

            <label>Fecha de vencimiento</label>
            <input type="date" id="l_vencimiento" min="<?= date('Y-m-d', strtotime('+91 days')) ?>">

            <label>Cantidad inicial</label>
            <input type="number" id="l_cantidad">

            <hr style="margin:15px 0;border:none;border-top:1px dashed #b6eccc;">

            <label>Proveedor principal</label>
            <select id="pr_proveedor">
                <option value="">Seleccione proveedor...</option>
                <?php foreach($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['razonSocial']) ?></option>
                <?php endforeach; ?>
            </select>

            <div id="catalogo_proveedor"
                style="display:none; background:#f6fef9; border:1px solid #b6eccc; border-radius:8px; padding:10px; margin-top:10px;">
                <strong style="color:#14532d;">Catálogo del proveedor</strong>
                <div id="catalogo_listado" style="margin-top:8px; font-size:14px;"></div>
            </div>

            <label>Precio de costo (proveedor)</label>
            <input type="number" id="pr_costo" step="0.01" placeholder="Ej: 1000">

            <label>Código del proveedor (opcional)</label>
            <input type="text" id="pr_codigo" placeholder="Ej: IBUP-600-CJ20">
        </div>

    </div>

    <button class="btn-save" onclick="guardarNuevoProducto()">
        <i class="fa-solid fa-floppy-disk"></i> Guardar
    </button>
</div>

<!-- ==========================================================
     TOAST ✔
========================================================== -->
<div id="toastSuccess" class="toast-success">✔ Producto agregado correctamente</div>

<style>
.toast-success{
  position:fixed;
  top:20px;
  right:-300px;
  background:#16a34a;
  color:#fff;
  padding:14px 18px;
  border-radius:10px;
  font-weight:600;
  box-shadow:0 4px 12px rgba(0,0,0,.2);
  transition:all .5s ease;
  z-index:9999;
}
.toast-success.show{
  right:20px;
}
</style>

<!-- ==========================================================
     ESTILOS stock_agregar (sin cambios)
========================================================== -->
<style>
.form-modal{ padding:20px; }
.title-modal{ margin-bottom:15px;font-size:20px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:8px;}
.title-modal i{ color:#16a34a; }
.form-grid{ display:grid;grid-template-columns:1fr 1fr;gap:18px; }
.form-box{ background:#f6fef9;border:1px solid #b6eccc;border-radius:10px;padding:18px; }
.form-box h3{ margin:0 0 10px;font-size:16px;color:#16a34a;display:flex;align-items:center;gap:6px; }
.form-box label{ display:block;margin-top:10px;font-size:14px;font-weight:600;color:#14532d; }
.form-box input,.form-box select{ width:100%;padding:10px;margin-top:5px;border:1px solid #cdcdcd;border-radius:8px;font-size:14px; }
.btn-save{ width:100%;margin-top:20px;padding:12px;background:#16a34a;color:white;font-weight:700;border:none;border-radius:10px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;gap:8px; }
.btn-back-farvec{
  background:linear-gradient(90deg,#00794f,#00a86b);
  color:#fff; padding:8px 14px;border:none;border-radius:10px;font-weight:600;cursor:pointer;margin-bottom:15px;
}
.btn-back-farvec:hover{ opacity:.9;transform:translateY(-1px); }
@media(max-width:900px){ .form-grid{ grid-template-columns:1fr; } }
</style>

<!-- ==========================================================
     SCRIPT COMPLETO + RETURN CON ANIMACIÓN
========================================================== -->
<script>

// ===============================
// TOAST
// ===============================
function showToastSuccess(){
    const t = document.getElementById("toastSuccess");
    t.classList.add("show");
    setTimeout(()=> t.classList.remove("show"), 3000);
}

// ===============================
// CARGA CATÁLOGO PROVEEDOR (igual que antes)
// ===============================
document.getElementById("pr_proveedor").addEventListener("change", function() {
    const provID = this.value;
    const box = document.getElementById("catalogo_proveedor");
    const list = document.getElementById("catalogo_listado");

    if (!provID) {
        box.style.display = "none";
        list.innerHTML = "";
        return;
    }

    fetch("proveedor_catalogo.php?proveedor_id=" + provID)
        .then(r => r.json())
        .then(data => {

            box.style.display = "block";

            if (data.length === 0) {
                list.innerHTML = "<em>Este proveedor aún no tiene productos cargados.</em>";
                return;
            }

            let html = "<div>";

            data.forEach(p => {
                html += `
                    <div class="item-cat"
                        onclick='seleccionarProductoProveedor(${JSON.stringify(p)})'>
                        <strong>${p.nombre}</strong><br>
                        <span style="color:#166534;">Costo: $${p.precio_costo}</span><br>
                        <small style="color:#475569;">Código prov: ${p.codigo_proveedor ?? "N/A"}</small>
                    </div>
                `;
            });

            html += "</div>";
            list.innerHTML = html;
        });
});

// ===============================
// AUTOCOMPLETE PROVEEDOR
// ===============================
function seleccionarProductoProveedor(prod) {
    document.getElementById("p_nombre").value = prod.nombre;
    document.getElementById("pr_costo").value = prod.precio_costo;
    document.getElementById("pr_codigo").value = prod.codigo_proveedor ?? "";
    if (prod.stock_minimo !== null) document.getElementById("p_minimo").value = prod.stock_minimo;
    if (prod.categoria_id) document.getElementById("p_categoria").value = prod.categoria_id;
    if (prod.presentacion_id) document.getElementById("p_presentacion").value = prod.presentacion_id;
    document.getElementById("p_receta").checked = prod.requiere_receta == 1;

    showToastSuccess("Producto seleccionado del proveedor");
}

// ===============================
// GUARDAR PRODUCTO (MODIFICADO)
// ===============================
function guardarNuevoProducto(){

    const nombre   = document.getElementById("p_nombre").value.trim();
    const precio   = document.getElementById("p_precio").value;
    const minimo   = document.getElementById("p_minimo").value;
    const categoria= document.getElementById("p_categoria").value;
    const present  = document.getElementById("p_presentacion").value;

    const lote     = document.getElementById("l_lote").value.trim();
    const vto      = document.getElementById("l_vencimiento").value;
    const cant     = document.getElementById("l_cantidad").value;

    const proveedor= document.getElementById("pr_proveedor").value;
    const costo    = document.getElementById("pr_costo").value;
    const codProv  = document.getElementById("pr_codigo").value.trim();

    if(!nombre) return alert("⚠ Ingresá el nombre del producto.");
    if(!precio || precio <= 0) return alert("⚠ Precio inválido.");
    if(!minimo || minimo < 0) return alert("⚠ Stock mínimo inválido.");
    if(!categoria) return alert("⚠ Seleccioná categoría.");
    if(!present) return alert("⚠ Seleccioná presentación.");
    if(!lote) return alert("⚠ Número de lote requerido.");
    if(!vto) return alert("⚠ Seleccioná la fecha de vencimiento.");
    if(!cant || cant <= 0) return alert("⚠ Cantidad inválida.");
    if(!proveedor) return alert("⚠ Seleccioná un proveedor.");
    if(!costo || costo <= 0) return alert("⚠ Costo inválido.");

    let data = new FormData();
    data.append("nombre", nombre);
    data.append("precio", precio);
    data.append("stock_minimo", minimo);
    data.append("requiere_receta", document.getElementById("p_receta").checked ? 1 : 0);
    data.append("categoria_id", categoria);
    data.append("presentacion_id", present);
    data.append("numero_lote", lote);
    data.append("fecha_vencimiento", vto);
    data.append("cantidad_inicial", cant);
    data.append("proveedor_id", proveedor);
    data.append("precio_costo", costo);
    data.append("codigo_proveedor", codProv);

    fetch("stock_agregar_json.php", { method:"POST", body:data })
    .then(r => r.json())
    .then(res => {

        if(res.ok){

            showToastSuccess();

            // Esperamos 800ms y volvemos al módulo de stock
            setTimeout(()=>{

                if (typeof cargarModulo === "function") {
                    cargarModulo("stock.php", "Stock y Lotes", {
                      wrapTitle:false,
                      reverse:true
                    });
                } else {
                    window.location.href = "stock.php";
                }

            }, 800);

        } else {
            alert("⚠ " + (res.msg || "Error al guardar"));
        }
    })
    .catch(err => {
        console.error(err);
        alert("❌ Error inesperado.");
    });
}

// ===============================
// BOTÓN VOLVER
// ===============================
document.addEventListener("click", (e) => {
  const btn = e.target.closest("#btnVolverStock");
  if (!btn) return;

  e.preventDefault();

  if (typeof cargarModulo === "function") {
    cargarModulo("stock.php", "Stock y Lotes", {
      wrapTitle:false,
      reverse:true
    });
  } else {
    window.location.href = "stock.php";
  }
});
</script>
