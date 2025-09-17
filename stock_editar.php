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

// Obtener producto
$producto = $conn->conexion->query("SELECT * FROM Producto WHERE id=$producto_id")->fetch_assoc();
if (!$producto) die("❌ Producto no encontrado.");

// Obtener lote (simplemente el primero asociado)
$lote = $conn->conexion->query("SELECT * FROM Lote WHERE producto_id=$producto_id LIMIT 1")->fetch_assoc();

// Categorías
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

    // Actualizar producto
    $sqlProd = "UPDATE Producto 
                SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? 
                WHERE id=?";
    $stmt = $conn->conexion->prepare($sqlProd);
    $stmt->bind_param("sdiiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id, $producto_id);
    $ok1 = $stmt->execute();

    // Actualizar o crear lote
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
  <link rel="stylesheet" href="estilos.css">
</head>
<body>
  <a href="Stock.php" class="btn-volver">⬅ Volver al Stock</a>
  <h1>✏️ Editar Producto y Lote</h1>

  <?php if (!empty($mensaje)): ?>
    <div class="<?= $exito ? 'alert-success' : 'alert-error' ?>">
      <?= htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <h2>Datos del Producto</h2>
    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" required>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($producto['precio']) ?>" required>

    <label>Stock Mínimo:</label>
    <input type="number" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo']) ?>" required>

    <label>Categoría:</label>
    <select name="categoria_id" required>
      <?php while ($c = $categorias->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>" <?= ($producto['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['nombre']) ?>
        </option>
      <?php endwhile; ?>
    </select>

    <label>
      <input type="checkbox" name="requiere_receta" <?= $producto['requiere_receta'] ? 'checked' : '' ?>> Requiere receta
    </label>

    <h2>Datos del Lote</h2>
    <label>Número de Lote:</label>
    <input type="text" name="numero_lote" value="<?= htmlspecialchars($lote['numero_lote'] ?? '') ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($lote['fecha_vencimiento'] ?? '') ?>" required>

    <label>Cantidad Actual:</label>
    <input type="number" name="cantidad_actual" value="<?= htmlspecialchars($lote['cantidad_actual'] ?? 0) ?>" required>

    <button type="submit" class="btn-editar">💾 Guardar Cambios</button>
  </form>
</body>
</html>
