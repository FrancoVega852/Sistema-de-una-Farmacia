<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION["usuario_rol"] !== "Administrador") {
    die("⛔ No tienes permisos para acceder a esta página.");
}

$conn        = new Conexion();
$productoObj = new Producto($conn->conexion);
$loteObj     = new Lote($conn->conexion);

$mensaje = "";
$exito   = false;

$producto_id = $_GET['id'] ?? null;
if (!$producto_id) {
    die("⚠️ No se especificó el producto a editar.");
}

$producto = $conn->conexion->query("SELECT * FROM Producto WHERE id=$producto_id")->fetch_assoc();
if (!$producto) die("❌ Producto no encontrado.");

$lote = $conn->conexion->query("SELECT * FROM Lote WHERE producto_id=$producto_id LIMIT 1")->fetch_assoc();

$categorias = $conn->conexion->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre           = $_POST["nombre"];
    $precio           = $_POST["precio"];
    $stock_minimo     = $_POST["stock_minimo"];
    $requiere_receta  = isset($_POST["requiere_receta"]) ? 1 : 0;
    $categoria_id     = $_POST["categoria_id"];

    $numero_lote       = $_POST["numero_lote"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $cantidad_actual   = $_POST["cantidad_actual"];

    $sqlProd = "UPDATE Producto 
                SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? 
                WHERE id=?";
    $stmt = $conn->conexion->prepare($sqlProd);
    $stmt->bind_param("sdiiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id, $producto_id);
    $ok1 = $stmt->execute();

    if ($lote) {
        $sqlLote = "UPDATE Lote 
                    SET numero_lote=?, fecha_vencimiento=?, cantidad_actual=? 
                    WHERE id=?";
        $stmt2 = $conn->conexion->prepare($sqlLote);
        $stmt2->bind_param("ssii", $numero_lote, $fecha_vencimiento, $cantidad_actual, $lote['id']);
        $ok2 = $stmt2->execute();
    } else {
        $ok2 = $loteObj->crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_actual);
    }

    if ($ok1 && $ok2) {
        $mensaje = "✅ Producto y lote actualizados correctamente.";
        $exito   = true;
        $producto = $conn->conexion->query("SELECT * FROM Producto WHERE id=$producto_id")->fetch_assoc();
        $lote     = $conn->conexion->query("SELECT * FROM Lote WHERE producto_id=$producto_id LIMIT 1")->fetch_assoc();
    } else {
        $mensaje = "❌ Error al actualizar los datos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Producto - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
  --verde:#16a34a;
  --verde-osc:#0d5d2a;
  --verde-claro:#44e47b;
  --verde-neon:#38f19d;
  --texto:#ffffff;
  --muted:#eeeeee;
}

/* GENERAL */
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;min-height:100vh;font-family:"Segoe UI",system-ui,sans-serif;color:var(--texto);overflow-x:hidden}

