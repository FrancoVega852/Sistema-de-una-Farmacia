<?php
class Usuario {
    private mysqli $db;

    public function __construct(mysqli $conexion) {
        $this->db = $conexion;
    }

    /**
     * Login de usuario
     */
    public function login(string $correo, string $contrasena) {
        try {
            $sql = "SELECT * FROM Usuario WHERE email = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado && $resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();

                if ($this->verificarPassword($contrasena, $usuario['password'])) {
                    return $usuario;
                }
            }
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Registrar nuevo usuario
     */
    public function registrar(
        string $nombre,
        string $apellido,
        string $correo,
        string $dni,
        string $domicilio,
        string $telefono,
        string $contrasena,
        string $rol
    ): bool {
        try {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);

            $sql = "INSERT INTO Usuario 
                (nombre, apellido, email, dni, domicilio, telefono, password, rol) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                "ssssssss",
                $nombre, $apellido, $correo, $dni, $domicilio, $telefono, $hash, $rol
            );

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en registrar: " . $e->getMessage());
            return false;
        }
    }

    private function verificarPassword(string $contrasena, string $hash): bool {
        return password_verify($contrasena, $hash);
    }
}
