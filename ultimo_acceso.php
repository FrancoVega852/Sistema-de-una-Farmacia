<?php 
session_start(); 
include("conexion.php"); 

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];

/* Datos de usuario para topbar */
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res_user = $stmt->get_result();
$usuario = $res_user->fetch_assoc();
$stmt->close();

$nombre_usuario   = $usuario["USUARIO_nombre"]  ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";

/* Último acceso del usuario logueado */
$sql = "SELECT U.USUARIO_nombre, U.USUARIO_apellido, L.LOGIN_fecha_y_hora_de_acceso 
        FROM LOGIN L 
        JOIN USUARIO U ON L.LOGIN_idUsuario = U.idUSUARIO 
        WHERE L.LOGIN_idUsuario = ?
        ORDER BY L.LOGIN_fecha_y_hora_de_acceso DESC 
        LIMIT 1"; 

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$acceso = $resultado->fetch_assoc();
$stmt->close();

$hay_acceso = $acceso ? true : false;

if ($hay_acceso) {
    $nombreCompleto = $acceso["USUARIO_nombre"] . " " . $acceso["USUARIO_apellido"]; 
    $fechaHora = $acceso["LOGIN_fecha_y_hora_de_acceso"]; 
    $fecha = date("d/m/Y", strtotime($fechaHora)); 
    $hora = date("H:i:s", strtotime($fechaHora)); 
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Último acceso - BALKFOX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ✅ Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

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
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
        }

        .theme-toggle:hover {
          background: rgba(255,255,255,0.16);
          border-color: #ffffff;
          transform: translateY(-1px);
        }

        /* CONTENT */
        .content {
          padding: 1.6rem;
          max-width: 900px;
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

        .card {
          background: var(--card-bg);
          border-radius: 0.9rem;
          border: 1px solid var(--card-border);
          padding: 1.4rem 1.5rem;
          box-shadow: var(--shadow-card);
          transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, transform .18s ease;
          animation: fadeUp .45s ease-out;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-strong); }

        /* ✅ Encabezado de tarjeta con color */
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
          font-size: 0.95rem;
          color: var(--text-muted);
          text-transform: uppercase;
          letter-spacing: 0.09em;
          font-weight: 700;
        }

        .pill {
          display: inline-flex;
          align-items: center;
          gap: 0.35rem;
          padding: 0.2rem 0.55rem;
          border-radius: 999px;
          font-size: 0.75rem;
          border: 1px solid #e5e7eb;
          background: #f9fafb;
          color: var(--text-main);
        }

        html[data-theme="dark"] .pill {
          border-color: #1f2937;
          background: #020617;
          color: #e5e7eb;
        }

        .card-body { font-size: 0.88rem; color: var(--text-muted); }

        .table-wrapper {
          margin-top: 1rem;
          border-radius: 0.9rem;
          border: 1px solid var(--card-border);
          overflow: hidden;
        }

        table {
          width: 100%;
          border-collapse: collapse;
          font-size: 0.9rem;
        }

        thead { background: #f3f4f6; }
        html[data-theme="dark"] thead { background: #020617; }

        th, td {
          padding: 0.75rem 0.9rem;
          text-align: left;
          border-bottom: 1px solid #e5e7eb;
        }

        html[data-theme="dark"] th,
        html[data-theme="dark"] td {
          border-bottom-color: #111827;
          color: #e5e7eb;
        }

        th {
          font-weight: 500;
          font-size: 0.78rem;
          text-transform: uppercase;
          letter-spacing: 0.08em;
          color: var(--text-muted);
        }

        tbody tr:hover { background: #f9fafb; }
        html[data-theme="dark"] tbody tr:hover { background: #020617; }

        .empty-state {
          margin-top: 1rem;
          font-size: 0.9rem;
          color: var(--text-muted);
        }

        .btn-back {
          display: inline-flex;
          align-items: center;
          gap: 0.4rem;
          margin-top: 1.4rem;
          padding: 0.55rem 1rem;
          border-radius: 999px;
          border: 1px solid rgba(37,99,235,0.35);
          font-size: 0.85rem;
          color: var(--text-main);
          background: #ffffff;
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
        }

        .btn-back:hover {
          background: rgba(37,99,235,0.08);
          border-color: rgba(37,99,235,0.6);
          transform: translateY(-1px);
        }

        html[data-theme="dark"] .btn-back {
          background: #020617;
          border-color: #1f2937;
          color: #e5e7eb;
        }

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
        <li><a href="ultimo_acceso.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-clock-rotate-left"></i> Último acceso</a></li>
        <li><a href="persona.php" class="sidebar-link"><i class="fa-solid fa-user"></i> Mis datos</a></li>
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
        <h1>Último acceso</h1>
        <p>Consulta del último inicio de sesión registrado para tu usuario.</p>
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
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Detalle del último acceso</h2>
        <p>Verificá la fecha y hora del último inicio de sesión de tu cuenta.</p>
      </div>

      <section class="card">
        <div class="card-header">
          <div class="card-title">Información</div>
          <span class="pill">
            <i class="fa-solid fa-circle-info"></i>
            Seguridad de acceso
          </span>
        </div>
        <div class="card-body">
          <?php if ($hay_acceso): ?>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><?php echo htmlspecialchars($nombreCompleto); ?></td>
                    <td><?php echo htmlspecialchars($fecha); ?></td>
                    <td><?php echo htmlspecialchars($hora); ?></td>
                  </tr>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="empty-state">
              Todavía no se registró ningún acceso para tu usuario en la tabla de LOGINS.
            </p>
          <?php endif; ?>

          <a href="menu.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            Volver al menú principal
          </a>
        </div>
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
</body>
</html>