/* ✅ Fondo más claro y futurista */
body{
  background: radial-gradient(circle at 30% 20%, #44c27b, #0d3825 75%);
  background-attachment: fixed;
  animation: bgShift 18s ease-in-out infinite alternate;
}
@keyframes bgShift {
  0%{filter:brightness(1) hue-rotate(0deg);}
  100%{filter:brightness(1.3) hue-rotate(10deg);}
}

/* 💊 Pastillas animadas */
.bg-pastillas{
  position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.5;
  background-image:url("data:image/svg+xml,%3Csvg width='180' height='180' viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2338f19d33'%3E%3Cellipse cx='40' cy='35' rx='12' ry='5' transform='rotate(25 40 35)'/%3E%3Cellipse cx='140' cy='25' rx='10' ry='4' transform='rotate(-20 140 25)'/%3E%3Crect x='72' y='60' width='18' height='6' rx='3' transform='rotate(45 72 60)'/%3E%3Ccircle cx='54' cy='150' r='5'/%3E%3Ccircle cx='160' cy='90' r='4'/%3E%3C/g%3E%3C/svg%3E");
  background-size:200px 200px;
  animation:pillsMove 35s linear infinite alternate;
}
@keyframes pillsMove{0%{background-position:0 0}100%{background-position:250px 240px}}

/* TOPBAR */
.topbar{display:flex;align-items:center;gap:12px;padding:14px 18px;position:sticky;top:0;z-index:5;}
.topbar-inner{
  width:100%;display:flex;align-items:center;gap:14px;
  background:linear-gradient(180deg,rgba(255,255,255,0.15),rgba(255,255,255,0.07));
  border:1px solid rgba(255,255,255,.2);
  border-radius:14px;padding:10px 12px;
  box-shadow:0 8px 20px rgba(0,0,0,.25);
  backdrop-filter:blur(12px);
}
.back{
  background:linear-gradient(90deg,var(--verde),var(--verde-neon));
  color:#fff;border:none;padding:10px 14px;border-radius:12px;cursor:pointer;
  box-shadow:0 0 20px rgba(56,241,157,.5);
}
.back:hover{transform:translateY(-1px) scale(1.02);box-shadow:0 0 26px rgba(56,241,157,.7);}
.h1{display:flex;align-items:center;gap:10px;font-size:22px;color:#fff;font-weight:700;text-shadow:0 0 10px #38f19d88}
.h1 i{color:var(--verde-neon);animation:pulse 2.2s ease-in-out infinite}
.flex-spacer{flex:1}

/* PANEL */
.panel{
  max-width:1100px;margin:22px auto;
  background:linear-gradient(180deg,rgba(255,255,255,.18),rgba(255,255,255,.1));
  border:1px solid rgba(255,255,255,.3);
  border-radius:20px;
  box-shadow:0 20px 40px rgba(0,0,0,.35);
  padding:24px;
  backdrop-filter:blur(16px) saturate(160%);
  position:relative;overflow:hidden;color:#fff;
}
.panel::after{
  content:"";position:absolute;right:-60px;top:-40px;width:180px;height:180px;
  border-radius:50%;background:radial-gradient(#38f19d44,transparent 70%);
  animation:pulseBlob 4s ease-in-out infinite;
}
@keyframes pulseBlob{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}

/* FORMULARIO */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
fieldset{
  border:1px dashed rgba(255,255,255,.3);padding:18px;border-radius:14px;
  background:rgba(255,255,255,.05);transition:.25s;
}
fieldset:hover{transform:translateY(-2px);box-shadow:0 0 22px rgba(56,241,157,.15)}
legend{
  padding:0 10px;font-weight:700;font-size:14px;color:#fff;
  background:rgba(56,241,157,.2);border-radius:999px;
  border:1px solid rgba(56,241,157,.4)
}
label{display:block;font-size:13px;margin:10px 0 6px;color:#fff;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
input,select{
  width:100%;padding:12px 14px;border:1px solid rgba(255,255,255,.3);
  border-radius:10px;outline:none;font-size:14px;background:rgba(255,255,255,.15);
  color:#fff;transition:border-color .2s,box-shadow .2s,transform .08s;
}
input::placeholder{color:#e5e5e5;}
input:focus,select:focus{border-color:var(--verde-neon);box-shadow:0 0 0 3px rgba(56,241,157,.3)}
select{appearance:none;color:#fff;background-image:linear-gradient(45deg,transparent 50%,#fff 50%),linear-gradient(135deg,#fff 50%,transparent 50%);
  background-position:calc(100% - 18px) calc(1.1em),calc(100% - 13px) calc(1.1em);background-size:5px 5px; background-repeat:no-repeat;
}

/* CHECKBOX */
.check{margin-top:12px;display:flex;align-items:center;gap:8px;font-size:14px}
.check input[type="checkbox"]{
  width:18px;height:18px;border-radius:6px;border:1px solid rgba(255,255,255,.4);
  appearance:none;display:grid;place-items:center;background:rgba(255,255,255,.1);cursor:pointer;transition:.2s;
}
.check input[type="checkbox"]:checked{background:var(--verde-neon);border-color:var(--verde-neon)}
.check label{color:#fff;}
.check input[type="checkbox"]::after{
  content:"\f00c";font:normal 12px/1 "Font Awesome 6 Free";font-weight:900;color:#001b10;opacity:0;transform:scale(.6);
  transition:.15s;
}
.check input[type="checkbox"]:checked::after{opacity:1;transform:scale(1)}

/* BOTÓN GUARDAR */
.btn-editar{
  grid-column:1/-1;margin-top:8px;
  background:linear-gradient(90deg,var(--verde),var(--verde-neon));
  color:#fff;border:none;padding:14px 16px;font-size:15px;border-radius:12px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:10px;
  box-shadow:0 0 25px rgba(56,241,157,.6);
  transition:transform .2s,box-shadow .3s;position:relative;overflow:hidden;
}
.btn-editar:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(56,241,157,.8)}
.btn-editar .shine{
  content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent 0%,#ffffff55 30%,transparent 60%);
  transform:translateX(-120%);transition:transform .6s ease;
}
.btn-editar:hover .shine{transform:translateX(120%)}

/* ALERTAS */
.alert-success,.alert-error{
  max-width:1100px;margin:16px auto;padding:14px 16px;border-radius:12px;font-weight:600;
  display:flex;align-items:center;gap:10px;animation:fadeIn .6s ease;color:#fff;
}
.alert-success{background:rgba(56,241,157,.2);border:1px solid rgba(56,241,157,.5);}
.alert-error{background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.4);}

/* FOOTER */
.footer{text-align:center;color:#fff;font-size:12px;margin:22px 0;animation:fadeIn 1.1s ease}

/* ANIMACIONES */
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
</style>
</head>
<body>

<div class="bg-pastillas" aria-hidden="true"></div>

<div class="topbar">
  <div class="topbar-inner">
    <a href="Stock.php">
      <button class="back"><i class="fa-solid fa-arrow-left"></i> Volver al Stock</button>
    </a>
    <div class="h1"><i class="fa-solid fa-pen-to-square"></i> Editar Producto y Lote</div>
    <div class="flex-spacer"></div>
  </div>
</div>

<?php if (!empty($mensaje)): ?>
  <div class="<?= $exito ? 'alert-success' : 'alert-error' ?>">
    <i class="fa-solid <?= $exito ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <?= htmlspecialchars($mensaje) ?>
  </div>
<?php endif; ?>

<form method="POST" class="panel form-grid">
  <fieldset>
    <legend>Datos del Producto</legend>
    <label>Nombre</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
    <label>Precio</label>
    <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($producto['precio']) ?>" required>
    <label>Stock Mínimo</label>
    <input type="number" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo']) ?>" required>
    <label>Categoría</label>
    <select name="categoria_id" required>
      <?php $categorias->data_seek(0); while ($c = $categorias->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>" <?= ($producto['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['nombre']) ?>
        </option>
      <?php endwhile; ?>
    </select>
    <div class="check">
      <input type="checkbox" name="requiere_receta" id="req" <?= $producto['requiere_receta'] ? 'checked' : '' ?>>
      <label for="req">Requiere receta</label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Datos del Lote</legend>
    <label>Número de Lote</label>
    <input type="text" name="numero_lote" value="<?= htmlspecialchars($lote['numero_lote'] ?? '') ?>" required>
    <label>Fecha de Vencimiento</label>
    <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($lote['fecha_vencimiento'] ?? '') ?>" required>
    <label>Cantidad Actual</label>
    <input type="number" name="cantidad_actual" value="<?= htmlspecialchars($lote['cantidad_actual'] ?? 0) ?>" required>
  </fieldset>

  <button type="submit" class="btn-editar">
    <span class="shine"></span>
    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
  </button>
</form>

<div class="footer">Farvec • Stock • <?= date('Y') ?></div>

<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest('.btn-editar');
  if(!btn) return;
  const circle = document.createElement('span');
  const d = Math.max(btn.clientWidth, btn.clientHeight);
  circle.style.width = circle.style.height = d + 'px';
  circle.style.position = 'absolute';
  circle.style.left = (e.clientX - btn.getBoundingClientRect().left - d/2) + 'px';
  circle.style.top  = (e.clientY - btn.getBoundingClientRect().top - d/2) + 'px';
  circle.style.borderRadius = '50%';
  circle.style.background = 'rgba(255,255,255,.4)';
  circle.style.transform = 'scale(0)';
  circle.style.animation = 'ripple .6s ease-out forwards';
  btn.appendChild(circle);
  setTimeout(()=>circle.remove(), 650);
});
(function addRippleCSS(){
  const css = document.createElement('style');
  css.textContent = "@keyframes ripple{to{transform:scale(2.8);opacity:0}}";
  document.head.appendChild(css);
})();
</script>
</body>
</html>
