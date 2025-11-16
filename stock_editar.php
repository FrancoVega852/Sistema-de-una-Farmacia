<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) { exit("ERROR-LOGIN"); }
// si querés permitir también Farmacéutico:
if (!in_array($_SESSION["usuario_rol"], ["Administrador","Farmaceutico"])) {
    exit("ERROR-PERMS");
}

$conn = new Conexion();
$db   = $conn->conexion;

/* ==========================================================
   MODO AJAX (POST): GUARDAR CAMBIOS Y DEVOLVER JSON
========================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    header("Content-Type: application/json; charset=utf-8");

    $producto_id = isset($_POST["producto_id"]) ? (int)$_POST["producto_id"] : 0;
    if ($producto_id <= 0) {
        echo json_encode(["ok"=>false,"msg"=>"ID de producto inválido."]);
        exit;
    }

    $nombre          = trim($_POST["nombre"] ?? "");
    $precio          = (float)($_POST["precio"] ?? 0);
    $stock_minimo    = isset($_POST["stock_minimo"]) ? (int)$_POST["stock_minimo"] : 0;
    $requiere_receta = !empty($_POST["requiere_receta"]) ? 1 : 0;
    $categoria_id    = isset($_POST["categoria_id"]) ? (int)$_POST["categoria_id"] : 0;

    $numero_lote       = trim($_POST["numero_lote"] ?? "");
    $fecha_vencimiento = $_POST["fecha_vencimiento"] ?? "";
    $cantidad_actual   = isset($_POST["cantidad_actual"]) ? (int)$_POST["cantidad_actual"] : 0;
    $lote_id           = isset($_POST["lote_id"]) ? (int)$_POST["lote_id"] : 0;

    // Validaciones básicas
    if ($nombre === "") {
        echo json_encode(["ok"=>false,"msg"=>"Ingresá el nombre del producto."]);
        exit;
    }
    if ($precio <= 0) {
        echo json_encode(["ok"=>false,"msg"=>"Precio inválido."]);
        exit;
    }
    if ($stock_minimo < 0) {
        echo json_encode(["ok"=>false,"msg"=>"Stock mínimo inválido."]);
        exit;
    }
    if ($categoria_id <= 0) {
        echo json_encode(["ok"=>false,"msg"=>"Seleccioná una categoría."]);
        exit;
    }
    if ($numero_lote === "") {
        echo json_encode(["ok"=>false,"msg"=>"Número de lote requerido."]);
        exit;
    }
    if ($fecha_vencimiento === "") {
        echo json_encode(["ok"=>false,"msg"=>"Seleccioná la fecha de vencimiento."]);
        exit;
    }
    if ($cantidad_actual < 0) {
        echo json_encode(["ok"=>false,"msg"=>"Cantidad inválida."]);
        exit;
    }

    // UPDATE Producto (mismo funcionamiento que tu versión original)
    $sqlProd = "UPDATE Producto 
                SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? 
                WHERE id=?";
    $stmt = $db->prepare($sqlProd);
    if (!$stmt) {
        echo json_encode(["ok"=>false,"msg"=>"Error al preparar actualización de producto."]);
        exit;
    }
    $stmt->bind_param("sdiiii",
        $nombre,
        $precio,
        $stock_minimo,
        $requiere_receta,
        $categoria_id,
        $producto_id
    );
    $ok1 = $stmt->execute();
    $stmt->close();

    // UPDATE / INSERT del lote principal
    $ok2 = false;
    if ($lote_id > 0) {
        $sqlLote = "UPDATE Lote
                    SET numero_lote=?, fecha_vencimiento=?, cantidad_actual=?
                    WHERE id=?";
        $stmt2 = $db->prepare($sqlLote);
        if (!$stmt2) {
            echo json_encode(["ok"=>false,"msg"=>"Error al preparar actualización de lote."]);
            exit;
        }
        $stmt2->bind_param("ssii",
            $numero_lote,
            $fecha_vencimiento,
            $cantidad_actual,
            $lote_id
        );
        $ok2 = $stmt2->execute();
        $stmt2->close();
    } else {
        // Si no tenía lote, lo creamos (usa tu clase Lote)
        $loteObj = new Lote($db);
        $ok2 = $loteObj->crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_actual);
    }

    if ($ok1 && $ok2) {
        echo json_encode(["ok"=>true,"msg"=>"Producto y lote actualizados correctamente."]);
    } else {
        echo json_encode(["ok"=>false,"msg"=>"Error al actualizar los datos."]);
    }
    exit;
}

/* ==========================================================
   MODO GET: CARGAR DATOS PARA MOSTRAR FORM
========================================================== */
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($producto_id <= 0) {
    exit("⚠️ No se especificó el producto a editar.");
}

