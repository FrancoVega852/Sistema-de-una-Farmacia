<?php
class Lote {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    // Crear lote para un producto
    public function crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial) {
        $sql = "INSERT INTO Lote (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issii", $producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial, $cantidad_inicial);

        if ($stmt->execute()) {
            // También actualizamos el stock del producto
            $sqlUpdate = "UPDATE Producto SET stock_actual = stock_actual + ? WHERE id = ?";
            $stmt2 = $this->conn->prepare($sqlUpdate);
            $stmt2->bind_param("ii", $cantidad_inicial, $producto_id);
            $stmt2->execute();
            return true;
        }
        return false;
    }

    // Obtener lotes de un producto
    public function obtenerPorProducto($producto_id) {
        $sql = "SELECT * FROM Lote WHERE producto_id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Dar de baja / devolución de stock (RF01.05)
    public function darDeBaja($lote_id, $cantidad) {
        // Reducimos cantidad del lote
        $sql = "UPDATE Lote SET cantidad_actual = cantidad_actual - ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $cantidad, $lote_id);
        $stmt->execute();

        // Reducimos stock del producto también
        $sql2 = "UPDATE Producto p 
                 JOIN Lote l ON p.id = l.producto_id 
                 SET p.stock_actual = p.stock_actual - ? 
                 WHERE l.id = ?";
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->bind_param("ii", $cantidad, $lote_id);
        return $stmt2->execute();
    }

    // Lotes próximos a vencer o vencidos (RF01.04)
    public function obtenerPorVencer($dias = 30) {
        $sql = "SELECT * FROM Lote 
                WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $dias);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
