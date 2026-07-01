<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuarioId = $_SESSION['usuario_id'];
$personaId = $_SESSION['persona_id'] ?? null;

$mensaje = "";
$mensaje_es_error = false; // ✅ para distinguir error/success en toast
$mostrarFormulario = false;

// Procesar POST (guardar o actualizar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni_raw       = $_POST['dni'] ?? '';
    $domicilio     = $_POST['domicilio'] ?? '';
    $telefono_raw  = $_POST['telefono'] ?? '';

    // ✅ Solo números (defensa backend)
    $dni = preg_replace('/\D+/', '', $dni_raw);
    $telefono = preg_replace('/\D+/', '', $telefono_raw);

    // ✅ Validaciones backend
    if ($dni === '' || strlen($dni) < 7 || strlen($dni) > 9) {
        $mensaje = "❌ El DNI debe tener solo números (7 a 9 dígitos).";
        $mensaje_es_error = true;
        $mostrarFormulario = true;
    } elseif ($telefono_raw !== '' && ($telefono === '' || strlen($telefono) < 6 || strlen($telefono) > 15)) {
        $mensaje = "❌ El teléfono debe tener solo números (6 a 15 dígitos).";
        $mensaje_es_error = true;
        $mostrarFormulario = true;
    } else {

        if ($personaId) {
            $sql = "UPDATE PERSONA 
                SET PERSONA_dni=?, PERSONA_domicilio=?, PERSONA_telefono=? 
                WHERE idPERSONA=? AND USUARIO_idUSUARIO=?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sssii", $dni, $domicilio, $telefono, $personaId, $usuarioId);
        } else {
            $sql = "INSERT INTO PERSONA (PERSONA_dni, PERSONA_domicilio, PERSONA_telefono, USUARIO_idUSUARIO) 
                VALUES (?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sssi", $dni, $domicilio, $telefono, $usuarioId);
        }

        if ($stmt->execute()) {
            if (!$personaId) {
                $personaId = $conexion->insert_id;
                $_SESSION['persona_id'] = $personaId;
            }

            $_SESSION['persona_dni']       = $dni;
            $_SESSION['persona_domicilio'] = $domicilio;
            $_SESSION['persona_telefono']  = $telefono;

            $mensaje = "Datos guardados correctamente.";
            $mensaje_es_error = false;
            $mostrarFormulario = false;
        } else {
            $mensaje = "Error al guardar datos: " . $stmt->error;
            $mensaje_es_error = true;
            $mostrarFormulario = true;
        }
    }
} else {
    // Si vino GET con ?editar=1 mostramos formulario para editar
    if (isset($_GET['editar']) && $_GET['editar'] == 1) {
        $mostrarFormulario = true;
    }
}

// Datos desde sesión
$dni       = $_SESSION['persona_dni']       ?? "";
$domicilio = $_SESSION['persona_domicilio'] ?? "";
$telefono  = $_SESSION['persona_telefono']  ?? "";

// Datos del usuario para topbar
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmtUser = $conexion->prepare($sql_user);
$stmtUser->bind_param("i", $usuarioId);
$stmtUser->execute();
$res_user = $stmtUser->get_result();
$usuario = $res_user->fetch_assoc();
$stmtUser->close();

