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

$conn = new Conexion();
$productoObj = new Producto($conn->conexion);
$loteObj     = new Lote($conn->conexion);

$mensaje = "";
$exito = false;

// Traer categorías desde la BD
$categorias = $conn->conexion->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre          = $_POST["nombre"];
    $precio          = $_POST["precio"];
    $stock_minimo    = $_POST["stock_minimo"];
    $requiere_receta = isset($_POST["requiere_receta"]) ? 1 : 0;
    $categoria_id    = $_POST["categoria_id"];

    $numero_lote      = $_POST["numero_lote"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $cantidad_inicial = $_POST["cantidad_inicial"];

    // Agregar producto
    if ($productoObj->agregarProducto($nombre, $precio, 0, $stock_minimo, $requiere_receta, $categoria_id)) {
        $producto_id = $conn->conexion->insert_id;

        // Agregar lote
        if ($loteObj->crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial)) {
            $mensaje = "✅ Producto y lote registrados correctamente.";
            $exito = true;
        } else {
            $mensaje = "⚠️ Producto registrado, pero error al crear lote.";
        }
    } else {
        $mensaje = "❌ Error al registrar producto.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Producto y Lote - Farvec</title>
  <link rel="stylesheet" href="estilos.css">
</head>
<body>
  <a href="Stock.php" class="btn-volver">⬅ Volver al Stock</a>
  <h1>➕ Agregar Producto y Lote</h1>

  <?php if (!empty($mensaje)): ?>
    <div class="<?= $exito ? 'alert-success' : 'alert-error' ?>">
      <?= htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <h2>Datos del Producto</h2>
    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" required>

    <label>Stock Mínimo:</label>
    <input type="number" name="stock_minimo" required>

    <label>Categoría:</label>
    <select name="categoria_id" required>
      <option value="">Seleccione...</option>
      <?php while ($c = $categorias->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endwhile; ?>
    </select>

    <label><input type="checkbox" name="requiere_receta"> Requiere receta</label>

    <h2>Datos del Lote</h2>
    <label>Número de Lote:</label>
    <input type="text" name="numero_lote" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required>

    <label>Cantidad Inicial:</label>
    <input type="number" name="cantidad_inicial" required>

    <button type="submit" class="btn-add">💾 Guardar</button>
  </form>
</body>
</html>
