<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION["usuario_id"];

// Obtener cuentas activas del usuario
$sql = "SELECT idCUENTA_BANCARIA, CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA 
        WHERE USUARIO_idUSUARIO = ? AND CUENTA_BANCARIA_estado = 'Activa'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado_cuentas = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pagos y Servicios</title>
</head>
<body>
    <h2>Pagar un servicio</h2>
    <form method="POST" action="procesar_pago.php">
        <label>Tipo de servicio:</label><br>
        <input type="text" name="tipo_servicio" required><br><br>

        <label>Monto a pagar:</label><br>
        <input type="number" step="0.01" name="monto" required><br><br>

        <label>Seleccionar cuenta:</label><br>
        <select name="cuenta_id" required>
            <?php while ($cuenta = $resultado_cuentas->fetch_assoc()) { ?>
                <option value="<?php echo $cuenta['idCUENTA_BANCARIA']; ?>">
                    <?php echo $cuenta['CUENTA_BANCARIA_numero_de_cuenta']; ?> - Saldo: $<?php echo $cuenta['CUENTA_BANCARIA_saldo']; ?>
                </option>
            <?php } ?>
        </select><br><br>

        <input type="submit" value="Generar factura y pagar">
    </form>
    <br><a href="menu.php">Volver al menú</a>
</body>
</html>