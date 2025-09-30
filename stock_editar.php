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
    $nombre          = $_POST["nombre"];
    $precio          = $_POST["precio"];
    $stock_minimo    = $_POST["stock_minimo"];
    $requiere_receta = isset($_POST["requiere_receta"]) ? 1 : 0;
    $categoria_id    = $_POST["categoria_id"];

    $numero_lote      = $_POST["numero_lote"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $cantidad_actual  = $_POST["cantidad_actual"];

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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{
  --verde:#16a34a; --verde-osc:#15803d; --rojo:#dc2626;
  --gris:#f9fafb; --borde:#e5e7eb; --texto:#111827; --muted:#6b7280;
}
*{box-sizing:border-box}
body{
  margin:0;font-family:Segoe UI,system-ui,sans-serif;min-height:100vh;color:var(--texto);
  background:linear-gradient(-45deg,#f0fdf4,#dcfce7,#e0f2fe,#f3e8ff);
  background-size:400% 400%;animation:gradientMove 20s ease infinite;position:relative;
}
body::before{
  content:"";position:absolute;inset:0;
  background-image:radial-gradient(rgba(0,0,0,0.03) 1px,transparent 1px);
  background-size:20px 20px;pointer-events:none;z-index:0;
}

/* Topbar */
.topbar{display:flex;align-items:center;gap:12px;padding:14px 18px;background:#fff;
  border-bottom:1px solid var(--borde);position:sticky;top:0;z-index:10;animation:slideDown .6s ease}
.back{background:var(--verde-osc);color:#fff;border:none;padding:10px 14px;border-radius:10px;
  box-shadow:0 6px 18px rgba(0,0,0,.08);cursor:pointer;transition:.2s}
.back:hover{transform:translateY(-2px) scale(1.02);opacity:.95}
.h1{display:flex;align-items:center;gap:10px;font-size:22px;color:#0f5132}
.h1 i{color:var(--verde-osc);animation:pulse 2s infinite}

/* Panel */
.panel{max-width:1100px;margin:20px auto;background:#fff;border:1px solid var(--borde);
  border-radius:14px;box-shadow:0 12px 28px rgba(0,0,0,.06);padding:20px;animation:fadeInUp .7s ease}
legend{font-weight:600;margin-bottom:8px;color:var(--verde-osc);font-size:16px}

/* Formulario */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
label{display:block;font-size:14px;margin:8px 0 4px;color:var(--muted)}
input,select{
  width:100%;padding:10px 12px;border:1px solid var(--borde);border-radius:8px;
  outline:none;transition:.2s;font-size:14px
}
input:focus,select:focus{border-color:var(--verde);box-shadow:0 0 0 3px rgba(22,163,74,.2)}
.check{margin-top:10px;display:flex;align-items:center;gap:6px;font-size:14px}

/* Botón */
.btn-editar{
  grid-column:1/-1;margin-top:10px;background:var(--verde);color:#fff;border:none;
  padding:12px;font-size:15px;border-radius:10px;cursor:pointer;
  transition:.3s;display:flex;align-items:center;justify-content:center;gap:8px;
  animation:fadeIn 1s ease;
}
.btn-editar:hover{background:var(--verde-osc);transform:scale(1.03)}

/* Alertas */
.alert-success,.alert-error{
  max-width:1100px;margin:16px auto;padding:12px 16px;border-radius:8px;font-weight:500;
  animation:fadeIn .6s ease
}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #86efac}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}

.footer{text-align:center;color:var(--muted);font-size:12px;margin:20px 0;animation:fadeIn 1.2s ease}

/* Animaciones */
@keyframes gradientMove{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:none}}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.1)}}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <a href="Stock.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Volver al Stock</button></a>
  <div class="h1"><i class="fa-solid fa-pen-to-square"></i> Editar Producto y Lote</div>
</div>

<?php if (!empty($mensaje)): ?>
  <div class="<?= $exito ? 'alert-success' : 'alert-error' ?>">
    <?= htmlspecialchars($mensaje) ?>
  </div>
<?php endif; ?>

<!-- FORM -->
<form method="POST" class="panel form-grid">
  <fieldset>
    <legend>Datos del Producto</legend>
    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($producto['precio']) ?>" required>

    <label>Stock Mínimo:</label>
    <input type="number" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo']) ?>" required>

    <label>Categoría:</label>
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
    <label>Número de Lote:</label>
    <input type="text" name="numero_lote" value="<?= htmlspecialchars($lote['numero_lote'] ?? '') ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($lote['fecha_vencimiento'] ?? '') ?>" required>

    <label>Cantidad Actual:</label>
    <input type="number" name="cantidad_actual" value="<?= htmlspecialchars($lote['cantidad_actual'] ?? 0) ?>" required>
  </fieldset>

  <button type="submit" class="btn-editar"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
</form>

<div class="footer">Farvec • Stock • <?= date('Y') ?></div>
</body>
</html>
