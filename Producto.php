<?php
class Producto {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    /**
     * RF01.01 - Obtener productos con categoría y lotes
     */
    public function obtenerProductosConLotes() {
        $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo, 
                       c.nombre AS categoria, 
                       l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
                FROM Producto p
                LEFT JOIN Categoria c ON p.categoria_id = c.id
                LEFT JOIN Lote l ON p.id = l.producto_id
                ORDER BY p.id DESC, l.fecha_vencimiento ASC";
        return $this->conn->query($sql);
    }

    /**
     * RF01.01 - Agregar nuevo producto
     */
    public function agregarProducto($nombre, $precio, $stock_actual, $stock_minimo, $requiere_receta, $categoria_id) {
        $sql = "INSERT INTO Producto (nombre, precio, stock_actual, stock_minimo, requiere_receta, categoria_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sdiiii", $nombre, $precio, $stock_actual, $stock_minimo, $requiere_receta, $categoria_id);
        return $stmt->execute();
    }

    /**
     * RF01.01 - Agregar lote a un producto
     */
    public function agregarLote($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial) {
        $sql = "INSERT INTO Lote (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issii", $producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial, $cantidad_inicial);
        $ok = $stmt->execute();

        if ($ok) {
            // Actualizar stock
            $this->actualizarStock($producto_id, $cantidad_inicial, "compra");
            // Registrar en historial
            $this->registrarMovimiento($producto_id, "Alta", $cantidad_inicial, "Ingreso lote $numero_lote");
        }
        return $ok;
    }

    /**
     * RF01.02 - Actualizar stock después de venta o compra
     */
    public function actualizarStock($producto_id, $cantidad, $operacion = "venta") {
        if ($operacion === "venta") {
            $sql = "UPDATE Producto SET stock_actual = stock_actual - ? WHERE id = ?";
        } else {
            $sql = "UPDATE Producto SET stock_actual = stock_actual + ? WHERE id = ?";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $cantidad, $producto_id);
        return $stmt->execute();
    }

    /**
     * RF01.03 - Productos en stock mínimo o menor
     */
    public function obtenerProductosStockMinimo() {
        $sql = "SELECT * FROM Producto WHERE stock_actual <= stock_minimo";
        return $this->conn->query($sql);
    }

    /**
     * RF01.04 - Lotes próximos a vencer
     */
    public function obtenerLotesPorVencer($dias = 30) {
        $sql = "SELECT * FROM Lote WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * RF01.05 - Registrar devolución/baja
     */
    public function registrarDevolucion($lote_id, $cantidad) {
        // Reducir cantidad en lote
        $sql = "UPDATE Lote SET cantidad_actual = cantidad_actual - ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $cantidad, $lote_id);
        $stmt->execute();

        // Reducir stock en producto
        $sql2 = "UPDATE Producto p 
                 JOIN Lote l ON p.id = l.producto_id 
                 SET p.stock_actual = p.stock_actual - ? 
                 WHERE l.id = ?";
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("ii", $cantidad, $lote_id);
        $stmt2->execute();

        // Obtener producto y registrar historial
        $producto_id = $this->obtenerProductoPorLote($lote_id);
        $this->registrarMovimiento($producto_id, "Baja", $cantidad, "Devolución/Baja del lote ID $lote_id");

        return true;
    }

    /**
     * RF01.06 - Insertar historial de movimientos
     */
    public function registrarMovimiento($producto_id, $tipo, $cantidad, $detalle = "") {
        $sql = "INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isis", $producto_id, $tipo, $cantidad, $detalle);
        return $stmt->execute();
    }

    /**
     * Auxiliar - Obtener producto a partir de un lote
     */
    private function obtenerProductoPorLote($lote_id) {
        $sql = "SELECT producto_id FROM Lote WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $lote_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ? $res['producto_id'] : null;
    }
}
?>
