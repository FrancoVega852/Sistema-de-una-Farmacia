<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

/* Helpers */
function normalize_card($value): string {
    // deja SOLO dígitos
    return preg_replace('/\D+/', '', (string) $value);
}
function format_card($value): string {
    $digits = normalize_card($value);
    if ($digits === '') return '';
    return trim(chunk_split($digits, 4, ' '));
}

/* Obtener idPERSONA del usuario */
$sql = "SELECT idPERSONA FROM PERSONA WHERE USUARIO_idUSUARIO = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
if ($fila = $result->fetch_assoc()) {
    $id_persona = (int) $fila['idPERSONA'];
} else {
    echo "No se pudo encontrar la persona asociada al usuario.";
    exit();
}
$stmt->close();

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

/* Variables para editar */
$editando = false;
$numero_edit = "";
$tipo_edit = "";
$estado_edit = "";
$fecha_vencimiento_edit = "";
$cvv_edit = ""; // Variable para CVV
$mensaje = "";
$mensaje_es_error = false;

/* Eliminar tarjeta (normalizado, funciona aunque en BD haya espacios) */
if (isset($_GET['eliminar'])) {
    $numero_eliminar_digits = normalize_card($_GET['eliminar']);

    if ($numero_eliminar_digits !== '') {
        $sql = "DELETE FROM TARJETA
                WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("si", $numero_eliminar_digits, $id_persona);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: tarjetas.php");
    exit();
}

/* Cargar datos para edición */
if (isset($_GET['editar'])) {
    $numero_edit_digits = normalize_card($_GET['editar']);

    if ($numero_edit_digits === '') {
        header("Location: tarjetas.php");
        exit();
    }

    $sql = "SELECT numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento, cvv
            FROM TARJETA
            WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $numero_edit_digits, $id_persona);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $fila = $resultado->fetch_assoc();
        $editando = true;
        $numero_edit = format_card($fila['numero_tarjeta']);
        $tipo_edit = $fila['tipo_tarjeta'] ?? '';
        $estado_edit = $fila['estado'] ?? 'Activa';
        $fecha_vencimiento_edit = $fila['fecha_vencimiento'] ?? '';
        $cvv_edit = $fila['cvv'] ?? '';
    } else {
        $stmt->close();
        header("Location: tarjetas.php");
        exit();
    }
    $stmt->close();
}

/* Procesar alta / actualización */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $numero_raw = $_POST['numero'] ?? '';
    $numero_digits = normalize_card($numero_raw);

    $tipo = trim($_POST['tipo'] ?? '');
    $estado = trim($_POST['estado'] ?? 'Activa');
    $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
    
    // Captura de CVV
    $cvv_raw = $_POST['cvv'] ?? '';
    $cvv_digits = normalize_card($cvv_raw);

    $estados_validos = ['Activa', 'Bloqueada'];

    // Validaciones
    if ($numero_digits === '' || $tipo === '' || $fecha_vencimiento === '' || $cvv_digits === '') {
        $mensaje = "❌ Completá todos los campos del formulario (incluyendo CVV).";
        $mensaje_es_error = true;
    } elseif (!ctype_digit($numero_digits) || strlen($numero_digits) < 13 || strlen($numero_digits) > 19) {
        $mensaje = "❌ El número de tarjeta debe contener solo números y tener entre 13 y 19 dígitos.";
        $mensaje_es_error = true;
    } elseif (!ctype_digit($cvv_digits) || strlen($cvv_digits) < 3 || strlen($cvv_digits) > 4) {
        $mensaje = "❌ El CVV debe contener solo números y tener 3 o 4 dígitos.";
        $mensaje_es_error = true;
    } elseif (!in_array($estado, $estados_validos, true)) {
        $mensaje = "❌ Estado inválido. Solo se permite: Activa o Bloqueada.";
        $mensaje_es_error = true;
    } else {
        if (isset($_POST['editar'])) {
            $sql = "UPDATE TARJETA
                    SET tipo_tarjeta = ?, estado = ?, fecha_vencimiento = ?, cvv = ?
                    WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("sssssi", $tipo, $estado, $fecha_vencimiento, $cvv_digits, $numero_digits, $id_persona);

            if (!$stmt->execute()) {
                $mensaje = "❌ Error al actualizar tarjeta.";
                $mensaje_es_error = true;
            } else {
                $stmt->close();
                header("Location: tarjetas.php");
                exit();
            }
            $stmt->close();
        } else {
            // Evitar duplicados aunque una tarjeta exista con espacios
            $check = $conexion->prepare("SELECT 1 FROM TARJETA WHERE REPLACE(numero_tarjeta, ' ', '') = ? AND PERSONA_idPERSONA = ? LIMIT 1");
            $check->bind_param("si", $numero_digits, $id_persona);
            $check->execute();
            $existe = $check->get_result()->num_rows > 0;
            $check->close();

            if ($existe) {
                $mensaje = "❌ Ya existe una tarjeta con ese número.";
                $mensaje_es_error = true;
            } else {
                $sql = "INSERT INTO TARJETA
                        (numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento, cvv, PERSONA_idPERSONA)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sssssi", $numero_digits, $tipo, $estado, $fecha_vencimiento, $cvv_digits, $id_persona);

                if (!$stmt->execute()) {
                    $mensaje = "❌ Error al agregar tarjeta.";
                    $mensaje_es_error = true;
                } else {
                    $stmt->close();
                    header("Location: tarjetas.php");
                    exit();
                }
                $stmt->close();
            }
        }
    }
}

