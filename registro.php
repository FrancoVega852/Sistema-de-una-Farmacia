<?php
include 'conexion.php';
$conn = $conexion;

$mensaje = "";

// Función para generar número de cuenta único (8 dígitos)
function generarNumeroCuenta(mysqli $conexion): string {
    do {
        $numero = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $sql = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_numero_de_cuenta = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $numero);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
    } while ($existe);
    return $numero;
}

// Función para generar CBU único de 10 dígitos que empieza con 100
function generarCBU(mysqli $conexion): string {
    do {
        $cbu = '100' . str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        $sql = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_cbu = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $cbu);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
    } while ($existe);
    return $cbu;
}

// Función para generar alias simple basado en nombre + apellido + número random
function generarAlias(string $nombre, string $apellido, mysqli $conexion): string {
    $base = strtolower(trim($nombre)) . '.' . strtolower(trim($apellido));
    $base = preg_replace('/\s+/', '', $base);

    do {
        $alias = $base . mt_rand(10, 99);
        $sql = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_alias = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $alias);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
    } while ($existe);
    return $alias;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $apellido    = trim($_POST['apellido'] ?? '');
    $dni         = trim($_POST['dni'] ?? '');
    $domicilio   = trim($_POST['domicilio'] ?? '');
    $correo      = strtolower(trim($_POST['correo'] ?? ''));
    $telefono    = trim($_POST['telefono'] ?? '');
    $contrasena  = $_POST['contrasena'] ?? '';
    $contrasena2 = $_POST['contrasena2'] ?? '';
    $tipo_cuenta = $_POST['tipo_cuenta'] ?? '';

    if ($nombre === '' || $apellido === '' || $dni === '' || $domicilio === '' ||
        $correo === '' || $telefono === '' || $contrasena === '' || $tipo_cuenta === '') {
        $mensaje = "Todos los campos son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "El correo ingresado no es válido.";
    } elseif ($contrasena !== $contrasena2) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $sql_check_mail = "SELECT 1 FROM USUARIO WHERE USUARIO_correo_direccion = ?";
        $stmt_check_mail = $conn->prepare($sql_check_mail);
        $stmt_check_mail->bind_param("s", $correo);
        $stmt_check_mail->execute();
        $stmt_check_mail->store_result();
        if ($stmt_check_mail->num_rows > 0) {
            $mensaje = "El correo ya se encuentra registrado.";
        }
        $stmt_check_mail->close();

        if ($mensaje === "") {
            $sql_check_dni = "
                SELECT 1 
                FROM PERSONA P
                INNER JOIN USUARIO U ON P.USUARIO_idUSUARIO = U.idUSUARIO
                WHERE P.PERSONA_dni = ?
            ";
            $stmt_check_dni = $conn->prepare($sql_check_dni);
            $stmt_check_dni->bind_param("s", $dni);
            $stmt_check_dni->execute();
            $stmt_check_dni->store_result();
            if ($stmt_check_dni->num_rows > 0) {
                $mensaje = "El DNI ya se encuentra registrado.";
            }
            $stmt_check_dni->close();
        }

        if ($mensaje === "") {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);

            $conn->begin_transaction();
            try {
                $sql_usuario = "
                    INSERT INTO USUARIO 
                        (USUARIO_nombre, USUARIO_apellido, USUARIO_contrasena, USUARIO_correo_direccion)
                    VALUES (?, ?, ?, ?)
                ";
                $stmt_usuario = $conn->prepare($sql_usuario);
                $stmt_usuario->bind_param("ssss", $nombre, $apellido, $hash, $correo);
                $stmt_usuario->execute();
                $idUsuario = $stmt_usuario->insert_id;
                $stmt_usuario->close();

                $sql_persona = "
                    INSERT INTO PERSONA 
                        (PERSONA_dni, PERSONA_domicilio, PERSONA_telefono, USUARIO_idUSUARIO)
                    VALUES (?, ?, ?, ?)
                ";
                $stmt_persona = $conn->prepare($sql_persona);
                $stmt_persona->bind_param("sssi", $dni, $domicilio, $telefono, $idUsuario);
                $stmt_persona->execute();
                $stmt_persona->close();

                $numero_cuenta  = generarNumeroCuenta($conn);
                $cbu            = generarCBU($conn);
                $alias          = generarAlias($nombre, $apellido, $conn);
                $saldo_inicial  = 0;
                $estado         = 'Activa';

                $sql_cuenta = "
                    INSERT INTO CUENTA_BANCARIA 
                        (CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_cbu, CUENTA_BANCARIA_alias, 
                         CUENTA_BANCARIA_saldo, USUARIO_idUSUARIO, CUENTA_BANCARIA_estado, CUENTA_BANCARIA_tipo_de_cuenta)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt_cuenta = $conn->prepare($sql_cuenta);
                $stmt_cuenta->bind_param(
                    "sssisss",
                    $numero_cuenta,
                    $cbu,
                    $alias,
                    $saldo_inicial,
                    $idUsuario,
                    $estado,
                    $tipo_cuenta
                );
                $stmt_cuenta->execute();
                $stmt_cuenta->close();

                $conn->commit();
                header("Location: login.php?registro=exitoso");
                exit();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $mensaje = "Ocurrió un error al registrar el usuario. Intente nuevamente.";
            }
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Registro - BALKFOX HomeBanking</title>