$nombre_usuario   = $usuario["USUARIO_nombre"]  ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8" />
    <title>Mis datos personales - BALKFOX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ✅ Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
          /* Tema CLARO */
          --bg-body: #f3f4f6;
          --bg-main: #f9fafb;

          /* ✅ MISMO AZUL DEL MENÚ */
          --balkfox-blue: #0c1c3d;

          --sidebar-bg: var(--balkfox-blue);
          --sidebar-border: rgba(255,255,255,0.15);

          --accent: #1d64f2;
          --accent-soft: rgba(29,100,242,0.15);
          --accent-dark: #1548b2;

          --text-main: #111827;
          --text-muted: #6b7280;

          --card-bg: #ffffff;
          --card-border: #e5e7eb;

          /* ✅ HEADER AZUL */
          --topbar-bg: var(--balkfox-blue);
          --topbar-border: rgba(255,255,255,0.15);

          --shadow-strong: 0 18px 35px rgba(15, 23, 42, 0.1);
          --shadow-card: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] {
          --bg-body: #020617;
          --bg-main: #0b1120;

          --balkfox-blue: #0c1c3d;
          --sidebar-bg: var(--balkfox-blue);
          --sidebar-border: rgba(255,255,255,0.15);

          --accent: #1d64f2;
          --accent-soft: rgba(29,100,242,0.15);
          --accent-dark: #1548b2;

          --text-main: #e5e7eb;
          --text-muted: #9ca3af;

          --card-bg: #020617;
          --card-border: #1e293b;

          --topbar-bg: var(--balkfox-blue);
          --topbar-border: rgba(255,255,255,0.15);

          --shadow-strong: 0 20px 35px rgba(0, 0, 0, 0.5);
          --shadow-card: 0 20px 35px rgba(0, 0, 0, 0.45);
        }

        * { box-sizing: border-box; }

        body {
          margin: 0;
          font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
          background:
            radial-gradient(circle at 0% 0%, rgba(29,100,242,0.10) 0, transparent 40%),
            radial-gradient(circle at 100% 100%, rgba(29,100,242,0.10) 0, transparent 40%),
            var(--bg-body);
          color: var(--text-main);
          display: flex;
          min-height: 100vh;
          transition: background-color 0.25s ease, color 0.25s ease;
        }

        a { text-decoration: none; color: inherit; }

        /* =========================
           ANIMACIONES (ENTRADA)
        ========================== */
        @keyframes sidebarEnter { from {opacity:0; transform: translateX(-16px);} to {opacity:1; transform: translateX(0);} }
        @keyframes topbarEnter  { from {opacity:0; transform: translateY(-10px);} to {opacity:1; transform: translateY(0);} }
        @keyframes fadeUp       { from {opacity:0; transform: translateY(10px);} to {opacity:1; transform: translateY(0);} }
        @keyframes menuItemEnter{ from {opacity:0; transform: translateX(-10px);} to {opacity:1; transform: translateX(0);} }

        /* SIDEBAR */
        .sidebar {
          width: 250px;
          background: var(--sidebar-bg);
          border-right: 1px solid var(--sidebar-border);
          padding: 1.4rem 1rem;
          display: flex;
          flex-direction: column;
          gap: 1.5rem;
          position: sticky;
          top: 0;
          height: 100vh;
          box-shadow: var(--shadow-card);
          animation: sidebarEnter 0.45s ease-out;
        }

        .sidebar-header {
          display: flex;
          align-items: center;
          gap: 0.8rem;
          padding: 0 0.4rem;
        }

        /* ✅ Logo con imagen */
        .sidebar-logo-img-wrapper {
          width: 48px;
          height: 48px;
          border-radius: 50%;
          overflow: hidden;
          border: 2px solid rgba(255,255,255,0.65);
          display: flex;
          align-items: center;
          justify-content: center;
          background: radial-gradient(circle at 10% 0, #4f8cff, #1d64f2 60%, #020617 100%);
          box-shadow: 0 0 0 3px rgba(0,0,0,0.35);
          transition: transform .25s ease;
          flex: 0 0 auto;
        }
        .sidebar-logo-img-wrapper:hover { transform: scale(1.08) rotate(3deg); }
        .sidebar-logo-img { width: 100%; height: 100%; object-fit: cover; display:block; }

        .sidebar-title {
          font-size: 0.95rem;
          letter-spacing: 0.11em;
          text-transform: uppercase;
          color: #ffffff;
          font-weight: 800;
        }

        .sidebar-subtitle {
          font-size: 0.7rem;
          color: #ffffff;
          opacity: .9;
        }

        .sidebar-menu { margin-top: 0.8rem; }
        .sidebar-menu ul { list-style: none; padding: 0; margin: 0; }

        .sidebar-menu li {
          margin-bottom: 0.2rem;
          opacity: 0;
          animation: menuItemEnter .35s ease-out forwards;
        }
        .sidebar-menu li:nth-child(1) { animation-delay: .05s; }
        .sidebar-menu li:nth-child(2) { animation-delay: .1s; }
        .sidebar-menu li:nth-child(3) { animation-delay: .15s; }
        .sidebar-menu li:nth-child(4) { animation-delay: .2s; }
        .sidebar-menu li:nth-child(5) { animation-delay: .25s; }
        .sidebar-menu li:nth-child(6) { animation-delay: .3s; }
        .sidebar-menu li:nth-child(7) { animation-delay: .35s; }
        .sidebar-menu li:nth-child(8) { animation-delay: .4s; }
        .sidebar-menu li:nth-child(9) { animation-delay: .45s; }
        .sidebar-menu li:nth-child(10){ animation-delay: .5s; }

        .sidebar-link {
          display: flex;
          align-items: center;
          gap: 0.6rem;
          padding: 0.55rem 0.75rem;
          border-radius: 0.55rem;
          font-size: 0.9rem;
          color: rgba(255,255,255,0.92);
          transition: 0.15s background-color ease, 0.15s color ease, 0.15s transform ease, 0.15s box-shadow ease;
        }

        .sidebar-link i {
          width: 18px;
          text-align: center;
          font-size: 0.95rem;
          color: #ffffff;
        }

        .sidebar-link:hover {
          background-color: rgba(255,255,255,0.16);
          color: #ffffff;
          transform: translateX(2px);
          box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
        }

        .sidebar-link--primary {
          background: radial-gradient(circle at 0 0, #60a5ff, #2563eb);
          color: #ffffff;
          border: 1px solid rgba(255,255,255,0.25);
          box-shadow: 0 0 25px rgba(59,130,246,0.6);
          font-weight: 700;
        }

        .sidebar-footer {
          margin-top: auto;
          font-size: 0.75rem;
          color: rgba(255,255,255,0.75);
          padding: 0 0.4rem;
        }

        /* MAIN */
        .main {
          flex: 1;
          display: flex;
          flex-direction: column;
          background: var(--bg-main);
          transition: background-color 0.25s ease;
        }

        /* TOPBAR */
        .topbar {
          padding: 0.8rem 1.6rem;
          border-bottom: 1px solid var(--topbar-border);
          display: flex;
          justify-content: space-between;
          align-items: center;
          backdrop-filter: blur(12px);
          position: sticky;
          top: 0;
          z-index: 20;
          background: var(--topbar-bg);
          animation: topbarEnter .45s ease-out;
          box-shadow: 0 10px 25px rgba(0,0,0,0.18);
        }

        .topbar-left h1 { margin: 0; font-size: 1.1rem; color: #ffffff; }
        .topbar-left p  { margin: 0.1rem 0 0; font-size: 0.8rem; color: #ffffff; opacity: .9; }

        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }

        .topbar-user {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 0.85rem;
          color: #ffffff;
        }

        .topbar-avatar {
          width: 32px;
          height: 32px;
          border-radius: 999px;
          background: radial-gradient(circle at 20% 0, #4f8cff, #1d64f2 60%, #020617 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 0.9rem;
          color: #ffffff;
          box-shadow: 0 0 10px rgba(0,0,0,0.35);
        }

        .topbar-user-actions a {
          font-size: 0.8rem;
          padding: 0.25rem 0.6rem;
          border-radius: 999px;
          border: 1px solid rgba(255,255,255,0.55);
          color: #ffffff;
          transition: .18s ease;
        }

        .topbar-user-actions a:hover {
          background: rgba(255,255,255,0.16);
          border-color: #ffffff;
        }

        .theme-toggle {
          border: 1px solid rgba(255,255,255,0.55);
          background: rgba(255,255,255,0.10);
          border-radius: 999px;
          width: 34px;
          height: 34px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          font-size: 0.9rem;
          color: #ffffff;
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease, 0.2s color ease;
        }

        .theme-toggle:hover {
          background: rgba(255,255,255,0.16);
          border-color: #ffffff;
          transform: translateY(-1px);
        }

        /* CONTENT */
        .content {
          padding: 1.6rem;
          max-width: 820px;
          width: 100%;
          margin: 0 auto;
        }

        .page-header {
          margin-bottom: 1.4rem;
          animation: fadeUp .45s ease-out;
        }

        .page-header h2 {
          margin: 0;
          font-size: 1.4rem;
          color: var(--text-main);
        }

        .page-header p {
          margin: 0.2rem 0 0;
          font-size: 0.85rem;
          color: var(--text-muted);
        }

        .alert {
          border-radius: 0.7rem;
          padding: 0.7rem 1rem;
          font-size: 0.9rem;
          font-weight: 600;
          margin-bottom: 1rem;
          animation: fadeUp .45s ease-out;
        }

        .alert-success {
          background: #ecfdf3;
          color: #166534;
          border: 1px solid #bbf7d0;
        }

        html[data-theme="dark"] .alert-success {
          background: rgba(22,163,74,0.18);
          color: #bbf7d0;
          border-color: rgba(34,197,94,0.5);
        }

        .card {
          background: var(--card-bg);
          border-radius: 1rem;
          border: 1px solid var(--card-border);
          padding: 1.6rem 1.6rem 1.3rem;
          box-shadow: var(--shadow-card);
          animation: fadeUp .45s ease-out;
          transition: transform .18s ease, box-shadow .18s ease;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-strong); }

        /* ✅ Encabezado color (solo header de la tarjeta) */
        .card-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 1rem;
          padding: .55rem .75rem;
          border-radius: .7rem;
          background: linear-gradient(90deg, rgba(37,99,235,.20), rgba(37,99,235,.06), transparent);
          border: 1px solid rgba(37,99,235,.35);
        }

        .card-title {
          display: flex;
          align-items: center;
          gap: 0.6rem;
          font-size: 1.05rem;
          color: var(--text-main);
          margin: 0;
        }

        .card-title-icon {
          width: 36px;
          height: 36px;
          border-radius: 999px;
          background: rgba(37,99,235,0.12);
          display: flex;
          align-items: center;
          justify-content: center;
          color: #1d4ed8;
        }

        html[data-theme="dark"] .card-title-icon {
          background: rgba(59,130,246,0.18);
          color: #bfdbfe;
        }

        .badge-edit {
          font-size: 0.75rem;
          padding: 0.25rem 0.7rem;
          border-radius: 999px;
          background: rgba(59,130,246,0.12);
          color: #1d4ed8;
          border: 1px solid rgba(59,130,246,0.35);
          font-weight: 700;
        }

        html[data-theme="dark"] .badge-edit {
          background: rgba(59,130,246,0.18);
          color: #bfdbfe;
          border-color: rgba(59,130,246,0.45);
        }

        .info-grid {
          display: flex;
          flex-direction: column;
          gap: 0.6rem;
          margin-top: 0.6rem;
        }

        .info-item {
          display: flex;
          justify-content: space-between;
          padding: 0.6rem 0.9rem;
          border-radius: 0.7rem;
          background: #f1f5f9;
          font-size: 0.95rem;
        }

        html[data-theme="dark"] .info-item { background: #020617; }

        .info-label { font-weight: 700; color: #475569; }
        html[data-theme="dark"] .info-label { color: #cbd5e1; }

        .info-value { font-weight: 600; color: #0f172a; }
        html[data-theme="dark"] .info-value { color: #e5e7eb; }

        .btn-edit {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.55rem 1rem;
          border-radius: 999px;
          border: 1px solid rgba(37,99,235,0.35);
          background: #ffffff;
          font-size: 0.9rem;
          cursor: pointer;
          color: var(--text-main);
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
          font-weight: 700;
        }

        .btn-edit:hover {
          background: rgba(37,99,235,0.08);
          border-color: rgba(37,99,235,0.6);
          transform: translateY(-1px);
        }

        html[data-theme="dark"] .btn-edit {
          background: #020617;
          border-color: #1f2937;
          color: #e5e7eb;
        }

        /* FORMULARIO (respeta tus inputs) */
        form { margin-top: 0.5rem; }

        form label {
          display: block;
          font-size: 0.85rem;
          font-weight: 700;
          margin-bottom: 0.15rem;
          color: var(--text-main);
        }

        form input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 6px 0 12px 0;
            box-sizing: border-box;
            font-size: 0.95rem;
            border-radius: 0.5rem;
            border: 1.5px solid #94a3b8;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: #ffffff;
            color: var(--text-main);
        }

        html[data-theme="dark"] form input[type="text"] {
            background: #020617;
            border-color: #1f2937;
            color: #e5e7eb;
        }

        form input[type="text"]:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 5px rgba(37,99,235,0.55);
        }

        form button {
            padding: 0.6rem 1.2rem;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 800;
            transition: background-color 0.3s ease, transform 0.15s ease;
        }

        form button:hover {
            background-color: #1e40af;
            transform: translateY(-1px);
        }

        .btn-secondary-link {
          display: inline-flex;
          align-items: center;
          gap: 0.3rem;
          margin-left: 0.8rem;
          font-size: 0.85rem;
          color: var(--text-muted);
          font-weight: 700;
        }

        .btn-secondary-link:hover { color: #64748b; }

        @media (max-width: 768px) {
          body { flex-direction: column; }
          .sidebar {
            width: 100%;
            height: auto;
            flex-direction: row;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            position: static;
          }
          .sidebar-menu { flex: 1; }
          .sidebar-menu ul { display: flex; flex-wrap: wrap; gap: 0.3rem; }
          .sidebar-menu li { margin-bottom: 0; }
          .sidebar-link { padding: 0.35rem 0.55rem; font-size: 0.78rem; }
          .sidebar-footer { display: none; }
          .main { min-height: calc(100vh - 60px); }
          .content { padding: 1.1rem; }
        }

        /* ✅ FIX DEFINITIVO: blanco SOLO en sidebar + topbar (por si Bootstrap pisa) */
        .sidebar, .sidebar * { color: #fff !important; }
        .topbar, .topbar * { color: #fff !important; }
    </style>
</head>
<body>

  <!-- ✅ TOAST (igual que en Tarjetas) -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <div id="balkfoxToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="balkfoxToastBody"></div>
        <button type="button" class="btn-close me-2 m-auto btn-close-white" data-bs-dismiss="toast" aria-label="Cerrar"></button>
      </div>
    </div>
  </div>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo-img-wrapper">
        <img src="logo.png" alt="Logo Balkfox" class="sidebar-logo-img">
      </div>
      <div>
        <div class="sidebar-title">BALKFOX</div>
        <div class="sidebar-subtitle">HomeBanking</div>
      </div>
    </div>

    <nav class="sidebar-menu">
      <ul>
        <li><a href="menu.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Panel principal</a></li>
        <li><a href="ingresar_dinero.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> Ingresar dinero</a></li>
        <li><a href="transferir.php" class="sidebar-link"><i class="fa-solid fa-right-left"></i> Transferir</a></li>
        <li><a href="pagos_y_servicios.php" class="sidebar-link"><i class="fa-solid fa-file-invoice-dollar"></i> Pagos y servicios</a></li>
        <li><a href="prestamos.php" class="sidebar-link"><i class="fa-solid fa-hand-holding-dollar"></i> Préstamos</a></li>
        <li><a href="tarjetas.php" class="sidebar-link"><i class="fa-solid fa-credit-card"></i> Tarjetas</a></li>
        <li><a href="notificaciones.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
        <li><a href="ultimo_acceso.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i> Último acceso</a></li>
        <li><a href="persona.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-user"></i> Mis datos</a></li>
        <li><a href="logout.php" class="sidebar-link"><i class="fa-solid fa-door-open"></i> Cerrar sesión</a></li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      &copy; 2025 BALKFOX · Proyecto académico
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <h1>Mis datos personales</h1>
        <p>Consultá y actualizá la información asociada a tu perfil.</p>
      </div>
      <div class="topbar-right">
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Cambiar tema">
          <i class="fa-solid fa-sun"></i>
        </button>
        <div class="topbar-user">
          <div class="topbar-avatar">
            <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
          </div>
          <div>
            <div><?php echo htmlspecialchars($nombre_usuario . ' ' . $apellido_usuario); ?></div>
            <div class="topbar-user-actions">
              <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- CONTENT -->
    <main class="content">
      <div class="page-header">
        <h2><i class="fa-solid fa-id-card"></i> Información de contacto</h2>
        <p>Estos datos son importantes para operaciones y comunicaciones del banco.</p>
      </div>

      <?php if ($mensaje && !$mensaje_es_error): ?>
        <div class="alert alert-success">
          <?php echo htmlspecialchars($mensaje); ?>
        </div>
      <?php endif; ?>

      <section class="card">
        <div class="card-header">
          <div class="card-title">
            <div class="card-title-icon">
              <i class="fa-solid fa-user"></i>
            </div>
            <span>Mis datos personales</span>
          </div>

          <?php if (!$mostrarFormulario): ?>
            <span class="badge-edit">Solo lectura</span>
          <?php endif; ?>
        </div>

        <?php if ($mostrarFormulario): ?>
          <form method="POST" action="persona.php" id="formPersona">
            <label for="dni">DNI:</label>
            <input
              type="text"
              id="dni"
              name="dni"
              value="<?php echo htmlspecialchars($dni); ?>"
              required
              inputmode="numeric"
              autocomplete="off"
              maxlength="9"
            >

            <label for="domicilio">Domicilio:</label>
            <input type="text" id="domicilio" name="domicilio" value="<?php echo htmlspecialchars($domicilio); ?>">

            <label for="telefono">Teléfono:</label>
            <input
              type="text"
              id="telefono"
              name="telefono"
              value="<?php echo htmlspecialchars($telefono); ?>"
              inputmode="numeric"
              autocomplete="off"
              maxlength="15"
            >

            <button type="submit">
              <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
            </button>
            <a class="btn-secondary-link" href="persona.php">
              <i class="fa-solid fa-xmark"></i> Cancelar
            </a>
          </form>
        <?php else: ?>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">DNI</span>
              <span class="info-value"><?php echo htmlspecialchars($dni ?: 'No registrado'); ?></span>
            </div>
            <div class="info-item">
              <span class="info-label">Domicilio</span>
              <span class="info-value"><?php echo htmlspecialchars($domicilio ?: 'No registrado'); ?></span>
            </div>
            <div class="info-item">
              <span class="info-label">Teléfono</span>
              <span class="info-value"><?php echo htmlspecialchars($telefono ?: 'No registrado'); ?></span>
            </div>
          </div>
          <div style="margin-top: 1rem;">
            <a href="persona.php?editar=1" class="btn-edit">
              <i class="fa-solid fa-pen"></i> Editar datos
            </a>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    // Tema inicial: localStorage > preferencia del sistema > light
    (function () {
      const stored = localStorage.getItem('balkfox-theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      const initial = stored || (prefersDark ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', initial);

      const btn = document.getElementById('themeToggle');
      const icon = btn.querySelector('i');

      function updateIcon(theme) {
        if (theme === 'dark') {
          icon.classList.remove('fa-sun');
          icon.classList.add('fa-moon');
          btn.setAttribute('aria-label', 'Cambiar a modo claro');
        } else {
          icon.classList.remove('fa-moon');
          icon.classList.add('fa-sun');
          btn.setAttribute('aria-label', 'Cambiar a modo oscuro');
        }
      }

      updateIcon(initial);

      btn.addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('balkfox-theme', next);
        updateIcon(next);
      });
    })();
  </script>

  <!-- Bootstrap JS (opcional) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // ✅ Toast helper
    function showToast(message, type = 'error') {
      const toastEl = document.getElementById('balkfoxToast');
      const toastBody = document.getElementById('balkfoxToastBody');

      toastEl.classList.remove('text-bg-danger', 'text-bg-success');
      toastEl.classList.add(type === 'success' ? 'text-bg-success' : 'text-bg-danger');

      toastBody.textContent = message;

      const t = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3200 });
      t.show();
    }

    // ✅ Si el backend detectó error, lo mostramos en toast
    <?php if (!empty($mensaje) && $mensaje_es_error): ?>
      showToast(<?php echo json_encode($mensaje); ?>, 'error');
    <?php endif; ?>

    // ✅ Bloquear letras en DNI y Teléfono (igual comportamiento que Tarjetas)
    (function () {
      const dni = document.getElementById('dni');
      const telefono = document.getElementById('telefono');
      const form = document.getElementById('formPersona');

      if (!dni || !telefono || !form) return;

      let lastToastAt = 0;

      function attachOnlyDigits(input, label, maxLen) {
        input.addEventListener('input', function () {
          const raw = input.value;
          let digits = raw.replace(/\D/g, '');

          if (typeof maxLen === 'number' && maxLen > 0) {
            digits = digits.slice(0, maxLen);
          }

          if (raw !== digits) {
            const now = Date.now();
            if (now - lastToastAt > 900) {
              showToast(`❌ En ${label} solo se permiten números.`, 'error');
              lastToastAt = now;
            }
          }

          input.value = digits;
        });
      }

      attachOnlyDigits(dni, 'DNI', 9);
      attachOnlyDigits(telefono, 'Teléfono', 15);

      // Normalizar antes de enviar (por si manipulan el DOM)
      form.addEventListener('submit', function (e) {
        dni.value = (dni.value || '').replace(/\D/g, '').slice(0, 9);
        telefono.value = (telefono.value || '').replace(/\D/g, '').slice(0, 15);

        // Validación rápida (coincide con backend)
        if (dni.value.length < 7 || dni.value.length > 9) {
          e.preventDefault();
          showToast('❌ El DNI debe tener solo números (7 a 9 dígitos).', 'error');
          return;
        }

        if (telefono.value !== '' && (telefono.value.length < 6 || telefono.value.length > 15)) {
          e.preventDefault();
          showToast('❌ El teléfono debe tener solo números (6 a 15 dígitos).', 'error');
          return;
        }
      });
    })();
  </script>
</body>
</html>
