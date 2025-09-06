<?php
session_start();
include 'Conexion.php';
include 'Producto.php';
include 'Lote.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["usuario_rol"] !== "Administrador") {
    die("⛔ No tienes permisos para acceder a esta página.");
}

$conn = new Conexion();
$conexion = $conn->conexion;

$productoObj = new Producto($conexion);
$loteObj = new Lote($conexion);

$mensaje = "";
$exito = false;
$producto_id = $_GET['id'] ?? null;

if (!$producto_id) {
    die("⚠️ No se especificó el producto a editar.");
}

// Obtener datos del producto
$producto = $conexion->query("SELECT * FROM Producto WHERE id=$producto_id")->fetch_assoc();

// Obtener lote (tomamos el primero relacionado para simplificar)
$lote = $conexion->query("SELECT * FROM Lote WHERE producto_id=$producto_id LIMIT 1")->fetch_assoc();

// Traer categorías desde la BD
$categorias = $conexion->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST["nombre"];
    $precio = $_POST["precio"];
    $stock_minimo = $_POST["stock_minimo"];
    $requiere_receta = isset($_POST["requiere_receta"]) ? 1 : 0;
    $categoria_id = $_POST["categoria_id"];

    $numero_lote = $_POST["numero_lote"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $cantidad_actual = $_POST["cantidad_actual"];

    // Actualizar producto
    $sqlProd = "UPDATE Producto 
                SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? 
                WHERE id=?";
    $stmt = $conexion->prepare($sqlProd);
    $stmt->bind_param("sdiiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id, $producto_id);
    $ok1 = $stmt->execute();

    // Actualizar lote
    if ($lote) {
        $sqlLote = "UPDATE Lote 
                    SET numero_lote=?, fecha_vencimiento=?, cantidad_actual=? 
                    WHERE id=?";
        $stmt2 = $conexion->prepare($sqlLote);
        $stmt2->bind_param("ssii", $numero_lote, $fecha_vencimiento, $cantidad_actual, $lote['id']);
        $ok2 = $stmt2->execute();
    } else {
        // Si no tenía lote, lo creamos
        $ok2 = $loteObj->crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_actual);
    }

    if ($ok1 && $ok2) {
        $mensaje = "✅ Producto y lote actualizados correctamente.";
        $exito = true;
        // Refrescamos datos
        $producto = $conexion->query("SELECT * FROM Producto WHERE id=$producto_id")->fetch_assoc();
        $lote = $conexion->query("SELECT * FROM Lote WHERE producto_id=$producto_id LIMIT 1")->fetch_assoc();
    } else {
        $mensaje = "⚠️ Error al actualizar.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Producto - Farvec</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
    }
    body { font-family: 'Segoe UI', sans-serif; background: var(--gris); padding: 20px; }
    h1 { color: var(--verde-oscuro); }
    form {
      background: var(--blanco);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      max-width: 600px;
      margin: auto;
    }
    label { display: block; margin-top: 15px; font-weight: bold; }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .btn {
      margin-top: 20px;
      padding: 12px;
      background: var(--verde);
      color: var(--blanco);
      font-weight: bold;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      width: 100%;
    }
    .btn:hover { background: #006837; }
    .btn-menu {
      background: #006837;
      color: white;
      padding: 10px 15px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-bottom: 20px;
      display: inline-block;
    }
    .btn-menu:hover { background: #009f4c; transform: scale(1.05); }
  </style>
</head>
<body>
  <a href="Menu.php" class="btn-menu">⬅️ Volver al Menú</a>
  <h1>✏️ Editar Producto y Lote</h1>

  <form method="POST">
    <!-- Datos del Producto -->
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

    <label><input type="checkbox" name="requiere_receta" <?= ($producto['requiere_receta']) ? 'checked' : '' ?>> Requiere Receta</label>

    <!-- Datos del Lote -->
    <h2>Datos del Lote</h2>
    <label>Número de Lote:</label>
    <input type="text" name="numero_lote" value="<?= htmlspecialchars($lote['numero_lote'] ?? '') ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($lote['fecha_vencimiento'] ?? '') ?>" required>

    <label>Cantidad Actual:</label>
    <input type="number" name="cantidad_actual" value="<?= htmlspecialchars($lote['cantidad_actual'] ?? 0) ?>" required>

    <button type="submit" class="btn">Guardar Cambios</button>
  </form>

  <?php if ($mensaje): ?>
    <script>
      Swal.fire({
        title: '<?= $exito ? "Éxito" : "Error" ?>',
        text: "<?= $mensaje ?>",
        icon: '<?= $exito ? "success" : "error" ?>',
        showClass: { popup: 'animate__animated animate__fadeInDown' },
        hideClass: { popup: 'animate__animated animate__fadeOutUp' }
      });
    </script>
  <?php endif; ?>
</body>
</html>
