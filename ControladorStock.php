<?php
class ControladorStock {
    private Producto $producto;
    private string $rol;

    public function __construct(mysqli $db) {
        if (!isset($_SESSION["usuario_id"])) { 
            header("Location: login.php"); 
            exit(); 
        }
        $this->rol = $_SESSION["usuario_rol"];
        $this->producto = new Producto($db);
    }

    public function obtenerRol(): string {
        return $this->rol;
    }

    public function obtenerProductos(): array {
        return $this->producto->obtenerProductosConLotes();
    }

    public function verificarAlertaVencimiento(?string $fecha): string {
        if (!$fecha) return "";
        $hoy = new DateTime();
        $vencimiento = new DateTime($fecha);
        $dias = (int)$hoy->diff($vencimiento)->days;
        return ($vencimiento < $hoy || $dias <= 30) ? "alerta" : "";
    }
}
