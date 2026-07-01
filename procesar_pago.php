<?php
require_once "conexion.php";
session_start();

// Verificamos si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Obtenemos los datos del formulario
$tipo_servicio = $_POST['tipo_servicio'] ?? '';
$monto = floatval($_POST['monto'] ?? 0);
$cuenta_id = intval($_POST['cuenta_id'] ?? 0); // idCUENTA_BANCARIA
$usuario_id = $_SESSION['usuario_id'];

// Validación básica
if ($tipo_servicio === '' || $monto <= 0 || $cuenta_id <= 0) {
    die("Datos inválidos.");
}

// Obtenemos el número de cuenta correspondiente a la cuenta bancaria
$sql_datos_cuenta = $conexion->prepare("SELECT CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA WHERE idCUENTA_BANCARIA = ?");
$sql_datos_cuenta->bind_param("i", $cuenta_id);
$sql_datos_cuenta->execute();
$sql_datos_cuenta->bind_result($numero_de_cuenta, $saldo_actual);
if (!$sql_datos_cuenta->fetch()) {
    die("Cuenta no encontrada.");
}
$sql_datos_cuenta->close();

// Validamos saldo
if ($saldo_actual < $monto) {
    header("Location: pagos_y_servicios.php?error=saldo");
    exit();
}



// 1. Insertamos en la tabla PAGO_DE_SERVICIOS
$fecha_actual = date("Y-m-d H:i:s");
$insert_pago = $conexion->prepare("INSERT INTO PAGO_DE_SERVICIOS (PAGO_DE_SERVICIOS_idcuenta, PAGO_DE_SERVICIOS_monto, PAGO_DE_SERVICIOS_fecha_de_pago, PAGO_DE_SERVICIOS_tipo_de_servicio, CUENTA_BANCARIA_idCUENTA_BANCARIA) VALUES (?, ?, ?, ?, ?)");
$insert_pago->bind_param("idssi", $numero_de_cuenta, $monto, $fecha_actual, $tipo_servicio, $cuenta_id);
$insert_pago->execute();
$insert_pago->close();

// 2. Actualizamos el saldo
$nuevo_saldo = $saldo_actual - $monto;
$update_saldo = $conexion->prepare("UPDATE CUENTA_BANCARIA SET CUENTA_BANCARIA_saldo = ? WHERE idCUENTA_BANCARIA = ?");
$update_saldo->bind_param("di", $nuevo_saldo, $cuenta_id);
$update_saldo->execute();
$update_saldo->close();

