<?php
class Conexion {
    private $host = "localhost";
    private $usuario = "root";
    private $password = "";
    private $baseDatos = "farmacia";
    public $conexion;

    public function __construct() {
        // Activar reportes de errores de mysqli
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conexion = new mysqli(
                $this->host,
                $this->usuario,
                $this->password,
                $this->baseDatos
            );
            $this->conexion->set_charset("utf8mb4"); // ✅ evita problemas con acentos y ñ
        } catch (mysqli_sql_exception $e) {
            die("Error de conexión a la BD: " . $e->getMessage());
        }
    }
}
?>
