<?php

/**
 * Clase RegistroLogin — Registro de accesos en la tabla LOGIN.
 * (Nombre de archivo RegistroLogin.php para evitar conflicto con login.php en Windows)
 */
class RegistroLogin
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Registra un nuevo acceso en la tabla LOGIN y devuelve el ID generado.
     */
    public function registrarAcceso(int $usuarioId): int
    {
        $sql  = "INSERT INTO LOGIN (LOGIN_idUsuario, LOGIN_estado, LOGIN_fecha_y_hora_de_acceso) VALUES (?, 'Activo', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Obtiene los datos del último acceso del usuario.
     * Devuelve ['USUARIO_nombre', 'USUARIO_apellido', 'LOGIN_fecha_y_hora_de_acceso'] o null.
     */
    public function obtenerUltimoAcceso(int $usuarioId): ?array
    {
        $sql = "
            SELECT U.USUARIO_nombre, U.USUARIO_apellido, L.LOGIN_fecha_y_hora_de_acceso
            FROM LOGIN L
            JOIN USUARIO U ON L.LOGIN_idUsuario = U.idUSUARIO
            WHERE L.LOGIN_idUsuario = ?
            ORDER BY L.LOGIN_fecha_y_hora_de_acceso DESC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $acceso = $resultado->fetch_assoc();
        $stmt->close();
        return $acceso ?: null;
    }

    /**
     * Cierra todos los login activos del usuario (al hacer logout).
     */
    public function cerrarSesion(int $usuarioId): void
    {
        $sql  = "UPDATE LOGIN SET LOGIN_estado = 'Inactivo' WHERE LOGIN_idUsuario = ? AND LOGIN_estado = 'Activo'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $stmt->close();
    }
}
