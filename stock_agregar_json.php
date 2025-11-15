<?php
session_start();
header("Content-Type: application/json");

require_once "Conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(["ok" => false, "msg" => "Sesión no iniciada"]);
    exit;
}

if (!in_array($_SESSION["usuario_rol"], ["Administrador", "Farmaceutico"])) {
    echo json_encode(["ok" => false, "msg" => "Permisos insuficientes"]);
    exit;
}

$conn = new Conexion();
$db   = $conn->conexion;

/* =====================================================
   CAPTURA DE VARIABLES
===================================================== */

$nombre           = trim($_POST["nombre"] ?? "");
$precio           = floatval($_POST["precio"] ?? 0);
$stock_minimo     = intval($_POST["stock_minimo"] ?? 0);
$requiere_receta  = intval($_POST["requiere_receta"] ?? 0);
$categoria_id     = intval($_POST["categoria_id"] ?? 0);
$presentacion_id  = intval($_POST["presentacion_id"] ?? 0);

$numero_lote      = trim($_POST["numero_lote"] ?? "");
$fecha_venc       = $_POST["fecha_vencimiento"] ?? "";
$cant_inicial     = intval($_POST["cantidad_inicial"] ?? 0);

$proveedor_id     = intval($_POST["proveedor_id"] ?? 0);
$precio_costo     = floatval($_POST["precio_costo"] ?? 0);
$codigo_proveedor = trim($_POST["codigo_proveedor"] ?? "");

/* =====================================================
   VALIDACIONES BÁSICAS
===================================================== */

if ($nombre === "" || $precio <= 0 || $cant_inicial <= 0) {
    echo json_encode(["ok" => false, "msg" => "Datos incompletos o incorrectos"]);
    exit;
}
if ($categoria_id <= 0 || $presentacion_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Faltan datos de categoría o presentación"]);
    exit;
}
if ($proveedor_id <= 0 || $precio_costo <= 0) {
    echo json_encode(["ok" => false, "msg" => "Debe seleccionar proveedor y precio de costo"]);
    exit;
}
if ($numero_lote === "" || $fecha_venc === "") {
    echo json_encode(["ok" => false, "msg" => "Datos de lote incompletos"]);
    exit;
}

/* =====================================================
   VALIDAR FECHA DE VENCIMIENTO (mínimo 90 días)
===================================================== */
try {
    $hoy = new DateTime();
    $vto = new DateTime($fecha_venc);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "msg" => "Fecha de vencimiento inválida"]);
    exit;
}

$diff = $hoy->diff($vto)->days;

if ($vto < $hoy || $diff < 90) {
    echo json_encode(["ok" => false, "msg" => "La fecha de vencimiento debe ser mayor a 90 días"]);
    exit;
}

/* =====================================================
   ¿EL PRODUCTO YA EXISTE? (match exacto por nombre)
===================================================== */

$stmtExist = $db->prepare("SELECT id FROM Producto WHERE nombre = ? LIMIT 1");
$stmtExist->bind_param("s", $nombre);
$stmtExist->execute();
$resExist = $stmtExist->get_result();

$producto_id = null;
$nuevo_producto = false;

if ($row = $resExist->fetch_assoc()) {
    $producto_id = intval($row["id"]);
} else {
    $nuevo_producto = true;
}

/* =====================================================
   INICIO TRANSACCIÓN
===================================================== */

$db->begin_transaction();

