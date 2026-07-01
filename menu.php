<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];

// Datos de usuario
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

// Cuenta activa
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
$res_cuenta = $stmt_cuenta->get_result();
$cuenta = $res_cuenta->fetch_assoc();
$stmt_cuenta->close();

$transacciones = null;

if ($cuenta) {
    $nro_cuenta = $cuenta["CUENTA_BANCARIA_numero_de_cuenta"];

    $sql_trans = "
        SELECT 
            TRANSACCIONES_fecha_y_hora, 
            TRANSACCIONES_monto, 
            TRANSACCIONES_tipo_de_movimiento, 
            TRANSACCIONES_descripcion, 
            TRANSACCIONES_moneda,
            TRANSACCIONES_cuenta_origen,
            TRANSACCIONES_cuenta_destino
        FROM TRANSACCIONES
        WHERE TRANSACCIONES_cuenta_origen = ? 
           OR TRANSACCIONES_cuenta_destino = ?
        ORDER BY TRANSACCIONES_fecha_y_hora DESC 
        LIMIT 10
    ";

    $stmt = $conexion->prepare($sql_trans);
    $stmt->bind_param("ii", $nro_cuenta, $nro_cuenta);
    $stmt->execute();
    $transacciones = $stmt->get_result();
    $stmt->close();
}

