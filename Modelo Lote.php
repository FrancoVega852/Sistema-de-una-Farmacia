<?php
class Lote {
    private $conn;
    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function crear($producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial) {
        $sql = "INSERT INTO Lote (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("issii", $producto_id, $numero_lote, $fecha_vencimiento, $cantidad_inicial, $cantidad_inicial);
        return $stmt->execute();
    }

    public function actualizar($id, $numero_lote, $fecha_vencimiento, $cantidad_actual) {
        $sql = "UPDATE Lote SET numero_lote=?, fecha_vencimiento=?, cantidad_actual=? WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssii", $numero_lote, $fecha_vencimiento, $cantidad_actual, $id);
        return $stmt->execute();
    }

    public function obtenerPorProducto($producto_id) {
        return $this->conn->query("SELECT * FROM Lote WHERE producto_id=$producto_id");
    }
}
?>
