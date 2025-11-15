<?php
require_once 'Conexion.php';
$conn = new Conexion();
$db   = $conn->conexion;

// FILTROS
$qCliente = $_GET['qCliente'] ?? '';
$qEstado  = $_GET['qEstado'] ?? '';
$qFecha   = $_GET['qFecha'] ?? '';
$soloHoy  = isset($_GET['soloHoy']) ? 1 : 0;

$where = "1=1";

if ($qCliente !== '') {
  $q = $db->real_escape_string($qCliente);
  $where .= " AND (c.nombre LIKE '%$q%' OR u.nombre LIKE '%$q%' OR c.apellido LIKE '%$q%')";
}
if ($qEstado !== '') {
  $e = $db->real_escape_string($qEstado);
  $where .= " AND v.estado='$e'";
}
if ($qFecha !== '') {
  $f = $db->real_escape_string($qFecha);
  $where .= " AND DATE(v.fecha)='$f'";
}
if ($soloHoy) {
  $where .= " AND DATE(v.fecha)=CURDATE()";
}

$sql = "SELECT v.id, v.fecha, v.total, v.estado,
               c.nombre AS cliente, u.nombre AS usuario
        FROM Venta v
        LEFT JOIN Cliente c ON c.id=v.cliente_id
        JOIN Usuario u ON u.id=v.usuario_id
        WHERE $where
        ORDER BY v.fecha DESC";

$ventas = $db->query($sql);

// KPIs
$totalVentas = $ventas->num_rows;

$r = $db->query("SELECT IFNULL(SUM(total),0) AS s FROM Venta WHERE $where");
$importeFiltro = (float)($r->fetch_assoc()['s'] ?? 0);

$ticketProm = ($totalVentas>0) ? ($importeFiltro / $totalVentas) : 0;

$r = $db->query("SELECT IFNULL(SUM(total),0) AS s FROM Venta WHERE DATE(fecha)=CURDATE()");
$ventasHoy = (float)($r->fetch_assoc()['s'] ?? 0);
?>

<!-- BOTÓN VOLVER + TÍTULO -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:15px">
  <a href="menu.php" style="text-decoration:none">
    <button style="
      background:#00794f;color:#fff;border:0;border-radius:10px;
      padding:10px 16px;cursor:pointer;font-weight:700;
    ">
      <i class="fa-solid fa-arrow-left"></i> Volver al Menú
    </button>
  </a>
  <h1 style="margin:0;font-size:22px;font-weight:800;color:#0b1320">
    Listado de Ventas
  </h1>
</div>

<!-- KPIs GRANDES -->
<div style="
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:14px;
  margin-bottom:18px;
">

  <div style="
    background:#25c45e;
    padding:18px;border-radius:16px;color:white;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
  ">
    <strong>Ventas (filtro)</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px"><?= $totalVentas ?></div>
  </div>

  <div style="
    background:#2f66ff;
    padding:18px;border-radius:16px;color:white;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
  ">
    <strong>Importe total (filtro)</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($importeFiltro,2,',','.') ?></div>
  </div>

  <div style="
    background:#f6a800;
    padding:18px;border-radius:16px;color:white;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
  ">
    <strong>Ticket promedio</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($ticketProm,2,',','.') ?></div>
  </div>

  <div style="
    background:#e63946;
    padding:18px;border-radius:16px;color:white;
    box-shadow:0 8px 18px rgba(0,0,0,.12);
  ">
    <strong>Hoy</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($ventasHoy,2,',','.') ?></div>
  </div>

</div>

<!-- BOTÓN REGISTRAR VENTA (AHORA DINÁMICO) -->
<div style="display:flex;justify-content:flex-end;margin-bottom:10px">
  <a href="ventas.php"
     class="btn-module"
     data-href="ventas.php"
     data-title="Nueva venta"
     style="
      display:inline-flex;align-items:center;gap:8px;
      background:#28a745;color:white;border:0;padding:12px 18px;
      border-radius:12px;font-size:14px;font-weight:700;
      cursor:pointer;box-shadow:0 8px 20px rgba(0,0,0,.15);
      text-decoration:none;
     ">
    <i class="fa-solid fa-plus"></i> Registrar Venta
  </a>
