<?php
class Producto {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    // Traer productos con su categoría y lotes
    public function obtenerProductosConLotes() {
        $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo, 
                       c.nombre AS categoria, 
                       l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
                FROM Producto p
                LEFT JOIN Categoria c ON p.categoria_id = c.id
                LEFT JOIN Lote l ON p.id = l.producto_id
                ORDER BY p.id DESC";
        return $this->conn->query($sql);
    }

    // Agregar nuevo producto
    public function agregarProducto($nombre, $precio, $stock_actual, $stock_minimo, $requiere_receta, $categoria_id) {
        $sql = "INSERT INTO Producto (nombre, precio, stock_actual, stock_minimo, requiere_receta, categoria_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sdiiii", $nombre, $precio, $stock_actual, $stock_minimo, $requiere_receta, $categoria_id);
        return $stmt->execute();
    }

    // Agregar lote a producto
    public function agregarLote($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial) {
        $sql = "INSERT INTO Lote (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issii", $producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial, $cantidad_inicial);
        return $stmt->execute();
    }

    // RF01.02 - Actualizar stock después de venta/compra
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

    // RF01.03 - Obtener productos con stock mínimo o menor
    public function obtenerProductosStockMinimo() {
        $sql = "SELECT * FROM Producto WHERE stock_actual <= stock_minimo";
        return $this->conn->query($sql);
    }

    // RF01.04 - Obtener lotes próximos a vencer (ejemplo: 30 días)
    public function obtenerLotesPorVencer($dias = 30) {
        $sql = "SELECT * FROM Lote WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        return $stmt->get_result();
    }

    // RF01.05 - Registrar devolución/baja
    public function registrarDevolucion($lote_id, $cantidad) {
        $sql = "UPDATE Lote SET cantidad_actual = cantidad_actual - ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $cantidad, $lote_id);
        $stmt->execute();

        // También actualizar stock en Producto
        $sql2 = "UPDATE Producto p 
                 JOIN Lote l ON p.id = l.producto_id 
                 SET p.stock_actual = p.stock_actual - ? 
                 WHERE l.id = ?";
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("ii", $cantidad, $lote_id);
        return $stmt2->execute();
    }

    // RF01.06 - Insertar historial de movimientos de stock
    public function registrarMovimiento($producto_id, $tipo, $cantidad, $detalle = "") {
        $sql = "INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, fecha) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isis", $producto_id, $tipo, $cantidad, $detalle);
        return $stmt->execute();
    }
}
?>
