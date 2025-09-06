<?php
include 'Conexion.php';

$conn = new Conexion();
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = trim($_POST['nombre']);
    $apellido   = trim($_POST['apellido']); // en tu BD no hay campo "apellido"
    $dni        = trim($_POST['dni']);      // tampoco hay "dni"
    $domicilio  = trim($_POST['domicilio']); // ni "domicilio"
    $correo     = trim($_POST['correo']);
    $telefono   = trim($_POST['telefono']); // tampoco hay "telefono" en Usuario
    $contrasena = $_POST['contrasena'];
    $rol        = $_POST['rol']; // Cliente / Empleado / Farmaceutico / Administrador

    // ⚠️ Tu tabla Usuario solo tiene: nombre, email, usuario, password, rol
    $usuario_generado = strtolower(explode('@', $correo)[0]); 

    $sql = "INSERT INTO Usuario (nombre, email, usuario, password, rol) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->conexion->prepare($sql);

    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt->bind_param("sssss", $nombre, $correo, $usuario_generado, $hash, $rol);

    if ($stmt->execute()) {
        header("Location: login.php?registro=exitoso");
        exit();
    } else {
        $mensaje = "Error al registrar usuario: " . $conn->conexion->error;
    }
}
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
      --gris: #f4f4f4;
      --acento: #e85c4a;
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
      padding: 1rem;
      box-sizing: border-box;
    }

    .form-container {
      background-color: var(--verde);
      padding: 1.0rem;
      border-radius: 1rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 500px;
      box-sizing: border-box;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      color: var(--blanco);
    }

    .avatar {
      position: absolute;
      top: -0px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 60px;
      background-color: var(--acento);
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--blanco);
      font-size: 28px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
      border: 3px solid var(--blanco);
    }

    .form-container h2 {
      font-size: 1.3rem;
      color: var(--blanco);
      margin-top: 3rem;
      margin-bottom: 1rem;
      text-align: center;
      width: 100%;
    }

    form { width: 100%; }

    .input-group {
      margin-bottom: 0.7rem;
      width: 100%;
    }

    .input-group label {
      display: block;
      font-size: 0.85rem;
      color: var(--blanco);
      margin-bottom: 0.25rem;
    }

    .input-group input,
    .input-group select {
      width: 100%;
      padding: 0.5rem;
      font-size: 0.9rem;
      border: 1px solid #cbd5e1;
      border-radius: 0.4rem;
      background: var(--gris);
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
      background-color: var(--acento);
      color: var(--blanco);
      font-weight: 600;
      border: none;
      padding: 0.6rem;
      font-size: 0.95rem;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      margin-top: 0.6rem;
    }

    .btn:hover {
      background-color: #d94c3c;
      transform: scale(1.03);
    }

    .mensaje {
      margin-top: 0.6rem;
      text-align: center;
      font-weight: bold;
      color: var(--acento);
      background: var(--blanco);
      padding: 0.4rem;
      border-radius: 0.4rem;
      font-size: 0.9rem;
    }

    p { margin-top: 1rem; color: var(--blanco); }

    p a {
      color: var(--gris);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    p a:hover {
      color: var(--blanco);
      text-decoration: underline;
    }

    @keyframes fadeIn {
      from { opacity: 0; } to { opacity: 1; }
    }
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
      <?php if (!empty($mensaje)) : ?>
        <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
      <?php endif; ?>
    </form>
    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
  </div>
</body>
</html>
