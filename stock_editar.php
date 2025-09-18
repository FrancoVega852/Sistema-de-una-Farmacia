<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

class PaginaEditarStock {
    private $conexion;
    private $productoObj;
    private $loteObj;
    private $mensaje = "";
    private $exito   = false;
    private $producto;
    private $lote;
    private $categorias;
    private $producto_id;

    public function __construct($conexion) {
        if (!isset($_SESSION["usuario_id"])) {
            header("Location: login.php");
            exit();
        }
        if ($_SESSION["usuario_rol"] !== "Administrador") {
            die("⛔ No tienes permisos para acceder a esta página.");
        }

        $this->conexion   = $conexion;
        $this->productoObj = new Producto($this->conexion);
        $this->loteObj     = new Lote($this->conexion);

        $this->producto_id = $_GET['id'] ?? null;
        if (!$this->producto_id) {
            die("⚠️ No se especificó el producto a editar.");
        }

        $this->cargarDatos();
    }

    private function cargarDatos() {
        $this->producto = $this->conexion
            ->query("SELECT * FROM Producto WHERE id={$this->producto_id}")
            ->fetch_assoc();
        if (!$this->producto) {
            die("❌ Producto no encontrado.");
        }

        $this->lote = $this->conexion
            ->query("SELECT * FROM Lote WHERE producto_id={$this->producto_id} LIMIT 1")
            ->fetch_assoc();

        $this->categorias = $this->conexion->query("SELECT id, nombre FROM Categoria ORDER BY nombre ASC");
    }

    private function procesarFormulario() {
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
            $stmt = $this->conexion->prepare($sqlProd);
            $stmt->bind_param("sdiiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id, $this->producto_id);
            $ok1 = $stmt->execute();

            // Actualizar o crear lote
            if ($this->lote) {
                $sqlLote = "UPDATE Lote 
                            SET numero_lote=?, fecha_vencimiento=?, cantidad_actual=? 
                            WHERE id=?";
                $stmt2 = $this->conexion->prepare($sqlLote);
                $stmt2->bind_param("ssii", $numero_lote, $fecha_vencimiento, $cantidad_actual, $this->lote['id']);
                $ok2 = $stmt2->execute();
            } else {
                $ok2 = $this->loteObj->crear($this->producto_id, $numero_lote, $fecha_vencimiento, $cantidad_actual);
            }

            if ($ok1 && $ok2) {
                $this->mensaje = "✅ Producto y lote actualizados correctamente.";
                $this->exito   = true;
                $this->cargarDatos(); // recargar datos actualizados
            } else {
                $this->mensaje = "❌ Error al actualizar los datos.";
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
          <title>Editar Producto - Farvec</title>
          <link rel="stylesheet" href="estilos.css">
        </head>
        <body>
          <a href="Stock.php" class="btn-volver">⬅ Volver al Stock</a>
          <h1>✏️ Editar Producto y Lote</h1>

          <?php if (!empty($this->mensaje)): ?>
            <div class="<?= $this->exito ? 'alert-success' : 'alert-error' ?>">
              <?= htmlspecialchars($this->mensaje) ?>
            </div>
          <?php endif; ?>

          <form method="POST" class="card">
            <h2>Datos del Producto</h2>
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($this->producto['nombre']) ?>" required>

            <label>Precio:</label>
            <input type="number" step="0.01" name="precio" value="<?= htmlspecialchars($this->producto['precio']) ?>" required>

            <label>Stock Mínimo:</label>
            <input type="number" name="stock_minimo" value="<?= htmlspecialchars($this->producto['stock_minimo']) ?>" required>

            <label>Categoría:</label>
            <select name="categoria_id" required>
              <?php while ($c = $this->categorias->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= ($this->producto['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>

            <label>
              <input type="checkbox" name="requiere_receta" <?= $this->producto['requiere_receta'] ? 'checked' : '' ?>> Requiere receta
            </label>

            <h2>Datos del Lote</h2>
            <label>Número de Lote:</label>
            <input type="text" name="numero_lote" value="<?= htmlspecialchars($this->lote['numero_lote'] ?? '') ?>" required>

            <label>Fecha de Vencimiento:</label>
            <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($this->lote['fecha_vencimiento'] ?? '') ?>" required>

            <label>Cantidad Actual:</label>
            <input type="number" name="cantidad_actual" value="<?= htmlspecialchars($this->lote['cantidad_actual'] ?? 0) ?>" required>

            <button type="submit" class="btn-editar">💾 Guardar Cambios</button>
          </form>
        </body>
        </html>
        <?php
    }
}

// Ejecución
$conn = new Conexion();
$pagina = new PaginaEditarStock($conn->conexion);
$pagina->mostrar();
