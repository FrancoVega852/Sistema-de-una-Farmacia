<?php
session_start();
include 'Conexion.php';
include 'Usuario.php';

class ControladorRegistro {
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
                header("Location: login.php?bienvenido=" . urlencode($nombre));
                exit();
            } else {
                $this->mensaje = "⚠️ Error al registrar usuario. Intente nuevamente.";
            }
        }
    }
}

$conn = new Conexion();
$controller = new ControladorRegistro($conn->conexion);
$controller->procesarFormulario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Cuenta - FARVEC</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --verde: #0e9f6e;
  --verde-oscuro: #0b7d55;
  --verde-claro: #34d399;
  --blanco: #ffffff;
  --error: #ef4444;
  --gris: #f3f4f6;
}

/* ======== CORRECCIÓN DEL FONDO ======== */
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #0f2027, #2c7744, #11998e);
  background-attachment: fixed;
  background-repeat: no-repeat;
  background-size: cover;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow-x: hidden;
  overflow-y: auto;
  animation: fadeIn 1.2s ease;
}

/* ======== CONTENEDOR PRINCIPAL ======== */
.container {
  display: flex;
  width: 900px;
  min-height: 560px;
  background: rgba(255,255,255,0.1);
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 0 30px rgba(0,0,0,0.3);
  backdrop-filter: blur(10px);
  transition: all 0.6s ease;
}

/* ANIMACIÓN AL VOLVER AL LOGIN */
.slide-out {
  animation: slideRightFade 0.6s forwards ease-in-out;
}
@keyframes slideRightFade {
  0% { transform: translateX(0); opacity: 1; }
  100% { transform: translateX(150%); opacity: 0; }
}

/* ======== PANEL IZQUIERDO ======== */
.left {
  flex: 1;
  background: var(--verde);
  color: var(--blanco);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  padding: 2rem;
}
.left::before {
  content: "";
  position: absolute;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.15), transparent 70%);
  transform: rotate(45deg);
}
.logo-circle {
  background: linear-gradient(135deg, var(--verde), var(--verde-claro));
  width: 90px;
  height: 90px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 25px var(--verde-claro);
  margin-bottom: 1rem;
  z-index: 2;
}
.logo-circle svg {
  width: 45px;
  height: 45px;
  fill: #fff;
}
.left h1 {
  font-size: 2.2rem;
  margin-bottom: 0.5rem;
  z-index: 2;
}
.left p {
  font-size: 1rem;
  width: 70%;
  text-align: center;
  margin-bottom: 2rem;
  z-index: 2;
}
.left a {
  z-index: 2;
  text-decoration: none;
  color: var(--blanco);
  border: 2px solid var(--blanco);
  padding: 0.7rem 1.8rem;
  border-radius: 2rem;
  font-weight: 600;
  transition: all 0.3s ease;
}
.left a:hover {
  background: var(--blanco);
  color: var(--verde);
}

/* ======== PANEL DERECHO (FORMULARIO) ======== */
.right {
  flex: 1.2;
  background: var(--blanco);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 3rem;
  color: #111827;
  position: relative;
}

.right h2 {
  font-size: 1.8rem;
  font-weight: 600;
  margin-bottom: 1.2rem;
  text-align: center;
  color: var(--verde-oscuro);
}

/* ======== FORMULARIO ======== */
.form-group {
  margin-bottom: 1rem;
}
label {
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--negro);
}
input, select {
  width: 100%;
  padding: 0.8rem;
  border-radius: 0.5rem;
  border: 1px solid #ccc;
  margin-top: 0.3rem;
  font-size: 1rem;
  transition: all 0.3s ease;
}
input:focus, select:focus {
  border-color: var(--verde);
  box-shadow: 0 0 0 3px #10b98133;
  outline: none;
}
button {
  width: 100%;
  background: var(--verde);
  color: #fff;
  border: none;
  border-radius: 0.5rem;
  padding: 0.9rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 0.5rem;
}
button:hover {
  background: var(--verde-oscuro);
  box-shadow: 0 0 10px var(--verde-claro);
  transform: translateY(-2px);
}

/* ======== MENSAJES Y LINKS ======== */
.error {
  background: var(--error);
  color: #fff;
  text-align: center;
  padding: 0.6rem;
  border-radius: 0.5rem;
  margin-top: 1rem;
  font-size: 0.9rem;
}
.links {
  text-align: center;
  margin-top: 1rem;
}
.links a {
  color: var(--verde-claro);
  font-weight: 500;
  text-decoration: none;
  font-size: 0.9rem;
}
.links a:hover {
  text-decoration: underline;
}

/* ======== ANIMACIONES ======== */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ======== RESPONSIVE ======== */
@media (max-width: 850px) {
  .container {
    flex-direction: column;
    width: 90%;
    height: auto;
  }
  .left, .right {
    width: 100%;
    padding: 2rem;
  }
  .left { border-bottom: 1px solid rgba(255,255,255,0.2); }
  .slide-out {
    animation: slideDownFade 0.6s forwards ease-in-out;
  }
  @keyframes slideDownFade {
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(150%); opacity: 0; }
  }
}
</style>
</head>
<body>
<div class="container" id="registroContainer">
  <!-- PANEL IZQUIERDO -->
  <div class="left">
    <div class="logo-circle">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 
                 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 
                 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
      </svg>
    </div>
    <h1>¡Bienvenido!</h1>
    <p>Complete los datos para crear su cuenta y acceder al sistema FARVEC.</p>
    <a href="#" id="loginBtn">Iniciar Sesión</a>
  </div>

  <!-- PANEL DERECHO -->
  <div class="right">
    <h2>Registrarse</h2>
    <form method="POST">
      <div class="form-group"><label for="nombre">Nombre</label><input type="text" name="nombre" id="nombre" required></div>
      <div class="form-group"><label for="apellido">Apellido</label><input type="text" name="apellido" id="apellido" required></div>
      <div class="form-group"><label for="dni">DNI</label><input type="text" name="dni" id="dni" required></div>
      <div class="form-group"><label for="domicilio">Domicilio</label><input type="text" name="domicilio" id="domicilio" required></div>
      <div class="form-group"><label for="correo">Correo electrónico</label><input type="email" name="correo" id="correo" required></div>
      <div class="form-group"><label for="telefono">Teléfono</label><input type="text" name="telefono" id="telefono" required></div>
      <div class="form-group"><label for="contrasena">Contraseña</label><input type="password" name="contrasena" id="contrasena" required></div>
      <div class="form-group">
        <label for="rol">Rol</label>
        <select name="rol" id="rol" required>
          <option value="">Seleccione un rol</option>
          <option value="Cliente">Cliente</option>
          <option value="Empleado">Empleado</option>
          <option value="Farmacéutico">Farmacéutico</option>
          <option value="Administrador">Administrador</option>
        </select>
      </div>
      <button type="submit">Registrarse</button>

      <?php if (!empty($controller->mensaje)): ?>
        <div class="error"><?= htmlspecialchars($controller->mensaje) ?></div>
      <?php endif; ?>
    </form>

    <div class="links">
      ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a>
    </div>
  </div>
</div>

<!-- ====== SCRIPT DE ANIMACIÓN ====== -->
<script>
document.getElementById("loginBtn").addEventListener("click", function(e) {
  e.preventDefault();
  const box = document.getElementById("registroContainer");
  box.classList.add("slide-out");
  setTimeout(() => window.location.href = "login.php", 600);
});
</script>
</body>
</html>
