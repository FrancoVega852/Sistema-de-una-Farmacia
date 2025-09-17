<?php
class StockController {
    private Producto $producto;
    private string $rol;

    public function __construct(mysqli $db){
        if (!isset($_SESSION["usuario_id"])) { header("Location: login.php"); exit(); }
        $this->rol = $_SESSION["usuario_rol"];
        $this->producto = new Producto($db);
    }

    public function rol(): string { return $this->rol; }
    public function productos(): array { return $this->producto->obtenerProductosConLotes(); }

    public function alertaVencimiento(?string $fecha): string {
        if(!$fecha) return "";
        $hoy = new DateTime();
        $vto = new DateTime($fecha);
        $dias = (int)$hoy->diff($vto)->days;
        return ($vto < $hoy || $dias <= 30) ? "alerta" : "";
    }
}
