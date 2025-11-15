<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) { header("Location: login.php"); exit(); }
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

/* Convertimos en arrays */
$categorias = [];
while ($c = $categoriasRes->fetch_assoc()) $categorias[] = $c;

$proveedores = [];
while ($p = $proveedoresRes->fetch_assoc()) $proveedores[] = $p;

$presentaciones = [];
while ($pr = $presentacionesRes->fetch_assoc()) $presentaciones[] = $pr;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Agregar producto - FARVEC</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --verde:#16a34a;
  --verdeOsc:#0e9f6e;
  --azul:#2563eb;
  --rojo:#b91c1c;
}

*{box-sizing:border-box;font-family:'Poppins',sans-serif;}

body{
  margin:0;
  background:#ffffff;
  color:#111827;
}

/* Fondo pill animado */
.bg-pastillas{
  position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.25;
  background-image:url("data:image/svg+xml,%3Csvg width='180' height='180' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2316a34a33'%3E%3Cellipse cx='40' cy='40' rx='12' ry='5' transform='rotate(25 40 40)'/%3E%3Cellipse cx='140' cy='120' rx='10' ry='4' transform='rotate(-35 140 120)'/%3E%3Crect x='80' y='90' width='20' height='6' rx='3' transform='rotate(45 80 90)'/%3E%3Ccircle cx='110' cy='50' r='5'/%3E%3Ccircle cx='60' cy='150' r='4'/%3E%3C/g%3E%3C/svg%3E");
  background-size:200px 200px;
  animation:pillsMove 40s linear infinite alternate;
}
@keyframes pillsMove{0%{background-position:0 0}100%{background-position:220px 200px}}

main{
  position:relative;z-index:1;
  padding:1.5rem 2rem 2.5rem;
}

/* Header */
.header{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:1rem;
}
.header h1{
  margin:0;
  font-size:1.4rem;
  font-weight:700;
  color:var(--verdeOsc);
}
.btn-back{
  background:#ffffff;
  border:1px solid #d1d5db;
  border-radius:.75rem;
  padding:.45rem .9rem;
  font-size:.9rem;
  display:inline-flex;
  align-items:center;
  gap:.4rem;
  color:#374151;
  text-decoration:none;
}
.btn-back:hover{
  background:#f3f4f6;
}

/* Formulario */
.form-modal{
  padding:20px;
  background:#ffffffcc;
  border-radius:1rem;
  border:1px solid #e5e7eb;
  box-shadow:0 10px 30px rgba(0,0,0,.08);
}
.title-modal{
  margin-bottom:15px;
  font-size:20px;
  font-weight:700;
  color:#15803d;
  display:flex;
  align-items:center;
  gap:8px;
}
.title-modal i{
  color:#16a34a;
}
.form-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
}
.form-box{
  background:#f6fef9;
  border:1px solid #b6eccc;
  border-radius:10px;
  padding:18px;
}
.form-box h3{
  margin:0 0 10px;
  font-size:16px;
  color:#16a34a;
  display:flex;
  align-items:center;
  gap:6px;
}
.form-box h3::before{
  content:"";
  width:6px;
  height:16px;
  border-radius:999px;
  background:#16a34a;
}
.form-box label{
  display:block;
  margin-top:10px;
  font-size:14px;
  font-weight:600;
  color:#14532d;
}
.form-box input,
.form-box select{
  width:100%;
  padding:10px;
  margin-top:5px;
  border:1px solid #cdcdcd;
  border-radius:8px;
  font-size:14px;
}
.check-line{
  margin-top:12px;
  font-size:14px;
  display:flex;
  align-items:center;
  gap:8px;
}
.check-line input[type="checkbox"]{
  width:16px;
  height:16px;
}
.btn-save{
  width:100%;
  margin-top:20px;
  padding:12px;
  background:#16a34a;
  color:white;
  font-weight:700;
  border:none;
  border-radius:10px;
  cursor:pointer;
  font-size:16px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:8px;
}
.btn-save:hover{
  filter:brightness(1.08);
}

