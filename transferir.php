<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];
$mensaje_error = "";
$mensaje_exito = "";

// Datos de usuario
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

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
$res_cuenta = $stmt_cuenta->get_result();
$cuenta = $res_cuenta->fetch_assoc();
$stmt_cuenta->close();

$nombre_usuario = $usuario["USUARIO_nombre"] ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";

// Procesar transferencia
if ($_SERVER["REQUEST_METHOD"] === "POST" && $cuenta) {
    $origen_numero = (int)$cuenta["CUENTA_BANCARIA_numero_de_cuenta"];
    $destino_input = trim($_POST["destino"] ?? "");
    $monto_input   = str_replace(',', '.', trim($_POST["monto"] ?? ""));
    $descripcion   = trim($_POST["descripcion"] ?? "");
    $moneda        = trim($_POST["moneda"] ?? "ARS");

    if ($destino_input === "" || $monto_input === "" || !is_numeric($monto_input) || $monto_input <= 0) {
        $mensaje_error = "Completá correctamente el destinatario y el monto de la transferencia.";
    } else {
        $monto = (float)$monto_input;

        // Buscar cuenta destino por número de cuenta / CBU / alias
        $sql_dest = "
            SELECT 
                CUENTA_BANCARIA_numero_de_cuenta,
                CUENTA_BANCARIA_cbu,
                CUENTA_BANCARIA_alias,
                CUENTA_BANCARIA_saldo,
                CUENTA_BANCARIA_estado,
                USUARIO_idUSUARIO
            FROM CUENTA_BANCARIA
            WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
               OR CUENTA_BANCARIA_cbu = ?
               OR CUENTA_BANCARIA_alias = ?
            LIMIT 1
        ";
        $stmt_dest = $conexion->prepare($sql_dest);
        $stmt_dest->bind_param("sss", $destino_input, $destino_input, $destino_input);
        $stmt_dest->execute();
        $res_dest = $stmt_dest->get_result();
        $cuenta_destino = $res_dest->fetch_assoc();
        $stmt_dest->close();

        if (!$cuenta_destino) {
            $mensaje_error = "No se encontró una cuenta destino con ese número, CBU o alias.";
        } else {
            $dest_numero = (int)$cuenta_destino["CUENTA_BANCARIA_numero_de_cuenta"];

            if ($dest_numero === $origen_numero) {
                $mensaje_error = "No podés transferirte a la misma cuenta.";
            } elseif ($cuenta_destino["CUENTA_BANCARIA_estado"] !== "Activa") {
                $mensaje_error = "La cuenta destino no se encuentra activa.";
            } else {
                // Iniciar transacción
                $conexion->begin_transaction();
                try {
                    // Bloquear fila de la cuenta de origen
                    $sql_saldo_origen = "
                        SELECT CUENTA_BANCARIA_saldo 
                        FROM CUENTA_BANCARIA 
                        WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
                        FOR UPDATE
                    ";
                    $stmt_so = $conexion->prepare($sql_saldo_origen);
                    $stmt_so->bind_param("i", $origen_numero);
                    $stmt_so->execute();
                    $res_so = $stmt_so->get_result();
                    $row_so = $res_so->fetch_assoc();
                    $stmt_so->close();

                    if (!$row_so) {
                        throw new Exception("No se encontró la cuenta de origen.");
                    }

                    $saldo_origen = (float)$row_so["CUENTA_BANCARIA_saldo"];
                    if ($saldo_origen < $monto) {
                        throw new Exception("Saldo insuficiente para realizar la transferencia.");
                    }

                    // Bloquear fila de la cuenta destino
                    $sql_saldo_dest = "
                        SELECT CUENTA_BANCARIA_saldo 
                        FROM CUENTA_BANCARIA 
                        WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
                        FOR UPDATE
                    ";
                    $stmt_sd = $conexion->prepare($sql_saldo_dest);
                    $stmt_sd->bind_param("i", $dest_numero);
                    $stmt_sd->execute();
                    $res_sd = $stmt_sd->get_result();
                    $row_sd = $res_sd->fetch_assoc();
                    $stmt_sd->close();

                    if (!$row_sd) {
                        throw new Exception("No se encontró la cuenta destino.");
                    }

                    $saldo_dest = (float)$row_sd["CUENTA_BANCARIA_saldo"];

                    // Actualizar saldos
                    $nuevo_saldo_origen = $saldo_origen - $monto;
                    $nuevo_saldo_dest   = $saldo_dest + $monto;

                    $sql_update_origen = "
                        UPDATE CUENTA_BANCARIA
                        SET CUENTA_BANCARIA_saldo = ?
                        WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
                    ";
                    $stmt_uo = $conexion->prepare($sql_update_origen);
                    $stmt_uo->bind_param("di", $nuevo_saldo_origen, $origen_numero);
                    $stmt_uo->execute();
                    $stmt_uo->close();

                    $sql_update_dest = "
                        UPDATE CUENTA_BANCARIA
                        SET CUENTA_BANCARIA_saldo = ?
                        WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
                    ";
                    $stmt_ud = $conexion->prepare($sql_update_dest);
                    $stmt_ud->bind_param("di", $nuevo_saldo_dest, $dest_numero);
                    $stmt_ud->execute();
                    $stmt_ud->close();

                    // Registrar transacción
                    $sql_trans = "
                        INSERT INTO TRANSACCIONES
                            (TRANSACCIONES_fecha_y_hora, 
                             TRANSACCIONES_monto, 
                             TRANSACCIONES_tipo_de_movimiento, 
                             TRANSACCIONES_descripcion, 
                             TRANSACCIONES_moneda,
                             TRANSACCIONES_cuenta_origen,
                             TRANSACCIONES_cuenta_destino)
                        VALUES
                            (NOW(), ?, ?, ?, ?, ?, ?)
                    ";

                    $tipo_envio = "Transferencia Enviada";
                    $monto_envio = -$monto;
                    $stmt_t1 = $conexion->prepare($sql_trans);
                    $stmt_t1->bind_param("dsssii", $monto_envio, $tipo_envio, $descripcion, $moneda, $origen_numero, $dest_numero);
                    $stmt_t1->execute();
                    $stmt_t1->close();

                    $tipo_recib = "Transferencia Recibida";
                    $monto_recib = $monto;
                    $stmt_t2 = $conexion->prepare($sql_trans);
                    $stmt_t2->bind_param("dsssii", $monto_recib, $tipo_recib, $descripcion, $moneda, $origen_numero, $dest_numero);
                    $stmt_t2->execute();
                    $stmt_t2->close();

                    $conexion->commit();
                    $mensaje_exito = "La transferencia se realizó correctamente.";
                    $cuenta["CUENTA_BANCARIA_saldo"] = $nuevo_saldo_origen;
                } catch (Exception $e) {
                    $conexion->rollback();
                    $mensaje_error = $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>BALKFOX - Transferencias</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      /* Tema CLARO */
      --bg-body: #f3f4f6;
      --bg-main: #f9fafb;

      /* ✅ MISMO AZUL (sidebar + header) */
      --balkfox-blue: #0c1c3d;
      --sidebar-bg: var(--balkfox-blue);
      --sidebar-border: rgba(255,255,255,0.12);

      --accent: #1d64f2;
      --accent-soft: rgba(29,100,242,0.12);
      --accent-dark: #1548b2;

      --text-main: #111827;
      --text-muted: #6b7280;

      --card-bg: #ffffff;
      --card-border: #e5e7eb;

      --success: #16a34a;
      --danger: #dc2626;

      --topbar-bg: var(--balkfox-blue);
      --topbar-border: rgba(255,255,255,0.12);

      --shadow-strong: 0 18px 35px rgba(15, 23, 42, 0.1);
      --shadow-card: 0 10px 25px rgba(15, 23, 42, 0.08);
    }

    html[data-theme="dark"] {
      --bg-body: #020617;
      --bg-main: #0b1120;

      /* ✅ mismo azul */
      --balkfox-blue: #0c1c3d;
      --sidebar-bg: var(--balkfox-blue);
      --sidebar-border: rgba(255,255,255,0.12);

      --accent: #1d64f2;
      --accent-soft: rgba(29,100,242,0.15);
      --accent-dark: #1548b2;

      --text-main: #e5e7eb;
      --text-muted: #9ca3af;

      --card-bg: #020617;
      --card-border: #1e293b;

      --success: #22c55e;
      --danger: #f97373;

      --topbar-bg: var(--balkfox-blue);
      --topbar-border: rgba(255,255,255,0.12);

      --shadow-strong: 0 20px 35px rgba(0, 0, 0, 0.5);
      --shadow-card: 0 20px 35px rgba(0, 0, 0, 0.45);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at 0% 0%, rgba(29,100,242,0.08) 0, transparent 40%),
        radial-gradient(circle at 100% 100%, rgba(29,100,242,0.08) 0, transparent 40%),
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
      transition: background-color 0.25s ease, border-color 0.25s ease;
      box-shadow: var(--shadow-card);
      animation: sidebarEnter .45s ease-out;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0 0.4rem;
    }

    /* ✅ LOGO CON IMAGEN */
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
      box-shadow: 0 0 0 3px rgba(0,0,0,0.35);
      transition: transform .25s ease;
    }
    .sidebar-logo-img-wrapper:hover { transform: scale(1.08) rotate(3deg); }
    .sidebar-logo-img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* ✅ letras blancas SOLO sidebar */
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
      color: rgba(255,255,255,0.9);
      transition: 0.15s background-color ease, 0.15s color ease, 0.15s transform ease, 0.15s box-shadow ease;
    }
    .sidebar-link i { width: 18px; text-align: center; font-size: 0.95rem; color: #fff; }

    .sidebar-link:hover {
      background-color: rgba(255,255,255,0.14);
      color: #ffffff;
      transform: translateX(3px);
      box-shadow: 0 10px 18px rgba(0,0,0,0.35);
    }

    .sidebar-link--primary {
      background: radial-gradient(circle at 0 0, #60a5ff, #2563eb);
      color: #ffffff;
      box-shadow: 0 0 25px rgba(59,130,246,0.75);
      font-weight: 600;
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
      transition: background-color 0.25s ease, border-color 0.25s ease;
      box-shadow: 0 8px 18px rgba(15,23,42,0.25);
      animation: topbarEnter .45s ease-out;
    }

    /* ✅ letras blancas SOLO header */
    .topbar-left h1 { margin: 0; font-size: 1.1rem; color: #ffffff; }
    .topbar-left p  { margin: 0.1rem 0 0; font-size: 0.8rem; color: #ffffff; opacity: .9; }

    .topbar-right { display: flex; align-items: center; gap: 0.75rem; }

    .topbar-user { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: #ffffff; }

    .topbar-avatar {
      width: 32px; height: 32px;
      border-radius: 999px;
      background: radial-gradient(circle at 20% 0, #4f8cff, #1d64f2 60%, #020617 100%);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem; color: #ffffff;
      box-shadow: 0 0 10px rgba(15,23,42,0.5);
    }

    .topbar-user-actions a {
      font-size: 0.8rem;
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.6);
      color: #ffffff;
      transition: .18s ease;
    }
    .topbar-user-actions a:hover { background: rgba(255,255,255,0.14); border-color: #fff; transform: translateY(-1px); }

    .theme-toggle {
      border: 1px solid rgba(255,255,255,0.6);
      background: rgba(255,255,255,0.12);
      border-radius: 999px;
      width: 34px; height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 0.9rem;
      color: #ffffff;
      transition: 0.2s background-color ease, 0.2s border-color ease, 0.2s transform ease;
    }
    .theme-toggle:hover { background: rgba(255,255,255,0.18); border-color: #fff; transform: translateY(-1px); }

    /* CONTENT */
    .content {
      padding: 1.6rem;
      max-width: 1100px;
      width: 100%;
      margin: 0 auto;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 0.8rem;
      margin-bottom: 1.4rem;
      flex-wrap: wrap;
      animation: fadeUp .45s ease-out;
    }

    .page-header h2 { margin: 0; font-size: 1.4rem; color: var(--text-main); }
    .page-header p  { margin: 0.15rem 0 0; font-size: 0.85rem; color: var(--text-muted); }

    .cards-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.2fr);
      gap: 1.2rem;
      margin-bottom: 1.3rem;
    }

    .card {
      background: var(--card-bg);
      border-radius: 0.9rem;
      border: 1px solid var(--card-border);
      padding: 1.2rem 1.3rem;
      box-shadow: var(--shadow-card);
      transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease, transform .18s ease;
      animation: fadeUp .45s ease-out;
    }
    .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-strong); }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.7rem;

      /* ✅ COLOR SOLO EN EL ENCABEZADO DE CADA TARJETA */
      padding: .55rem .75rem;
      border-radius: .7rem;
      border: 1px solid transparent;
    }

    /* 1ra tarjeta (Cuenta origen) -> azul */
    .cards-grid .card:nth-child(1) .card-header{
      background: linear-gradient(90deg, rgba(37,99,235,.22), rgba(37,99,235,.06), transparent);
      border-color: rgba(37,99,235,.35);
    }

    /* 2da tarjeta (Datos de la transferencia) -> celeste */
    .cards-grid .card:nth-child(2) .card-header{
      background: linear-gradient(90deg, rgba(96,165,255,.22), rgba(96,165,255,.06), transparent);
      border-color: rgba(96,165,255,.35);
    }

    .card-title {
      font-size: 0.95rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.09em;
      font-weight: 700;
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
    html[data-theme="dark"] .status-pill { background: rgba(22,163,74,0.15); color: #bbf7d0; }

    .main-balance { font-size: 1.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.3rem; }
    .balance-sub  { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.4rem; }

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
      background: rgba(15,23,42,0.04);
      border: 1px solid var(--card-border);
    }
    html[data-theme="dark"] .account-meta span { background: rgba(255,255,255,0.06); }

    .alias-tag { font-size: 0.85rem; margin-top: 0.25rem; color: var(--text-main); }
    html[data-theme="dark"] .alias-tag { color: #cbd5f5; }

    /* FORM */
    form { margin-top: 0.4rem; }

    .form-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 0.9rem 0.9rem;
    }
    .form-grid .full { grid-column: 1 / -1; }

    .input-group { width: 100%; }

    .input-group label {
      display: block;
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.25rem;
      font-weight: 600;
    }

    .input-group input,
    .input-group select,
    .input-group textarea {
      width: 100%;
      padding: 0.55rem 0.6rem;
      font-size: 0.9rem;
      border: 1px solid #d1d5db;
      border-radius: 0.45rem;
      transition: 0.2s border-color ease, 0.2s box-shadow ease, 0.2s background-color ease;
      background: #ffffff;
      color: var(--text-main);
      resize: vertical;
      min-height: 38px;
    }

    html[data-theme="dark"] .input-group input,
    html[data-theme="dark"] .input-group select,
    html[data-theme="dark"] .input-group textarea {
      background: #020617;
      border-color: #1f2937;
      color: #e5e7eb;
    }

    .input-group input:focus,
    .input-group select:focus,
    .input-group textarea:focus {
      border-color: var(--accent);
      outline: none;
      box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
    }

    .btn-primary {
      margin-top: 0.8rem;
      width: 100%;
      background-color: var(--accent);
      color: #ffffff;
      font-weight: 800;
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
    .btn-primary:active { transform: translateY(0); box-shadow: none; }

    .alert {
      border-radius: 0.6rem;
      padding: 0.65rem 0.85rem;
      font-size: 0.85rem;
      margin-bottom: 0.9rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      animation: fadeUp .45s ease-out;
    }

    .alert-success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    html[data-theme="dark"] .alert-success { background: rgba(22,163,74,0.15); color: #bbf7d0; border-color: rgba(34,197,94,0.4); }
    html[data-theme="dark"] .alert-error   { background: rgba(239,68,68,0.15); color: #fecaca; border-color: rgba(248,113,113,0.4); }

    .alert-icon { margin-top: 2px; }

    .empty-state { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; animation: fadeUp .45s ease-out; }

    @media (max-width: 960px) { .cards-grid { grid-template-columns: minmax(0, 1fr); } }

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
    }

    @media (max-width: 480px) {
      .topbar { padding: 0.7rem 1rem; }
      .content { padding: 1.1rem; }
      .topbar-left p { display:none; }
    }

    /* ===== FIX DEFINITIVO: TEXTO BLANCO SOLO EN SIDEBAR ===== */
.sidebar { color: #fff !important; }

.sidebar a,
.sidebar a:link,
.sidebar a:visited,
.sidebar a:hover,
.sidebar a:active,
.sidebar .sidebar-link,
.sidebar .sidebar-link i,
.sidebar .sidebar-title,
.sidebar .sidebar-subtitle,
.sidebar .sidebar-footer,
.sidebar ul,
.sidebar li,
.sidebar span,
.sidebar p,
.sidebar small,
.sidebar strong,
.sidebar div {
  color: #fff !important;
}

/* Por si Bootstrap te mete un "text-dark" o similar */
.sidebar .text-dark,
.sidebar .text-secondary,
.sidebar .text-muted {
  color: #fff !important;
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
        <li><a href="menu.php" class="sidebar-link"><i class="fa-solid fa-gauge"></i> Panel principal</a></li>
        <li><a href="ingresar_dinero.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> Ingresar dinero</a></li>
        <li><a href="transferir.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-right-left"></i> Transferir</a></li>
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
        <h1>Transferencias</h1>
        <p>Enviá dinero a otras cuentas usando número, CBU o alias.</p>
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
        <div>
          <h2>Nueva transferencia</h2>
          <p>Revisá los datos antes de confirmar la operación.</p>
        </div>
      </div>

      <?php if ($mensaje_exito): ?>
        <div class="alert alert-success">
          <div class="alert-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div><?php echo htmlspecialchars($mensaje_exito); ?></div>
        </div>
      <?php endif; ?>

      <?php if ($mensaje_error): ?>
        <div class="alert alert-error">
          <div class="alert-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
          <div><?php echo htmlspecialchars($mensaje_error); ?></div>
        </div>
      <?php endif; ?>

      <?php if ($cuenta): ?>
        <section class="cards-grid">
          <!-- Cuenta origen -->
          <article class="card">
            <div class="card-header">
              <div class="card-title">Cuenta origen</div>
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

            <p style="margin-top:0.8rem;font-size:0.8rem;color:var(--text-muted);">
              Podés transferir hasta el monto disponible en tu saldo.
            </p>
          </article>

          <!-- Formulario de transferencia -->
          <article class="card">
            <div class="card-header">
              <div class="card-title">Datos de la transferencia</div>
            </div>

            <form method="POST" autocomplete="off">
              <div class="form-grid">
                <div class="input-group full">
                  <label for="destino">Cuenta destino (N° de cuenta, CBU o alias)</label>
                  <input type="text" id="destino" name="destino" required />
                </div>

                <div class="input-group">
                  <label for="monto">Monto</label>
                  <input type="number" step="0.01" min="0.01" id="monto" name="monto" required />
                </div>

                <div class="input-group">
                  <label for="moneda">Moneda</label>
                  <select id="moneda" name="moneda">
                    <option value="ARS">ARS - Peso argentino</option>
                    <option value="USD">USD - Dólares</option>
                  </select>
                </div>

                <div class="input-group full">
                  <label for="descripcion">Descripción (opcional)</label>
                  <textarea id="descripcion" name="descripcion" rows="2" placeholder="Ejemplo: pago alquiler, devolución, ahorro, etc."></textarea>
                </div>
              </div>

              <button type="submit" class="btn-primary">
                <i class="fa-solid fa-shuffle"></i>
                Confirmar transferencia
              </button>
            </form>
          </article>
        </section>
      <?php else: ?>
        <p class="empty-state">
          No tenés una cuenta activa para realizar transferencias. Comunicate con la entidad para habilitar una cuenta.
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
