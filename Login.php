<?php
session_start();
require_once 'Conexion.php';
require_once 'Usuario.php';

/* =======================
   CONTROLADOR DE LOGIN
   ======================= */
class ControladorLogin {
    private $usuarioObj;
    private $error = "";

    public function __construct($conexion) {
        $this->usuarioObj = new Usuario($conexion);
    }

    public function procesarLogin() {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $correo = trim($_POST["correo"]);
            $contrasena = $_POST["contrasena"];

            $usuario = $this->usuarioObj->login($correo, $contrasena);

            if ($usuario) {
                // Guardamos sesión del usuario
                $_SESSION["usuario_id"]     = $usuario["id"];
                $_SESSION["usuario_nombre"] = $usuario["nombre"];
                $_SESSION["usuario_correo"] = $usuario["email"];
                $_SESSION["usuario_rol"]    = $usuario["rol"];

                // Redirección según rol
                switch ($usuario["rol"]) {
                    case "Administrador":
                        header("Location: menu.php");
                        break;

                    case "Farmaceutico":
                    case "Farmacéutico":
                        header("Location: menu_farmaceutico.php");
                        break;

                    case "Cliente":
                        header("Location: menu_cliente.php");
                        break;

                    case "Empleado":
                        header("Location: menu_empleado.php");
                        break;

                    default:
                        header("Location: menu.php");
                        break;
                }
                exit;
            } else {
                $this->error = "Correo o contraseña incorrectos.";
            }
        }
    }

    public function getError() {
        return $this->error;
    }
}

$conn = new Conexion();
$loginController = new ControladorLogin($conn->conexion);
$loginController->procesarLogin();
$error = $loginController->getError();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso al Sistema - FARVEC</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --verde: #0e9f6e;
  --verde-oscuro: #0b7d55;
  --verde-claro: #34d399;
  --gris: #f1f5f9;
  --blanco: #fff;
  --negro: #111827;
  --error: #ef4444;
}

/* ====== ESTILOS GENERALES ====== */
body {
  margin: 0;
  padding: 0;
  height: 100vh;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #0f2027, #2c7744, #11998e);
  display: flex;
  justify-content: center;
  align-items: center;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Asegura que los inputs estén por encima de fondos o animaciones */
input, button {
  position: relative;
  z-index: 10;
}

/* ====== CONTENEDOR PRINCIPAL ====== */
.container {
  display: flex;
  width: 900px;
  min-height: 520px;
  background: rgba(255,255,255,0.1);
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 0 30px rgba(0,0,0,0.3);
  backdrop-filter: blur(10px);
  animation: fadeIn 0.6s ease;
  transition: all 0.6s ease;
}

/* Animación de salida al registrarse */
.slide-out {
  animation: slideLeftFade 0.6s forwards ease-in-out;
}
@keyframes slideLeftFade {
  0% { transform: translateX(0); opacity: 1; }
  100% { transform: translateX(-150%); opacity: 0; }
}

/* ====== PANEL IZQUIERDO ====== */
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

/* ====== PANEL DERECHO ====== */
.right {
  flex: 1;
  background: var(--blanco);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 3rem;
  color: var(--negro);
}

.right h2 {
  font-size: 1.8rem;
  font-weight: 600;
  margin-bottom: 1.2rem;
  text-align: center;
  color: var(--verde-oscuro);
}

/* ====== REDES SOCIALES ====== */
.social-icons {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.social-icons a {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--gris);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}
.social-icons a:hover {
  background: var(--verde);
  transform: translateY(-2px);
}
.social-icons svg {
  width: 20px;
  height: 20px;
  fill: var(--verde-oscuro);
}
.social-icons a:hover svg {
  fill: var(--blanco);
}

/* ====== FORMULARIO ====== */
.form-group {
  margin-bottom: 1.2rem;
}
label {
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--negro);
}
input {
  width: 100%;
  padding: 0.8rem;
  border-radius: 0.5rem;
  border: 1px solid #ccc;
  margin-top: 0.3rem;
  font-size: 1rem;
  transition: all 0.3s ease;
}
input:focus {
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
  margin-top: 0.3rem;
}
button:hover {
  background: var(--verde-oscuro);
  box-shadow: 0 0 10px var(--verde-claro);
  transform: translateY(-2px);
}

.error {
  background: var(--error);
  color: #fff;
  text-align: center;
  padding: 0.6rem;
  border-radius: 0.5rem;
  margin-top: 1rem;
  font-size: 0.9rem;
}

/* Enlaces inferiores */
.links {
  text-align: center;
  margin-top: 1rem;
}
.links a {
  color: var(--verde);
  text-decoration: none;
  font-weight: 500;
}
.links a:hover {
  text-decoration: underline;
}

/* Animación general */
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}

