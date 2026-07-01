<?php

/**
 * Clase Notificacion — Gestión de notificaciones del sistema.
 */
class Notificacion
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Inserta una nueva notificación para el usuario.
     */
    public function crear(int $usuarioId, string $mensaje, string $tipo = 'Préstamo', string $estado = 'Pendiente'): void
    {
        $sql  = "INSERT INTO NOTIFICACIONES (NOTIFICACIONES_mensaje, NOTIFICACIONES_fecha_y_hora, NOTIFICACIONES_tipo_de_notificaciones, NOTIFICACIONES_estado, USUARIO_idUSUARIO) VALUES (?, NOW(), ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssi", $mensaje, $tipo, $estado, $usuarioId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Inserta una notificación con LOGIN asociado.
     */
    public function crearConLogin(int $usuarioId, int $loginId, string $mensaje, string $tipo, string $estado, string $fecha): void
    {
        $sql  = "INSERT INTO NOTIFICACIONES (NOTIFICACIONES_mensaje, NOTIFICACIONES_fecha_y_hora, NOTIFICACIONES_tipo_de_notificaciones, NOTIFICACIONES_estado, USUARIO_idUSUARIO, LOGIN_idLOGIN) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssi i", $mensaje, $fecha, $tipo, $estado, $usuarioId, $loginId);
        $stmt->close();

        // Re-preparar con bind correcto
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssssii", $mensaje, $fecha, $tipo, $estado, $usuarioId, $loginId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Verifica si ya existe una notificación exacta (mismo mensaje y mismo usuario).
     */
    public function existe(int $usuarioId, string $mensaje): bool
    {
        $sql  = "SELECT 1 FROM NOTIFICACIONES WHERE USUARIO_idUSUARIO = ? AND NOTIFICACIONES_mensaje = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $usuarioId, $mensaje);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Devuelve el resultado mysqli con todas las notificaciones del usuario, ordenadas por fecha DESC.
     */
    public function obtenerPorUsuarioResultado(int $usuarioId): mysqli_result
    {
        $sql = "
            SELECT
                idNOTIFICACIONES,
                NOTIFICACIONES_mensaje,
                NOTIFICACIONES_fecha_y_hora,
                NOTIFICACIONES_tipo_de_notificaciones,
                NOTIFICACIONES_estado
            FROM NOTIFICACIONES
            WHERE USUARIO_idUSUARIO = ?
            ORDER BY NOTIFICACIONES_fecha_y_hora DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();
        return $resultado;
    }

    /**
     * Obtiene el idLOGIN activo más reciente del usuario.
     */
    public function obtenerIdLoginActivo(int $usuarioId): ?int
    {
        $sql  = "SELECT idLOGIN FROM LOGIN WHERE LOGIN_idUsuario = ? AND LOGIN_estado = 'Activo' ORDER BY LOGIN_fecha_y_hora_de_acceso DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $stmt->bind_result($idLogin);
        $encontrado = $stmt->fetch();
        $stmt->close();
        return $encontrado ? (int)$idLogin : null;
    }
}