</div>

<!-- FILTROS -->
<div style="
  background:white;border-radius:14px;padding:15px;
  box-shadow:0 8px 20px rgba(0,0,0,.12);margin-bottom:15px;
">

  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">

    <input name="qCliente" value="<?= htmlspecialchars($qCliente) ?>"
      placeholder="Buscar cliente / usuario"
      style="padding:10px 12px;border-radius:8px;border:1px solid #ccc;flex:1">

    <select name="qEstado" style="padding:10px 12px;border-radius:8px;border:1px solid #ccc">
      <option value="">Todos los estados</option>
      <option value="Pagada"   <?= $qEstado==='Pagada'?'selected':'' ?>>Pagada</option>
      <option value="Pendiente"<?= $qEstado==='Pendiente'?'selected':'' ?>>Pendiente</option>
    </select>

    <input type="date" name="qFecha"
      value="<?= htmlspecialchars($qFecha) ?>"
      style="padding:10px 12px;border-radius:8px;border:1px solid #ccc">

    <label style="display:flex;align-items:center;gap:6px">
      <input type="checkbox" name="soloHoy" <?= $soloHoy?'checked':'' ?>>
      Solo hoy
    </label>

    <button style="
      background:#1662c2;color:white;border:0;padding:10px 16px;
      border-radius:10px;cursor:pointer;font-weight:600;
    "><i class="fa-solid fa-filter"></i> Aplicar</button>

    <a href="ventas_listar.php" style="
      background:#f6b100;color:white;border:0;padding:10px 16px;
      border-radius:10px;text-decoration:none;font-weight:600;
    "><i class="fa-solid fa-rotate-right"></i> Limpiar</a>

  </form>
</div>

<!-- TABLA -->
<div style="
  background:white;border-radius:16px;
  box-shadow:0 8px 22px rgba(0,0,0,.15);
  overflow:hidden;
">

<table style="width:100%;border-collapse:collapse;font-size:14px">
  <thead style="background:#eef2f7">
    <tr>
      <th style="padding:10px">ID</th>
      <th>Cliente</th>
      <th>Usuario</th>
      <th>Total</th>
      <th>Fecha</th>
      <th>Estado</th>
      <th style="text-align:center">Acciones</th>
    </tr>
  </thead>
  <tbody>

<?php while($v = $ventas->fetch_assoc()):

$estadoColor = $v['estado']==='Pagada'
  ? "color:#0b7f55;font-weight:700"
  : "color:#d62828;font-weight:700";

?>
<tr style="border-bottom:1px solid #eee">
  <td style="padding:10px"><?= $v['id'] ?></td>
  <td><?= htmlspecialchars($v['cliente'] ?? 'Consumidor Final') ?></td>
  <td><?= htmlspecialchars($v['usuario']) ?></td>
  <td>$<?= number_format($v['total'],2,',','.') ?></td>
  <td><?= date("d/m/Y, g:i a", strtotime($v['fecha'])) ?></td>
  <td><span style="<?= $estadoColor ?>"><?= $v['estado'] ?></span></td>

  <td style="text-align:center">

    <a href="venta_ver.php?id=<?= $v['id'] ?>"
       style="background:#0aa06e;color:white;padding:6px 10px;border-radius:8px;
              font-size:13px;text-decoration:none;font-weight:600;margin-right:4px">
      Ver
    </a>

    <a href="venta_imprimir.php?id=<?= $v['id'] ?>"
       style="background:#1662c2;color:white;padding:6px 10px;border-radius:8px;
              font-size:13px;text-decoration:none;font-weight:600;margin-right:4px">
      Imp
    </a>

    <a href="venta_anular.php?id=<?= $v['id'] ?>"
       style="background:#d62828;color:white;padding:6px 10px;border-radius:8px;
              font-size:13px;text-decoration:none;font-weight:600">
      Anular
    </a>

  </td>
</tr>

<?php endwhile; ?>

  </tbody>
</table>

</div>