try {

    /* =====================================================
       1) CREAR PRODUCTO SI NO EXISTE
    ====================================================== */
    if ($nuevo_producto) {

        $stmt = $db->prepare("
            INSERT INTO Producto 
            (nombre, precio, stock_actual, stock_minimo, requiere_receta, categoria_id) 
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "sdiisi",
            $nombre,
            $precio,
            $cant_inicial,   // stock inicial
            $stock_minimo,
            $requiere_receta,
            $categoria_id
        );
        $stmt->execute();

        $producto_id = $db->insert_id;

        // Registrar presentación principal
        $stmtP = $db->prepare("
            INSERT INTO PresentacionProducto (producto_id, presentacion_id)
            VALUES (?,?)
        ");
        $stmtP->bind_param("ii", $producto_id, $presentacion_id);
        $stmtP->execute();

    } else {
        // Producto ya existe → se actualizan algunos datos básicos (opcional)
        $stmtUpd = $db->prepare("
            UPDATE Producto
            SET precio = ?, stock_minimo = ?, requiere_receta = ?, categoria_id = ?
            WHERE id = ?
        ");
        $stmtUpd->bind_param("diiii", $precio, $stock_minimo, $requiere_receta, $categoria_id, $producto_id);
        $stmtUpd->execute();
    }

    /* =====================================================
       2) REGISTRAR LOTE
    ====================================================== */
    $stmtL = $db->prepare("
        INSERT INTO Lote 
        (producto_id, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual)
        VALUES (?,?,?,?,?)
    ");
    $stmtL->bind_param(
        "issii",
        $producto_id,
        $numero_lote,
        $fecha_venc,
        $cant_inicial,
        $cant_inicial
    );
    $stmtL->execute();

    /* =====================================================
       3) ACTUALIZAR STOCK ACTUAL
    ====================================================== */
    $stmtStock = $db->prepare("
        UPDATE Producto SET stock_actual = stock_actual + ?
        WHERE id = ?
    ");
    $stmtStock->bind_param("ii", $cant_inicial, $producto_id);
    $stmtStock->execute();

    /* =====================================================
       4) REGISTRAR EN PROVEEDORPRODUCTO
          (si ya existe, actualizamos costo y código)
    ====================================================== */
    $stmtCheckProv = $db->prepare("
        SELECT id FROM ProveedorProducto
        WHERE proveedor_id = ? AND producto_id = ?
    ");
    $stmtCheckProv->bind_param("ii", $proveedor_id, $producto_id);
    $stmtCheckProv->execute();
    $resProv = $stmtCheckProv->get_result();

    if ($rowProv = $resProv->fetch_assoc()) {
        $idProvProd = (int)$rowProv['id'];
        $stmtUpdProv = $db->prepare("
            UPDATE ProveedorProducto
            SET precio_costo = ?, codigo_proveedor = ?
            WHERE id = ?
        ");
        $stmtUpdProv->bind_param("dsi", $precio_costo, $codigo_proveedor, $idProvProd);
        $stmtUpdProv->execute();
    } else {
        $stmtAddProv = $db->prepare("
            INSERT INTO ProveedorProducto (proveedor_id, producto_id, precio_costo, codigo_proveedor)
            VALUES (?,?,?,?)
        ");
        $stmtAddProv->bind_param("iids", $proveedor_id, $producto_id, $precio_costo, $codigo_proveedor);
        $stmtAddProv->execute();
    }

    /* =====================================================
       5) REGISTRAR HISTORIAL DE STOCK
    ====================================================== */
    $detalle = "Carga de lote {$numero_lote}";
    $mov = "Alta";
    $usuario_id = (int)$_SESSION["usuario_id"];

    $stmtHist = $db->prepare("
        INSERT INTO HistorialStock (producto_id, tipo, cantidad, detalle, usuario_id)
        VALUES (?,?,?,?,?)
    ");
    $stmtHist->bind_param("isisi", $producto_id, $mov, $cant_inicial, $detalle, $usuario_id);
    $stmtHist->execute();

    /* =====================================================
       FINALIZAR TRANSACCIÓN
    ====================================================== */
    $db->commit();

    echo json_encode([
        "ok" => true,
        "msg" => $nuevo_producto
            ? "Producto creado y lote registrado correctamente."
            : "Lote agregado al producto existente correctamente."
    ]);
    exit;

} catch (Exception $e) {

    $db->rollback();
    echo json_encode([
        "ok"  => false,
        "msg" => "Error en la transacción: " . $e->getMessage()
    ]);
    exit;
}
?>
