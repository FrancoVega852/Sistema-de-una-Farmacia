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
$st = $db->prepare("
    SELECT 
        d.cantidad,
        d.precio_unitario,
        d.subtotal,

        p.nombre AS producto,
        p.precio AS precio_base,

        c.nombre AS categoria,

        -- Todas las presentaciones del producto
        GROUP_CONCAT(pr.nombre SEPARATOR ', ') AS presentaciones,

        l.numero_lote,
        l.fecha_vencimiento

    FROM DetalleOrdenCompra d
    INNER JOIN Producto p 
        ON p.id = d.producto_id

    LEFT JOIN Categoria c 
        ON c.id = p.categoria_id

    LEFT JOIN PresentacionProducto pp 
        ON pp.producto_id = p.id

    LEFT JOIN Presentacion pr 
        ON pr.id = pp.presentacion_id

    LEFT JOIN Lote l 
        ON l.producto_id = p.id

    WHERE d.orden_id = ?

    GROUP BY d.id
");
$st->bind_param("i", $id);
$st->execute();
$det = $st->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Compra #<?= $oc['id'] ?> - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
  --brand:#0ea5a6;
  --brand-dark:#0b8384;
  --bg1:#0f172a;
  --bg2:#0b1222;
  --text:#e5e7eb;
  --muted:#94a3b8;
  --accent:#38bdf8;
  --ok:#22c55e;
  --warn:#fbbf24;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:'Inter',system-ui,Arial;
  color:var(--text);
  background:linear-gradient(180deg,#0b1020 0%,#0f172a 100%);
  overflow-x:hidden;
}

/* Fondo animado */
body::before,body::after{
  content:'';position:fixed;inset:auto;z-index:-1;
  width:480px;height:480px;border-radius:50%;
  filter:blur(80px);opacity:.35;
  background:radial-gradient(circle at 40% 40%,#0ea5a666 0%,transparent 70%);
  animation:float1 25s ease-in-out infinite;
}
body::after{
  right:-120px;bottom:-60px;
  background:radial-gradient(circle at 60% 60%,#38bdf855 0%,transparent 70%);
  animation:float2 30s ease-in-out infinite;
}
@keyframes float1{0%,100%{transform:translate(0,0)}50%{transform:translate(25px,-20px)}}
@keyframes float2{0%,100%{transform:translate(0,0)}50%{transform:translate(-25px,20px)}}

/* Header */
.top{
  display:flex;align-items:center;gap:14px;
  padding:16px 20px;
  position:sticky;top:0;
  backdrop-filter:blur(8px);
  background:linear-gradient(180deg,rgba(11,16,32,.8) 0%,rgba(11,16,32,.4) 100%);
  border-bottom:1px solid rgba(56,189,248,.15);
  box-shadow:0 8px 25px rgba(0,0,0,.25);
}
.back{
  background:linear-gradient(135deg,var(--brand-dark),var(--brand));
  color:#fff;border:0;border-radius:10px;
  padding:10px 14px;cursor:pointer;font-weight:600;
  transition:.25s ease;box-shadow:0 6px 16px rgba(14,165,166,.25);
}
.back:hover{transform:translateY(-1px);box-shadow:0 10px 25px rgba(14,165,166,.4)}
.top h1{margin:0;font-size:20px;font-weight:800;display:flex;align-items:center;gap:10px}
.top h1 i{color:#67e8f9}

/* Container */
.wrap{max-width:1100px;margin:24px auto;padding:0 18px;display:flex;flex-direction:column;gap:20px}

/* Card */
.card{
  background:rgba(11,19,36,.6);
  border:1px solid rgba(56,189,248,.15);
  border-radius:16px;
  box-shadow:0 20px 60px rgba(0,0,0,.3);
  padding:20px;
  backdrop-filter:blur(10px);
  animation:fadeUp .6s ease both;
}
.card h3{margin:0 0 14px;font-size:18px;color:#67e8f9}
.card p{margin:6px 0;font-size:15px;color:#e2e8f0}

/* Tabla */
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{padding:12px;text-align:left;font-size:14px}
.table th{
  background:#031225;color:#67e8f9;
  text-transform:uppercase;letter-spacing:.3px;
  font-size:12px;
}
.table td{border-bottom:1px solid rgba(56,189,248,.1)}
.table tbody tr{transition:background .2s ease}
.table tbody tr:hover{background:rgba(14,165,166,.1)}

/* Total */
.total{font-size:20px;font-weight:800;text-align:right;margin-top:14px;color:#38bdf8}

/* Buttons */
.actions{display:flex;gap:12px;margin-top:14px}
.btn{
  padding:10px 14px;border-radius:10px;border:0;cursor:pointer;
  font-weight:600;font-size:14px;transition:.25s ease;
}
.btn.print{
  background:linear-gradient(135deg,#38bdf8,#0ea5a6);
  color:#002b2f;box-shadow:0 8px 25px rgba(56,189,248,.25);
}
.btn.primary{
  background:linear-gradient(135deg,#22d3ee,#0ea5a6);
  color:#002c2e;box-shadow:0 8px 25px rgba(34,211,238,.25);
}
.btn:hover{transform:translateY(-1px);opacity:.95}

/* Animaciones */
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
</style>
</head>
<div id="toast-ok" style="position:fixed;right:16px;bottom:16px;background:#00a86b;color:#fff;
  padding:12px 16px;border-radius:12px;font-weight:800;display:none;box-shadow:0 10px 26px rgba(0,0,0,.18)">
  Compra registrada con éxito
</div>
<script>
  (function(){
    const ok = new URLSearchParams(location.search).get('ok');
    if(ok==='1'){
      const t=document.getElementById('toast-ok');
      t.style.display='block';
      setTimeout(()=>t.style.display='none',2000);
    }
  })();
</script>

<body>

<div class="top">
  <a href="compras_listar.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Volver</button></a>
  <h1><i class="fa-solid fa-file-invoice-dollar"></i> Orden de Compra #<?= $oc['id'] ?></h1>
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
          <td><?= htmlspecialchars($d['presentaciones'] ?? '-') ?></td>
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
