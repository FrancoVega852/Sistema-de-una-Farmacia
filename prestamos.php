<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuarioId = $_SESSION["usuario_id"];
$mensaje = "";

// Datos de usuario para el topbar
$sql_user = "SELECT USUARIO_nombre, USUARIO_apellido FROM USUARIO WHERE idUSUARIO = ?";
$stmt = $conexion->prepare($sql_user);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$res_user = $stmt->get_result();
$usuario = $res_user->fetch_assoc();
$stmt->close();

$nombre_usuario   = $usuario["USUARIO_nombre"]  ?? "";
$apellido_usuario = $usuario["USUARIO_apellido"] ?? "";

/* =========================================================
   ✅ B1) AUTO-PROCESAR PRESTAMOS + NOTIFICACIONES (SIN CRON)
   ========================================================= */

function crearNotificacion($conexion, $usuarioId, $mensaje, $tipo = 'Préstamo', $estado = 'Pendiente') {
    $sql = "INSERT INTO NOTIFICACIONES
              (NOTIFICACIONES_mensaje, NOTIFICACIONES_fecha_y_hora, NOTIFICACIONES_tipo_de_notificaciones, NOTIFICACIONES_estado, USUARIO_idUSUARIO)
            VALUES (?, NOW(), ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssi", $mensaje, $tipo, $estado, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function existeNotificacionExacta($conexion, $usuarioId, $mensaje) {
    $sql = "SELECT 1
            FROM NOTIFICACIONES
            WHERE USUARIO_idUSUARIO = ?
              AND NOTIFICACIONES_mensaje = ?
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $usuarioId, $mensaje);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();
    return $existe;
}

// Busca la fecha de la notificación "Solicitud registrada" para ese préstamo
function obtenerFechaSolicitud($conexion, $usuarioId, $idPrestamo) {
    $mensaje = "[PRESTAMO:$idPrestamo] Solicitud registrada";
    $sql = "SELECT NOTIFICACIONES_fecha_y_hora AS fecha
            FROM NOTIFICACIONES
            WHERE USUARIO_idUSUARIO = ?
              AND NOTIFICACIONES_tipo_de_notificaciones = 'Préstamo'
              AND NOTIFICACIONES_mensaje = ?
            ORDER BY NOTIFICACIONES_fecha_y_hora ASC
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $usuarioId, $mensaje);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row['fecha'] ?? null;
}