$nombre_usuario = $usuario["USUARIO_nombre"] ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>BALKFOX - Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      /* Colores base */
      --bg-body: #f3f4f6;
      --bg-main: #f9fafb;

      /* Azul del proyecto (sidebar + header) */
      --sidebar-bg: #0c1c3d;
      --sidebar-border: #061635;

      --topbar-bg: #0c1c3d;
      --topbar-border: #061635;

      --accent: #2563eb;
      --accent-soft: #e4edff;
      --accent-dark: #1548b2;

      --text-main: #111827;
      --text-muted: #6b7280;

      --card-bg: #ffffff;
      --card-border: #e5e7eb;

      --success: #16a34a;
      --danger: #dc2626;
      --warning: #f59e0b;

      --table-header: #f3f4f6;

      --shadow-strong: 0 18px 35px rgba(15, 23, 42, 0.1);
      --shadow-card: 0 10px 25px rgba(15, 23, 42, 0.08);
    }

    html[data-theme="dark"] {
      --bg-body: #020617;
      --bg-main: #020617;

      --sidebar-bg: #0c1c3d;
      --sidebar-border: #020617;

      --topbar-bg: #0c1c3d;
      --topbar-border: #020617;

      --accent: #60a5ff;
      --accent-soft: #1d64f21a;
      --accent-dark: #93c5fd;

      --text-main: #e5e7eb;
      --text-muted: #9ca3af;

      --card-bg: #020617;
      --card-border: #1e293b;

      --success: #22c55e;
      --danger: #f97373;
      --warning: #fbbf24;

      --table-header: #020617;

      --shadow-strong: 0 20px 35px rgba(0, 0, 0, 0.5);
      --shadow-card: 0 20px 35px rgba(0, 0, 0, 0.45);
    }

    * {
      box-sizing: border-box;
    }

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

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ================= SIDEBAR ================= */

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
      color: #ffffff; /* 🔹 texto base blanco en sidebar */
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0 0.4rem;
    }

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
    }

    .sidebar-logo-img-wrapper:hover {
      transform: scale(1.08) rotate(3deg);
    }

    .sidebar-logo-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .sidebar-title {
      font-size: 0.95rem;
      letter-spacing: 0.11em;
      text-transform: uppercase;
      color: #ffffff; /* 🔹 blanco */
    }

    .sidebar-subtitle {
  font-size: 0.7rem;
  color: #ffffff; /* AHORA BLANCO PURO */
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
      color: #ffffff; /* 🔹 enlaces blancos */
      transition:
        background-color 0.15s ease,
        color 0.15s ease,
        transform 0.15s ease,
        box-shadow 0.15s ease;
    }

    .sidebar-link i {
      width: 18px;
      text-align: center;
      font-size: 0.95rem;
    }

    .sidebar-link:hover {
      background-color: rgba(15,23,42,0.35);
      color: #ffffff;
      transform: translateX(4px);
      box-shadow: 0 12px 24px rgba(0,0,0,0.7);
    }

    .sidebar-link--primary {
      background: radial-gradient(circle at 0 0, #60a5ff, #2563eb);
      color: #ffffff;
      box-shadow: 0 0 25px rgba(59,130,246,0.9);
      font-weight: 500;
    }

    .sidebar-link--primary:hover {
      transform: translateX(4px) translateY(-1px);
      box-shadow: 0 0 35px rgba(59,130,246,1);
    }

    .sidebar-footer {
      margin-top: auto;
      font-size: 0.75rem;
      color: rgba(255,255,255,0.75); /* 🔹 blanco suave */
      padding: 0 0.4rem;
    }

    /* ================= MAIN LAYOUT ================= */

    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      background: var(--bg-main);
      transition: background-color 0.25s ease;
    }

    /* ================= TOPBAR (HEADER) ================= */

    .topbar {
      padding: 0.8rem 1.6rem;
      border-bottom: 1px solid var(--topbar-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      backdrop-filter: blur(10px);
      position: sticky;
      top: 0;
      z-index: 20;
      background: var(--topbar-bg);
      box-shadow: 0 8px 18px rgba(15,23,42,0.25);
    }

    .topbar-left h1 {
      margin: 0;
      font-size: 1.1rem;
      color: #ffffff; /* 🔹 blanco */
    }

   .topbar-left p {
  margin: 0.1rem 0 0;
  font-size: 0.8rem;
  color: #ffffff; /* AHORA BLANCO PURO */
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
      color: #ffffff; /* 🔹 nombre blanco */
    }

    .topbar-avatar {
      width: 32px;
      height: 32px;
      border-radius: 999px;
      background: #2563eb;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.9rem;
      color: #ffffff;
      box-shadow: 0 0 10px rgba(15,23,42,0.5);
    }

    .topbar-user-actions a {
      font-size: 0.8rem;
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(248,250,252,0.7);
      color: #ffffff; /* 🔹 texto botón salir blanco */
      background: transparent;
    }

    .topbar-user-actions a:hover {
      background: rgba(15,23,42,0.35);
      border-color: #ffffff;
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
      color: #f9fafb;
      transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease, 0.2s color ease;
    }

    .theme-toggle:hover {
      background: rgba(15,23,42,0.4);
      border-color: #ffffff;
      transform: translateY(-1px);
    }

    /* ================= CONTENT ================= */

    .content {
      padding: 1.6rem;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
    }

    .welcome-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.8rem;
      margin-bottom: 1.2rem;
    }

    .welcome-row h2 {
      margin: 0;
      font-size: 1.4rem;
      color: var(--text-main);
    }

    .welcome-row p {
      margin: 0.15rem 0 0;
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .quick-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      font-size: 0.8rem;
    }

    .quick-actions a {
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      border: 1px solid #d1d5db;
      color: var(--text-muted);
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      background: #ffffff;
    }

    html[data-theme="dark"] .quick-actions a {
      border-color: #1f2937;
      background: #020617;
      color: #e5e7eb;
    }

    .quick-actions a:hover {
      border-color: var(--accent);
      color: var(--accent-dark);
    }

    html[data-theme="dark"] .quick-actions a:hover {
      color: #e5e7eb;
      background: #020617;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
      gap: 1.2rem;
      margin-bottom: 1.4rem;
    }

    .card {
      background: var(--card-bg);
      border-radius: 0.9rem;
      border: 1px solid var(--card-border);
      padding: 1.2rem 1.3rem;
      box-shadow: var(--shadow-card);
      transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.7rem;
    }

    .card-title {
      font-size: 0.95rem;
      text-transform: uppercase;
      letter-spacing: 0.09em;
      color: #0c1c3d;  /* 🔹 títulos “CUENTA PRINCIPAL” y “DATOS DE CUENTA” en azul */
      font-weight: 600;
    }

    html[data-theme="dark"] .card-title {
      color: #e5e7eb;
    }

    .card-chip {
      padding: 0.2rem 0.7rem;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      font-size: 0.75rem;
      color: var(--text-muted);
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      background: #f9fafb;
    }

    html[data-theme="dark"] .card-chip {
      border-color: #1f2937;
      background: #020617;
      color: #e5e7eb;
    }

    .main-balance {
      font-size: 1.9rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 0.3rem;
    }

    .balance-sub {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.4rem;
    }

    .account-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 0.3rem;
    }

    .account-meta span {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.18rem 0.5rem;
      border-radius: 999px;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
    }

    html[data-theme="dark"] .account-meta span {
      background: #020617;
      border-color: #111827;
    }

    .alias-tag {
      font-size: 0.85rem;
      margin-top: 0.25rem;
      color: var(--text-main);
    }

    html[data-theme="dark"] .alias-tag {
      color: #cbd5f5;
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

    html[data-theme="dark"] .status-pill {
      background: rgba(22,163,74,0.15);
      color: #bbf7d0;
    }

    .card-secondary p {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin: 0.15rem 0;
    }

    /* TRANSACCIONES */

    .section-title {
      margin: 1.4rem 0 0.5rem;
      font-size: 1rem;
      color: #0c1c3d;  /* 🔹 “Transacciones recientes” en azul */
      display: flex;
      align-items: center;
      gap: 0.4rem;
      font-weight: 600;
    }

    .section-title i {
      color: #2563eb; /* 🔹 icono celeste */
    }

    html[data-theme="dark"] .section-title {
      color: #e5e7eb;
    }

    .section-caption {
      margin: 0 0 0.7rem;
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .table-wrapper {
      overflow-x: auto;
      border-radius: 0.8rem;
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      box-shadow: var(--shadow-strong);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
      color: var(--text-main);
    }

    thead {
      background: var(--table-header);
    }

    th, td {
      padding: 0.7rem 0.85rem;
      text-align: left;
      white-space: nowrap;
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

    tbody tr:hover {
      background: #f9fafb;
    }

    html[data-theme="dark"] tbody tr:hover {
      background: #020617;
    }

    .amount-pos {
      color: var(--success);
      font-weight: 500;
    }

    .amount-neg {
      color: var(--danger);
      font-weight: 500;
    }

    .badge-mov {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.15rem 0.55rem;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
      color: var(--text-main);
    }

    html[data-theme="dark"] .badge-mov {
      border-color: #1f2937;
      background: #020617;
      color: #e5e7eb;
    }

    .empty-state {
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-top: 0.5rem;
    }

    /* Responsivo */
    @media (max-width: 960px) {
      .cards-grid {
        grid-template-columns: minmax(0, 1fr);
      }
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
    }

    @media (max-width: 480px) {
      .topbar {
        padding: 0.7rem 1rem;
      }
      .content {
        padding: 1.1rem;
      }
    }

    /* Animaciones */
    @keyframes sidebarEnter {
      from { opacity: 0; transform: translateX(-20px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    @keyframes menuItemEnter {
      from { opacity: 0; transform: translateX(-10px); }
      to   { opacity: 1; transform: translateX(0); }
    }

    /* ====== TARJETAS DE CUENTA ====== */

/* Borde superior de las cards */
.card {
  border-top: 3px solid #0c1c3d; /* azul institucional */
}

.card.card-secondary {
  border-top-color: #2563eb;     /* un azul un poco más vibrante */
}

/* Título de las cards: CUENTA PRINCIPAL / DATOS DE CUENTA */
.card-title {
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: #0c1c3d;        /* azul oscuro */
  font-weight: 600;
}

/* “Saldo disponible” y textos secundarios */
.balance-sub {
  font-size: 0.8rem;
  color: #6b7280;
}

/* Chips de tipo de cuenta, N° de cuenta, etc. */
.account-meta span {
  background: #e4edff;           /* celestito suave */
  border-color: #c3dafc;
  color: #1e3a8a;
}

/* Alias */
.alias-tag i {
  color: #2563eb;
}
.alias-tag {
  color: #111827;
}

/* ====== TÍTULO Y ÁREA DE TRANSACCIONES ====== */

.section-title {
  margin: 1.4rem 0 0.5rem;
  font-size: 1rem;
  color: #0c1c3d;        /* título “Transacciones recientes” en azul */
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 600;
}

.section-title i {
  color: #2563eb;        /* iconito en azul más vivo */
}

/* Subtítulo bajo “Transacciones recientes” */
.section-caption {
  font-size: 0.8rem;
  color: #4b5563;
}

/* Header de la tabla de movimientos */
.table-wrapper thead {
  background: #0c1c3d;
}
.table-wrapper thead th {
  color: #ffffff;
}

/* Badge del tipo de movimiento (Transferencia, etc.) */
.badge-mov {
  background: #e4edff;
  border-color: #c3dafc;
  color: #1e3a8a;
}

/* Montos en verde/rojo bien marcados */
.amount-pos {
  color: #16a34a;
  font-weight: 600;
}
.amount-neg {
  color: #dc2626;
  font-weight: 600;
}

/* ===============================
   COLORES EN ENCABEZADO DE TARJETAS
   (SIN TOCAR EL RESTO)
   =============================== */

/* Cuenta principal (1ra card) */
.cards-grid .card:nth-child(1) .card-header {
  padding: 0.6rem 0.8rem;
  border-radius: 0.6rem;
  background: linear-gradient(
    90deg,
    rgba(37,99,235,0.18),
    rgba(37,99,235,0.05)
  );
}

/* Datos de cuenta (2da card) */
.cards-grid .card:nth-child(2) .card-header {
  padding: 0.6rem 0.8rem;
  border-radius: 0.6rem;
  background: linear-gradient(
    90deg,
    rgba(22,163,74,0.18),
    rgba(22,163,74,0.05)
  );
}

/* Dark mode – un poco más intenso */
html[data-theme="dark"] .cards-grid .card:nth-child(1) .card-header {
  background: linear-gradient(
    90deg,
    rgba(96,165,255,0.28),
    rgba(96,165,255,0.08)
  );
}

html[data-theme="dark"] .cards-grid .card:nth-child(2) .card-header {
  background: linear-gradient(
    90deg,
    rgba(34,197,94,0.28),
    rgba(34,197,94,0.08)
  );
}


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
        <li>
          <a href="menu.php" class="sidebar-link sidebar-link--primary">
            <i class="fa-solid fa-gauge"></i> Panel principal
          </a>
        </li>
        <li>
          <a href="ingresar_dinero.php" class="sidebar-link">
            <i class="fa-solid fa-wallet"></i> Ingresar dinero
          </a>
        </li>
        <li>
          <a href="transferir.php" class="sidebar-link">
            <i class="fa-solid fa-right-left"></i> Transferir
          </a>
        </li>
        <li>
          <a href="pagos_y_servicios.php" class="sidebar-link">
            <i class="fa-solid fa-file-invoice-dollar"></i> Pagos de servicios
          </a>
        </li>
        <li>
          <a href="prestamos.php" class="sidebar-link">
            <i class="fa-solid fa-hand-holding-dollar"></i> Préstamos
          </a>
        </li>
        <li>
          <a href="tarjetas.php" class="sidebar-link">
            <i class="fa-solid fa-credit-card"></i> Tarjetas
          </a>
        </li>
        <li>
          <a href="notificaciones.php" class="sidebar-link">
            <i class="fa-solid fa-bell"></i> Notificaciones
          </a>
        </li>
        <li>
          <a href="ultimo_acceso.php" class="sidebar-link">
            <i class="fa-solid fa-clock-rotate-left"></i> Último acceso
          </a>
        </li>
        <li>
          <a href="persona.php" class="sidebar-link">
            <i class="fa-solid fa-user"></i> Mis datos
          </a>
        </li>
        <li>
          <a href="logout.php" class="sidebar-link">
            <i class="fa-solid fa-door-open"></i> Cerrar sesión
          </a>
        </li>
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
        <h1>Panel principal</h1>
        <p>Resumen de tu cuenta y últimos movimientos</p>
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
      <div class="welcome-row">
        <div>
          <h2>Hola, <?php echo htmlspecialchars($nombre_usuario); ?></h2>
          <p>Desde aquí podés revisar tu saldo, datos de cuenta y transacciones recientes.</p>
        </div>
        <div class="quick-actions">
          <a href="transferir.php"><i class="fa-solid fa-paper-plane"></i> Nueva transferencia</a>
          <a href="ingresar_dinero.php"><i class="fa-solid fa-circle-plus"></i> Ingresar dinero</a>
          <a href="pagos_y_servicios.php"><i class="fa-solid fa-bolt"></i> Pagar servicios</a>
        </div>
      </div>

      <?php if ($cuenta): ?>
        <section class="cards-grid">
          <!-- Cuenta principal -->
          <article class="card">
            <div class="card-header">
              <div>
                <div class="card-title">Cuenta principal</div>
              </div>
              <div class="status-pill">
                <i class="fa-solid fa-circle"></i>
                <?php echo htmlspecialchars($cuenta["CUENTA_BANCARIA_estado"]); ?>
              </div>
            </div>
            <div class="main-balance">
              $<?php echo number_format($cuenta["CUENTA_BANCARIA_saldo"], 2, ',', '.'); ?>
            </div>
            <div class="balance-sub">Saldo disponible</div>

            <div class="account-meta">
              <span><i class="fa-solid fa-layer-group"></i> 
                <?php echo htmlspecialchars($cuenta["CUENTA_BANCARIA_tipo_de_cuenta"]); ?>
              </span>
              <span><i class="fa-solid fa-hashtag"></i> 
                N° <?php echo htmlspecialchars($cuenta["CUENTA_BANCARIA_numero_de_cuenta"]); ?>
              </span>
            </div>
            <?php if (!empty($cuenta["CUENTA_BANCARIA_alias"])): ?>
              <div class="alias-tag">
                <i class="fa-solid fa-at"></i>
                Alias: <?php echo htmlspecialchars($cuenta["CUENTA_BANCARIA_alias"]); ?>
              </div>
            <?php endif; ?>
          </article>

          <!-- Datos adicionales -->
          <article class="card card-secondary">
            <div class="card-header">
              <div class="card-title">Datos de cuenta</div>
              <div class="card-chip">
                <i class="fa-solid fa-shield-halved"></i> Operación segura
              </div>
            </div>
            <p><strong>CBU:</strong><br><?php echo htmlspecialchars($cuenta["CUENTA_BANCARIA_cbu"]); ?></p>
            <p style="margin-top:0.5rem;">
              <strong>Recomendación:</strong><br>
              Compartí tu CBU o alias solo con personas o entidades de confianza.
            </p>
          </article>
        </section>
      <?php else: ?>
        <p class="empty-state">
          No tenés cuentas activas en este momento. Comunicate con la entidad para gestionar la apertura de una cuenta.
        </p>
      <?php endif; ?>

      <!-- TRANSACCIONES -->
      <h3 class="section-title">
        <i class="fa-solid fa-clock-rotate-left"></i> Transacciones recientes
      </h3>
      <p class="section-caption">
        Últimos movimientos registrados en tu cuenta.
      </p>

      <?php if ($cuenta && $transacciones && $transacciones->num_rows > 0): ?>
        <div class="table-wrapper">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Fecha y hora</th>
                <th>Movimiento</th>
                <th>Monto</th>
                <th>Moneda</th>
                <th>Descripción</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($fila = $transacciones->fetch_assoc()): ?>
                <?php
                  $monto = (float)$fila['TRANSACCIONES_monto'];
                  $tipo = $fila['TRANSACCIONES_tipo_de_movimiento'];
                  $claseMonto = $monto >= 0 ? 'amount-pos' : 'amount-neg';
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($fila['TRANSACCIONES_fecha_y_hora']); ?></td>
                  <td>
                    <span class="badge-mov">
                      <?php if ($monto >= 0): ?>
                        <i class="fa-solid fa-arrow-trend-up"></i>
                      <?php else: ?>
                        <i class="fa-solid fa-arrow-trend-down"></i>
                      <?php endif; ?>
                      <?php echo htmlspecialchars($tipo); ?>
                    </span>
                  </td>
                  <td class="<?php echo $claseMonto; ?>">
                    $<?php echo number_format($monto, 2, ',', '.'); ?>
                  </td>
                  <td><?php echo htmlspecialchars($fila['TRANSACCIONES_moneda']); ?></td>
                  <td><?php echo htmlspecialchars($fila['TRANSACCIONES_descripcion']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php elseif (!$cuenta): ?>
        <p class="empty-state">No tenés cuentas activas, por lo que no hay transacciones para mostrar.</p>
      <?php else: ?>
        <p class="empty-state">No hay transacciones registradas aún.</p>
      <?php endif; ?>
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

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  ></script>
</body>
</html>
