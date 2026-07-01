<?php

/**
 * Clase BaseDatos — Singleton para la conexión mysqli.
 * Proporciona una única instancia de conexión durante toda la ejecución.
 */
class BaseDatos
{
    private static ?BaseDatos $instancia = null;
    private mysqli $conexion;

    private function __construct()
    {
        $this->conexion = new mysqli("localhost", "root", "", "HomeBanking");

        if ($this->conexion->connect_error) {
            die("Error en la conexión: " . $this->conexion->connect_error);
        }

        $this->conexion->set_charset("utf8mb4");
    }

    /** Devuelve la única instancia de BaseDatos. */
    public static function obtenerInstancia(): BaseDatos
    {
        if (self::$instancia === null) {
            self::$instancia = new BaseDatos();
        }
        return self::$instancia;
    }

    /** Devuelve el objeto mysqli para ejecutar queries. */
    public function getConexion(): mysqli
    {
        return $this->conexion;
    }

    /** Evita la clonación del singleton. */
    private function __clone() {}
}
