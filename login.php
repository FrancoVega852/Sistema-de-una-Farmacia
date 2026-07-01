<?php
session_start();
include("conexion.php");

$error = "";

// Solo procesar si viene por POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Normalizar correo
    $correo = isset($_POST["correo"]) ? strtolower(trim($_POST["correo"])) : "";
    $contrasena = $_POST["contrasena"] ?? "";

    if ($correo === "" || $contrasena === "") {
        $error = "Ingresá tu correo y tu contraseña.";
    } else {
        // Consulta con LEFT JOIN para obtener datos de usuario y persona
        $sql = "
            SELECT 
                U.idUSUARIO, 
                U.USUARIO_nombre, 
                U.USUARIO_apellido,
                U.USUARIO_contrasena,
                U.USUARIO_correo_direccion,
                P.idPERSONA,
                P.PERSONA_dni,
                P.PERSONA_domicilio,
                P.PERSONA_telefono
            FROM USUARIO U
            LEFT JOIN PERSONA P ON U.idUSUARIO = P.USUARIO_idUSUARIO
            WHERE U.USUARIO_correo_direccion = ?
            LIMIT 1
        ";

        if ($stmt = $conexion->prepare($sql)) {
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado && $resultado->num_rows === 1) {
                $usuario = $resultado->fetch_assoc();
                $passDB = $usuario["USUARIO_contrasena"];

                // Detectar si la contraseña está hasheada con bcrypt
                if (strlen($passDB) === 60 && preg_match('/^\$2[ayb]\$.{56}$/', $passDB)) {
                    $acceso_valido = password_verify($contrasena, $passDB);
                } else {
                    // Solo temporalmente mientras migrás a hashes
                    $acceso_valido = ($contrasena === $passDB);
                }

                if ($acceso_valido) {
                    // Guardar datos en sesión
                    $_SESSION["usuario_id"] = $usuario["idUSUARIO"];
                    $_SESSION["usuario_nombre"] = $usuario["USUARIO_nombre"];
                    $_SESSION["usuario_apellido"] = $usuario["USUARIO_apellido"];
                    $_SESSION["persona_id"] = $usuario["idPERSONA"];
                    $_SESSION["persona_dni"] = $usuario["PERSONA_dni"];
                    $_SESSION["persona_domicilio"] = $usuario["PERSONA_domicilio"];
                    $_SESSION["persona_telefono"] = $usuario["PERSONA_telefono"];

                    // Registrar login en tabla LOGIN
                    $sql_login = "
                        INSERT INTO LOGIN (LOGIN_idUsuario, LOGIN_estado, LOGIN_fecha_y_hora_de_acceso) 
                        VALUES (?, 'Activo', NOW())
                    ";
                    if ($stmt_login = $conexion->prepare($sql_login)) {
                        $stmt_login->bind_param("i", $usuario["idUSUARIO"]);
                        $stmt_login->execute();
                        $_SESSION["idLOGIN"] = $stmt_login->insert_id;
                        $stmt_login->close();
                    }

                    // Redirigir al menú principal
                    header("Location: menu.php");
                    exit;
                } else {
                    $error = "Datos de acceso incorrectos.";
                }
            } else {
                $error = "Datos de acceso incorrectos.";
            }

            $stmt->close();
        } else {
            $error = "Error interno al procesar el ingreso.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar Sesión - BALKFOX HomeBanking</title>

<!-- Bootstrap (como el menú de la farmacia) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
  /* COLORES BALKFOX (se mantienen) */
  --bg: #0b1120;

  /* Azul apenas más realista (mínimo ajuste) */
  --primario: #1e57cf;
  --primario-oscuro: #1643a3;
  --primario-claro: #4a86ff;

  --gris: #f1f5f9;
  --blanco: #ffffff;
  --negro: #111827;
  --muted: #6b7280;

  --error: #dc2626;
}

