<?php
session_start();
include 'Conexion.php';
include 'Usuario.php';

$conn = new Conexion();
$usuarioObj = new Usuario($conn->conexion);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST["correo"]);
    $contrasena = $_POST["contrasena"];

    // Ahora login busca en la tabla Usuario de tu base
    $sql = "SELECT id, nombre, email, password, rol 
            FROM Usuario 
            WHERE email = ? 
            LIMIT 1";
    $stmt = $conn->conexion->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        if (password_verify($contrasena, $usuario["password"])) {
            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_nombre"] = $usuario["nombre"];
            $_SESSION["usuario_correo"] = $usuario["email"];
            $_SESSION["usuario_rol"] = $usuario["rol"];

            header("Location: menu.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Correo no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Iniciar Sesión - Farvec</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --verde: #008f4c;
            --verde-oscuro: #006837;
            --blanco: #ffffff;
            --gris: #f4f4f4;
            --acento: #e85c4a;
            --texto: #222222;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--gris), #c7f0d8, #e6f4ec);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            animation: fadeIn 1.2s ease;
        }

        .login-container {
            background: var(--verde);
            color: var(--blanco);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: slideUp 1s ease;
        }

        .icon-persona {
            width: 70px;
            height: 70px;
            background-color: var(--blanco);
            border-radius: 50%;
            margin: 0 auto 1rem auto;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .icon-persona svg {
            fill: var(--verde);
            width: 36px;
            height: 36px;
        }

        .login-container h2 {
            margin-bottom: 1.5rem;
            color: var(--blanco);
        }

        .input-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: var(--blanco);
            font-weight: 600;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 0.5rem;
            font-size: 1rem;
            background: var(--gris);
        }

        .input-group input:focus {
            border-color: var(--acento);
            box-shadow: 0 0 6px rgba(232,92,74,0.5);
            outline: none;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--acento);
            color: var(--blanco);
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            font-size: 1rem;
            margin-top: 0.5rem;
        }

        .btn:hover {
            background-color: #d94c3c;
            transform: scale(1.03);
        }

        .error {
            color: var(--acento);
            background: var(--blanco);
            padding: 0.5rem;
            border-radius: 0.4rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            text-align: center;
            font-weight: bold;
        }

        .register-link {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--blanco);
        }

        .register-link a {
            color: var(--gris);
            font-weight: 600;
            text-decoration: none;
            margin-left: 5px;
        }

        .register-link a:hover {
            text-decoration: underline;
            color: var(--blanco);
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
