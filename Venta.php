<?php
class Venta {
    private mysqli $db;
    public function __construct(mysqli $db){ 
        $this->db = $db; 
    }

    /**
     * Listar ventas registradas
     */
    public function listarVentas(): mysqli_result {
        $sql = "SELECT v.id, v.fecha, v.total, v.estado,
                       c.nombre AS cliente, u.nombre AS usuario
                FROM Venta v
                LEFT JOIN Cliente c ON v.cliente_id = c.id
                INNER JOIN Usuario u ON v.usuario_id = u.id
                ORDER BY v.fecha DESC";
        return $this->db->query($sql);
    }

    /**
     * Registrar nueva venta
     * @param int|null $cliente_id  ID cliente (null = consumidor final)
     * @param int $usuario_id       ID usuario logueado
     * @param array $items          Productos vendidos [['id'=>producto_id,'cant'=>N], ...]
     * @return int ID de la venta registrada
     */
    public function registrarVenta(?int $cliente_id, int $usuario_id, array $items): int {
        $this->db->begin_transaction();
        try {
            // Insert inicial de la venta con total en 0
            $sql = "INSERT INTO Venta(cliente_id, usuario_id, total, estado) VALUES (?, ?, ?, ?)";
            $estado = "Pagada";
            $totalInicial = 0.00;
            $st = $this->db->prepare($sql);
            $st->bind_param("iids", $cliente_id, $usuario_id, $totalInicial, $estado);
            $st->execute();
            $venta_id = $st->insert_id;

            $total = 0;

            foreach($items as $it){
                if(empty($it['id']) || empty($it['cant'])) continue;
                $pid  = (int)$it['id'];
                $cant = (int)$it['cant'];

                // Obtener precio y stock del producto
                $rs = $this->db->query("SELECT precio, stock_actual FROM Producto WHERE id=".$pid);
                $p  = $rs->fetch_assoc();
                if ($p['stock_actual'] < $cant) {
                    throw new Exception("Stock insuficiente para producto $pid");
                }

                $precio  = (float)$p['precio'];
                $sub     = $precio * $cant;
                $total  += $sub;

                // Insertar detalle de la venta
                $stD = $this->db->prepare("INSERT INTO DetalleVenta
                          (venta_id, producto_id, cantidad, precio_unitario, subtotal)
                          VALUES (?, ?, ?, ?, ?)");
                $stD->bind_param("iiidd", $venta_id, $pid, $cant, $precio, $sub);
                $stD->execute();

                // Actualizar stock
                $this->db->query("UPDATE Producto 
                                  SET stock_actual = stock_actual - {$cant} 
                                  WHERE id={$pid}");

                // Insertar en historial de stock
                $stH = $this->db->prepare("INSERT INTO HistorialStock
                          (producto_id, tipo, cantidad, detalle, fecha)
                          VALUES (?, ?, ?, ?, NOW())");
                $tipo="Venta"; 
                $det="Venta #$venta_id";
                $stH->bind_param("isis", $pid, $tipo, $cant, $det);
                $stH->execute();
            }

            // Actualizar total en la venta
            $stU = $this->db->prepare("UPDATE Venta SET total=? WHERE id=?");
            $stU->bind_param("di", $total, $venta_id);
            $stU->execute();

            $this->db->commit();
            return $venta_id;

        } catch(Exception $e){
            $this->db->rollback();
            throw $e;
        }
    }
}
?>