/* ====== ESTILOS GENERALES (misma estructura FARVEC) ====== */
body{
  margin:0;
  padding:0;
  min-height:100vh;
  font-family:'Inter', system-ui, -apple-system, "Segoe UI", sans-serif;

  /* fondo oscuro BALKFOX */
  background:
    radial-gradient(circle at 0% 0%, rgba(29,100,242,0.22) 0, transparent 40%),
    radial-gradient(circle at 100% 100%, rgba(29,100,242,0.22) 0, transparent 40%),
    var(--bg);

  display:flex;
  justify-content:center;
  align-items:center;

  overflow-y:auto;
  overflow-x:hidden;
}

/* Asegura que los inputs estén por encima de fondos o animaciones (igual FARVEC) */
input, button, a{
  position:relative;
  z-index:10;
}

/* ====== CONTENEDOR PRINCIPAL (IGUAL FARVEC) ====== */
.container{
  display:flex;
  width:900px;
  min-height:520px;

  background: rgba(255,255,255,0.08);

  /* FIX línea negra: quito border externo y lo reemplazo por “anillo” suave */
  border: none;
  box-shadow:
    0 0 0 1px rgba(255,255,255,0.12),
    0 20px 60px rgba(0,0,0,0.55);

  border-radius: 1rem;

  overflow:hidden;
  backdrop-filter: blur(10px);

  animation: fadeIn 0.6s ease;
  transition: all 0.6s ease;
}

/* Animación de salida al registrarse (IGUAL FARVEC) */
.slide-out{
  animation: slideLeftFade 0.6s forwards ease-in-out;
}
@keyframes slideLeftFade{
  0% { transform: translateX(0); opacity: 1; }
  100% { transform: translateX(-150%); opacity: 0; }
}

/* ====== PANEL IZQUIERDO (IGUAL FARVEC pero azul) ====== */
.left{
  flex:1;
  background: var(--primario);
  color: var(--blanco);

  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;

  position:relative;
  padding:2rem;
}

.left::before{
  content:"";
  position:absolute;
  width:200%;
  height:200%;
  background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.15), transparent 70%);
  transform: rotate(45deg);
}

.logo-circle{
  background: linear-gradient(135deg, var(--primario), var(--primario-claro));
  width:90px;
  height:90px;
  border-radius:50%;

  display:flex;
  align-items:center;
  justify-content:center;

  box-shadow: 0 0 25px rgba(79,140,255,0.55);
  margin-bottom:1rem;
  z-index:2;
}

.logo-circle svg{
  width:45px;
  height:45px;
  fill:#fff;
}

.left h1{
  font-size:2.1rem;
  margin: 0 0 0.5rem 0;
  z-index:2;
  color: var(--blanco);
}

.left p{
  font-size:1rem;
  width: 78%;
  text-align:center;
  margin: 0 0 1.7rem 0;
  z-index:2;
  line-height:1.5;
}

.left a{
  z-index:2;
  text-decoration:none;
  color: var(--blanco);
  border: 2px solid var(--blanco);
  padding: 0.7rem 1.8rem;
  border-radius: 2rem;
  font-weight:700;
  transition: all 0.3s ease;
}

.left a:hover{
  background: var(--blanco);
  color: var(--primario);
}

/* ====== PANEL DERECHO (IGUAL FARVEC) ====== */
.right{
  flex:1;
  background: var(--blanco);

  display:flex;
  flex-direction:column;
  justify-content:center;

  padding: 3rem;
  color: var(--negro);
}

.right h2{
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 1.2rem;
  text-align:center;
  color: var(--primario-oscuro);
}

/* ====== “REDES” (del FARVEC, pero con colores BALKFOX) ====== */
.social-icons{
  display:flex;
  justify-content:center;
  gap:1rem;
  margin-bottom:1.5rem;
}
.social-icons a{
  width:38px;
  height:38px;
  border-radius:50%;
  background: var(--gris);

  display:flex;
  align-items:center;
  justify-content:center;

  transition: all 0.3s ease;
}

.social-icons a:hover{
  background: var(--primario);
  transform: translateY(-2px);
}
.social-icons svg{
  width:20px;
  height:20px;
  fill: var(--primario-oscuro);
}
.social-icons a:hover svg{
  fill: var(--blanco);
}

