<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$conn = new Conexion();
$db   = $conn->conexion;

// Listado de compras
$sql = "SELECT oc.id, oc.fecha, oc.total, oc.estado,
               pr.razonSocial AS proveedor, u.nombre AS usuario
        FROM OrdenCompra oc
        INNER JOIN Proveedor pr ON pr.id=oc.proveedor_id
        INNER JOIN Usuario u ON u.id=oc.usuario_id
        ORDER BY oc.fecha DESC";
$res = $db->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Listado de Compras - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{ --verde:#008f4c; --osc:#006837; --borde:#e5e7eb; --txt:#1f2937; --muted:#6b7280 }
body{margin:0;font-family:Segoe UI,system-ui,Arial;background:#f5f7f8;color:var(--txt)}
.top{display:flex;align-items:center;gap:12px;background:#fff;padding:14px 18px;border-bottom:1px solid var(--borde)}
.top h1{font-size:20px;color:var(--osc);margin:0;display:flex;align-items:center;gap:8px}
.back{background:var(--osc);color:#fff;border:0;border-radius:10px;padding:10px 14px;cursor:pointer}
.wrap{max-width:1200px;margin:20px auto;padding:0 14px}
.card{background:#fff;border:1px solid var(--borde);border-radius:14px;box-shadow:0 6px 16px rgba(0,0,0,.06);padding:14px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid var(--borde);font-size:14px;text-align:left}
.table th{background:#f0fdf4;color:#064e3b}
.actions{display:flex;gap:6px}
.btn{padding:8px 10px;border-radius:8px;border:1px solid var(--borde);cursor:pointer;font-size:13px}
.btn.primary{background:var(--verde);border-color:var(--verde);color:#fff}
.btn.view{background:#2563eb;color:#fff;border-color:#2563eb}
.btn:hover{opacity:.9}
.topbar-actions{margin-left:auto}
</style>
</head>
<body>

<div class="top">
  <a href="Menu.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Menú</button></a>
  <h1><i class="fa-solid fa-truck"></i> Listado de Compras</h1>
  <div class="topbar-actions">
    <button class="btn primary" onclick="location.href='compras.php'"><i class="fa-solid fa-plus"></i> Nueva Compra</button>
  </div>
</div>

<div class="wrap">
  <div class="card">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Proveedor</th>
          <th>Usuario</th>
          <th>Total</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if($res && $res->num_rows): while($row=$res->fetch_assoc()): ?>
          <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['proveedor']) ?></td>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td>$<?= number_format($row['total'],2,',','.') ?></td>
            <td><?= htmlspecialchars($row['fecha']) ?></td>
            <td><?= htmlspecialchars($row['estado']) ?></td>
            <td>
              <div class="actions">
                <button class="btn view" onclick="location.href='compras_ver.php?id=<?= $row['id'] ?>'">
                  <i class="fa-solid fa-eye"></i> Ver
                </button>
              </div>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:20px">No hay compras registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
