<?php

/**
 * Clase Sesion — Gestión de sesiones PHP y autenticación.
 */
class Sesion
{
    /** Inicia la sesión si todavía no está iniciada. */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Destruye la sesión activa y redirige al login. */
    public static function destruir(): void
    {
        self::iniciar();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    /**
     * Verifica que el usuario esté autenticado.
     * Si no lo está, redirige al login y termina la ejecución.
     */
    public static function requiereAutenticacion(): void
    {
        self::iniciar();
        if (!isset($_SESSION["usuario_id"])) {
            header("Location: login.php");
            exit();
        }
    }

    /** Devuelve el ID del usuario en sesión o null si no está logueado. */
    public static function obtenerUsuarioId(): ?int
    {
        self::iniciar();
        return isset($_SESSION["usuario_id"]) ? (int)$_SESSION["usuario_id"] : null;
    }

    /** Guarda múltiples datos en la sesión de una sola vez. */
    public static function guardarDatos(array $datos): void
    {
        self::iniciar();
        foreach ($datos as $clave => $valor) {
            $_SESSION[$clave] = $valor;
        }
    }

    /** Devuelve un valor de sesión o el valor por defecto si no existe. */
    public static function obtener(string $clave, $porDefecto = null)
    {
        self::iniciar();
        return $_SESSION[$clave] ?? $porDefecto;
    }
}
