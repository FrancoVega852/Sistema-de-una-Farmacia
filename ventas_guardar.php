<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';
require_once 'ControladorVentas.php';

class PaginaGuardarVenta {
    private $conexion;
    private $controlador;

    public function __construct($conexion) {
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: login.php");
            exit();
        }
        $this->conexion = $conexion;
        $this->controlador = new ControladorVentas($this->conexion);
    }

    public function procesar() {
        $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        $usuario_id = (int)$_SESSION['usuario_id'];
        $items      = array_values($_POST['prod'] ?? []);

        try {
            $venta_id = $this->controlador->guardarVenta($cliente_id, $usuario_id, $items);
            header("Location: ventas_ver.php?id=" . $venta_id);
            exit();
        } catch (Exception $e) {
            header("Location: ventas_listar.php?msg=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// 🚀 Ejecución
$conn = new Conexion();
$pagina = new PaginaGuardarVenta($conn->conexion);
$pagina->procesar();
