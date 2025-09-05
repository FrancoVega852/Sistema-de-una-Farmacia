<?php
class Usuario {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // Registrar usuario (y cliente si corresponde)
    public function registrar($nombre, $apellido, $correo, $dni, $domicilio, $telefono, $contrasena, $rol) {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $usuarioLogin = strtolower($nombre) . "." . strtolower($apellido);

        // Insertar en Usuario
        $sql_usuario = "INSERT INTO Usuario (nombre, email, usuario, password, rol) VALUES (?, ?, ?, ?, ?)";
        $stmt_usuario = $this->conexion->prepare($sql_usuario);
        $stmt_usuario->bind_param("sssss", $nombre, $correo, $usuarioLogin, $hash, $rol);

        if ($stmt_usuario->execute()) {
            $idUsuario = $stmt_usuario->insert_id;

            // Si es cliente, guardar datos en Cliente
            if ($rol === "Cliente") {
                $sql_cliente = "INSERT INTO Cliente (nombre, apellido, tipoDocumento, nroDocumento, telefono, email, direccion) 
                                VALUES (?, ?, 'DNI', ?, ?, ?, ?)";
                $stmt_cliente = $this->conexion->prepare($sql_cliente);
                $stmt_cliente->bind_param("ssssss", $nombre, $apellido, $dni, $telefono, $correo, $domicilio);
                $stmt_cliente->execute();
                $stmt_cliente->close();
            }
            return true;
        } else {
            return false;
        }
    }

    // Login
    public function login($correo, $contrasena) {
        $sql = "SELECT * FROM Usuario WHERE email = ? LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();
            if (password_verify($contrasena, $usuario['password'])) {
                return $usuario;
            }
        }
        return false;
    }
}
?>
