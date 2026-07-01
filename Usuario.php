<?php

/**
 * Clase Usuario — Operaciones sobre la tabla USUARIO.
 */
class Usuario
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Obtiene el nombre y apellido de un usuario por su ID.
     * Devuelve ['USUARIO_nombre' => ..., 'USUARIO_apellido' => ...] o array vacío.
     */
    public function obtenerNombreApellido(int $id): array
    {
        $sql  = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return $fila ?? [];
    }

    /**
     * Verifica las credenciales de login.
     * Devuelve el array completo del usuario (con datos de PERSONA) si son correctas, o null si no.
     */
    public function verificarCredenciales(string $correo, string $contrasena): ?array
    {
        $sql = "
            SELECT
                U.idUSUARIO,
                U.USUARIO_nombre,
                U.USUARIO_apellido,
                U.USUARIO_contrasena,
                U.USUARIO_correo_direccion,
                P.idPERSONA,
                P.PERSONA_dni,
                P.PERSONA_domicilio,
                P.PERSONA_telefono
            FROM USUARIO U
            LEFT JOIN PERSONA P ON U.idUSUARIO = P.USUARIO_idUSUARIO
            WHERE U.USUARIO_correo_direccion = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) return null;

        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if (!$resultado || $resultado->num_rows !== 1) {
            $stmt->close();
            return null;
        }

        $usuario = $resultado->fetch_assoc();
        $passDB  = $usuario["USUARIO_contrasena"];

        // Detectar si la contraseña está hasheada con bcrypt
        if (strlen($passDB) === 60 && preg_match('/^\$2[ayb]\$.{56}$/', $passDB)) {
            $accesoValido = password_verify($contrasena, $passDB);
        } else {
            // Solo temporalmente mientras se migra a hashes
            $accesoValido = ($contrasena === $passDB);
        }

        $stmt->close();
        return $accesoValido ? $usuario : null;
    }

    /**
     * Busca un usuario por su correo electrónico.
     * Devuelve el array del usuario o null si no existe.
     */
    public function buscarPorCorreo(string $correo): ?array
    {
        $sql  = "SELECT idUSUARIO FROM USUARIO WHERE USUARIO_correo_direccion = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->num_rows === 1 ? $resultado->fetch_assoc() : null;
        $stmt->close();
        return $fila;
    }

    /**
     * Guarda el token de reseteo de contraseña para un usuario.
     */
    public function guardarTokenReset(int $idUsuario, string $token, string $expira): void
    {
        $sql  = "UPDATE USUARIO SET USUARIO_reset_token=?, USUARIO_reset_expira=? WHERE idUSUARIO=?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssi", $token, $expira, $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Verifica el token de reseteo y devuelve el idUSUARIO si es válido, o null si no.
     */
    public function verificarTokenReset(string $token): ?int
    {
        $sql  = "SELECT idUSUARIO FROM USUARIO WHERE USUARIO_reset_token=? AND USUARIO_reset_expira > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows !== 1) {
            $stmt->close();
            return null;
        }

        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return (int)$fila["idUSUARIO"];
    }

    /**
     * Actualiza la contraseña del usuario y limpia el token de reseteo.
     */
    public function actualizarPassword(int $idUsuario, string $nuevaContrasena): void
    {
        $hash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
        $sql  = "UPDATE USUARIO SET USUARIO_contrasena=?, USUARIO_reset_token=NULL, USUARIO_reset_expira=NULL WHERE idUSUARIO=?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $hash, $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Verifica si un correo ya está registrado.
     */
    public function correoExiste(string $correo): bool
    {
        $sql  = "SELECT 1 FROM USUARIO WHERE USUARIO_correo_direccion = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Inserta un nuevo usuario y devuelve su ID.
     */
    public function crear(string $nombre, string $apellido, string $contrasena, string $correo): int
    {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sql  = "INSERT INTO USUARIO (USUARIO_nombre, USUARIO_apellido, USUARIO_contrasena, USUARIO_correo_direccion) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ssss", $nombre, $apellido, $hash, $correo);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