function autoResolverPrestamosUsuario($conexion, $usuarioId, $minutosEspera = 1, $limiteAprobar = 200000) {
    $sql = "SELECT idPRESTAMO, PRESTAMO_monto_solicitado, PRESTAMO_monto_aprobado
            FROM PRESTAMO
            WHERE USUARIO_idUSUARIO = ?
              AND PRESTAMO_estado = 'Pendiente'
            ORDER BY idPRESTAMO ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    while ($p = $res->fetch_assoc()) {
        $idPrestamo = (int)$p['idPRESTAMO'];
        $montoSol   = (float)$p['PRESTAMO_monto_solicitado'];
        $montoApr   = (float)$p['PRESTAMO_monto_aprobado'];

        // Si no existe la notificación base, la creamos (para no romper el flujo)
        $msgSolicitud = "[PRESTAMO:$idPrestamo] Solicitud registrada";
        if (!existeNotificacionExacta($conexion, $usuarioId, $msgSolicitud)) {
            crearNotificacion($conexion, $usuarioId, $msgSolicitud, "Préstamo", "Pendiente");
            continue; // recién creada -> esperar al próximo refresh
        }

        $fechaSolicitud = obtenerFechaSolicitud($conexion, $usuarioId, $idPrestamo);
        if (!$fechaSolicitud) continue;

        $tsSolicitud = strtotime($fechaSolicitud);
        if ($tsSolicitud === false) continue;

        if ((time() - $tsSolicitud) < ($minutosEspera * 60)) {
            continue; // todavía no pasó el tiempo
        }

        // Regla simple de decisión
        $nuevoEstado = ($montoSol <= $limiteAprobar) ? 'Aprobado' : 'Rechazado';

        // Update seguro (solo si sigue Pendiente)
        $up = $conexion->prepare("
            UPDATE PRESTAMO
            SET PRESTAMO_estado = ?
            WHERE idPRESTAMO = ?
              AND USUARIO_idUSUARIO = ?
              AND PRESTAMO_estado = 'Pendiente'
        ");
        $up->bind_param("sii", $nuevoEstado, $idPrestamo, $usuarioId);
        $up->execute();
        $afectadas = $up->affected_rows;
        $up->close();

        if ($afectadas > 0) {
            if ($nuevoEstado === 'Aprobado') {
                $msgFinal = "[PRESTAMO:$idPrestamo] ✅ APROBADO. Monto a acreditar: $" . number_format($montoApr, 2, ',', '.');
                if (!existeNotificacionExacta($conexion, $usuarioId, $msgFinal)) {
                    crearNotificacion($conexion, $usuarioId, $msgFinal, "Préstamo", "Pendiente");
                }
            } else {
                $msgFinal = "[PRESTAMO:$idPrestamo] ❌ RECHAZADO. Podés intentar nuevamente con otro monto/cuotas.";
                if (!existeNotificacionExacta($conexion, $usuarioId, $msgFinal)) {
                    crearNotificacion($conexion, $usuarioId, $msgFinal, "Préstamo", "Pendiente");
                }
            }
        }
    }
}

// ✅ Se ejecuta en cada carga/refresh de prestamos.php
autoResolverPrestamosUsuario($conexion, $usuarioId, 1, 200000);


// Procesar solicitud de préstamo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar'])) {
    $cantidad_cuotas   = intval($_POST['cantidad_cuotas'] ?? 0);
    $monto_solicitado  = floatval($_POST['monto_solicitado'] ?? 0);
    $tipo_interes      = trim($_POST['tipo_interes'] ?? '');
    $tipo_movimiento   = "Crédito";
    $estado            = 'Pendiente';

    if ($cantidad_cuotas < 1 || $monto_solicitado <= 0 || $tipo_interes === '') {
        $mensaje = "❌ Completá correctamente la cantidad de cuotas, el monto y el tipo de interés.";
    } else {
        // 4% de costos, se acredita el 96% al aprobarse
        $monto_aprobado = $monto_solicitado * 0.96;

        $sql_insert = "
            INSERT INTO PRESTAMO 
                (PRESTAMO_cantidad_cuotas,
                 PRESTAMO_estado,
                 PRESTAMO_monto_solicitado,
                 PRESTAMO_monto_aprobado,
                 PRESTAMO_tipo_de_interes,
                 PRESTAMO_tipo_de_movimiento_prestamo,
                 USUARIO_idUSUARIO)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conexion->prepare($sql_insert);
        $stmt->bind_param(
            "isddssi",
            $cantidad_cuotas,
            $estado,
            $monto_solicitado,
            $monto_aprobado,
            $tipo_interes,
            $tipo_movimiento,
            $usuarioId
        );

        if ($stmt->execute()) {
            // ✅ NUEVO: notificación base “Solicitud registrada” para este préstamo
            $idPrestamo = (int)$conexion->insert_id;
            $msgSolicitud = "[PRESTAMO:$idPrestamo] Solicitud registrada";
            if (!existeNotificacionExacta($conexion, $usuarioId, $msgSolicitud)) {
                crearNotificacion($conexion, $usuarioId, $msgSolicitud, "Préstamo", "Pendiente");
            }

            $mensaje = "✅ Solicitud registrada. Monto solicitado: $" .
                number_format($monto_solicitado, 2, ',', '.') .
                " · Monto a acreditar (estimado): $" .
                number_format($monto_aprobado, 2, ',', '.');
        } else {
            $mensaje = "❌ Ocurrió un error al registrar el préstamo. Intentá nuevamente.";
        }
        $stmt->close();
    }
}

// Obtener préstamos del usuario
$sql_prestamos = "
    SELECT 
        idPRESTAMO,
        PRESTAMO_cantidad_cuotas,
        PRESTAMO_estado,
        PRESTAMO_monto_solicitado,
        PRESTAMO_monto_aprobado,
        PRESTAMO_tipo_de_interes,
        PRESTAMO_tipo_de_movimiento_prestamo
    FROM PRESTAMO 
    WHERE USUARIO_idUSUARIO = ?
    ORDER BY idPRESTAMO DESC