/* Consultar tarjetas del usuario */
$sql = "SELECT numero_tarjeta, tipo_tarjeta, estado, fecha_vencimiento
        FROM TARJETA
        WHERE PERSONA_idPERSONA = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_persona);
$stmt->execute();
$resultado = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>Tarjetas - BALKFOX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --bg-body: #f3f4f6;
      --bg-main: #f9fafb;

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

    @keyframes sidebarEnter { from {opacity:0; transform: translateX(-16px);} to {opacity:1; transform: translateX(0);} }
    @keyframes topbarEnter  { from {opacity:0; transform: translateY(-10px);} to {opacity:1; transform: translateY(0);} }
    @keyframes fadeUp       { from {opacity:0; transform: translateY(10px);} to {opacity:1; transform: translateY(0);} }
    @keyframes menuItemEnter{ from {opacity:0; transform: translateX(-10px);} to {opacity:1; transform: translateX(0);} }

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

    .sidebar-header { display: flex; align-items: center; gap: 0.8rem; padding: 0 0.4rem; }

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

    .sidebar-subtitle { font-size: 0.7rem; color: #ffffff; opacity: .9; }

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

    .sidebar-link i { width: 18px; text-align: center; font-size: 0.95rem; color: #ffffff; }

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

    .main { flex: 1; display: flex; flex-direction: column; background: var(--bg-main); transition: background-color 0.25s ease; }

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

    .topbar-user { display: flex; align-items: center; gap: 0.75rem; font-size: 0.85rem; color: #ffffff; }

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
    .topbar-user-actions a:hover { background: rgba(255,255,255,0.16); border-color: #ffffff; }

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
    .theme-toggle:hover { background: rgba(255,255,255,0.16); border-color: #ffffff; transform: translateY(-1px); }

    .content { padding: 1.6rem; max-width: 1100px; width: 100%; margin: 0 auto; }

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
    html[data-theme="dark"] td { border-bottom-color: #111827; color: #e5e7eb; }

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

    .badge-estado--activa   { background: rgba(22,163,74,0.08); color: #166534; border-color: rgba(22,163,74,0.4); }
    .badge-estado--inactiva { background: rgba(148,163,184,0.15); color: #0f172a; border-color: rgba(148,163,184,0.6); }
    .badge-estado--bloqueada{ background: rgba(239,68,68,0.08); color: #b91c1c; border-color: rgba(239,68,68,0.4); }

    html[data-theme="dark"] .badge-estado--activa   { background: rgba(22,163,74,0.15); color: #bbf7d0; border-color: rgba(34,197,94,0.5); }
    html[data-theme="dark"] .badge-estado--inactiva { background: rgba(148,163,184,0.22); color: #e5e7eb; border-color: rgba(148,163,184,0.7); }
    html[data-theme="dark"] .badge-estado--bloqueada{ background: rgba(239,68,68,0.15); color: #fecaca; border-color: rgba(248,113,113,0.5); }

    .badge-tipo {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.15rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid #e5e7eb;
      background: #f9fafb;
    }
    html[data-theme="dark"] .badge-tipo { border-color: #1f2937; background: #020617; }

    .acciones { display: flex; gap: 0.4rem; }

    .btn-icon {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      border: none;
      background: transparent;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      transition: 0.15s background-color ease, 0.15s transform ease;
    }

    .btn-icon--edit { color: #2563eb; }
    .btn-icon--delete { color: #dc2626; }
    .btn-icon:hover { background: rgba(148,163,184,0.15); transform: translateY(-1px); }

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

    /* ✅ FIX DEFINITIVO */
    .sidebar, .sidebar * { color: #fff !important; }
    .topbar, .topbar * { color: #fff !important; }

    /* Toast arriba a la derecha */
    .toast-container { z-index: 9999; }
  </style>
</head>
<body>

  <div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="balkfoxToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="balkfoxToastBody"></div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
      </div>
    </div>
  </div>

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
        <li><a href="tarjetas.php" class="sidebar-link sidebar-link--primary"><i class="fa-solid fa-credit-card"></i> Tarjetas</a></li>
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

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <h1>Tarjetas</h1>
        <p>Gestioná tus tarjetas de débito y crédito asociadas a tu cuenta.</p>
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

    <main class="content">
      <div class="page-header">
        <h2><i class="fa-solid fa-credit-card"></i> <?php echo $editando ? "Editar tarjeta" : "Agregar nueva tarjeta"; ?></h2>
        <p>Completá los datos para vincular una tarjeta o actualizar sus datos.</p>
      </div>

      <section class="cards-grid">
        <article class="card">
          <div class="card-header">
            <div class="card-title">Información</div>
          </div>
          <div class="card-body">
            <p>Desde esta sección podés:</p>
            <ul style="margin: 0.5rem 0 0 1rem; padding: 0; color: var(--text-muted);">
              <li>Registrar nuevas tarjetas de débito o crédito.</li>
              <li>Actualizar estado (Activa, Bloqueada).</li>
              <li>Administrar fecha de vencimiento.</li>
            </ul>
            <p style="margin-top:0.8rem;">
              Recordá mantener actualizados los datos para evitar rechazos en compras o débitos automáticos.
            </p>
            <span class="pill" style="margin-top:0.5rem;">
              <i class="fa-solid fa-shield-halved"></i>
              No compartas el número de tarjeta ni el código de seguridad (CVV).
            </span>
          </div>
        </article>

        <article class="card">
          <div class="card-header">
            <div class="card-title">Datos de la tarjeta</div>
          </div>
          <form method="POST" action="tarjetas.php" autocomplete="off" id="formTarjetas">
            <div class="form-grid">
              <div class="input-group">
                <label for="numero">Número de tarjeta</label>
                <?php if ($editando): ?>
                  <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($numero_edit); ?>" readonly>
                <?php else: ?>
                  <input
                    type="text"
                    id="numero"
                    name="numero"
                    placeholder="XXXX XXXX XXXX XXXX"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                  >
                <?php endif; ?>
              </div>

              <div class="input-group">
                <label for="tipo">Tipo de tarjeta</label>
                <select id="tipo" name="tipo" required>
                  <option value="" disabled <?php echo $tipo_edit == "" ? "selected" : ""; ?>>Seleccionar tipo</option>
                  <option value="Débito"  <?php echo $tipo_edit == "Débito"  ? "selected" : ""; ?>>Débito</option>
                  <option value="Crédito" <?php echo $tipo_edit == "Crédito" ? "selected" : ""; ?>>Crédito</option>
                </select>
              </div>

              <div class="input-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                  <option value="Activa" <?php echo ($estado_edit === "Activa" || $estado_edit === "" ) ? "selected" : ""; ?>>Activa</option>
                  <option value="Bloqueada" <?php echo ($estado_edit === "Bloqueada") ? "selected" : ""; ?>>Bloqueada</option>
                </select>
              </div>

              <div class="input-group">
                <label for="fecha_vencimiento">Fecha de vencimiento</label>
                <input type="date" id="fecha_vencimiento" name="fecha_vencimiento"
                       value="<?php echo htmlspecialchars($fecha_vencimiento_edit); ?>" required>
              </div>

              <div class="input-group">
                <label for="cvv">Código de seguridad (CVV)</label>
                <input 
                  type="text" 
                  id="cvv" 
                  name="cvv" 
                  placeholder="Ej: 123" 
                  inputmode="numeric" 
                  maxlength="4" 
                  autocomplete="off"
                  value="<?php echo htmlspecialchars($cvv_edit); ?>" 
                  required>
              </div>

            </div>

            <?php if ($editando): ?>
              <button type="submit" name="editar" class="btn-primary">
                <i class="fa-solid fa-floppy-disk"></i>
                Guardar cambios
              </button>
            <?php else: ?>
              <button type="submit" class="btn-primary">
                <i class="fa-solid fa-plus"></i>
                Agregar tarjeta
              </button>
            <?php endif; ?>
          </form>
        </article>
      </section>

      <div class="page-header" style="margin-top:1.6rem;">
        <h2><i class="fa-solid fa-list"></i> Mis tarjetas</h2>
        <p>Listado de las tarjetas asociadas a tu perfil.</p>
      </div>

      <?php if ($resultado->num_rows > 0): ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Número</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Vencimiento</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($fila = $resultado->fetch_assoc()): ?>
                <?php
                  $estado = $fila['estado'] ?? '';
                  $estadoLower = strtolower($estado);

                  if ($estadoLower === 'bloqueada') {
                      $claseEstado = 'badge-estado--bloqueada';
                  } elseif ($estadoLower === 'inactiva') {
                      $claseEstado = 'badge-estado--inactiva';
                  } else {
                      $claseEstado = 'badge-estado--activa';
                  }

                  $numero_formateado = format_card($fila['numero_tarjeta'] ?? '');
                  $numero_param = normalize_card($fila['numero_tarjeta'] ?? ''); 
                ?>
                <tr>
                  <td><?php echo htmlspecialchars($numero_formateado); ?></td>
                  <td>
                    <span class="badge-tipo">
                      <i class="fa-solid fa-credit-card"></i>
                      <?php echo htmlspecialchars($fila['tipo_tarjeta']); ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge-estado <?php echo $claseEstado; ?>">
                      <i class="fa-solid fa-circle"></i>
                      <?php echo htmlspecialchars($estado); ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($fila['fecha_vencimiento']); ?></td>
                  <td>
                    <div class="acciones">
                      <a href="tarjetas.php?editar=<?php echo urlencode($numero_param); ?>"
                         class="btn-icon btn-icon--edit" title="Editar tarjeta">
                        <i class="fa-solid fa-pen"></i>
                      </a>
                      <a href="tarjetas.php?eliminar=<?php echo urlencode($numero_param); ?>"
                         class="btn-icon btn-icon--delete"
                         onclick="return confirm('¿Seguro que querés eliminar esta tarjeta?');"
                         title="Eliminar tarjeta">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="empty-state">
          Todavía no tenés tarjetas cargadas. Agregá una desde el formulario superior.
        </p>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // ====== Toast helper ======
    function showToast(message, type = 'error') {
      const toastEl = document.getElementById('balkfoxToast');
      const toastBody = document.getElementById('balkfoxToastBody');

      // estilo
      toastEl.classList.remove('text-bg-danger', 'text-bg-success', 'text-bg-warning');
      toastEl.classList.add(type === 'success' ? 'text-bg-success' : 'text-bg-danger');

      const closeBtn = toastEl.querySelector('.btn-close');
      closeBtn.classList.remove('btn-close-white');
      closeBtn.classList.add('btn-close-white');

      toastBody.textContent = message;

      const t = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3200 });
      t.show();
    }

    // ====== Mostrar toast si el backend tiró error ======
    <?php if (!empty($mensaje) && $mensaje_es_error): ?>
      showToast(<?php echo json_encode($mensaje); ?>, 'error');
    <?php endif; ?>

    // ====== Validaciones Frontend ======
    (function () {
      const form = document.getElementById('formTarjetas');
      const numero = document.getElementById('numero');
      const estado = document.getElementById('estado');
      const cvv = document.getElementById('cvv');

      let lastToastAt = 0;

      if (numero && !numero.hasAttribute('readonly')) {

        function formatCardDigits(digits) {
          // agrupa de a 4 para ver "lindo"
          return digits.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
        }

        numero.addEventListener('input', function () {
          const raw = numero.value;
          const digits = raw.replace(/\D/g, '');

          // si el user intentó meter letras/símbolos, avisamos (con cooldown)
          if (raw !== formatCardDigits(raw)) {
            const now = Date.now();
            if (now - lastToastAt > 900) {
              showToast('❌ En el número de tarjeta solo se permiten números.', 'error');
              lastToastAt = now;
            }
          }

          // limitar a 19 dígitos máximo
          const limited = digits.slice(0, 19);
          numero.value = formatCardDigits(limited);
        });
      }

      if (cvv) {
        cvv.addEventListener('input', function () {
          const raw = cvv.value;
          const digits = raw.replace(/\D/g, '');

          if (raw !== digits) {
            const now = Date.now();
            if (now - lastToastAt > 900) {
              showToast('❌ El CVV solo permite números.', 'error');
              lastToastAt = now;
            }
          }
          cvv.value = digits.slice(0, 4);
        });
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          
          if (numero && !numero.hasAttribute('readonly')) {
             const digits = numero.value.replace(/\D/g, '');
             if (digits.length < 13 || digits.length > 19) {
               e.preventDefault();
               showToast('❌ El número de tarjeta debe tener entre 13 y 19 dígitos.', 'error');
               return;
             }
             numero.value = digits; 
          }

          if (cvv) {
             const cvvDigits = cvv.value.replace(/\D/g, '');
             if (cvvDigits.length < 3 || cvvDigits.length > 4) {
               e.preventDefault();
               showToast('❌ El CVV debe tener 3 o 4 dígitos.', 'error');
               return;
             }
             cvv.value = cvvDigits; 
          }

          const est = (estado?.value || '').trim();
          if (!['Activa', 'Bloqueada'].includes(est)) {
            e.preventDefault();
            showToast("❌ Estado inválido. Solo se permite: Activa o Bloqueada.", 'error');
          }
        });
      }
    })();
  </script>

</body>
</html>