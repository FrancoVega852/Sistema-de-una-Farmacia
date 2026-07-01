<?php
include("conexion.php");

$mensaje = "";
$tipoMensaje = "";
$token = $_GET["token"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["token"];
    $nueva = $_POST["nueva_contrasena"];

    if (!empty($nueva)) {

        $sql = "SELECT idUSUARIO 
                FROM USUARIO 
                WHERE USUARIO_reset_token=? 
                AND USUARIO_reset_expira > NOW()";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {

            $usuario = $resultado->fetch_assoc();
            $idUsuario = $usuario["idUSUARIO"];

            $hash = password_hash($nueva, PASSWORD_DEFAULT);

            $update = "UPDATE USUARIO 
                       SET USUARIO_contrasena=?, 
                           USUARIO_reset_token=NULL, 
                           USUARIO_reset_expira=NULL 
                       WHERE idUSUARIO=?";

            $stmt2 = $conexion->prepare($update);
            $stmt2->bind_param("si", $hash, $idUsuario);
            $stmt2->execute();

            $mensaje = "Contraseña actualizada correctamente.";
            $tipoMensaje = "success";

        } else {
            $mensaje = "El enlace es inválido o ha expirado.";
            $tipoMensaje = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva contraseña - BALKFOX</title>

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

<?php if (!empty($token)): ?>

    <h3 class="text-center mb-3">🔑 Nueva contraseña</h3>

    <form method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="nueva_contrasena" class="form-control" placeholder="Ingresá tu nueva contraseña" required>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-custom">Cambiar contraseña</button>
        </div>
    </form>

    <?php if (!empty($mensaje)) { ?>
        <div class="alert alert-<?php echo $tipoMensaje; ?> mt-3">
            <?php echo $mensaje; ?>
        </div>
    <?php } ?>

    <div class="text-center mt-3">
        <a href="login.php" class="btn btn-outline-secondary btn-sm">
            ← Volver al login
        </a>
    </div>

<?php else: ?>

    <div class="alert alert-danger text-center">
        Token inválido.
    </div>

    <div class="text-center mt-3">
        <a href="login.php" class="btn btn-outline-secondary btn-sm">
            ← Volver al login
        </a>
    </div>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>