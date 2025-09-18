<?php
session_start();
require_once 'Conexion.php';
require_once 'Producto.php';
require_once 'Lote.php';

class PaginaEliminarStock {
    private $conexion;
    private $productoObj;
    private $loteObj;
    private $producto_id;

    public function __construct($conexion) {
        if (!isset($_SESSION["usuario_id"])) {
            header("Location: login.php");
            exit();
        }
        if ($_SESSION["usuario_rol"] !== "Administrador") {
            die("⛔ No tienes permisos para acceder a esta página.");
        }
        if (!isset($_GET["id"])) {
            die("⚠️ No se especificó el producto a eliminar.");
        }

        $this->conexion    = $conexion;
        $this->productoObj = new Producto($this->conexion);
        $this->loteObj     = new Lote($this->conexion);
        $this->producto_id = intval($_GET["id"]);
    }

    public function eliminar() {
        // 1️⃣ Obtener datos del producto
        $producto = $this->conexion
            ->query("SELECT nombre FROM Producto WHERE id={$this->producto_id}")
            ->fetch_assoc();
        if (!$producto) {
            die("❌ Producto no encontrado.");
        }

        // 2️⃣ Obtener cantidad total en lotes (para historial)
        $totalLotes = $this->conexion
            ->query("SELECT SUM(cantidad_actual) AS total FROM Lote WHERE producto_id={$this->producto_id}")
            ->fetch_assoc();
        $cantidadEliminada = $totalLotes['total'] ?? 0;

        // 3️⃣ Eliminar lotes asociados
        $this->conexion->query("DELETE FROM Lote WHERE producto_id = {$this->producto_id}");

        // 4️⃣ Eliminar producto
        if ($this->conexion->query("DELETE FROM Producto WHERE id = {$this->producto_id}")) {
            // 5️⃣ Registrar movimiento en historial
            $tipo    = "Baja";
            $detalle = "Eliminación del producto '" . $producto['nombre'] . "' y sus lotes asociados";

            $stmt = $this->conexion->prepare("
                INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha) 
                VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isis", $this->producto_id, $tipo, $cantidadEliminada, $detalle);
            $stmt->execute();

            header("Location: Stock.php?msg=eliminado");
            exit();
        } else {
            die("❌ Error al eliminar el producto.");
        }
    }
}

// 🚀 Ejecución
$conn = new Conexion();
$pagina = new PaginaEliminarStock($conn->conexion);
$pagina->eliminar();
