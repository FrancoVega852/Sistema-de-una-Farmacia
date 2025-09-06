<?php
session_start();
include 'Conexion.php';
include 'Producto.php';

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit();
}

$rol = $_SESSION["usuario_rol"];
$conn = new Conexion();
$conexion = $conn->conexion;

$productoObj = new Producto($conexion);
$productos = $productoObj->obtenerProductosConLotes();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Stock y Lotes - Farvec</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
    }
    body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--gris); padding: 20px; }
    h1 { color: var(--verde-oscuro); }
    .btn {
      display: inline-block; padding: 8px 12px; border-radius: 6px;
      text-decoration: none; font-weight: bold; transition: 0.3s; font-size: 0.9rem;
    }
    .btn:hover { transform: scale(1.05); }
    .btn-add { background: var(--acento); color: var(--blanco); margin-bottom: 20px; }
    .btn-add:hover { background: #d94c3c; }
    .btn-edit { background: var(--verde-oscuro); color: var(--blanco); margin-left: 5px; }
    .btn-edit:hover { background: #004d2b; }
    .btn-del { background: #b71c1c; color: var(--blanco); margin-left: 5px; }
    .btn-del:hover { background: #7f0000; }
    .btn-menu {
      background: #006837;
      color: white;
      padding: 10px 15px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      margin-bottom: 20px;
      display: inline-block;
    }
    .btn-menu:hover { background: #009f4c; transform: scale(1.05); }
    table {
      width: 100%; border-collapse: collapse; background: var(--blanco);
      border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: var(--verde); color: var(--blanco); }
    tr:hover { background: #f1f1f1; }
    .alerta { color: var(--acento); font-weight: bold; }
  </style>
  <script>
    function confirmarEliminacion(id, nombre) {
      Swal.fire({
        title: '¿Eliminar producto?',
        text: "Se eliminará '" + nombre + "' y todos sus lotes.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        showClass: { popup: 'animate__animated animate__zoomIn' },
        hideClass: { popup: 'animate__animated animate__zoomOut' }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = "stock_eliminar.php?id=" + id;
        }
      });
    }
  </script>
</head>
<body>
  <a href="Menu.php" class="btn-menu">⬅️ Volver al Menú</a>
  <h1>📦 Gestión de Stock y Lotes</h1>

  <?php if ($rol === 'Administrador'): ?>
    <a href="stock_agregar.php" class="btn btn-add"><i class="fa-solid fa-plus"></i> Agregar Producto/Lote</a>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Producto</th>
        <th>Categoría</th>
        <th>Precio</th>
        <th>Stock Actual</th>
        <th>Stock Mínimo</th>
        <th>Lote</th>
        <th>Vencimiento</th>
        <th>Cantidad Lote</th>
        <?php if ($rol === 'Administrador' || $rol === 'Farmaceutico'): ?>
          <th>Acciones</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php while ($fila = $productos->fetch_assoc()): ?>
        <?php
          $alertaVencimiento = "";
          if (!empty($fila['fecha_vencimiento'])) {
              $hoy = new DateTime();
              $fechaVto = new DateTime($fila['fecha_vencimiento']);
              $intervalo = $hoy->diff($fechaVto)->days;

              if ($fechaVto < $hoy) {
                  $alertaVencimiento = "alerta";
              } elseif ($intervalo <= 30) {
                  $alertaVencimiento = "alerta";
              }
          }
        ?>
        <tr>
          <td><?= htmlspecialchars($fila['id']) ?></td>
          <td><?= htmlspecialchars($fila['nombre']) ?></td>
          <td><?= htmlspecialchars($fila['categoria'] ?? '-') ?></td>
          <td>$<?= number_format($fila['precio'], 2) ?></td>
          <td class="<?= ($fila['stock_actual'] <= $fila['stock_minimo']) ? 'alerta' : '' ?>">
            <?= htmlspecialchars($fila['stock_actual']) ?>
          </td>
          <td><?= htmlspecialchars($fila['stock_minimo']) ?></td>
          <td><?= htmlspecialchars($fila['numero_lote'] ?? '-') ?></td>
          <td class="<?= $alertaVencimiento ?>">
            <?= htmlspecialchars($fila['fecha_vencimiento'] ?? '-') ?>
          </td>
          <td><?= htmlspecialchars($fila['cantidad_actual'] ?? '-') ?></td>
          <?php if ($rol === 'Administrador' || $rol === 'Farmaceutico'): ?>
            <td>
              <a href="Historial.php?producto_id=<?= $fila['id'] ?>" class="btn btn-add">📊 Historial</a>
              <?php if ($rol === 'Administrador'): ?>
                <a href="stock_editar.php?id=<?= $fila['id'] ?>" class="btn btn-edit">✏️ Editar</a>
                <a href="javascript:void(0);" onclick="confirmarEliminacion(<?= $fila['id'] ?>, '<?= htmlspecialchars($fila['nombre']) ?>')" class="btn btn-del">🗑️ Eliminar</a>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
