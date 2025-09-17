<?php
class Usuario {
    private mysqli $db;

    public function __construct(mysqli $conexion) {
        $this->db = $conexion;
    }

    /**
     * Login de usuario
     * @param string $correo
     * @param string $contrasena
     * @return array|false  Retorna datos de usuario o false si falla
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

                // Caso 1: contraseña en texto plano → migrar a hash
                if ($contrasena === $usuario['password']) {
                    $nuevoHash = $this->migrarPassword($usuario['id'], $contrasena);
                    $usuario['password'] = $nuevoHash;
                    return $usuario;
                }

                // Caso 2: contraseña ya hasheada
                if ($this->verificarPassword($contrasena, $usuario['password'])) {
                    return $usuario;
                }
            }
        } catch (Exception $e) {
            error_log("❌ Error en login: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Migra contraseña en texto plano a hash seguro
     * @param int $usuario_id
     * @param string $contrasena
     * @return string Hash generado
     */
    private function migrarPassword(int $usuario_id, string $contrasena): string {
        $nuevoHash = password_hash($contrasena, PASSWORD_DEFAULT);
        $sql = "UPDATE Usuario SET password=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("si", $nuevoHash, $usuario_id);
        $stmt->execute();
        return $nuevoHash;
    }

    /**
     * Verifica contraseña contra hash
     */
    private function verificarPassword(string $contrasena, string $hash): bool {
        return password_verify($contrasena, $hash);
    }
}
