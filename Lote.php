<?php
class Lote {
    private mysqli $db;
    public function __construct(mysqli $db){ $this->db = $db; }

    public function crear(int $producto_id, string $numero_lote, string $fecha_venc, int $cantidad): int {
        $sql = "INSERT INTO Lote(producto_id,numero_lote,fecha_vencimiento,cantidad_inicial,cantidad_actual)
                VALUES(?,?,?,?,?)";
        $st  = $this->db->prepare($sql);
        $st->bind_param("issii", $producto_id, $numero_lote, $fecha_venc, $cantidad, $cantidad);
        $st->execute();
        return $st->insert_id;
    }

    public function obtenerPorVencer(int $dias=30): array {
        $sql = "SELECT * FROM Lote WHERE fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $st  = $this->db->prepare($sql);
        $st->bind_param("i",$dias);
        $st->execute();
        return $st->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
