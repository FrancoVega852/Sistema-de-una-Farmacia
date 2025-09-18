<?php
class Producto {
    private mysqli $db;
    public function __construct(mysqli $db){ $this->db = $db; }

    public function obtenerProductosConLotes(): array {
        $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo,
                       c.nombre AS categoria,
                       l.numero_lote, l.fecha_vencimiento, l.cantidad_actual
                FROM Producto p
                LEFT JOIN Categoria c ON p.categoria_id = c.id
                LEFT JOIN Lote l ON p.id = l.producto_id
                ORDER BY p.id DESC";
        $rs = $this->db->query($sql);
        return $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function agregarProducto(string $nombre, float $precio, int $stock_minimo, bool $requiere_receta, ?int $categoria_id): int {
        $sql = "INSERT INTO Producto(nombre,precio,stock_actual,stock_minimo,requiere_receta,categoria_id)
                VALUES(?, ?, 0, ?, ?, ?)";
        $st  = $this->db->prepare($sql);
        $req = $requiere_receta ? 1 : 0;
        $st->bind_param("sdiii", $nombre, $precio, $stock_minimo, $req, $categoria_id);
        $st->execute();
        return $st->insert_id;
    }

    public function actualizarStock(int $producto_id, int $cantidad, string $operacion="venta"): void {
        $sql = $operacion === "venta"
             ? "UPDATE Producto SET stock_actual = stock_actual - ? WHERE id=?"
             : "UPDATE Producto SET stock_actual = stock_actual + ? WHERE id=?";
        $st = $this->db->prepare($sql);
        $st->bind_param("ii", $cantidad, $producto_id);
        $st->execute();
    }

    public function registrarMovimiento(int $producto_id, string $tipo, int $cantidad, string $detalle=""): void {
        $sql = "INSERT INTO HistorialStock(producto_id,tipo,cantidad,detalle) VALUES(?,?,?,?)";
        $st  = $this->db->prepare($sql);
        $st->bind_param("isis", $producto_id, $tipo, $cantidad, $detalle);
        $st->execute();
    }

    // ✅ Nuevo método para agregar producto + lote
    public function agregarProductoConLote(
        string $nombre,
        float $precio,
        int $stock_minimo,
        bool $requiere_receta,
        ?int $categoria_id,
        string $numero_lote,
        ?string $fecha_vencimiento,
        int $cantidad
    ): bool {
        // Primero agregamos el producto
        $producto_id = $this->agregarProducto($nombre, $precio, $stock_minimo, $requiere_receta, $categoria_id);

        if ($producto_id > 0) {
            // Después insertamos el lote asociado
            $sql = "INSERT INTO Lote(producto_id, numero_lote, fecha_vencimiento, cantidad_actual) 
                    VALUES (?, ?, ?, ?)";
            $st = $this->db->prepare($sql);
            $st->bind_param("issi", $producto_id, $numero_lote, $fecha_vencimiento, $cantidad);
            return $st->execute();
        }
        return false;
    }
}
