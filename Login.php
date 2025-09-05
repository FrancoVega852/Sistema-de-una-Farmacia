<?php
session_start();
include 'Conexion.php';
include 'Usuario.php';

$conn = new Conexion();
$usuarioObj = new Usuario($conn->conexion);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST["correo"]);
    $contrasena = $_POST["contrasena"];

    $usuario = $usuarioObj->login($correo, $contrasena);

    if ($usuario) {
        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["usuario_nombre"] = $usuario["nombre"];
        $_SESSION["usuario_correo"] = $usuario["email"];
        $_SESSION["usuario_rol"] = $usuario["rol"];

        // (Opcional) guardar log si tenés una tabla Login
        // $stmt = $conn->conexion->prepare("INSERT INTO Login (usuario_id, estado, fecha_hora_acceso) VALUES (?, 'Activo', NOW())");
        // $stmt->bind_param("i", $usuario["id"]);
        // $stmt->execute();

        header("Location: menu.php");
        exit;
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Iniciar Sesión - Farmacia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1f2937, #3b82f6);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            animation: fadeIn 1.2s ease;
        }

        .login-container {
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 0 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: slideUp 1s ease;
        }

        .icon-persona {
            width: 60px;
            height: 60px;
            background-color: #3b82f6;
            border-radius: 50%;
            margin: 0 auto 1rem auto;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 12px rgba(59,130,246,0.5);
        }
        .icon-persona svg {
            fill: white;
            width: 32px;
            height: 32px;
        }

        .login-container h2 {
            margin-bottom: 1.5rem;
            color: #1f2937;
        }

        .input-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            transition: border-color 0.3s ease;
            font-size: 1rem;
        }

        .input-group input:focus {
            border-color: #3b82f6;
            outline: none;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 1rem;
        }

        .btn:hover {
            background-color: #2563eb;
            transform: scale(1.03);
        }

        .error {
            color: red;
            margin-top: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .register-link {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #374151;
        }

        .register-link a {
            color: #3b82f6;
            font-weight: 600;
            text-decoration: none;
            margin-left: 5px;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="icon-persona" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4
                v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
        </div>
        <h2>Iniciar Sesión</h2>
        <form method="POST" action="">
            <div class="input-group">
                <label for="correo">Correo electrónico:</label>
                <input type="email" id="correo" name="correo" required />
            </div>
            <div class="input-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required />
            </div>
            <button type="submit" class="btn">Ingresar</button>

            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="register-link">
                ¿No tenés cuenta?
                <a href="registro.php">Registrate</a>
            </div>
        </form>
    </div>
</body>
</html>
