<?php

/**
 * Clase Tarjeta — Operaciones CRUD sobre la tabla TARJETA.
 */
class Tarjeta
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /* ── Helpers estáticos ─────────────────────────── */

    /** Deja solo dígitos en un string. */
    public static function normalizar(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor);
    }

    /** Formatea un string de dígitos en grupos de 4. */
    public static function formatear(string $valor): string
    {
        $digitos = self::normalizar($valor);
        if ($digitos === '') return '';
        return trim(chunk_split($digitos, 4, ' '));
    }

    /* ── Consultas ─────────────────────────────────── */

    /**
     * Devuelve el resultado mysqli con todas las tarjetas de una persona.
     */
    public function obtenerPorPersonaResultado(int $idPersona): mysqli_result
    {
        $sql  = "SELECT numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento FROM TARJETA WHERE PERSONA_idPERSONA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $idPersona);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();
        return $resultado;
    }

    /**
     * Carga los datos de una tarjeta para edición.
     * Devuelve el array de la tarjeta o null si no pertenece a la persona.
     */
    public function cargarParaEdicion(string $numeroDigits, int $idPersona): ?array
    {
        $sql = "
            SELECT numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento, cvv
            FROM TARJETA
            WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $numeroDigits, $idPersona);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows !== 1) {
            $stmt->close();
            return null;
        }

        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return $fila;
    }

    /**
     * Verifica si ya existe una tarjeta con ese número para la persona.
     */
    public function existe(string $numeroDigits, int $idPersona): bool
    {
        $check = $this->db->prepare("SELECT 1 FROM TARJETA WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ? LIMIT 1");
        $check->bind_param("si", $numeroDigits, $idPersona);
        $check->execute();
        $existe = $check->get_result()->num_rows > 0;
        $check->close();
        return $existe;
    }

    /**
     * Inserta una nueva tarjeta.
     * Devuelve true si la inserción fue exitosa.
     */
    public function agregar(string $numeroDigits, string $tipo, string $estado, string $fechaVencimiento, string $cvvDigits, int $idPersona): bool
    {
        $sql  = "INSERT INTO TARJETA (numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento, cvv, PERSONA_idPERSONA) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssi", $numeroDigits, $tipo, $estado, $fechaVencimiento, $cvvDigits, $idPersona);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Actualiza los datos de una tarjeta.
     * Devuelve true si la actualización fue exitosa.
     */
    public function editar(string $tipo, string $estado, string $fechaVencimiento, string $cvvDigits, string $numeroDigits, int $idPersona): bool
    {
        $sql  = "UPDATE TARJETA SET tipo_tarjeta = ?, estado = ?, fecha_vencimiento = ?, cvv = ? WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssssi", $tipo, $estado, $fechaVencimiento, $cvvDigits, $numeroDigits, $idPersona);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Elimina una tarjeta por número y persona.
     */
    public function eliminar(string $numeroDigits, int $idPersona): void
    {
        $sql  = "DELETE FROM TARJETA WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $numeroDigits, $idPersona);
        $stmt->execute();
        $stmt->close();
    }
}