";
$stmt = $conexion->prepare($sql_prestamos);
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <title>Préstamos - BALKFOX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap (solo CSS + JS, sin tocar tu HTML) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
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
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
      transition: background-color 0.25s ease, border-color 0.25s ease;
      animation: sidebarEnter .45s ease-out;
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
    .sidebar-logo-img { width: 100%; height: 100%; object-fit: cover; display: block; }

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
      transition: background-color 0.25s ease, border-color 0.25s ease;
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
      max-width: 1100px;
      width: 100%;
      margin: 0 auto;
    }

    .page-header { margin-bottom: 1.4rem; animation: fadeUp .45s ease-out; }
    .page-header h2 { margin: 0; font-size: 1.4rem; color: var(--text-main); }
    .page-header p  { margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--text-muted); }

    .cards-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.2fr);
      gap: 1.2rem;
      margin-bottom: 1.6rem;
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

    /* ✅ Color SOLO en encabezado de tarjeta */
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.7rem;
      padding: .55rem .75rem;
      border-radius: .7rem;
      border: 1px solid transparent;
    }
    .cards-grid .card:nth-child(1) .card-header{
      background: linear-gradient(90deg, rgba(37,99,235,.20), rgba(37,99,235,.06), transparent);
      border-color: rgba(37,99,235,.35);
    }
    .cards-grid .card:nth-child(2) .card-header{
      background: linear-gradient(90deg, rgba(96,165,255,.20), rgba(96,165,255,.06), transparent);
      border-color: rgba(96,165,255,.35);
    }

    .card-title {
      font-size: 0.95rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.09em;
      font-weight: 700;
    }

    .card-body { font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; }

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

    .form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0.9rem; margin-top: 0.4rem; }

    .input-group label {
      display: block;
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.25rem;
      font-weight: 500;
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
      font-weight: 600;
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
      margin-bottom: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
      animation: fadeUp .45s ease-out;
    }
    .alert-success { background: #ecfdf3; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    html[data-theme="dark"] .alert-success { background: rgba(22,163,74,0.15); color: #bbf7d0; border-color: rgba(34,197,94,0.4); }
    html[data-theme="dark"] .alert-error   { background: rgba(239,68,68,0.15); color: #fecaca; border-color: rgba(248,113,113,0.4); }

    .table-wrapper {
      overflow-x: auto;
      border-radius: 0.9rem;
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      box-shadow: var(--shadow-strong);
      transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
      animation: fadeUp .45s ease-out;
    }

    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; color: var(--text-main); }
    thead { background: #f3f4f6; }
    html[data-theme="dark"] thead { background: #020617; }

    th, td {
      padding: 0.75rem 0.9rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
      white-space: nowrap;
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

    .badge-estado {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.15rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid transparent;
    }

    .badge-estado--pendiente { background: rgba(245,158,11,0.08); color: #92400e; border-color: rgba(245,158,11,0.4); }
    .badge-estado--aprobado  { background: rgba(22,163,74,0.08); color: #166534; border-color: rgba(22,163,74,0.4); }
    .badge-estado--rechazado { background: rgba(239,68,68,0.08); color: #b91c1c; border-color: rgba(239,68,68,0.4); }

    html[data-theme="dark"] .badge-estado--pendiente { background: rgba(245,158,11,0.15); color: #fed7aa; border-color: rgba(252,211,77,0.5); }
    html[data-theme="dark"] .badge-estado--aprobado  { background: rgba(22,163,74,0.15); color: #bbf7d0; border-color: rgba(34,197,94,0.5); }
    html[data-theme="dark"] .badge-estado--rechazado { background: rgba(239,68,68,0.15); color: #fecaca; border-color: rgba(248,113,113,0.5); }

    .badge-interes {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.15rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
    }
    html[data-theme="dark"] .badge-interes { border-color: #1f2937; background: #020617; }

    .empty-state { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.9rem; animation: fadeUp .45s ease-out; }

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
    }

    /* ✅ FIX DEFINITIVO: blanco solo en sidebar + topbar (por si Bootstrap pisa) */
    .sidebar, .sidebar * { color: #fff !important; }
    .topbar, .topbar * { color: #fff !important; }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <!-- ✅ reemplazo SOLO logo "B" por imagen -->
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
        <li><a href="prestamos.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-hand-holding-dollar"></i> Préstamos</a></li>
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
        <h1>Préstamos</h1>
        <p>Solicitá y consultá el estado de tus préstamos personales.</p>
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
        <h2><i class="fa-solid fa-hand-holding-dollar"></i> Solicitar nuevo préstamo</h2>
        <p>Completá el formulario para generar una nueva solicitud.</p>
      </div>

      <?php if ($mensaje): ?>
        <div class="alert <?php echo strpos($mensaje, '✅') !== false ? 'alert-success' : 'alert-error'; ?>">
          <i class="fa-solid <?php echo strpos($mensaje, '✅') !== false ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" style="margin-top:2px;"></i>
          <div><?php echo htmlspecialchars($mensaje); ?></div>
        </div>
      <?php endif; ?>

      <section class="cards-grid">
        <!-- Info -->
        <article class="card">
          <div class="card-header">
            <div class="card-title">Información</div>
          </div>
          <div class="card-body">
            <p>Los préstamos solicitados pasan por un proceso de evaluación y pueden tener distintos estados:</p>
            <ul style="margin: 0.5rem 0 0 1rem; padding: 0; color: var(--text-muted);">
              <li><strong>Pendiente:</strong> en revisión por la entidad.</li>
              <li><strong>Aprobado:</strong> acreditado en tu cuenta según las condiciones.</li>
              <li><strong>Rechazado:</strong> no cumple con los requisitos.</li>
            </ul>
            <p style="margin-top:0.8rem;">
              El <strong>monto aprobado</strong> considera un 4% en costos administrativos sobre el monto solicitado.
            </p>
            <span class="pill" style="margin-top:0.5rem;">
              <i class="fa-solid fa-circle-info"></i>
              Asegurate de que las cuotas sean acordes a tu capacidad de pago.
            </span>
          </div>
        </article>

        <!-- Formulario -->
        <article class="card">
          <div class="card-header">
            <div class="card-title">Datos de la solicitud</div>
          </div>
          <form method="POST" action="prestamos.php" autocomplete="off">
            <div class="form-grid">
              <div class="input-group">
                <label for="cantidad_cuotas">Cantidad de cuotas</label>
                <input
                  type="number"
                  name="cantidad_cuotas"
                  id="cantidad_cuotas"
                  min="1"
                  required
                />
              </div>

              <div class="input-group">
                <label for="monto_solicitado">Monto solicitado</label>
                <input
                  type="number"
                  name="monto_solicitado"
                  id="monto_solicitado"
                  step="0.01"
                  min="0.01"
                  required
                />
              </div>

              <div class="input-group">
                <label for="tipo_interes">Tipo de interés</label>
                <select name="tipo_interes" id="tipo_interes" required>
                  <option value="">Seleccionar tipo de interés</option>
                  <option value="Fijo">Fijo</option>
                  <option value="Variable">Variable</option>
                </select>
              </div>
            </div>

            <button type="submit" name="agregar" class="btn-primary">
              <i class="fa-solid fa-paper-plane"></i>
              Enviar solicitud
            </button>
          </form>
        </article>
      </section>

      <div class="page-header" style="margin-top:1.6rem;">
        <h2><i class="fa-solid fa-list-check"></i> Préstamos solicitados</h2>
        <p>Revisá el estado y los montos de tus solicitudes anteriores.</p>
      </div>

      <?php if ($result->num_rows > 0): ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Cuotas</th>
                <th>Estado</th>
                <th>Solicitado</th>
                <th>Aprobado</th>
                <th>Interés</th>
                <th>Movimiento</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($prestamo = $result->fetch_assoc()): ?>
                <?php
                  $estado = $prestamo['PRESTAMO_estado'];
                  $estadoLower = mb_strtolower($estado, 'UTF-8');

                  if ($estadoLower === 'aprobado') {
                      $claseEstado = 'badge-estado--aprobado';
                      $iconEstado  = 'fa-circle-check';
                  } elseif ($estadoLower === 'rechazado') {
                      $claseEstado = 'badge-estado--rechazado';
                      $iconEstado  = 'fa-circle-xmark';
                  } else {
                      $claseEstado = 'badge-estado--pendiente';
                      $iconEstado  = 'fa-clock';
                  }
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($prestamo['idPRESTAMO']); ?></td>
                  <td><?php echo htmlspecialchars($prestamo['PRESTAMO_cantidad_cuotas']); ?></td>
                  <td>
                    <span class="badge-estado <?php echo $claseEstado; ?>">
                      <i class="fa-solid <?php echo $iconEstado; ?>"></i>
                      <?php echo htmlspecialchars($estado); ?>
                    </span>
                  </td>
                  <td>
                    $<?php echo number_format($prestamo['PRESTAMO_monto_solicitado'], 2, ',', '.'); ?>
                  </td>
                  <td>
                    $<?php echo number_format($prestamo['PRESTAMO_monto_aprobado'], 2, ',', '.'); ?>
                  </td>
                  <td>
                    <span class="badge-interes">
                      <i class="fa-solid fa-percent"></i>
                      <?php echo htmlspecialchars($prestamo['PRESTAMO_tipo_de_interes']); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($prestamo['PRESTAMO_tipo_de_movimiento_prestamo']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="empty-state">
          Aún no registraste solicitudes de préstamo.
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
