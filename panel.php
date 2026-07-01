<?php
session_start();
include("conexion.php");

// Redirige si no está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Captura mensaje si viene por GET
$mensaje = $_GET['mensaje'] ?? '';

// Datos de usuario para topbar
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res_user = $stmt->get_result();
$usuario = $res_user->fetch_assoc();
$stmt->close();

$nombre_usuario   = $usuario["USUARIO_nombre"]  ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Servicios - BALKFOX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
          /* Tema CLARO */
          --bg-body: #f3f4f6;
          --bg-main: #f9fafb;
          --sidebar-bg: #ffffff;
          --sidebar-border: #e5e7eb;

          --accent: #1d64f2;
          --accent-soft: #e4edff;
          --accent-dark: #1548b2;

          --text-main: #111827;
          --text-muted: #6b7280;

          --card-bg: #ffffff;
          --card-border: #e5e7eb;

          --topbar-bg: rgba(249, 250, 251, 0.9);
          --topbar-border: #e5e7eb;

          --shadow-strong: 0 18px 35px rgba(15, 23, 42, 0.1);
          --shadow-card: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] {
          --bg-body: #020617;
          --bg-main: #0b1120;
          --sidebar-bg: #020617;
          --sidebar-border: #111827;

          --accent: #1d64f2;
          --accent-soft: #1d64f21a;
          --accent-dark: #1548b2;

          --text-main: #e5e7eb;
          --text-muted: #9ca3af;

          --card-bg: #020617;
          --card-border: #1e293b;

          --topbar-bg: linear-gradient(to right, #020617ee, #020617dd);
          --topbar-border: #111827;

          --shadow-strong: 0 20px 35px rgba(0, 0, 0, 0.5);
          --shadow-card: 0 20px 35px rgba(0, 0, 0, 0.45);
        }

        * {
          box-sizing: border-box;
        }

        body {
          margin: 0;
          font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
          background:
            radial-gradient(circle at 0% 0%, #1d64f210 0, transparent 40%),
            radial-gradient(circle at 100% 100%, #1d64f210 0, transparent 40%),
            var(--bg-body);
          color: var(--text-main);
          display: flex;
          min-height: 100vh;
          transition: background-color 0.25s ease, color 0.25s ease;
        }

        a {
          text-decoration: none;
          color: inherit;
        }

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
          transition: background-color 0.25s ease, border-color 0.25s ease;
          box-shadow: var(--shadow-card);
        }

        .sidebar-header {
          display: flex;
          align-items: center;
          gap: 0.8rem;
          padding: 0 0.4rem;
        }

        .sidebar-logo {
          width: 40px;
          height: 40px;
          border-radius: 12px;
          background: radial-gradient(circle at 10% 0, #4f8cff, #1d64f2 60%, #020617 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: 700;
          color: #ffffff;
        }

        .sidebar-title {
          font-size: 0.95rem;
          letter-spacing: 0.11em;
          text-transform: uppercase;
          color: var(--text-main);
        }

        .sidebar-subtitle {
          font-size: 0.7rem;
          color: var(--text-muted);
        }

        .sidebar-menu {
          margin-top: 0.8rem;
        }

        .sidebar-menu ul {
          list-style: none;
          padding: 0;
          margin: 0;
        }

        .sidebar-menu li {
          margin-bottom: 0.2rem;
        }

        .sidebar-link {
          display: flex;
          align-items: center;
          gap: 0.6rem;
          padding: 0.55rem 0.75rem;
          border-radius: 0.55rem;
          font-size: 0.9rem;
          color: var(--text-muted);
          transition: 0.15s background-color ease, 0.15s color ease, 0.15s transform ease, 0.15s box-shadow ease;
        }

        .sidebar-link i {
          width: 18px;
          text-align: center;
          font-size: 0.95rem;
        }

        .sidebar-link:hover {
          background-color: var(--accent-soft);
          color: var(--accent-dark);
          transform: translateX(2px);
          box-shadow: 0 8px 16px rgba(15, 23, 42, 0.15);
        }

        .sidebar-link--primary {
          background-color: var(--accent-soft);
          color: var(--accent-dark);
          border: 1px solid #1d4ed8;
        }

        html[data-theme="dark"] .sidebar-link--primary {
          color: #e5e7eb;
          border-color: #1d4ed8;
        }

        .sidebar-footer {
          margin-top: auto;
          font-size: 0.75rem;
          color: var(--text-muted);
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
          transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .topbar-left h1 {
          margin: 0;
          font-size: 1.1rem;
          color: var(--text-main);
        }

        .topbar-left p {
          margin: 0.1rem 0 0;
          font-size: 0.8rem;
          color: var(--text-muted);
        }

        .topbar-right {
          display: flex;
          align-items: center;
          gap: 0.75rem;
        }

        .topbar-user {
          display: flex;
          align-items: center;
          gap: 0.75rem;
          font-size: 0.85rem;
          color: var(--text-muted);
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
        }

        .topbar-user-actions a {
          font-size: 0.8rem;
          padding: 0.25rem 0.6rem;
          border-radius: 999px;
          border: 1px solid #d1d5db;
          color: var(--text-main);
        }

        html[data-theme="dark"] .topbar-user-actions a {
          border-color: #1f2937;
          color: #e5e7eb;
        }

        .topbar-user-actions a:hover {
          background: var(--accent-soft);
          border-color: var(--accent);
        }

        .theme-toggle {
          border: 1px solid #d1d5db;
          background: #ffffff;
          border-radius: 999px;
          width: 34px;
          height: 34px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          font-size: 0.9rem;
          color: #6b7280;
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease, 0.2s color ease;
        }

        .theme-toggle:hover {
          background: var(--accent-soft);
          border-color: var(--accent);
          transform: translateY(-1px);
        }

        html[data-theme="dark"] .theme-toggle {
          background: #020617;
          border-color: #1f2937;
          color: #e5e7eb;
        }

        /* CONTENT */
        .content {
          padding: 1.6rem;
          max-width: 800px;
          width: 100%;
          margin: 0 auto;
        }

        .page-header {
          margin-bottom: 1.4rem;
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
          transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .card-header {
          display: flex;
          align-items: center;
          gap: 0.6rem;
          margin-bottom: 0.8rem;
        }

        .card-header-icon {
          width: 36px;
          height: 36px;
          border-radius: 999px;
          background: var(--accent-soft);
          display: flex;
          align-items: center;
          justify-content: center;
          color: var(--accent-dark);
        }

        .card-header h3 {
          margin: 0;
          font-size: 1.05rem;
          color: var(--text-main);
        }

        .card-body {
          font-size: 0.9rem;
          color: var(--text-muted);
        }

        .mensaje {
          margin-top: 0.6rem;
          padding: 0.7rem 0.9rem;
          border-radius: 0.6rem;
          font-weight: 500;
          background-color: #dcfce7;
          color: #166534;
          border: 1px solid #bbf7d0;
        }

        html[data-theme="dark"] .mensaje {
          background: rgba(22,163,74,0.18);
          color: #bbf7d0;
          border-color: rgba(34,197,94,0.5);
        }

        .card-actions {
          margin-top: 1.2rem;
          display: flex;
          flex-wrap: wrap;
          gap: 0.6rem;
        }

        .btn {
          display: inline-flex;
          align-items: center;
          gap: 0.4rem;
          padding: 0.55rem 1rem;
          border-radius: 999px;
          font-size: 0.85rem;
          border: 1px solid #d1d5db;
          background: #ffffff;
          color: var(--text-main);
          cursor: pointer;
          transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
        }

        .btn:hover {
          background: var(--accent-soft);
          border-color: var(--accent);
          transform: translateY(-1px);
        }

        html[data-theme="dark"] .btn {
          background: #020617;
          border-color: #1f2937;
          color: #e5e7eb;
        }

        .btn-primary {
          background: var(--accent);
          border-color: var(--accent);
          color: #ffffff;
          box-shadow: 0 10px 22px rgba(37,99,235,0.25);
        }

        .btn-primary:hover {
          background: var(--accent-dark);
          border-color: var(--accent-dark);
        }

        @media (max-width: 768px) {
          body {
            flex-direction: column;
          }
          .sidebar {
            width: 100%;
            height: auto;
            flex-direction: row;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            position: static;
          }
          .sidebar-menu {
            flex: 1;
          }
          .sidebar-menu ul {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
          }
          .sidebar-menu li {
            margin-bottom: 0;
          }
          .sidebar-link {
            padding: 0.35rem 0.55rem;
            font-size: 0.78rem;
          }
          .sidebar-footer {
            display: none;
          }
          .main {
            min-height: calc(100vh - 60px);
          }
          .content {
            padding: 1.1rem;
          }
        }
    </style>
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo"><span>B</span></div>
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
        <li><a href="pagos_y_servicios.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-file-invoice-dollar"></i> Pagos y servicios</a></li>
        <li><a href="prestamos.php" class="sidebar-link"><i class="fa-solid fa-hand-holding-dollar"></i> Préstamos</a></li>
        <li><a href="tarjetas.php" class="sidebar-link"><i class="fa-solid fa-credit-card"></i> Tarjetas</a></li>
        <li><a href="notificaciones.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> Notificaciones</a></li>
        <li><a href="ultimo_acceso.php" class="sidebar-link"><i class="fa-solid fa-clock-rotate-left"></i> Último acceso</a></li>
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
        <h1>Servicios</h1>
        <p>Resumen de la operación realizada sobre pagos y servicios.</p>
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
      <section class="card">
        <div class="card-header">
          <div class="card-header-icon">
            <i class="fa-solid fa-receipt"></i>
          </div>
          <div>
            <h3>Resultado de la operación</h3>
          </div>
        </div>
        <div class="card-body">
          <?php if ($mensaje): ?>
            <div class="mensaje">
              <?php echo htmlspecialchars($mensaje); ?>
            </div>
          <?php else: ?>
            <p>No se recibió ningún mensaje. Es posible que hayas accedido directamente a esta pantalla.</p>
          <?php endif; ?>

          <div class="card-actions">
            <a href="pagos_y_servicios.php" class="btn btn-primary">
              <i class="fa-solid fa-file-invoice-dollar"></i>
              Volver a pagos y servicios
            </a>
            <a href="menu.php" class="btn">
              <i class="fa-solid fa-arrow-left"></i>
              Volver al menú principal
            </a>
          </div>
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
</body>
</html>
