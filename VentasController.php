<?php
class VentasController {
    private Venta $venta;
    private mysqli $db;

    public function __construct(mysqli $db){
        if (!isset($_SESSION["usuario_id"])) { header("Location: login.php"); exit(); }
        $this->db = $db;
        $this->venta = new Venta($db);
    }

    public function listar(): mysqli_result { return $this->venta->listarVentas(); }

    public function clientes(): mysqli_result {
        return $this->db->query("SELECT id, nombre, apellido FROM Cliente ORDER BY nombre");
    }
    public function productos(): mysqli_result {
        return $this->db->query("SELECT id, nombre, precio, stock_actual FROM Producto ORDER BY nombre");
    }

    public function guardar(?int $cliente_id, int $usuario_id, array $items): int {
        return $this->venta->registrarVenta($cliente_id, $usuario_id, $items);
    }
}
