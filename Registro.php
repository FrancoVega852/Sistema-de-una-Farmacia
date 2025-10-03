<?php
session_start();
include 'Conexion.php';
include 'Usuario.php';

class RegistroController {
    private $usuario;
    public $mensaje = "";

    public function __construct($conexion) {
        $this->usuario = new Usuario($conexion);
    }

    public function procesarFormulario() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre     = trim($_POST['nombre']);
            $apellido   = trim($_POST['apellido']);
            $dni        = trim($_POST['dni']);
            $domicilio  = trim($_POST['domicilio']);
            $correo     = trim($_POST['correo']);
            $telefono   = trim($_POST['telefono']);
            $contrasena = $_POST['contrasena'];
            $rol        = $_POST['rol'];

            if ($this->usuario->registrar($nombre, $apellido, $correo, $dni, $domicilio, $telefono, $contrasena, $rol)) {
                header("Location: login.php?registro=exitoso");
                exit();
            } else {
                $this->mensaje = "⚠️ Error al registrar usuario. Intente nuevamente.";
            }
        }
    }
}

// Inicializar controlador
$conn = new Conexion();
$controller = new RegistroController($conn->conexion);
$controller->procesarFormulario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro - Sistema de Farmacia</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    :root {
      --verde: #008f4c;        
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --acento: #e85c4a;
      --texto: #222222;
    }

    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: var(--verde);  
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      animation: fadeIn 1.2s ease;
      padding: 1rem;
      box-sizing: border-box;
    }

    .form-container {
      background-color: var(--blanco);
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
      width: 100%;
      max-width: 500px;
      box-sizing: border-box;
      position: relative;
      animation: slideUp 1s ease;
    }

    .avatar {
      position: absolute;
      top: -30px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 60px;
      background-color: var(--verde-oscuro);
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--blanco);
      font-size: 28px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      border: 3px solid var(--blanco);
    }

    .form-container h2 {
      font-size: 1.5rem;
      color: var(--verde-oscuro);
      margin-top: 2.5rem;
      margin-bottom: 1rem;
      text-align: center;
    }

    form { width: 100%; }

    .input-group {
      margin-bottom: 0.8rem;
      width: 100%;
    }

    .input-group label {
      display: block;
      font-size: 0.9rem;
      color: var(--texto);
      margin-bottom: 0.3rem;
      font-weight: 600;
    }

    .input-group input,
    .input-group select {
      width: 100%;
      padding: 0.6rem;
      font-size: 0.95rem;
      border: 1px solid #ccc;
      border-radius: 0.4rem;
      background: #f9f9f9;
      color: #222;
      transition: 0.3s border-color ease;
    }

    .input-group input:focus,
    .input-group select:focus {
      border-color: var(--acento);
      outline: none;
    }

    .btn {
      width: 100%;
      background-color: var(--verde);
      color: var(--blanco);
      font-weight: 600;
      border: none;
      padding: 0.7rem;
      font-size: 1rem;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      margin-top: 0.8rem;
    }

    .btn:hover {
      background-color: var(--verde-oscuro);
      transform: scale(1.03);
    }

    .mensaje {
      margin-top: 0.6rem;
      text-align: center;
      font-weight: bold;
      color: var(--acento);
      background: #ffecec;
      padding: 0.4rem;
      border-radius: 0.4rem;
      font-size: 0.9rem;
    }

    p { margin-top: 1rem; text-align: center; color: var(--texto); }

    p a {
      color: var(--verde);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    p a:hover {
      color: var(--verde-oscuro);
      text-decoration: underline;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body>
  <div class="form-container">
    <div class="avatar">
      <i class="fas fa-user"></i>
    </div>
    <h2>Registro - Farvec</h2>
    <form method="POST">
      <div class="input-group">
        <label>Nombre:</label>
        <input type="text" name="nombre" required />
      </div>
      <div class="input-group">
        <label>Apellido:</label>
        <input type="text" name="apellido" required />
      </div>
      <div class="input-group">
        <label>Correo Electrónico:</label>
        <input type="email" name="correo" required />
      </div>
      <div class="input-group">
        <label>Contraseña:</label>
        <input type="password" name="contrasena" required />
      </div>
      <div class="input-group">
        <label>DNI:</label>
        <input type="text" name="dni" required />
      </div>
      <div class="input-group">
        <label>Domicilio:</label>
        <input type="text" name="domicilio" required />
      </div>
      <div class="input-group">
        <label>Teléfono:</label>
        <input type="text" name="telefono" required />
      </div>
      <div class="input-group">
        <label>Rol:</label>
        <select name="rol" required>
          <option value="">Seleccione un rol</option>
          <option value="Cliente">Cliente</option>
          <option value="Empleado">Empleado</option>
          <option value="Farmaceutico">Farmacéutico</option>
          <option value="Administrador">Administrador</option>
        </select>
      </div>
      <button type="submit" class="btn">Registrarse</button>
      <?php if (!empty($controller->mensaje)) : ?>
        <div class="mensaje"><?= htmlspecialchars($controller->mensaje) ?></div>
      <?php endif; ?>
    </form>
    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
  </div>
</body>
</html>
