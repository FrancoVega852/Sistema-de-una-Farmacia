<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'ControladorStock.php';

class PaginaAgregarStock {
    private $conexion;
    private $controlador;
    private $mensaje = "";

    public function __construct($conexion) {
        if (!isset($_SESSION["usuario_id"])) {
            header("Location: login.php");
            exit();
        }
        $this->conexion = $conexion;
        $this->controlador = new ControladorStock($this->conexion);
    }

    private function procesarFormulario() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $nombre       = trim($_POST['nombre']);
            $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
            $precio       = floatval($_POST['precio']);
            $stockMinimo  = intval($_POST['stock_minimo']);
            $requiereReceta = isset($_POST['requiere_receta']) ? true : false;
            $numeroLote   = trim($_POST['numero_lote']);
            $fechaVto     = $_POST['fecha_vencimiento'] ?? null;
            $cantidad     = intval($_POST['cantidad']);

            $producto = new Producto($this->conexion);
            $resultado = $producto->agregarProductoConLote(
                $nombre,
                $precio,
                $stockMinimo,
                $requiereReceta,
                $categoria_id,
                $numeroLote,
                $fechaVto,
                $cantidad
            );

            if ($resultado) {
                $this->mensaje = "✅ Producto y lote agregados correctamente.";
            } else {
                $this->mensaje = "❌ Error al agregar el producto/lote.";
            }
        }
    }

    public function mostrar() {
        $this->procesarFormulario();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Agregar Producto/Lote - Farvec</title>
            <link rel="stylesheet" href="estilos.css">
        </head>
        <body>
            <a href="stock.php" class="btn-volver">⬅ Volver a Stock</a>
            <h1>➕ Agregar Producto/Lote</h1>

            <?php if (!empty($this->mensaje)): ?>
                <p class="mensaje"><?= htmlspecialchars($this->mensaje) ?></p>
            <?php endif; ?>

            <form method="POST" class="formulario">
                <label>Nombre:</label>
                <input type="text" name="nombre" required>

                <label>Categoría (ID):</label>
                <input type="number" name="categoria_id">

                <label>Precio:</label>
                <input type="number" name="precio" step="0.01" required>

                <label>Stock mínimo:</label>
                <input type="number" name="stock_minimo" required>

                <label>Requiere receta:</label>
                <input type="checkbox" name="requiere_receta">

                <label>Número de lote:</label>
                <input type="text" name="numero_lote">

                <label>Fecha de vencimiento:</label>
                <input type="date" name="fecha_vencimiento">

                <label>Cantidad:</label>
                <input type="number" name="cantidad" required>

                <button type="submit" class="btn-add">Guardar</button>
            </form>
        </body>
        </html>
        <?php
    }
}

// Ejecución
$conn = new Conexion();
$pagina = new PaginaAgregarStock($conn->conexion);
$pagina->mostrar();
