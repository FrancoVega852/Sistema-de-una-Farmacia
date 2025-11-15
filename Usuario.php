<?php
class Usuario {
    private mysqli $db;

    public function __construct(mysqli $conexion) {
        $this->db = $conexion;
    }

    /**
     * Login de usuario - robusto para texto plano o hash
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

                $guardada = trim($usuario['password']);
                $ingresada = trim($contrasena);

                // 1️⃣ Si está hasheada
                if (password_get_info($guardada)['algo'] !== 0) {
                    if (password_verify($ingresada, $guardada)) {
                        return $usuario;
                    }
                }

                // 2️⃣ Si es texto plano (usuarios de prueba)
                if (strcasecmp($guardada, $ingresada) === 0) { 
                    return $usuario;
                }

                // 3️⃣ Si aún no coincide, log para depurar
                error_log("Login fallido para {$correo}: ingresada={$ingresada}, guardada={$guardada}");
            }
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Registrar nuevo usuario (guarda hash)
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
            $hash = password_hash(trim($contrasena), PASSWORD_DEFAULT);

            $sql = "INSERT INTO Usuario 
                    (nombre, apellido, email, dni, domicilio, telefono, password, rol) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            if (!$stmt) return false;

            $stmt->bind_param("ssssssss", 
                $nombre, $apellido, $correo, $dni, $domicilio, $telefono, $hash, $rol
            );

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en registrar: " . $e->getMessage());
            return false;
        }
    }
}