/* ====== FORMULARIO (misma estructura FARVEC) ====== */
.form-group{
  margin-bottom: 1.2rem;
}
label{
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--negro);
}

.input-wrapper{
  position:relative;
}

input{
  width:100%;
  padding: 0.8rem;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  margin-top: 0.3rem;
  font-size: 1rem;
  transition: all 0.3s ease;
}

/* Deja espacio para el ícono del ojo */
#contrasena{
  padding-right: 3rem;
}

input:focus{
  border-color: var(--primario);
  box-shadow: 0 0 0 3px rgba(29,100,242,0.18);
  outline:none;
}

/* BOTÓN OJO: FIX real (evita que herede width:100% y estilos de submit) */
.toggle-password{
  position:absolute;
  right: 0.9rem;
  top: 50%;
  transform: translateY(-50%);
  border:none;
  background:none;
  cursor:pointer;

  /* FIX CLAVE */
  width: auto !important;
  margin-top: 0 !important;
  padding: 0.15rem;
  border-radius: 0.45rem;

  transition: background 0.2s ease;
}

.toggle-password:hover{
  background: rgba(29,100,242,0.08);
}

.toggle-password svg{
  width: 20px;
  height: 20px;
  display:block;
  fill: var(--primario-oscuro);
}
.toggle-password:hover svg{
  fill: var(--primario);
}

/* FIX CLAVE: estos estilos eran para el submit, no para el botón del ojo */
button[type="submit"]{
  width:100%;
  background: var(--primario);
  color:#fff;
  border:none;
  border-radius: 0.5rem;
  padding: 0.9rem;
  font-size: 1rem;
  font-weight: 800;
  cursor:pointer;
  transition: all 0.3s ease;
  margin-top: 0.3rem;
}
button[type="submit"]:hover{
  background: var(--primario-oscuro);
  box-shadow: 0 0 10px rgba(79,140,255,0.55);
  transform: translateY(-2px);
}

.error{
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: var(--error);

  padding: 0.65rem;
  border-radius: 0.5rem;
  margin-top: 1rem;
  font-size: 0.9rem;
}

/* Enlaces inferiores (como FARVEC) */
.links{
  text-align:center;
  margin-top: 1rem;
  display:flex;
  justify-content:space-between;
  gap: 0.75rem;
  flex-wrap:wrap;
}
.links a{
  color: var(--primario);
  text-decoration:none;
  font-weight:700;
}
.links a:hover{
  text-decoration: underline;
}

/* Animación general (IGUAL FARVEC) */
@keyframes fadeIn{
  from { opacity:0; transform: scale(0.98); }
  to { opacity:1; transform: scale(1); }
}

/* ====== RESPONSIVE (IGUAL FARVEC) ====== */
@media (max-width: 850px){
  .container{
    flex-direction:column;
    width:90%;
    height:auto;
  }
  .left, .right{
    width:100%;
    height:auto;
    padding:2rem;
  }
  .left{
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }
  /* Animación vertical (IGUAL FARVEC) */
  .slide-out{
    animation: slideUpFade 0.6s forwards ease-in-out;
  }
  @keyframes slideUpFade{
    0% { transform: translateY(0); opacity:1; }
    100% { transform: translateY(-150%); opacity:0; }
  }
}
</style>
</head>

