<?php
class Reporte {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /** 🔹 RF08.01: Ventas por período */
    public function ventasPorPeriodo(string $periodo): mysqli_result {
        $where = match($periodo) {
            'dia' => "DATE(v.fecha) = CURDATE()",
            'semana' => "YEARWEEK(v.fecha, 1) = YEARWEEK(CURDATE(), 1)",
            'mes' => "MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha)=YEAR(CURDATE())",
            default => "1=1"
        };

        $sql = "SELECT DATE(v.fecha) AS fecha, SUM(v.total) AS total, COUNT(v.id) AS cantidad
                FROM Venta v
                WHERE $where
                GROUP BY DATE(v.fecha)
                ORDER BY v.fecha DESC";
        return $this->db->query($sql);
    }

    /** 🔹 RF08.02: Productos más vendidos */
    public function productosMasVendidos(): mysqli_result {
        $sql = "SELECT p.nombre, SUM(dv.cantidad) AS total_vendido
                FROM DetalleVenta dv
                INNER JOIN Producto p ON dv.producto_id = p.id
                GROUP BY p.id
                ORDER BY total_vendido DESC
                LIMIT 10";
        return $this->db->query($sql);
    }

    public function productosProximosAVencer(int $dias = 30): mysqli_result {
    // ✅ Consulta compatible con tu base de datos
    $sql = "SELECT 
                p.nombre AS producto, 
                l.id AS lote_id, 
                l.fecha_vencimiento
            FROM lote l
            INNER JOIN producto p ON p.id = l.producto_id
            WHERE l.fecha_vencimiento 
                  BETWEEN CURDATE() 
                  AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY l.fecha_vencimiento ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->bind_param('i', $dias);
    $stmt->execute();
    return $stmt->get_result();
    }

public function productosProximosAVencer(int $dias = 30): mysqli_result {
    // ✅ Consulta compatible con tu base de datos
    $sql = "SELECT 
                p.nombre AS producto, 
                l.id AS lote_id, 
                l.fecha_vencimiento
            FROM lote l
            INNER JOIN producto p ON p.id = l.producto_id
            WHERE l.fecha_vencimiento 
                  BETWEEN CURDATE() 
                  AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY l.fecha_vencimiento ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->bind_param('i', $dias);
    $stmt->execute();
    return $stmt->get_result();
}

?>