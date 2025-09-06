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
    $cantidad_inicial = $_POST["cantidad_inicial"];

    // Primero agregamos el producto
    if ($productoObj->agregarProducto($nombre, $precio, 0, $stock_minimo, $requiere_receta, $categoria_id)) {
        $producto_id = $conexion->insert_id;

        // Luego agregamos el lote
        if ($loteObj->crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial)) {
            $mensaje = "✅ Producto y lote registrados correctamente.";
            $exito = true;
        } else {
            $mensaje = "⚠️ Producto registrado pero error al registrar el lote.";
        }
    } else {
        $mensaje = "⚠️ Error al registrar el producto.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Producto y Lote - Farvec</title>
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
  <h1>➕ Agregar Producto y Lote</h1>

  <form method="POST">
    <!-- Datos del Producto -->
    <h2>Datos del Producto</h2>
    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" required>

    <label>Stock Mínimo:</label>
    <input type="number" name="stock_minimo" required>

    <label>Categoría:</label>
    <select name="categoria_id" required>
      <option value="">Seleccione</option>
      <?php while ($c = $categorias->fetch_assoc()): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
      <?php endwhile; ?>
    </select>

    <label><input type="checkbox" name="requiere_receta"> Requiere Receta</label>

    <!-- Datos del Lote -->
    <h2>Datos del Lote</h2>
    <label>Número de Lote:</label>
    <input type="text" name="numero_lote" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required>

    <label>Cantidad Inicial:</label>
    <input type="number" name="cantidad_inicial" required>

    <button type="submit" class="btn">Guardar</button>
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