<!-- Bootstrap (como pediste) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<style>
:root{
  --bg:#020617;

  /* MISMO AZUL DEL LOGIN (solo un toque más realista) */
  --primario:#1e57cf;
  --primario-oscuro:#1643a3;
  --primario-claro:#4a86ff;

  --blanco:#ffffff;
  --negro:#111827;
  --muted:#6b7280;

  --border: rgba(255,255,255,0.12);
  --danger:#dc2626;
  --card:#ffffff;
  --gris:#f3f4f6;
}

html, body{
  height:100%;
  margin:0;
  padding:0;
  font-family:'Inter',system-ui,-apple-system,"Segoe UI",sans-serif;
}

body{
  background:
    radial-gradient(circle at 0% 0%, rgba(29,100,242,0.22) 0, transparent 40%),
    radial-gradient(circle at 100% 100%, rgba(29,100,242,0.22) 0, transparent 40%),
    var(--bg);
  display:flex;
  justify-content:center;
  align-items:center;
  padding:1.5rem;
  overflow-x:hidden;
  overflow-y:auto;
  animation: fadeIn 0.8s ease;
}

/* ======== CONTENEDOR PRINCIPAL (misma estructura del registro FARVEC) ======== */
.auth-container{
  display:flex;
  width:900px;
  min-height:560px;
  background: rgba(255,255,255,0.08);
  border-radius: 1rem;
  overflow:hidden;

  /* evita “línea negra” dura */
  border:none;
  box-shadow:
    0 0 0 1px var(--border),
    0 24px 60px rgba(0,0,0,0.65);

  backdrop-filter: blur(10px);
  transition: all 0.6s ease;
}

/* ANIMACIÓN AL VOLVER AL LOGIN (mantenida) */
.slide-out{
  animation: slideRightFade 0.6s forwards ease-in-out;
}
@keyframes slideRightFade{
  0% { transform: translateX(0); opacity: 1; }
  100% { transform: translateX(150%); opacity: 0; }
}

/* ======== PANEL IZQUIERDO ======== */
.left{
  flex:1;
  background: linear-gradient(135deg, var(--primario), var(--primario-oscuro));
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
  background: radial-gradient(circle at 30% 50%, rgba(255,255,255,0.16), transparent 70%);
  transform: rotate(45deg);
  z-index:1;
}

.logo-circle{
  background: radial-gradient(circle at 30% 20%, var(--primario-claro), var(--primario) 60%, var(--primario-oscuro) 100%);
  width:90px;
  height:90px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow: 0 0 25px rgba(74,134,255,0.55);
  margin-bottom:1rem;
  z-index:2;
}
.logo-circle i{
  font-size: 38px;
  color:#fff;
}
.left h1{
  font-size:2.2rem;
  margin-bottom:0.5rem;
  z-index:2;
  color:#fff;
}
.left p{
  font-size:1rem;
  width:70%;
  text-align:center;
  margin-bottom:2rem;
  z-index:2;
  color: #ffffff;
}
.left a{
  z-index:2;
  text-decoration:none;
  color: var(--blanco);
  border: 2px solid var(--blanco);
  padding: 0.7rem 1.8rem;
  border-radius: 2rem;
  font-weight: 700;
  transition: all 0.25s ease;
}
.left a:hover{
  background: var(--blanco);
  color: var(--primario);
}

/* ======== PANEL DERECHO (FORM) ======== */
.right{
  flex: 1.2;
  background: var(--card);
  display:flex;
  flex-direction:column;
  justify-content:center;
  padding: 3rem;
  color: var(--negro);
  position:relative;
}

.right h2{
  font-size:1.8rem;
  font-weight:700;
  margin-bottom:1.2rem;
  text-align:center;
  color: var(--primario-oscuro);
}

/* ======== TU FORM (MISMO MARKUP, solo estilo) ======== */
form{ width:100%; }

/* no toco tu estructura de inputs; solo estilos */
.form-grid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem 0.9rem;
}
.form-grid .full{ grid-column: 1 / -1; }

.input-group label{
  display:block;
  font-size:0.85rem;
  color:#111827;
  margin-bottom:0.25rem;
  font-weight:600;
}

.input-group input,
.input-group select{
  width:100%;
  padding: 0.8rem;
  border-radius: 0.5rem;
  border: 1px solid #cbd5e1;
  margin-top: 0.15rem;
  font-size: 1rem;
  transition: all 0.25s ease;
  background: #eef5ff;
}

