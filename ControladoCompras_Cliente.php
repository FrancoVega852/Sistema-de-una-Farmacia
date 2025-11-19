<?php
require_once 'Compra_Cliente.php';

class ControladorCompras_Cliente {

    private Compra_Cliente $compra;
    private mysqli $db;

    public function __construct(mysqli $db) {

        if (!isset($_SESSION["usuario_id"])) { 
            header("Location: login.php");
            exit(); 
        }

        $this->db     = $db;
        $this->compra = new Compra_Cliente($db);
    }

    /* ================================
       LISTADO DE COMPRAS DEL CLIENTE
       ================================= */
    public function listar(): mysqli_result {
        return $this->compra->listarCompras();
    }

    /* ================================
       LISTAR PRODUCTOS DISPONIBLES
       ================================= */
    public function productos(): mysqli_result {
        return $this->db->query("
            SELECT id, nombre, precio, stock_actual 
            FROM Producto 
            ORDER BY nombre
        ");
    }

    /* ================================
       LISTAR CLIENTE ACTUAL
       ================================= */
    public function clienteActual(): array {
        $st = $this->db->prepare("
            SELECT id, nombre, apellido, email 
            FROM Usuario 
            WHERE id = ?
            LIMIT 1
        ");
        $st->bind_param("i", $_SESSION["usuario_id"]);
        $st->execute();
        return $st->get_result()->fetch_assoc() ?: [];
    }

    /* ==================================
       GUARDAR LA COMPRA (usa el modelo)
       =================================== */
    public function guardar(?int $cliente_id, int $usuario_id, array $items): int {
        return $this->compra->registrarCompra($cliente_id, $usuario_id, $items);
    }
}
?>
