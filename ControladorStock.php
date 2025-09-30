<?php
require_once 'Producto.php';
require_once 'Lote.php';

class ControladorStock {
    private Producto $producto;
    private Lote $lote;
    private string $rol;

    public function __construct(mysqli $db) {
        if (!isset($_SESSION["usuario_id"])) { 
            header("Location: login.php"); 
            exit(); 
        }
        $this->rol = $_SESSION["usuario_rol"];
        $this->producto = new Producto($db);
        $this->lote = new Lote($db);
    }

    // ============ Roles ============
    public function rol(): string { 
        return $this->rol; 
    }

    // ============ Listado ============
    public function productos(): array {
        $res = $this->producto->obtenerProductosConLotes();
        $data = [];
        if ($res && $res->num_rows > 0) {
            while ($fila = $res->fetch_assoc()) {
                $data[] = $fila;
            }
        }
        return $data;
    }

    // ============ Alta ============
    public function agregarProductoConLote(array $data): bool {
        $ok = $this->producto->agregarProducto(
            $data['nombre'], 
            $data['precio'], 
            0, 
            $data['stock_minimo'], 
            $data['requiere_receta'], 
            $data['categoria_id']
        );

        if ($ok) {
            $producto_id = $this->producto->conn->insert_id;
            return $this->lote->crear(
                $producto_id,
                $data['numero_lote'],
                $data['fecha_vencimiento'],
                $data['cantidad_inicial']
            );
        }
        return false;
    }

    // ============ Edición ============
    public function editarProducto(int $id, array $data): bool {
        $sql = "UPDATE Producto 
                SET nombre=?, precio=?, stock_minimo=?, requiere_receta=?, categoria_id=? 
                WHERE id=?";
        $stmt = $this->producto->conn->prepare($sql);
        $stmt->bind_param(
            "sdiiii", 
            $data['nombre'], 
            $data['precio'], 
            $data['stock_minimo'], 
            $data['requiere_receta'], 
            $data['categoria_id'], 
            $id
        );
        return $stmt->execute();
    }

    // ============ Eliminación ============
    public function eliminarProducto(int $id): bool {
        // Primero eliminar lotes
        $this->producto->conn->query("DELETE FROM Lote WHERE producto_id=$id");
        // Luego historial
        $this->producto->conn->query("DELETE FROM HistorialStock WHERE producto_id=$id");
        // Finalmente producto
        return $this->producto->conn->query("DELETE FROM Producto WHERE id=$id");
    }

    // ============ Historial ============
    public function historial(int $producto_id): array {
        $sql = "SELECT h.id, h.tipo, h.cantidad, h.detalle, h.fecha 
                FROM HistorialStock h
                WHERE h.producto_id=?
                ORDER BY h.fecha DESC";
        $stmt = $this->producto->conn->prepare($sql);
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $data = [];
        while ($fila = $res->fetch_assoc()) {
            $data[] = $fila;
        }
        return $data;
    }

    // ============ Alerta ============
    public function alertaVencimiento(?string $fecha): string {
        if (!$fecha) return "";
        $hoy = new DateTime();
        $vto = new DateTime($fecha);
        $dias = (int)$hoy->diff($vto)->days;
        return ($vto < $hoy || $dias <= 30) ? "alerta" : "";
    }
}
?>
