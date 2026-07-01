<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario_id'])) {
    echo "No est├ís logueado.";
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$personaId = $_SESSION['persona_id'] ?? null;

$mensaje = "";
$mostrarFormulario = false;

// Procesar POST (guardar o actualizar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni       = $_POST['dni'];
    $domicilio = $_POST['domicilio'];
    $telefono  = $_POST['telefono'];

    if ($personaId) {
        $sql = "UPDATE PERSONA SET PERSONA_dni=?, PERSONA_domicilio=?, PERSONA_telefono=? WHERE idPERSONA=? AND USUARIO_idUSUARIO=?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssii", $dni, $domicilio, $telefono, $personaId, $usuarioId);
    } else {
        $sql = "INSERT INTO PERSONA (PERSONA_dni, PERSONA_domicilio, PERSONA_telefono, USUARIO_idUSUARIO) VALUES (?, ?, ?, ?)";
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
        $mostrarFormulario = false;
    } else {
        $mensaje = "Error al guardar datos: " . $stmt->error;
        $mostrarFormulario = true;
    }
} else {
    // Si vino GET con ?editar=1 mostramos formulario para editar
    if (isset($_GET['editar']) && $_GET['editar'] == 1) {
        $mostrarFormulario = true;
    }
}

// Datos para mostrar
$dni       = $_SESSION['persona_dni'] ?? "";
$domicilio = $_SESSION['persona_domicilio'] ?? "";
$telefono  = $_SESSION['persona_telefono'] ?? "";

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Mis datos personales</title>
    <style>
        .tarjeta {
            border: 1px solid #ccc;
            padding: 15px 20px;
            max-width: 400px;
            margin: 20px auto;
            border-radius: 8px;
            background: #f9f9f9;
            position: relative;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        .btn-editar {
            position: absolute;
            right: 20px;
            top: 20px;
            padding: 5px 10px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-editar:hover {
            background-color: #004d99;
        }
        form input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 6px 0 12px 0;
            box-sizing: border-box;
        }
        form button {
            padding: 10px 15px;
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        form button:hover {
            background-color: #004d99;
        }
        .mensaje {
            max-width: 400px;
            margin: 10px auto;
            color: green;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2 style="text-align:center;">Mis datos personales</h2>

<?php if ($mensaje): ?>
    <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>
<?php endif; ?>

<?php if ($mostrarFormulario): ?>
    <div class="tarjeta">
        <form method="POST" action="persona.php">
            <label>DNI:</label>
            <input type="text" name="dni" value="<?php echo htmlspecialchars($dni); ?>" required>

            <label>Domicilio:</label>
            <input type="text" name="domicilio" value="<?php echo htmlspecialchars($domicilio); ?>">

            <label>Tel├®fono:</label>
            <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>">

            <button type="submit">Guardar cambios</button>
        </form>
    </div>

<?php else: ?>

    <div class="tarjeta">
        <p><strong>DNI:</strong> <?php echo htmlspecialchars($dni); ?></p>
        <p><strong>Domicilio:</strong> <?php echo htmlspecialchars($domicilio); ?></p>
        <p><strong>Tel├®fono:</strong> <?php echo htmlspecialchars($telefono); ?></p>

        <a href="persona.php?editar=1"><button class="btn-editar">Editar</button></a>
    </div>

<?php endif; ?>

<br>
<a href="menu.php" style="display:block; text-align:center;">Ô¼à Volver al men├║</a>

</body>
</html>