/* Tarjetas catálogo */
#catalogo_proveedor{
  background:#ecfdf3;
  border:1px solid #bbf7d0;
  border-radius:8px;
  padding:10px;
  margin-top:10px;
}
.item-cat{
  background:#e7f9ed;
  border:1px solid #b6eccc;
  padding:10px;
  border-radius:8px;
  margin-bottom:8px;
  cursor:pointer;
  transition:.15s;
}
.item-cat:hover{
  background:#d1f3dd;
  border-color:#16a34a;
}
.item-cat strong{color:#14532d;}
.item-cat small{color:#64748b;font-size:12px;}

/* Botón ver catálogo completo */
.btn-small{
  margin-top:8px;
  display:inline-flex;
  align-items:center;
  gap:.3rem;
  padding:.35rem .65rem;
  border-radius:999px;
  border:1px dashed #16a34a;
  background:#f0fdf4;
  color:#166534;
  font-size:.75rem;
  cursor:pointer;
}

/* Toast FARVEC */
.farvec-toast-container{
  position:fixed;
  top:18px;
  right:18px;
  z-index:9999;
  display:flex;
  flex-direction:column;
  gap:8px;
}
.farvec-toast{
  min-width:260px;
  max-width:340px;
  background:#111827;
  color:#f9fafb;
  border-radius:999px;
  padding:8px 14px;
  display:flex;
  align-items:center;
  gap:8px;
  box-shadow:0 10px 25px rgba(0,0,0,.35);
  opacity:0;
  transform:translateX(40px);
  animation:toast-in .28s ease forwards;
}
.farvec-toast-success{background:#16a34a;color:#ecfdf5;}
.farvec-toast-error{background:#b91c1c;color:#fee2e2;}
.farvec-toast i{font-size:1rem;}
.farvec-toast.hide{
  animation:toast-out .25s ease forwards;
}
@keyframes toast-in{
  from{opacity:0;transform:translateX(40px);}
  to{opacity:1;transform:translateX(0);}
}
@keyframes toast-out{
  from{opacity:1;transform:translateX(0);}
  to{opacity:0;transform:translateX(40px);}
}

@media(max-width:900px){
  main{padding:1rem;}
  .form-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="bg-pastillas"></div>
<main>
  <div class="header">
    <h1><i class="fa-solid fa-circle-plus"></i> Nuevo producto / lote</h1>
    <a class="btn-back" href="Stock.php">
      <i class="fa-solid fa-arrow-left"></i> Volver a stock
    </a>
  </div>

  <div class="form-modal">
    <h2 class="title-modal">
        <i class="fa-solid fa-circle-plus"></i> Agregar producto, lote y proveedor
    </h2>

    <div class="form-grid">

        <!-- PRODUCTO -->
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
                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Presentación principal</label>
            <select id="p_presentacion">
                <option value="">Seleccione...</option>
                <?php foreach($presentaciones as $pr): ?>
                    <option value="<?= (int)$pr['id'] ?>">
                        <?= htmlspecialchars($pr['nombre']) ?>
                        <?php if (!empty($pr['unidad_medida'])): ?>
                            (<?= htmlspecialchars($pr['unidad_medida']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="check-line">
                <input type="checkbox" id="p_receta"> Requiere receta
            </label>
        </div>

        <!-- LOTE + PROVEEDOR -->
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
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['razonSocial']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- CATÁLOGO DEL PROVEEDOR -->
            <div id="catalogo_proveedor" style="display:none;margin-top:10px;">
                <strong style="color:#14532d;">Catálogo del proveedor</strong>
                <div id="catalogo_listado" style="margin-top:8px;font-size:14px;"></div>
                <button type="button" class="btn-small" id="btnCatalogoCompleto" style="display:none;">
                    <i class="fa-solid fa-list"></i> Ver catálogo completo
                </button>
            </div>

            <label>Precio de costo (proveedor)</label>
            <input type="number" id="pr_costo" step="0.01" placeholder="Ej: 1000">

            <label>Código del proveedor (opcional)</label>
            <input type="text" id="pr_codigo" placeholder="Ej: IBUP-600-CJ20">
        </div>

    </div>

    <!-- BOTÓN -->
    <button class="btn-save" onclick="guardarNuevoProducto()">
        <i class="fa-solid fa-floppy-disk"></i> Guardar
    </button>
  </div>
</main>

<div class="farvec-toast-container" id="farvecToasts"></div>

<script>
/* ========== Toast FARVEC ========= */
function showToast(message, type = 'success'){
    const container = document.getElementById('farvecToasts');
    if(!container) return alert(message);

    const toast = document.createElement('div');
    toast.className = 'farvec-toast farvec-toast-' + (type === 'error' ? 'error' : 'success');

    const icon = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 230);
    }, 2800);
}

/* ========== Cargar catálogo del proveedor ========= */

document.getElementById("pr_proveedor").addEventListener("change", function() {
    const provID = this.value;
    const box = document.getElementById("catalogo_proveedor");
    const list = document.getElementById("catalogo_listado");
    const btnFull = document.getElementById("btnCatalogoCompleto");

    if (!provID) {
        box.style.display = "none";
        list.innerHTML = "";
        btnFull.style.display = "none";
        return;
    }

    fetch("proveedor_catalogo.php?proveedor_id=" + encodeURIComponent(provID))
        .then(r => r.json())
        .then(data => {
            box.style.display = "block";

            if (!Array.isArray(data) || data.length === 0) {
                list.innerHTML = "<em>Este proveedor aún no tiene productos cargados.</em>";
                btnFull.style.display = "none";
                return;
            }

            let html = "";
            data.forEach(p => {
                html += `
                <div class="item-cat"
                     onclick='seleccionarProductoProveedor(${JSON.stringify(p)})'>
                    <strong>${p.nombre}</strong><br>
                    <span style="color:#166534;">Costo: $${Number(p.precio_costo).toFixed(2)}</span><br>
                    <small>Código prov: ${p.codigo_proveedor ? p.codigo_proveedor : "N/A"}</small>
                </div>`;
            });

            list.innerHTML = html;
            btnFull.style.display = "inline-flex";
            btnFull.onclick = () => {
                window.open("catalogo_completo.php?proveedor_id=" + encodeURIComponent(provID), "_blank");
            };
        })
        .catch(err => {
            console.error(err);
            box.style.display = "block";
            list.innerHTML = "<em>Error al cargar el catálogo.</em>";
            btnFull.style.display = "none";
        });
});

/* ========== Autocompleta datos del producto seleccionado del catálogo ========= */

function seleccionarProductoProveedor(prod) {
    document.getElementById("p_nombre").value  = prod.nombre || "";
    document.getElementById("pr_costo").value  = prod.precio_costo || "";
    document.getElementById("pr_codigo").value = prod.codigo_proveedor || "";

    if (prod.stock_minimo !== null && prod.stock_minimo !== undefined) {
        document.getElementById("p_minimo").value = prod.stock_minimo;
    }
    if (prod.categoria_id) {
        document.getElementById("p_categoria").value = prod.categoria_id;
    }
    if (prod.presentacion_id) {
        document.getElementById("p_presentacion").value = prod.presentacion_id;
    }
    if (prod.requiere_receta !== undefined) {
        document.getElementById("p_receta").checked = prod.requiere_receta == 1;
    }

    showToast("Producto del catálogo seleccionado: " + prod.nombre, "success");
}

/* ========== Guardar producto / lote ========= */

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

    // Validaciones básicas
    if(!nombre)   return showToast("Ingresá el nombre del producto.","error");
    if(!precio || precio <= 0) return showToast("Ingresá un precio de venta válido.","error");
    if(minimo === "" || minimo < 0) return showToast("Ingresá un stock mínimo válido.","error");
    if(!categoria) return showToast("Seleccioná una categoría.","error");
    if(!present)   return showToast("Seleccioná una presentación principal.","error");
    if(!lote)      return showToast("Ingresá el número de lote.","error");
    if(!vto)       return showToast("Seleccioná la fecha de vencimiento.","error");
    if(!cant || cant <= 0) return showToast("Ingresá una cantidad inicial válida.","error");
    if(!proveedor) return showToast("Seleccioná un proveedor principal.","error");
    if(!costo || costo <= 0) return showToast("Ingresá el precio de costo del proveedor.","error");

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

    fetch("stock_agregar_json.php", {
        method:"POST",
        body:data
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            showToast(res.msg || "Registro guardado correctamente.","success");
            setTimeout(() => {
                // Volver a la pantalla de stock
                history.back();
            }, 1200);
        }else{
            showToast(res.msg || "Error al guardar el registro.","error");
        }
    })
    .catch(err => {
        console.error(err);
        showToast("Error inesperado al guardar el producto.","error");
    });
}
</script>
</body>
</html>
