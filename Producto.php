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
        $result = $this->conn->query($sql);
        return $result;
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
}
?>