<body>
  <div class="container" id="loginContainer">

    <!-- PANEL IZQUIERDO -->
    <div class="left">
      <div class="logo-circle">
        <!-- Avatar SVG (igual FARVEC) -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
      </div>

      <h1>¡Hola!</h1>
      <p>Ingresá con tus credenciales para acceder a BALKFOX HomeBanking.</p>

      <!-- Botón registro con slide-out (igual FARVEC) -->
      <a href="#" id="registrarBtn">Registrarse</a>
    </div>

    <!-- PANEL DERECHO -->
    <div class="right">
      <h2>Iniciar Sesión</h2>

      <!-- Íconos (del FARVEC, decorativos) -->
      <div class="social-icons" aria-hidden="true">
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 3L2 6v12h5v3h3l3-3h4l5-5V3H5zm14 8l-2 2h-4l-3 3v-3H4V5h15v6z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M22.46 6c-.77.35-1.5.57-2.33.67a4.12 4.12 0 001.8-2.27c-.8.48-1.63.83-2.56 1.02a4.1 4.1 0 00-7 3.74 11.65 11.65 0 01-8.47-4.3 4.1 4.1 0 001.27 5.48c-.67 0-1.33-.2-1.93-.5v.05a4.1 4.1 0 003.3 4.02 4.1 4.1 0 01-1.85.07 4.1 4.1 0 003.83 2.85A8.23 8.23 0 012 19.54 11.64 11.64 0 008.29 21c7.55 0 11.68-6.25 11.68-11.68v-.53c.8-.6 1.5-1.3 2.06-2.1z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 2C4.2 2 2 4.2 2 7v10c0 2.8 2.2 5 5 5h10c2.8 0 5-2.2 5-5V7c0-2.8-2.2-5-5-5H7zm10.5 4A1.5 1.5 0 0119 7.5 1.5 1.5 0 0117.5 9 1.5 1.5 0 0116 7.5 1.5 1.5 0 0117.5 6zM12 8a4 4 0 110 8 4 4 0 010-8zm0 1.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5z"/></svg></a>
        <a href="#"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 8.04a5.5 5.5 0 002.4-.5v2.3a8.4 8.4 0 01-2.3-.4v6.3c0 3.4-2.8 6.2-6.2 6.2S3.7 19.2 3.7 15.8c0-.3 0-.7.1-1 1.1.7 2.3 1.1 3.7 1.1a3.8 3.8 0 01-3.4-2.5 3.8 3.8 0 001.7 0A3.8 3.8 0 014 10.5v-.1c.6.3 1.3.5 2 .5A3.8 3.8 0 015 9a3.8 3.8 0 012.8-1.3c1 0 2 .4 2.7 1.1a8.3 8.3 0 002.6-1v2.3z"/></svg></a>
      </div>

      <!-- FORMULARIO (mismos datos) -->
      <form method="POST" action="">
        <div class="form-group">
          <label for="correo">Correo electrónico</label>
          <input type="email" id="correo" name="correo" required autocomplete="email">
        </div>

        <div class="form-group">
          <label for="contrasena">Contraseña</label>
          <div class="input-wrapper">
            <input type="password" id="contrasena" name="contrasena" required autocomplete="current-password">
            <button type="button" class="toggle-password" id="togglePass" aria-label="Mostrar u ocultar contraseña">
              <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit">Ingresar</button>

        <?php if (!empty($error)): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="links">
          <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
          <a href="#" id="registrarLink">Registrarse</a>
        </div>
      </form>
    </div>
  </div>

<!-- Bootstrap JS (como el menú de la farmacia) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Slide-out al registrarse (igual FARVEC)
  function goRegistro(e){
    e.preventDefault();
    const box = document.getElementById("loginContainer");
    box.classList.add("slide-out");
    setTimeout(() => window.location.href = "registro.php", 600);
  }
  document.getElementById("registrarBtn").addEventListener("click", goRegistro);
  document.getElementById("registrarLink").addEventListener("click", goRegistro);

  // Mostrar / ocultar contraseña (conservado)
  const passInput = document.getElementById('contrasena');
  const toggleBtn = document.getElementById('togglePass');

  toggleBtn.addEventListener('click', () => {
    const isPassword = passInput.type === 'password';
    passInput.type = isPassword ? 'text' : 'password';
    const icon = document.getElementById('eyeIcon');
    icon.innerHTML = isPassword
      ? '<path d="M2 5l17 17-1.4 1.4-3.2-3.2A10.7 10.7 0 0 1 12 19C5 19 1 12 1 12a21.7 21.7 0 0 1 4.3-5.4L.6 3.4z"/>'
      : '<path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>';
  });
</script>
</body>
</html>
