<?php
class Producto {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function obtenerTodos() {
        $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo,
                       c.nombre AS categoria, 
                       l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
                FROM Producto p
                LEFT JOIN Categoria c ON p.categoria_id = c.id
                LEFT JOIN Lote l ON p.id = l.producto_id
                ORDER BY p.id DESC";
        return $this->conn->query($sql);
    }

    public function agregar($nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id) {
        $sql = "INSERT INTO Producto (nombre, precio, stock_actual, stock_minimo, requiere_receta, categoria_id)
                VALUES (?, ?, 0, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sdiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id);
        return $stmt->execute() ? $this->conn->insert_id : false;
    }

    public function actualizar($id, $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id) {
        $sql = "UPDATE Producto SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? WHERE id=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sdiiii", $nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id, $id);
        return $stmt->execute();
    }

    public function eliminar($id) {
        $this->conn->query("DELETE FROM Lote WHERE producto_id=$id"); // eliminar lotes primero
        return $this->conn->query("DELETE FROM Producto WHERE id=$id");
    }
}
?>