// Producto
$stmtProd = $db->prepare("SELECT * FROM Producto WHERE id = ? LIMIT 1");
$stmtProd->bind_param("i", $producto_id);
$stmtProd->execute();
$producto = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();

if (!$producto) {
    exit("❌ Producto no encontrado.");
}

// Lote principal (el más próximo a vencer)
$stmtLote = $db->prepare("SELECT * FROM Lote WHERE producto_id = ? ORDER BY fecha_vencimiento ASC LIMIT 1");
$stmtLote->bind_param("i", $producto_id);
$stmtLote->execute();
$lote = $stmtLote->get_result()->fetch_assoc();
$stmtLote->close();

// Categorías
$categoriasRes = $db->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");
$categorias = [];
while ($c = $categoriasRes->fetch_assoc()) $categorias[] = $c;

// IDs y fecha para JS/inputs
$lote_id_js = $lote ? (int)$lote['id'] : 'null';
$fechaVal   = '';
if (!empty($lote['fecha_vencimiento'])) {
    $fechaVal = date('Y-m-d', strtotime($lote['fecha_vencimiento']));
}
?>
<!-- ==========================================================
     BOTÓN VOLVER  (igual que en stock_agregar)
========================================================== -->
<button id="btnVolverStock" class="btn-back-farvec">
  <i class="fa-solid fa-arrow-left"></i> Volver al Stock
</button>

<!-- ==========================================================
     FORMULARIO DE EDICIÓN (mismo diseño que stock_agregar)
