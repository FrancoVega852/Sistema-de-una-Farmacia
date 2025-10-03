<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$conn = new Conexion();
$db   = $conn->conexion;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: compras_listar.php"); exit(); }

// Datos de la orden de compra
$st = $db->prepare("SELECT oc.id, oc.fecha, oc.total, oc.estado, oc.observaciones,
                           pr.razonSocial AS proveedor, u.nombre AS usuario
                    FROM OrdenCompra oc
                    INNER JOIN Proveedor pr ON pr.id = oc.proveedor_id
                    INNER JOIN Usuario u ON u.id = oc.usuario_id
                    WHERE oc.id=?");
$st->bind_param("i",$id); 
$st->execute();
$oc = $st->get_result()->fetch_assoc();
if(!$oc){ header("Location: compras_listar.php"); exit(); }

// Detalle de productos
$st = $db->prepare("SELECT d.cantidad, d.precio_unitario, d.subtotal,
                           p.nombre AS producto, p.presentacion, p.precio AS precio_base,
                           c.nombre AS categoria,
                           l.numero_lote, l.fecha_vencimiento
                    FROM DetalleOrdenCompra d
                    INNER JOIN Producto p ON p.id=d.producto_id
                    LEFT JOIN Categoria c ON c.id=p.categoria_id
                    LEFT JOIN Lote l ON l.producto_id=p.id AND l.cantidad_inicial=d.cantidad
                    WHERE d.orden_id=?");
$st->bind_param("i",$id); 
$st->execute();
$det = $st->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Compra #<?= $oc['id'] ?> - Farvec</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{ --verde:#008f4c; --osc:#006837; --borde:#e5e7eb; --txt:#1f2937; --muted:#6b7280 }
body{margin:0;font-family:Segoe UI,system-ui,Arial;background:#f5f7f8;color:var(--txt)}
.top{display:flex;align-items:center;gap:12px;background:#fff;padding:14px 18px;border-bottom:1px solid var(--borde)}
.top h1{font-size:20px;color:var(--osc);margin:0;display:flex;align-items:center;gap:8px}
.back{background:var(--osc);color:#fff;border:0;border-radius:10px;padding:10px 14px;cursor:pointer}
.wrap{max-width:1200px;margin:20px auto;padding:0 14px;display:flex;flex-direction:column;gap:16px}
.card{background:#fff;border:1px solid var(--borde);border-radius:14px;box-shadow:0 6px 16px rgba(0,0,0,.06);padding:14px}
.card h3{margin:0 0 10px 0;color:var(--osc)}
.table{width:100%;border-collapse:collapse;margin-top:8px}
.table th,.table td{padding:10px;border-bottom:1px solid var(--borde);text-align:left;font-size:14px}
.table th{background:#f0fdf4;color:#064e3b}
.total{font-size:18px;font-weight:800;text-align:right;margin-top:10px}
.actions{display:flex;gap:10px;margin-top:10px}
.btn{padding:10px 12px;border-radius:10px;border:1px solid var(--borde);cursor:pointer}
.btn.primary{background:var(--verde);color:#fff;border-color:var(--verde)}
.btn.print{background:#2563eb;color:#fff;border-color:#2563eb}
</style>
</head>
<body>

<div class="top">
  <a href="compras_listar.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Volver</button></a>
  <h1><i class="fa-solid fa-truck"></i> Orden de Compra #<?= $oc['id'] ?></h1>
</div>

<div class="wrap">

  <!-- Datos generales -->
  <div class="card">
    <h3>Información de la Orden</h3>
    <p><strong>Proveedor:</strong> <?= htmlspecialchars($oc['proveedor']) ?></p>
    <p><strong>Usuario:</strong> <?= htmlspecialchars($oc['usuario']) ?></p>
    <p><strong>Fecha:</strong> <?= htmlspecialchars($oc['fecha']) ?></p>
    <p><strong>Estado:</strong> <?= htmlspecialchars($oc['estado']) ?></p>
    <?php if(!empty($oc['observaciones'])): ?>
      <p><strong>Observaciones:</strong> <?= htmlspecialchars($oc['observaciones']) ?></p>
    <?php endif; ?>
  </div>

  <!-- Detalle -->
  <div class="card">
    <h3>Productos Comprados</h3>
    <table class="table">
      <thead>
        <tr>
          <th>Producto</th>
          <th>Presentación</th>
          <th>Categoría</th>
          <th>Precio Base</th>
          <th>Costo Unitario</th>
          <th>Cantidad</th>
          <th>Subtotal</th>
          <th>Lote</th>
          <th>Vencimiento</th>
        </tr>
      </thead>
      <tbody>
      <?php while($d=$det->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($d['producto']) ?></td>
          <td><?= htmlspecialchars($d['presentacion'] ?? '-') ?></td>
          <td><?= htmlspecialchars($d['categoria'] ?? '-') ?></td>
          <td>$<?= number_format($d['precio_base'],2,',','.') ?></td>
          <td>$<?= number_format($d['precio_unitario'],2,',','.') ?></td>
          <td><?= (int)$d['cantidad'] ?></td>
          <td>$<?= number_format($d['subtotal'],2,',','.') ?></td>
          <td><?= $d['numero_lote'] ? htmlspecialchars($d['numero_lote']) : '-' ?></td>
          <td><?= $d['fecha_vencimiento'] ? htmlspecialchars($d['fecha_vencimiento']) : '-' ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <div class="total">TOTAL: $<?= number_format($oc['total'],2,',','.') ?></div>
  </div>

  <!-- Acciones -->
  <div class="actions">
    <button class="btn print" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir</button>
    <button class="btn primary" onclick="location.href='compras_listar.php'"><i class="fa-solid fa-list"></i> Ir al listado</button>
  </div>

</div>

</body>
</html>
