<?php
class Conexion {
    public $conexion;

    public function __construct() {
        $this->conexion = new mysqli("localhost", "root", "", "farmacia"); 
        if ($this->conexion->connect_error) {
            die("Error de conexión: " . $this->conexion->connect_error);
        }
    }
}
?>
