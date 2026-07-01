<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id   = $_SESSION["usuario_id"];
$mensaje      = "";
$saldo_actual = null;

// Datos de usuario (para topbar)
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res_user = $stmt->get_result();
$usuario = $res_user->fetch_assoc();
$stmt->close();

$nombre_usuario   = $usuario["USUARIO_nombre"]  ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";

// Cuenta activa del usuario
$sql_cuenta = "
    SELECT 
        CUENTA_BANCARIA_numero_de_cuenta,
        CUENTA_BANCARIA_cbu,
        CUENTA_BANCARIA_saldo,
        CUENTA_BANCARIA_estado,
        CUENTA_BANCARIA_tipo_de_cuenta,
        CUENTA_BANCARIA_alias
    FROM CUENTA_BANCARIA
    WHERE USUARIO_idUSUARIO = ?
      AND CUENTA_BANCARIA_estado = 'Activa'
    LIMIT 1
";
$stmt_cuenta = $conexion->prepare($sql_cuenta);
$stmt_cuenta->bind_param("i", $usuario_id);
$stmt_cuenta->execute();
$res_cuenta  = $stmt_cuenta->get_result();
$cuenta_activa = $res_cuenta->fetch_assoc();
$stmt_cuenta->close();

// Procesar ingreso de dinero
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $numero_cuenta = $_POST["numero_cuenta"] ?? "";
    $monto_input   = trim($_POST["monto"] ?? "");
    $moneda        = $_POST["moneda"] ?? "ARS";

    if ($numero_cuenta === "" || $monto_input === "" || !is_numeric($monto_input) || (float)$monto_input <= 0) {
        $mensaje = "❌ Completá correctamente el número de cuenta y un monto mayor a 0.";
    } else {
        $monto = (float)$monto_input;

        $sql = "
            SELECT CUENTA_BANCARIA_saldo
            FROM CUENTA_BANCARIA
            WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
              AND USUARIO_idUSUARIO = ?
            LIMIT 1
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $numero_cuenta, $usuario_id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $cuenta = $resultado->fetch_assoc();
            $saldo_actual = (float)$cuenta["CUENTA_BANCARIA_saldo"];
            $nuevo_saldo  = $saldo_actual + $monto;

            // Actualizar saldo
            $update = $conexion->prepare("
                UPDATE CUENTA_BANCARIA 
                SET CUENTA_BANCARIA_saldo = ?
                WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
            ");
            $update->bind_param("di", $nuevo_saldo, $numero_cuenta);
            $update->execute();
            $update->close();

            // Registrar transacción
            $descripcion = "Ingreso por cajero automático";
            $insert = $conexion->prepare("
                INSERT INTO TRANSACCIONES (
                    TRANSACCIONES_cuenta_destino,
                    TRANSACCIONES_monto,
                    TRANSACCIONES_tipo_de_movimiento,
                    TRANSACCIONES_descripcion,
                    TRANSACCIONES_moneda,
                    TRANSACCIONES_fecha_y_hora
                ) VALUES (?, ?, 'Ingreso', ?, ?, NOW())
            ");
            $insert->bind_param("idss", $numero_cuenta, $monto, $descripcion, $moneda);
            $insert->execute();
            $insert->close();

            $mensaje      = "✅ Se ingresaron $monto $moneda correctamente.";
            $saldo_actual = $nuevo_saldo;
            if ($cuenta_activa && (int)$cuenta_activa["CUENTA_BANCARIA_numero_de_cuenta"] === (int)$numero_cuenta) {
                $cuenta_activa["CUENTA_BANCARIA_saldo"] = $nuevo_saldo;
            }
        } else {
            $mensaje = "❌ Número de cuenta inválido o no pertenece al usuario.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>Ingresar Dinero - BALKFOX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --bg-body: #f3f4f6;
      --bg-main: #f9fafb;

      /* MISMO AZUL DEL MENÚ */
      --sidebar-bg: #0c1c3d;
      --sidebar-border: #061635;

      --topbar-bg: #0c1c3d;
      --topbar-border: #061635;

      --accent: #1d64f2;
      --accent-dark: #1548b2;

      --text-main: #111827;
      --text-muted: #6b7280;

      --card-bg: #ffffff;
      --card-border: #e5e7eb;

      --success: #16a34a;
      --danger: #dc2626;

      --shadow-strong: 0 18px 35px rgba(15, 23, 42, 0.1);
      --shadow-card: 0 10px 25px rgba(15, 23, 42, 0.12);
    }

    html[data-theme="dark"] {
      --bg-body: #020617;
      --bg-main: #0b1120;

      --sidebar-bg: #0c1c3d;
      --sidebar-border: #020617;

      --topbar-bg: #0c1c3d;
      --topbar-border: #020617;

      --accent: #1d64f2;
      --accent-dark: #1548b2;

      --text-main: #e5e7eb;
      --text-muted: #9ca3af;

      --card-bg: #020617;
      --card-border: #1e293b;

      --success: #22c55e;
      --danger: #f97373;

      --shadow-strong: 0 20px 35px rgba(0, 0, 0, 0.5);
      --shadow-card: 0 20px 35px rgba(0, 0, 0, 0.45);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at 0% 0%, #1d64f210 0, transparent 40%),
        radial-gradient(circle at 100% 100%, #1d64f210 0, transparent 40%),
        var(--bg-body);
      color: var(--text-main);
      display: flex;
      min-height: 100vh;
      transition: background-color 0.25s ease, color 0.25s ease;
    }

    a { text-decoration: none; color: inherit; }

    /* ANIMACIONES */
    @keyframes sidebarEnter { from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes menuItemEnter { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes contentEnter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes cardEnter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

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

    /* ✅ FORZAR BLANCO EN TODO EL SIDEBAR (esto te faltaba) */
    .sidebar, .sidebar * { color: #ffffff; }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0 0.4rem;
    }

    /* ✅ LOGO CON IMAGEN CIRCULAR (como el menú) */
    .sidebar-logo-img-wrapper {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      overflow: hidden;
      border: 2px solid rgba(255,255,255,0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle at 10% 0, #4f8cff, #1d64f2 60%, #020617 100%);
      box-shadow: 0 0 0 3px rgba(0,0,0,0.4);
      transition: transform .25s ease;
      flex: 0 0 auto;
    }
    .sidebar-logo-img-wrapper:hover { transform: scale(1.08) rotate(3deg); }

    .sidebar-logo-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .sidebar-title {
      font-size: 0.95rem;
      letter-spacing: 0.11em;
      text-transform: uppercase;
      color: #ffffff;
    }

    .sidebar-subtitle {
      font-size: 0.7rem;
      color: rgba(255,255,255,0.88);
    }

    .sidebar-menu { margin-top: 0.8rem; }
    .sidebar-menu ul { list-style: none; padding: 0; margin: 0; }

    .sidebar-menu li {
      margin-bottom: 0.2rem;
      opacity: 0;
      animation: menuItemEnter .35s ease-out forwards;
    }
    .sidebar-menu li:nth-child(1) { animation-delay: .05s; }
    .sidebar-menu li:nth-child(2) { animation-delay: .10s; }
    .sidebar-menu li:nth-child(3) { animation-delay: .15s; }
    .sidebar-menu li:nth-child(4) { animation-delay: .20s; }
    .sidebar-menu li:nth-child(5) { animation-delay: .25s; }
    .sidebar-menu li:nth-child(6) { animation-delay: .30s; }
    .sidebar-menu li:nth-child(7) { animation-delay: .35s; }
    .sidebar-menu li:nth-child(8) { animation-delay: .40s; }
    .sidebar-menu li:nth-child(9) { animation-delay: .45s; }
    .sidebar-menu li:nth-child(10){ animation-delay: .50s; }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.55rem 0.75rem;
      border-radius: 0.55rem;
      font-size: 0.9rem;
      color: rgba(255,255,255,0.92);
      transition: 0.15s background-color ease, 0.15s transform ease, 0.15s box-shadow ease;
    }

    .sidebar-link:hover {
      background-color: rgba(15,23,42,0.35);
      transform: translateX(4px);
      box-shadow: 0 12px 24px rgba(0,0,0,0.55);
      color: #ffffff;
    }

    .sidebar-link--primary {
      background: radial-gradient(circle at 0 0, #60a5ff, #2563eb);
      color: #ffffff;
      border: 1px solid rgba(255,255,255,0.18);
      box-shadow: 0 0 22px rgba(59,130,246,0.65);
    }

    .sidebar-footer {
      margin-top: auto;
      font-size: 0.75rem;
      color: rgba(255,255,255,0.72);
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
      box-shadow: 0 8px 18px rgba(15,23,42,0.25);
    }

    /* ✅ FORZAR BLANCO EN TODO EL HEADER (esto te faltaba) */
    .topbar, .topbar * { color: #ffffff; }

    .topbar-left h1 { margin: 0; font-size: 1.1rem; }
    .topbar-left p  { margin: 0.1rem 0 0; font-size: 0.8rem; color: rgba(255,255,255,0.88); }

    .topbar-right { display: flex; align-items: center; gap: 0.75rem; }

    .topbar-avatar {
      width: 32px;
      height: 32px;
      border-radius: 999px;
      background: radial-gradient(circle at 20% 0, #4f8cff, #1d64f2 60%, #020617 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      box-shadow: 0 0 10px rgba(0,0,0,0.45);
    }

    .topbar-user-actions a {
      font-size: 0.8rem;
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(248,250,252,0.7);
      background: transparent;
      transition: 0.15s background-color ease, 0.15s border-color ease, 0.15s transform ease;
    }

    .topbar-user-actions a:hover {
      background: rgba(15,23,42,0.35);
      border-color: #ffffff;
      transform: translateY(-1px);
    }

    .theme-toggle {
      border: 1px solid rgba(248,250,252,0.7);
      background: rgba(15,23,42,0.25);
      border-radius: 999px;
      width: 34px;
      height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 0.9rem;
      transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
    }

    .theme-toggle:hover {
      background: rgba(15,23,42,0.4);
      border-color: #ffffff;
      transform: translateY(-1px);
    }

    /* CONTENT */
    .content {
      padding: 1.6rem;
      max-width: 900px;
      width: 100%;
      margin: 0 auto;
      animation: contentEnter .35s ease-out both;
    }

    .page-header { margin-bottom: 1.4rem; }
    .page-header h2 { margin: 0; font-size: 1.4rem; color: var(--text-main); }
    .page-header p  { margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); }

    .cards-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.1fr);
      gap: 1.2rem;
    }

    .card {
      background: var(--card-bg);
      border-radius: 0.9rem;
      border: 1px solid var(--card-border);
      padding: 1.2rem 1.3rem;
      box-shadow: var(--shadow-card);
      animation: cardEnter .45s ease-out both;
    }
    .cards-grid .card:nth-child(1){ animation-delay: .06s; }
    .cards-grid .card:nth-child(2){ animation-delay: .14s; }

    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.7rem; }

    .card-title {
      font-size: 0.95rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.09em;
      font-weight: 600;
    }

    /* ✅ Color SOLO en el encabezado de cada tarjeta */
    .cards-grid .card:nth-child(1) .card-header{
      padding: 0.55rem 0.75rem;
      border-radius: 0.65rem;
      background: linear-gradient(90deg, rgba(37,99,235,0.16), rgba(37,99,235,0.04));
    }
    .cards-grid .card:nth-child(2) .card-header{
      padding: 0.55rem 0.75rem;
      border-radius: 0.65rem;
      background: linear-gradient(90deg, rgba(96,165,255,0.18), rgba(96,165,255,0.05));
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.8rem;
      padding: 0.2rem 0.6rem;
      border-radius: 999px;
      background: rgba(22,163,74,0.08);
      color: #166534;
      border: 1px solid rgba(22,163,74,0.4);
    }

    .main-balance { font-size: 1.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.3rem; }
    .balance-sub  { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.4rem; }

    .account-meta { display: flex; flex-wrap: wrap; gap: 0.6rem; font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem; }

    .account-meta span {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.18rem 0.5rem;
      border-radius: 999px;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
    }

    .alias-tag { font-size: 0.85rem; margin-top: 0.25rem; color: var(--text-main); }

    .form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.9rem; margin-top: 0.5rem; }

    .input-group label {
      display: block;
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.25rem;
      font-weight: 600;
    }

    .input-group input,
    .input-group select {
      width: 100%;
      padding: 0.55rem 0.6rem;
      font-size: 0.9rem;
      border: 1px solid #d1d5db;
      border-radius: 0.45rem;
      transition: 0.2s border-color ease, 0.2s box-shadow ease, 0.2s background-color ease;
      background: #ffffff;
      color: var(--text-main);
      min-height: 38px;
    }

    html[data-theme="dark"] .input-group input,
    html[data-theme="dark"] .input-group select {
      background: #020617;
      border-color: #1f2937;
      color: #e5e7eb;
    }

    .input-group input:focus,
    .input-group select:focus {
      border-color: var(--accent);
      outline: none;
      box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
    }

    .btn-primary {
      margin-top: 0.8rem;
      width: 100%;
      background-color: var(--accent);
      color: #ffffff;
      font-weight: 700;
      border: none;
      padding: 0.7rem;
      font-size: 0.95rem;
      border-radius: 0.55rem;
      cursor: pointer;
      transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      box-shadow: 0 10px 22px rgba(37,99,235,0.25);
    }

    .btn-primary:hover {
      background-color: var(--accent-dark);
      transform: translateY(-1px);
      box-shadow: 0 14px 28px rgba(37,99,235,0.35);
    }

    .alert {
      border-radius: 0.6rem;
      padding: 0.65rem 0.85rem;
      font-size: 0.85rem;
      margin-top: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      animation: cardEnter .35s ease-out both;
    }
    .alert-success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    .empty-state { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; }

    @media (max-width: 960px) {
      .cards-grid { grid-template-columns: minmax(0, 1fr); }
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
      .sidebar-menu li { margin-bottom: 0; animation: none; opacity: 1; }
      .sidebar-link { padding: 0.35rem 0.55rem; font-size: 0.78rem; }
      .sidebar-footer { display: none; }
      .main { min-height: calc(100vh - 60px); }
    }

    @media (max-width: 480px) {
      .topbar { padding: 0.7rem 1rem; }
      .content { padding: 1.1rem; }
    }

    /* ========= FIX DEFINITIVO: TEXTO BLANCO SIDEBAR + HEADER ========= */
.sidebar,
.sidebar * ,
.sidebar a,
.sidebar a:link,
.sidebar a:visited,
.sidebar i,
.sidebar .sidebar-title,
.sidebar .sidebar-subtitle,
.sidebar .sidebar-footer {
  color: #ffffff !important;
}

.topbar,
.topbar * ,
.topbar a,
.topbar a:link,
.topbar a:visited,
.topbar i,
.topbar .topbar-left h1,
.topbar .topbar-left p,
.topbar .topbar-user,
.topbar .topbar-user-actions a {
  color: #ffffff !important;
}

/* Para que los links no cambien al hover a negro */
.sidebar-link:hover,
.sidebar-link--primary:hover,
.topbar-user-actions a:hover {
  color: #ffffff !important;
}

  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo-img-wrapper" aria-label="Logo BALKFOX">
        <img src="logo.png" alt="Logo BALKFOX" class="sidebar-logo-img"
             onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=&quot;color:#fff;font-weight:800;&quot;>B</span>';">
      </div>
      <div>
        <div class="sidebar-title">BALKFOX</div>
        <div class="sidebar-subtitle">HomeBanking</div>
      </div>
    </div>

    <nav class="sidebar-menu">
      <ul>
        <li><a href="menu.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Panel principal</a></li>
        <li><a href="ingresar_dinero.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-wallet"></i> Ingresar dinero</a></li>
        <li><a href="transferir.php" class="sidebar-link"><i class="fa-solid fa-right-left"></i> Transferir</a></li>
        <li><a href="pagos_y_servicios.php" class="sidebar-link"><i class="fa-solid fa-file-invoice-dollar"></i> Pagos de servicios</a></li>
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
        <h1>Ingresar dinero</h1>
        <p>Simulá un ingreso desde cajero automático a tu cuenta.</p>
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
        <h2><i class="fa-solid fa-coins"></i> Cajero automático</h2>
        <p>Ingresá el monto y la moneda para acreditar fondos en tu cuenta.</p>
      </div>

      <?php if ($mensaje): ?>
        <div class="alert <?php echo strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-error'; ?>">
          <div style="margin-top:2px;">
            <i class="fa-solid <?php echo strpos($mensaje, '✅') !== false ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
          </div>
          <div><?php echo htmlspecialchars($mensaje); ?></div>
        </div>
      <?php endif; ?>

      <?php if ($cuenta_activa): ?>
        <section class="cards-grid">
          <!-- Cuenta destino (activa) -->
          <article class="card">
            <div class="card-header">
              <div class="card-title">Cuenta destino</div>
              <div class="status-pill">
                <i class="fa-solid fa-circle"></i>
                <?php echo htmlspecialchars($cuenta_activa["CUENTA_BANCARIA_estado"]); ?>
              </div>
            </div>

            <div class="main-balance">
              $<?php echo number_format($cuenta_activa["CUENTA_BANCARIA_saldo"], 2, ',', '.'); ?>
            </div>
            <div class="balance-sub">Saldo actual</div>

            <div class="account-meta">
              <span><i class="fa-solid fa-layer-group"></i> <?php echo htmlspecialchars($cuenta_activa["CUENTA_BANCARIA_tipo_de_cuenta"]); ?></span>
              <span><i class="fa-solid fa-hashtag"></i> N° <?php echo htmlspecialchars($cuenta_activa["CUENTA_BANCARIA_numero_de_cuenta"]); ?></span>
            </div>

            <?php if (!empty($cuenta_activa["CUENTA_BANCARIA_alias"])): ?>
              <div class="alias-tag">
                <i class="fa-solid fa-at"></i>
                Alias: <?php echo htmlspecialchars($cuenta_activa["CUENTA_BANCARIA_alias"]); ?>
              </div>
            <?php endif; ?>
          </article>

          <!-- Formulario ingreso -->
          <article class="card">
            <div class="card-header">
              <div class="card-title">Datos del ingreso</div>
            </div>

            <form method="POST" autocomplete="off">
              <div class="form-grid">
                <div class="input-group">
                  <label>Número de cuenta</label>
                  <input
                    type="number"
                    name="numero_cuenta"
                    value="<?php echo $cuenta_activa ? htmlspecialchars($cuenta_activa["CUENTA_BANCARIA_numero_de_cuenta"]) : ''; ?>"
                    required
                  >
                </div>

                <div class="input-group">
                  <label>Moneda</label>
                  <select name="moneda" required>
                    <option value="ARS">ARS - Pesos Argentinos</option>
                    <option value="USD">USD - Dólares</option>
                    <option value="EUR">EUR - Euros</option>
                  </select>
                </div>

                <div class="input-group">
                  <label>Monto a ingresar</label>
                  <input type="number" step="0.01" name="monto" min="0.01" required>
                </div>
              </div>

              <button type="submit" class="btn-primary">
                <i class="fa-solid fa-circle-plus"></i>
                Confirmar ingreso
              </button>
            </form>
          </article>
        </section>
      <?php else: ?>
        <p class="empty-state">
          No tenés una cuenta activa para realizar ingresos. Comunicate con la entidad para habilitar una cuenta.
        </p>
      <?php endif; ?>
    </main>
  </div>

  <script>
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
        } else {
          icon.classList.remove('fa-moon');
          icon.classList.add('fa-sun');
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
