<?php
class Usuario {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // Login con compatibilidad y migración automática
    public function login($correo, $contrasena) {
        $sql = "SELECT * FROM Usuario WHERE email = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            // Caso: contraseña en texto plano (ej: "1234")
            if ($contrasena === $usuario['password']) {
                // Migramos a hash automáticamente
                $nuevoHash = password_hash($contrasena, PASSWORD_DEFAULT);
                $upd = $this->conexion->prepare("UPDATE Usuario SET password=? WHERE id=?");
                $upd->bind_param("si", $nuevoHash, $usuario['id']);
                $upd->execute();

                $usuario['password'] = $nuevoHash; 
                return $usuario;
            }

            // Caso: contraseña ya en hash
            if (password_verify($contrasena, $usuario['password'])) {
                return $usuario;
            }
        }
        return false;
    }
}
?>