// 3. Registramos la transacción
$descripcion = "Pago de servicio: $tipo_servicio";
$estado = "Confirmado";
$moneda = "ARS";
$tipo_movimiento = "Pago de Servicio";
$insert_transaccion = $conexion->prepare("INSERT INTO TRANSACCIONES (TRANSACCIONES_monto, TRANSACCIONES_moneda, TRANSACCIONES_fecha_y_hora, TRANSACCIONES_cuenta_origen, TRANSACCIONES_descripcion, TRANSACCIONES_tipo_de_movimiento, TRANSACCIONES_estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
$insert_transaccion->bind_param("dssisss", $monto, $moneda, $fecha_actual, $numero_de_cuenta, $descripcion, $tipo_movimiento, $estado);
$insert_transaccion->execute();
$id_transaccion = $insert_transaccion->insert_id;
$insert_transaccion->close();

// 4. Vinculamos la transacción con la cuenta
$insert_cb_tx = $conexion->prepare("INSERT INTO CUENTA_BANCARIA_TRANSACCIONES (CUENTA_BANCARIA_idCUENTA_BANCARIA, TRANSACCIONES_idTRANSACCIONES) VALUES (?, ?)");
$insert_cb_tx->bind_param("ii", $cuenta_id, $id_transaccion);
$insert_cb_tx->execute();
$insert_cb_tx->close();

// 5. Insertamos una notificación
$mensaje = "Se realizó un pago de servicio: $tipo_servicio por \$$monto.";
$tipo_notificacion = "Pago";
$estado_notificacion = "No Leída";

// Obtenemos el último LOGIN activo del usuario
$sql_login = $conexion->prepare("SELECT idLOGIN FROM LOGIN WHERE LOGIN_idUsuario = ? AND LOGIN_estado = 'Activo' ORDER BY LOGIN_fecha_y_hora_de_acceso DESC LIMIT 1");
$sql_login->bind_param("i", $usuario_id);
$sql_login->execute();
$sql_login->bind_result($id_login);
$sql_login->fetch();
$sql_login->close();

$insert_notificacion = $conexion->prepare("INSERT INTO NOTIFICACIONES (NOTIFICACIONES_mensaje, NOTIFICACIONES_fecha_y_hora, NOTIFICACIONES_tipo_de_notificaciones, NOTIFICACIONES_estado, USUARIO_idUSUARIO, LOGIN_idLOGIN) VALUES (?, ?, ?, ?, ?, ?)");
$insert_notificacion->bind_param("ssssii", $mensaje, $fecha_actual, $tipo_notificacion, $estado_notificacion, $usuario_id, $id_login);
$insert_notificacion->execute();
$insert_notificacion->close();

// Redireccionamos o mostramos éxito
// Al final del procesamiento, armar mensaje con datos
$mensaje = urlencode("Pago realizado: $tipo_servicio por \$$monto. Saldo restante: \$$nuevo_saldo");

// Redireccionar con mensaje
header("Location: panel.php?mensaje=$mensaje");
exit();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pago de Servicios</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f1f5f9;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 480px;
      margin: 50px auto;
      background: #ffffff;
      padding: 30px 40px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    }
    h2 {
      text-align: center;
      color: #1e293b;
      margin-bottom: 24px;
    }
    label {
      display: block;
      margin-top: 16px;
      margin-bottom: 6px;
      color: #475569;
      font-weight: 600;
    }
    select, input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.2s ease;
    }
    select:focus, input:focus {
      border-color: #3b82f6;
      outline: none;
    }
    button {
      margin-top: 24px;
      width: 100%;
      background-color: #3b82f6;
      color: #ffffff;
      padding: 12px;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }
    button:hover {
      background-color: #2563eb;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Pago de Servicios</h2>
  <form method="POST" action="procesar_pago.php" onsubmit="return validarFormulario()">
    <label for="tipo_servicio">Tipo de Servicio</label>
    <select name="tipo_servicio" id="tipo_servicio" required>
      <option value="">Seleccione un servicio</option>
      <option value="Luz">Luz</option>
      <option value="Agua">Agua</option>
      <option value="Internet">Internet</option>
      <option value="Gas">Gas</option>
      <option value="Telefonía">Telefonía</option>
    </select>

    <label for="monto">Monto ($)</label>
    <input type="number" step="0.01" name="monto" id="monto" min="1" required />

    <label for="cuenta_id">Cuenta Origen</label>
    <select name="cuenta_id" id="cuenta_id" required>
      <!-- Estos valores deben ser generados dinámicamente con PHP -->
      <?php
        session_start();
        require_once "conexion.php";
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        if ($usuario_id) {
          $sql = $conexion->prepare("SELECT idCUENTA_BANCARIA, CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_idUsuario = ?");
          $sql->bind_param("i", $usuario_id);
          $sql->execute();
          $sql->bind_result($id, $nro_cuenta, $saldo);
          while ($sql->fetch()) {
            echo "<option value='$id'>Cuenta $nro_cuenta - Saldo: \$$saldo</option>";
          }
          $sql->close();
        }
      ?>
    </select>

    <button type="submit">Realizar Pago</button>
  </form>
</div>

<script>
function validarFormulario() {
  const monto = parseFloat(document.getElementById("monto").value);
  if (isNaN(monto) || monto <= 0) {
    alert("Ingrese un monto válido.");
    return false;
  }
  return true;
}
</script>

</body>
</html>