/* ====== RESPONSIVE ====== */
@media (max-width: 850px) {
  .container {
    flex-direction: column;
    width: 90%;
    height: auto;
  }
  .left, .right {
    width: 100%;
    height: auto;
    padding: 2rem;
  }
  .left { border-bottom: 1px solid rgba(255,255,255,0.2); }
  /* Animación vertical */
  .slide-out {
    animation: slideUpFade 0.6s forwards ease-in-out;
  }
  @keyframes slideUpFade {
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(-150%); opacity: 0; }
  }
}
</style>
</head>
<body>
  <div class="container" id="loginContainer">
    <!-- PANEL IZQUIERDO -->
    <div class="left">
      <div class="logo-circle">
        <!-- AVATAR SVG -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 
                   1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 
                   1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
      </div>
      <h1>¡Hola!</h1>
      <p>Ingrese con sus datos personales para acceder al sistema FARVEC.</p>
      <a href="#" id="registrarBtn">Registrarse</a>
    </div>

    <!-- PANEL DERECHO -->
    <div class="right">
      <h2>Iniciar Sesión</h2>

      <!-- REDES SOCIALES -->
      <div class="social-icons">
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 3L2 6v12h5v3h3l3-3h4l5-5V3H5zm14 8l-2 2h-4l-3 3v-3H4V5h15v6z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.5.57-2.33.67a4.12 4.12 0 001.8-2.27c-.8.48-1.63.83-2.56 1.02a4.1 4.1 0 00-7 3.74 11.65 11.65 0 01-8.47-4.3 4.1 4.1 0 001.27 5.48c-.67 0-1.33-.2-1.93-.5v.05a4.1 4.1 0 003.3 4.02 4.1 4.1 0 01-1.85.07 4.1 4.1 0 003.83 2.85A8.23 8.23 0 012 19.54 11.64 11.64 0 008.29 21c7.55 0 11.68-6.25 11.68-11.68v-.53c.8-.6 1.5-1.3 2.06-2.1z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 2C4.2 2 2 4.2 2 7v10c0 2.8 2.2 5 5 5h10c2.8 0 5-2.2 5-5V7c0-2.8-2.2-5-5-5H7zm10.5 4A1.5 1.5 0 0119 7.5 1.5 1.5 0 0117.5 9 1.5 1.5 0 0116 7.5 1.5 1.5 0 0117.5 6zM12 8a4 4 0 110 8 4 4 0 010-8zm0 1.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 8.04a5.5 5.5 0 002.4-.5v2.3a8.4 8.4 0 01-2.3-.4v6.3c0 3.4-2.8 6.2-6.2 6.2S3.7 19.2 3.7 15.8c0-.3 0-.7.1-1 1.1.7 2.3 1.1 3.7 1.1a3.8 3.8 0 01-3.4-2.5 3.8 3.8 0 001.7 0A3.8 3.8 0 014 10.5v-.1c.6.3 1.3.5 2 .5A3.8 3.8 0 015 9a3.8 3.8 0 012.8-1.3c1 0 2 .4 2.7 1.1a8.3 8.3 0 002.6-1v2.3z"/></svg></a>
      </div>

      <!-- FORMULARIO -->
      <form method="POST" action="">
        <div class="form-group">
          <label for="correo">Correo electrónico</label>
          <input type="email" id="correo" name="correo" required>
        </div>
        <div class="form-group">
          <label for="contrasena">Contraseña</label>
          <input type="password" id="contrasena" name="contrasena" required>
        </div>
        <button type="submit">Ingresar</button>

        <?php if (!empty($error)): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="links">
          <a href="#">¿Olvidaste tu contraseña?</a>
        </div>
      </form>
    </div>
  </div>

<!-- ====== SCRIPT ANIMACIÓN Y MENSAJE ====== -->
<script>
document.getElementById("registrarBtn").addEventListener("click", function(e) {
  e.preventDefault();
  const box = document.getElementById("loginContainer");
  box.classList.add("slide-out");
  setTimeout(() => window.location.href = "registro.php", 600);
});

// Mostrar mensaje de bienvenida si existe ?bienvenido=nombre
const params = new URLSearchParams(window.location.search);
const nombre = params.get("bienvenido");
if (nombre) {
  const msg = document.createElement("div");
  msg.textContent = "✅ Bienvenido, " + nombre;
  Object.assign(msg.style, {
    position: "fixed",
    bottom: "20px",
    left: "50%",
    transform: "translateX(-50%)",
    background: "var(--verde)",
    color: "white",
    padding: "0.8rem 1.4rem",
    borderRadius: "0.5rem",
    boxShadow: "0 0 10px rgba(0,0,0,0.3)",
    fontWeight: "500",
    fontFamily: "'Poppins', sans-serif",
    zIndex: "999"
  });
  document.body.appendChild(msg);
  setTimeout(() => msg.remove(), 3000);
}
</script>
</body>
</html>
