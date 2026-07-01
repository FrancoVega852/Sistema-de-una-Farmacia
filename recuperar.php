<?php
include("conexion.php");

$mensaje = "";
$tipoMensaje = ""; // para estilo bootstrap

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = strtolower(trim($_POST["correo"]));

    if (!empty($correo)) {

        // Buscar usuario
        $sql = "SELECT idUSUARIO FROM USUARIO WHERE USUARIO_correo_direccion = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {

            $usuario = $resultado->fetch_assoc();
            $idUsuario = $usuario["idUSUARIO"];

            // Generar token seguro
            $token = bin2hex(random_bytes(32));
            $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Guardar token en la base
            $update = "UPDATE USUARIO 
                       SET USUARIO_reset_token=?, USUARIO_reset_expira=? 
                       WHERE idUSUARIO=?";
            $stmt2 = $conexion->prepare($update);
            $stmt2->bind_param("ssi", $token, $expira, $idUsuario);
            $stmt2->execute();

            $mensaje = "Link de recuperación (simulación): 
                        <br><a href='nueva_contrasena.php?token=$token' class='alert-link'>
                        Cambiar contraseña</a>";

            $tipoMensaje = "success";

        } else {
            $mensaje = "Si el correo existe, se enviará un enlace de recuperación.";
            $tipoMensaje = "info";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña - BALKFOX</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-custom {
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0,0,0,0.4);
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-custom {
            background-color: #1abc9c;
            border: none;
        }

        .btn-custom:hover {
            background-color: #16a085;
        }
    </style>
</head>
<body>

<div class="card card-custom p-4 col-md-4 bg-light">

    <h3 class="text-center mb-3">🔐 Recuperar contraseña</h3>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control" placeholder="Ingresá tu correo" required>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-custom">Enviar enlace</button>
        </div>
    </form>

    <?php if (!empty($mensaje)) { ?>
        <div class="alert alert-<?php echo $tipoMensaje; ?> mt-3 animate__animated animate__fadeIn">
            <?php echo $mensaje; ?>
        </div>
    <?php } ?>

    <div class="text-center mt-3">
        <a href="login.php" class="btn btn-outline-secondary btn-sm">
            ← Volver al login
        </a>
    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>