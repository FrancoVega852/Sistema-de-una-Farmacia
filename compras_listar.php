<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$conn = new Conexion();
$db   = $conn->conexion;

// Listado de compras (SIN CAMBIOS EN LA LÓGICA)
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
:root{
  --verde:#008f4c; --osc:#006837; --borde:#e5e7eb; --txt:#1f2937; --muted:#6b7280;
  --bg1:#f7fafc; --bg2:#eefdf5; --card:#ffffff; --azul:#2563eb; --sombra:0 12px 30px rgba(0,0,0,.08);
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:Segoe UI, system-ui, -apple-system, sans-serif; color:var(--txt);
  background: radial-gradient(1200px 600px at -10% -10%, rgba(0,143,76,.10) 0%, transparent 60%),
              radial-gradient(900px 700px at 110% 10%, rgba(37,99,235,.12) 0%, transparent 55%),
              linear-gradient(180deg, var(--bg1), var(--bg2));
}

/* Barra superior */
.top{
  position:sticky; top:0; z-index:10;
  display:flex; align-items:center; gap:12px;
  background:rgba(255,255,255,.85); backdrop-filter: blur(10px);
  padding:14px 18px; border-bottom:1px solid var(--borde);
}
.back{
  background:var(--osc); color:#fff; border:0; border-radius:12px; padding:10px 14px; cursor:pointer;
  display:inline-flex; align-items:center; gap:8px; box-shadow:var(--sombra); transition:transform .15s ease;
}
.back:hover{ transform:translateY(-1px) }
.top h1{font-size:22px; color:var(--osc); margin:0; display:flex; gap:10px; align-items:center}
.topbar-actions{margin-left:auto; display:flex; gap:8px}
.btn{
  padding:10px 12px; border-radius:12px; border:1px solid var(--borde); cursor:pointer; font-size:14px;
  background:#fff; transition:all .2s ease; display:inline-flex; align-items:center; gap:8px;
}
.btn.primary{ background:var(--verde); border-color:var(--verde); color:#fff; box-shadow:var(--sombra) }
.btn.view{ background:var(--azul); border-color:var(--azul); color:#fff }
.btn:hover{ transform:translateY(-1px); box-shadow:0 10px 20px rgba(0,0,0,.08) }

/* Contenedor */
.wrap{ max-width:1200px; margin:22px auto; padding:0 14px; }

/* Tarjeta tabla */
.card{
  background:var(--card); border:1px solid var(--borde); border-radius:16px;
  box-shadow:var(--sombra); overflow:hidden; animation:fadeIn .5s ease both;
}
.table{ width:100%; border-collapse:collapse }
.table th,.table td{ padding:12px 12px; border-bottom:1px solid var(--borde); font-size:14px; text-align:left }
.table th{
  background:linear-gradient(180deg,#ebfff4,#dff7ea); color:#064e3b; position:sticky; top:0; z-index:1;
}
tbody tr{ background:#fff; transition:transform .15s ease, box-shadow .15s ease, background .2s ease; }
tbody tr:nth-child(even){ background:#fbfdff }
tbody tr:hover{ transform:scale(1.005); background:#f0fdf4; box-shadow:0 6px 18px rgba(0,0,0,.06) }

/* Estado pill */
.pill{
  display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600;
  border:1px solid #e5e7eb; background:#fff;
}
.pill.ok{ color:#065f46; background:#ecfdf5; border-color:#bbf7d0 }
.pill.pending{ color:#92400e; background:#fff7ed; border-color:#fed7aa }
.pill.cancel{ color:#991b1b; background:#fee2e2; border-color:#fecaca }

/* Animaciones */
@keyframes fadeIn{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
.row-anim{ opacity:0; transform:translateY(8px); }
.row-anim.show{ opacity:1; transform:translateY(0); transition: opacity .45s ease, transform .45s ease }

/* Footer info vacío */
.empty{ text-align:center; color:var(--muted); padding:24px }
.currency{ white-space:nowrap }
</style>
</head>
<body>

<div class="top">
  <a href="Menu.php"><button class="back"><i class="fa-solid fa-arrow-left"></i> Menú</button></a>
  <h1><i class="fa-solid fa-truck"></i> Listado de Compras</h1>
  <div class="topbar-actions">
    <button class="btn primary" onclick="location.href='compras.php'">
      <i class="fa-solid fa-plus"></i> Nueva Compra
    </button>
  </div>
</div>

<div class="wrap">
  <div class="card">
    <table class="table" aria-label="Listado de órdenes de compra">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Proveedor</th>
          <th>Usuario</th>
          <th>Estado</th>
          <th style="width:140px">Fecha</th>
          <th style="width:150px">Total</th>
          <th style="width:140px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if($res && $res->num_rows): 
              $i=0;
              while($row=$res->fetch_assoc()):
                $estado = strtolower($row['estado']);
                $cls = $estado==='recibida' || $estado==='pagada' ? 'ok' : ($estado==='pendiente' ? 'pending' : 'cancel');
        ?>
          <tr class="row-anim" style="animation-delay: <?= ($i*40) ?>ms">
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['proveedor']) ?></td>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td><span class="pill <?= $cls ?>"><i class="fa-solid fa-circle"></i><?= htmlspecialchars($row['estado']) ?></span></td>
            <td><?= htmlspecialchars($row['fecha']) ?></td>
            <td class="currency">$<?= number_format($row['total'],2,',','.') ?></td>
            <td>
              <button class="btn view" onclick="location.href='compras_ver.php?id=<?= $row['id'] ?>'">
                <i class="fa-solid fa-eye"></i> Ver
              </button>
            </td>
          </tr>
        <?php $i++; endwhile; else: ?>
          <tr><td colspan="7" class="empty">No hay compras registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Entrada suave de filas (IntersectionObserver)
const rows = document.querySelectorAll('.row-anim');
const io = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('show'); });
},{ threshold:.08 });
rows.forEach(r => io.observe(r));
</script>

</body>
</html>