.input-group input:focus,
.input-group select:focus{
  border-color: var(--primario);
  box-shadow: 0 0 0 3px rgba(29,100,242,0.18);
  outline:none;
}

/* botón principal */
.btn-submit{
  width:100%;
  background: var(--primario);
  color:#fff;
  border:none;
  border-radius: 0.6rem;
  padding: 0.95rem;
  font-size: 1rem;
  font-weight: 800;
  cursor:pointer;
  transition: all 0.3s ease;
  margin-top: 0.8rem;
}
.btn-submit:hover{
  background: var(--primario-oscuro);
  box-shadow: 0 10px 22px rgba(37,99,235,0.25);
  transform: translateY(-2px);
}

/* mensaje error */
.mensaje{
  margin-top: 0.9rem;
  font-size: 0.9rem;
  text-align:left;
  color: var(--danger);
  padding: 0.65rem 0.75rem;
  border-radius: 0.6rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.login-link{
  margin-top: 1rem;
  font-size: 0.9rem;
  color: var(--muted);
  text-align:center;
}
.login-link a{
  color: var(--primario);
  text-decoration:none;
  font-weight:800;
}
.login-link a:hover{
  text-decoration: underline;
}

/* Animación fade */
@keyframes fadeIn{
  from{ opacity:0; transform: scale(0.99); }
  to{ opacity:1; transform: scale(1); }
}

/* Responsive (igual al FARVEC) */
@media (max-width: 850px){
  .auth-container{
    flex-direction:column;
    width: 90%;
    height:auto;
  }
  .left, .right{
    width:100%;
    padding:2rem;
  }
  .left{
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }
  .form-grid{
    grid-template-columns: minmax(0, 1fr);
  }

  /* animación vertical si querés mantener feeling */
  .slide-out{
    animation: slideDownFade 0.6s forwards ease-in-out;
  }
  @keyframes slideDownFade{
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(150%); opacity: 0; }
  }
}

/* FIX: Bootstrap pisa .input-group; lo devolvemos a un contenedor normal */
.input-group{
  display: block !important;
}

/* FIX: evita que el pseudo-elemento agarre clicks encima de todo */
.left::before{
  pointer-events: none;
}

</style>
</head>

<body>
  <div class="auth-container" id="registroContainer">

    <!-- PANEL IZQUIERDO (diseño FARVEC, color login) -->
    <div class="left">
      <div class="logo-circle">
        <i class="fas fa-university"></i>
      </div>
      <h1>¡Bienvenido!</h1>
      <p>Completá tus datos para crear tu cuenta y acceder a BALKFOX HomeBanking.</p>
      <a href="#" id="loginBtn">Iniciar Sesión</a>
    </div>

    <!-- PANEL DERECHO -->
    <div class="right">
      <h2>Registrarse</h2>

      <!-- ✅ TU FORM ORIGINAL (MISMO ORDEN / MISMOS CAMPOS) -->
      <form method="POST">
        <div class="form-grid">
          <div class="input-group">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" required />
          </div>
          <div class="input-group">
            <label for="apellido">Apellido</label>
            <input type="text" id="apellido" name="apellido" required />
          </div>

          <div class="input-group">
            <label for="correo">Correo electrónico</label>
            <input type="email" id="correo" name="correo" required />
          </div>
          <div class="input-group">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" required />
          </div>

          <div class="input-group">
            <label for="dni">DNI</label>
            <input type="text" id="dni" name="dni" required />
          </div>
          <div class="input-group">
            <label for="domicilio">Domicilio</label>
            <input type="text" id="domicilio" name="domicilio" required />
          </div>

          <div class="input-group">
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required />
          </div>
          <div class="input-group">
            <label for="contrasena2">Confirmar contraseña</label>
            <input type="password" id="contrasena2" name="contrasena2" required />
          </div>

          <div class="input-group full">
            <label for="tipo_cuenta">Tipo de cuenta</label>
            <select id="tipo_cuenta" name="tipo_cuenta" required>
              <option value="">Seleccioná una opción</option>
              <option value="Cuenta Corriente">Cuenta Corriente</option>
              <option value="Caja de Ahorro">Caja de Ahorro</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn-submit">Registrarse</button>

        <?php if (!empty($mensaje)) : ?>
          <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
      </form>

      <div class="login-link">
        ¿Ya tenés una cuenta? <a href="login.php" id="loginLink">Iniciá sesión</a>
      </div>
    </div>

  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Animación al volver al login (mantenida) -->
  <script>
    function goLogin(e){
      e.preventDefault();
      const box = document.getElementById("registroContainer");
      box.classList.add("slide-out");
      setTimeout(() => window.location.href = "login.php", 600);
    }
    document.getElementById("loginBtn").addEventListener("click", goLogin);
    document.getElementById("loginLink").addEventListener("click", goLogin);
  </script>
</body>
</html>
