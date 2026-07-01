<?php

/**
 * Clase ModeloPersona — Operaciones sobre la tabla PERSONA.
 */
class ModeloPersona
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Guarda o actualiza los datos personales de un usuario.
     * Si existe $personaId, los actualiza; de lo contrario, los inserta.
     * Devuelve ['ok' => true, 'personaId' => ...] o ['ok' => false, 'mensaje' => ...].
     */
    public function guardar(int $usuarioId, ?int $personaId, string $dni, string $domicilio, string $telefono): array
    {
        if ($personaId) {
            $sql = "UPDATE PERSONA SET PERSONA_dni = ?, PERSONA_domicilio = ?, PERSONA_telefono = ? WHERE idPERSONA = ? AND USUARIO_idUSUARIO = ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return ['ok' => false, 'mensaje' => 'Error al preparar consulta de actualización: ' . $this->db->error];
            }
            $stmt->bind_param("sssii", $dni, $domicilio, $telefono, $personaId, $usuarioId);
        } else {
            $sql = "INSERT INTO PERSONA (PERSONA_dni, PERSONA_domicilio, PERSONA_telefono, USUARIO_idUSUARIO) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return ['ok' => false, 'mensaje' => 'Error al preparar consulta de inserción: ' . $this->db->error];
            }
            $stmt->bind_param("sssi", $dni, $domicilio, $telefono, $usuarioId);
        }

        if ($stmt->execute()) {
            $nuevoPersonaId = $personaId ? $personaId : $this->db->insert_id;
            $stmt->close();
            return ['ok' => true, 'personaId' => (int)$nuevoPersonaId];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'mensaje' => 'Error al ejecutar la consulta: ' . $error];
        }
    }
}