========================================================== -->
<div class="form-modal module-dynamic">
    <h2 class="title-modal">
        <i class="fa-solid fa-pen-to-square"></i> Editar producto y lote
    </h2>

    <div class="form-grid">

        <!-- ============================
             BLOQUE: PRODUCTO
        ============================ -->
        <div class="form-box">
            <h3>Datos del producto</h3>

            <label>Nombre</label>
            <input 
                type="text" 
                id="p_nombre" 
                value="<?= htmlspecialchars($producto['nombre']) ?>" 
                placeholder="Ej: Ibuprofeno 400mg"
            >

            <label>Precio de venta</label>
            <input 
                type="number" 
                id="p_precio" 
                step="0.01" 
                value="<?= htmlspecialchars($producto['precio']) ?>" 
                placeholder="1500"
            >

            <label>Stock mínimo</label>
            <input 
                type="number" 
                id="p_minimo" 
                value="<?= (int)$producto['stock_minimo'] ?>" 
                placeholder="10"
            >

            <label>Categoría</label>
            <select id="p_categoria">
                <option value="">Seleccione...</option>
                <?php foreach($categorias as $c): ?>
                    <option 
                        value="<?= $c['id'] ?>" 
                        <?= ($producto['categoria_id'] == $c['id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="check-line">
                <input 
                    type="checkbox" 
                    id="p_receta" 
                    <?= !empty($producto['requiere_receta']) ? 'checked' : '' ?>
                > Requiere receta
            </label>
        </div>

        <!-- ============================
             BLOQUE: LOTE
        ============================ -->
        <div class="form-box">
            <h3>Datos del lote</h3>

            <label>Número de lote</label>
            <input 
                type="text" 
                id="l_lote" 
                value="<?= htmlspecialchars($lote['numero_lote'] ?? '') ?>"
            >

            <label>Fecha de vencimiento</label>
            <input 
                type="date" 
                id="l_vencimiento"
                value="<?= htmlspecialchars($fechaVal) ?>"
            >

            <label>Cantidad actual</label>
            <input 
                type="number" 
                id="l_cantidad" 
                value="<?= isset($lote['cantidad_actual']) ? (int)$lote['cantidad_actual'] : 0 ?>"
            >
        </div>

    </div>

    <button class="btn-save" onclick="guardarEdicionProducto()">
        <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
    </button>
</div>

<!-- ==========================================================
     ESTILOS (copiados de stock_agregar)
========================================================== -->
<style>
.form-modal{ padding:20px; }
.title-modal{ margin-bottom:15px;font-size:20px;font-weight:700;color:#15803d;display:flex;align-items:center;gap:8px;}
.title-modal i{ color:#16a34a; }
.form-grid{ display:grid;grid-template-columns:1fr 1fr;gap:18px; }
.form-box{ background:#f6fef9;border:1px solid #b6eccc;border-radius:10px;padding:18px; }
.form-box h3{ margin:0 0 10px;font-size:16px;color:#16a34a;display:flex;align-items:center;gap:6px; }
.form-box h3::before{ content:"";width:6px;height:16px;border-radius:999px;background:#16a34a; }
.form-box label{ display:block;margin-top:10px;font-size:14px;font-weight:600;color:#14532d; }
.form-box input,.form-box select{ width:100%;padding:10px;margin-top:5px;border:1px solid #cdcdcd;border-radius:8px;font-size:14px; }
.check-line{ margin-top:12px;font-size:14px;display:flex;align-items:center;gap:8px; }
.btn-save{ width:100%;margin-top:20px;padding:12px;background:#16a34a;color:white;font-weight:700;border:none;border-radius:10px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;gap:8px; }
.btn-save:hover{ filter:brightness(1.08); }
@media(max-width:900px){ .form-grid{ grid-template-columns:1fr; } }

/* Botón volver */
.btn-back-farvec{
  background:linear-gradient(90deg,#00794f,#00a86b);
  color:#fff;
  padding:8px 14px;
  border:none;
  border-radius:10px;
  font-weight:600;
  cursor:pointer;
  margin-bottom:15px;
}
.btn-back-farvec:hover{
  opacity:.9;
  transform:translateY(-1px);
}
</style>

<!-- ==========================================================
     SCRIPT COMPLETO
========================================================== -->
<script>
// IDs PHP → JS
const EDIT_PRODUCTO_ID = <?= (int)$producto_id ?>;
const EDIT_LOTE_ID     = <?= $lote_id_js ?>;

/* ============================
   Guardar edición (AJAX)
============================ */
function guardarEdicionProducto(){

    const nombre   = document.getElementById("p_nombre").value.trim();
    const precio   = document.getElementById("p_precio").value;
    const minimo   = document.getElementById("p_minimo").value;
    const categoria= document.getElementById("p_categoria").value;

    const lote     = document.getElementById("l_lote").value.trim();
    const vto      = document.getElementById("l_vencimiento").value;
    const cant     = document.getElementById("l_cantidad").value;

    if(!nombre)  return alert("⚠ Ingresá el nombre del producto.");
    if(!precio || precio <= 0) return alert("⚠ Precio inválido.");
    if(minimo === "" || minimo < 0) return alert("⚠ Stock mínimo inválido.");
    if(!categoria) return alert("⚠ Seleccioná categoría.");
    if(!lote) return alert("⚠ Número de lote requerido.");
    if(!vto)  return alert("⚠ Seleccioná la fecha de vencimiento.");
    if(cant === "" || cant < 0) return alert("⚠ Cantidad inválida.");

    let data = new FormData();
    data.append("producto_id", EDIT_PRODUCTO_ID);
    if (EDIT_LOTE_ID !== null) {
        data.append("lote_id", EDIT_LOTE_ID);
    }
    data.append("nombre", nombre);
    data.append("precio", precio);
    data.append("stock_minimo", minimo);
    data.append("requiere_receta", document.getElementById("p_receta").checked ? 1 : 0);
    data.append("categoria_id", categoria);

    data.append("numero_lote", lote);
    data.append("fecha_vencimiento", vto);
    data.append("cantidad_actual", cant);

    fetch("stock_editar.php", { method:"POST", body:data })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            alert("✔ Producto y lote actualizados correctamente.");

            // ✅ Volver al módulo de stock con animación (dashboard)
            if (typeof cargarModulo === "function") {
                cargarModulo("stock.php", "Stock y Lotes", {
                  wrapTitle: false,
                  reverse: true  // animación de regreso
                });
            } else {
                // ✅ Fallback: si abriste stock_editar.php directo
                window.location.href = "stock.php";
            }
        }else{
            alert("⚠ " + (res.msg || "Error al guardar los cambios"));
        }
    })
    .catch(err => {
        console.error(err);
        alert("❌ Error inesperado al editar.");
    });
}

/* ============================
   BOTÓN VOLVER
============================ */
document.addEventListener("click", (e) => {
  const btn = e.target.closest("#btnVolverStock");
  if (!btn) return;

  e.preventDefault();

  if (typeof cargarModulo === "function") {
    cargarModulo("stock.php", "Stock y Lotes", {
      wrapTitle: false,
      reverse: true
    });
  } else {
    window.location.href = "stock.php";
  }
});
</script>
