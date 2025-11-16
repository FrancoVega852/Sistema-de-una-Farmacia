<?php
session_start();
require_once 'Conexion.php';
require_once 'Venta.php';

if (!isset($_SESSION['usuario_id'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'  => false,
            'msg' => 'Sesión expirada. Iniciá sesión nuevamente.'
        ]);
    } else {
        echo "<div style='padding:20px;font-family:Arial'>
                <h3 style='color:#b93142'>Error: sesión expirada</h3>
                <p>Inicie sesión nuevamente para registrar ventas.</p>
              </div>";
    }
    exit();
}

/* ==========================================
   FUNCIONES AUXILIARES PARA RESPUESTA JSON
   ========================================== */
function json_response(array $data, int $code = 200){
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/* =============================
   VALIDAR QUE VENGA INFORMACIÓN
   ============================= */
if (!isset($_POST['productos']) || empty($_POST['productos'])) {
    if ($isAjax) {
        json_response([
            'ok'  => false,
            'msg' => 'Carrito vacío. No se enviaron productos para la venta.'
        ], 400);
    } else {
        exit("<div style='padding:20px;font-family:Arial'>
                <h3 style='color:#b93142'>Error: Carrito vacío</h3>
                <p>No se enviaron productos para procesar la venta.</p>
              </div>");
    }
}

$cliente_id = isset($_POST['cliente_id']) && $_POST['cliente_id'] !== ''
              ? (int)$_POST['cliente_id']
              : null;

/* =============================
   ARMAR ITEMS DESDE EL POST
   ============================= */
$items = [];
foreach ($_POST['productos'] as $pid => $data) {
    if (!isset($data['id'], $data['cantidad'])) {
        continue;
    }

    $id   = (int)$data['id'];
    $cant = (int)$data['cantidad'];

    if ($id > 0 && $cant > 0) {
        $items[] = [
            'id'   => $id,
            'cant' => $cant
        ];
    }
}

if (empty($items)) {
    if ($isAjax) {
        json_response([
            'ok'  => false,
            'msg' => 'Carrito inválido. No se pudo interpretar los productos de la venta.'
        ], 400);
    } else {
        exit("<div style='padding:20px;font-family:Arial'>
                <h3 style='color:#b93142'>Error: Carrito inválido</h3>
                <p>No se pudo interpretar los productos de la venta.</p>
              </div>");
    }
}

/* =============================
   PROCESAR LA VENTA
   ============================= */
try {
    $conn       = new Conexion();
    $db         = $conn->conexion;
    $ventaModel = new Venta($db);

    // Guardar venta
    $venta_id = $ventaModel->registrarVenta(
        $cliente_id,
        $_SESSION['usuario_id'],
        $items
    );

    // ========================================
    //  ✔ REGISTRAR INGRESO EN MOVIMIENTO
    // ========================================
    $st = $db->prepare("SELECT total FROM Venta WHERE id=? LIMIT 1");
    $st->bind_param("i", $venta_id);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $totalVenta = (float)($res['total'] ?? 0);

    if ($totalVenta > 0) {
        $mov = $db->prepare("
            INSERT INTO Movimiento (tipo, monto, descripcion, origen, ref_id)
            VALUES ('Ingreso', ?, 'Venta realizada', 'Venta', ?)
        ");
        $mov->bind_param("di", $totalVenta, $venta_id);
        $mov->execute();
    }

    // ==============================
    // RESPUESTA SEGÚN TIPO DE LLAMADA
    // ==============================
    if ($isAjax) {
        json_response([
            'ok'       => true,
            'venta_id' => (int)$venta_id
        ], 200);

    } else {
        header("Location: ventas_listar.php?ok=1&id=" . $venta_id);
        exit();
    }

} catch (Exception $e) {

    $msgError = $e->getMessage();

    if ($isAjax) {
        json_response([
            'ok'  => false,
            'msg' => 'Error al registrar la venta: ' . $msgError
        ], 500);
    } else {
        echo "<div style='padding:20px;font-family:Arial'>
                <h3 style='color:#b93142'>Error al registrar la venta</h3>
                <p>" . htmlspecialchars($msgError) . "</p>
                <a href='ventas_listar.php' style='color:#00794f;font-weight:bold'>Volver al listado</a>
              </div>";
        exit();
    }
}
