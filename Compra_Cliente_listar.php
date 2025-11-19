<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
  echo "<div class='panel'><h3>Error: sesión expirada.</h3></div>";
  return;
}

$conn = new Conexion();
$db   = $conn->conexion;
$usuarioId = (int)$_SESSION['usuario_id'];

/* ===================== FILTROS ===================== */
$qCliente = $_GET['qCliente'] ?? '';
$qEstado  = $_GET['qEstado'] ?? '';
$qFecha   = $_GET['qFecha'] ?? '';
$soloHoy  = isset($_GET['soloHoy']) ? 1 : 0;

$where = "1=1";
$params = [];
$types  = "";

// Los filtros por cliente/usuario usan JOIN, así que sólo se aplican
// en las consultas que tienen JOIN (listado, sumas filtradas)
if ($qCliente !== '') {
  $where .= " AND (c.nombre LIKE CONCAT('%', ?, '%')
               OR  c.apellido LIKE CONCAT('%', ?, '%')
               OR  u.nombre LIKE CONCAT('%', ?, '%'))";
  $types  .= "sss";
  $params[] = $qCliente;
  $params[] = $qCliente;
  $params[] = $qCliente;
}

if ($qEstado !== '') {
  $where  .= " AND v.estado = ?";
  $types  .= "s";
  $params[] = $qEstado;
}

if ($qFecha !== '') {
  $where  .= " AND DATE(v.fecha) = ?";
  $types  .= "s";
  $params[] = $qFecha;
}

if ($soloHoy) {
  $where  .= " AND DATE(v.fecha) = CURDATE()";
}

// SIEMPRE filtrar por el usuario (cliente) logueado
$where  .= " AND v.usuario_id = ?";
$types  .= "i";
$params[] = $usuarioId;

// Base FROM para reutilizar en KPIs
$sqlBase = "
  FROM Venta v
  LEFT JOIN Cliente c ON c.id = v.cliente_id
  INNER JOIN Usuario u ON u.id = v.usuario_id
";

/* ===================== LISTADO ===================== */
$sql = "
  SELECT v.id, v.fecha, v.total, v.estado,
         c.nombre AS cliente, u.nombre AS usuario
  $sqlBase
  WHERE $where
  ORDER BY v.fecha DESC
";
$st = $db->prepare($sql);
if ($types !== "") {
  $st->bind_param($types, ...$params);
}
$st->execute();
$ventas = $st->get_result();

/* ===================== KPIs ===================== */
$totalVentas = $ventas->num_rows;

// Importe total (con el mismo filtro)
$sqlImp = "
  SELECT IFNULL(SUM(v.total),0) AS s
  $sqlBase
  WHERE $where
";
$stImp = $db->prepare($sqlImp);
if ($types !== "") {
  $stImp->bind_param($types, ...$params);
}
$stImp->execute();
$importeFiltro = (float)($stImp->get_result()->fetch_assoc()['s'] ?? 0);

// Ticket promedio
$ticketProm = ($totalVentas > 0)
  ? ($importeFiltro / $totalVentas)
  : 0;

// Ventas de HOY del mismo usuario
$sqlHoy = "
  SELECT IFNULL(SUM(v.total),0) AS s
  $sqlBase
  WHERE DATE(v.fecha) = CURDATE()
    AND v.usuario_id = ?
";
$stHoy = $db->prepare($sqlHoy);
$stHoy->bind_param("i", $usuarioId);
$stHoy->execute();
$ventasHoy = (float)($stHoy->get_result()->fetch_assoc()['s'] ?? 0);
?>

<!-- BOTÓN VOLVER + TÍTULO (DINÁMICO) -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:15px;flex-wrap:wrap">

  <button onclick="window.location.href='menu_cliente.php'"
          style="background:#00794f;color:#fff;border:0;border-radius:10px;
                 padding:10px 16px;cursor:pointer;font-weight:700;">
      <i class="fa-solid fa-arrow-left"></i> Volver al Portal
  </button>

  <h1 style="margin:0;font-size:22px;font-weight:800;color:#0b1320">
    Mis Compras
  </h1>

</div>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px;">

  <div style="background:#25c45e;padding:18px;border-radius:16px;color:white;box-shadow:0 8px 18px rgba(0,0,0,.12);">
    <strong>Compras (filtro)</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px"><?= $totalVentas ?></div>
  </div>

  <div style="background:#2f66ff;padding:18px;border-radius:16px;color:white;box-shadow:0 8px 18px rgba(0,0,0,.12);">
    <strong>Importe total (filtro)</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($importeFiltro,2,',','.') ?></div>
  </div>

  <div style="background:#f6a800;padding:18px;border-radius:16px;color:white;box-shadow:0 8px 18px rgba(0,0,0,.12);">
    <strong>Ticket promedio</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($ticketProm,2,',','.') ?></div>
  </div>

  <div style="background:#e63946;padding:18px;border-radius:16px;color:white;box-shadow:0 8px 18px rgba(0,0,0,.12);">
    <strong>Compras de hoy</strong>
    <div style="font-size:26px;font-weight:700;margin-top:6px">$<?= number_format($ventasHoy,2,',','.') ?></div>
  </div>

</div>

<!-- BOTÓN NUEVA COMPRA -->
<div style="display:flex;justify-content:flex-end;margin-bottom:10px">
  <button class="btn-module" data-href="Compra_Cliente_guardar.php"
          style="display:inline-flex;align-items:center;gap:8px;background:#28a745;
                 color:white;border:0;padding:12px 18px;border-radius:12px;font-size:14px;
                 font-weight:700;cursor:pointer;box-shadow:0 8px 20px rgba(0,0,0,.15);">
      <i class="fa-solid fa-plus"></i> Nueva compra
  </button>
</div>

<!-- FILTROS -->
<div style="background:white;border-radius:14px;padding:15px;
            box-shadow:0 8px 20px rgba(0,0,0,.12);margin-bottom:15px;">

  <form onsubmit="aplicarFiltroCompra(event)"
        style="display:flex;gap:10px;flex-wrap:wrap">

    <input id="qCliente" name="qCliente"
      value="<?= htmlspecialchars($qCliente) ?>"
      placeholder="Buscar por cliente / usuario"
      style="padding:10px 12px;border-radius:8px;border:1px solid #ccc;flex:1">

    <select id="qEstado" name="qEstado"
            style="padding:10px 12px;border-radius:8px;border:1px solid #ccc">
      <option value="">Todos los estados</option>
      <option value="Pagada"   <?= $qEstado==='Pagada'?'selected':'' ?>>Pagada</option>
      <option value="Pendiente"<?= $qEstado==='Pendiente'?'selected':'' ?>>Pendiente</option>
    </select>

    <input type="date" id="qFecha" name="qFecha"
      value="<?= htmlspecialchars($qFecha) ?>"
      style="padding:10px 12px;border-radius:8px;border:1px solid #ccc">

    <label style="display:flex;align-items:center;gap:6px">
      <input type="checkbox" id="soloHoy" name="soloHoy"
             <?= $soloHoy?'checked':'' ?>>
      Solo hoy
    </label>

    <button style="background:#1662c2;color:white;border:0;padding:10px 16px;
                   border-radius:10px;cursor:pointer;font-weight:600;">
      <i class="fa-solid fa-filter"></i> Aplicar
    </button>

    <button type="button"
            onclick="cargarModulo('Compra_Cliente_listar.php')"
            style="background:#f6b100;color:white;border:0;padding:10px 16px;
                   border-radius:10px;font-weight:600;cursor:pointer;">
      <i class="fa-solid fa-rotate-right"></i> Limpiar
    </button>

  </form>
</div>

<!-- TABLA -->
<div style="background:white;border-radius:16px;box-shadow:0 8px 22px rgba(0,0,0,.15);overflow:hidden;">

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

    <!-- VER -->
    <button class="btn-module"
            data-href="Compra_Cliente_ver.php?id=<?= $v['id'] ?>"
            style="background:#0aa06e;color:white;padding:6px 10px;border-radius:8px;
                   font-size:13px;font-weight:600;margin-right:4px;border:0;cursor:pointer;">
      Ver
    </button>

    <!-- IMPRIMIR -->
    <button onclick="window.open('venta_imprimir.php?id=<?= $v['id'] ?>','_blank')"
            style="background:#1662c2;color:white;padding:6px 10px;border-radius:8px;
                   font-size:13px;font-weight:600;margin-right:4px;border:0;cursor:pointer;">
      Imp
    </button>

    <!-- ANULAR -->
    <button onclick="anularCompraCliente(<?= $v['id'] ?>)"
            style="background:#d62828;color:white;padding:6px 10px;border-radius:8px;
                   font-size:13px;font-weight:600;border:0;cursor:pointer;">
      Anular
    </button>

  </td>
</tr>

<?php endwhile; ?>

  </tbody>
</table>

</div>

<!-- SCRIPT DINÁMICO -->
<script>
document.querySelectorAll(".btn-module").forEach(btn => {
  btn.onclick = () => cargarModulo(btn.dataset.href);
});

/* Aplicar filtro sin recargar */
function aplicarFiltroCompra(e){
  e.preventDefault();

  const params = new URLSearchParams();
  params.append("qCliente", document.getElementById("qCliente").value);
  params.append("qEstado",  document.getElementById("qEstado").value);
  params.append("qFecha",   document.getElementById("qFecha").value);

  if(document.getElementById("soloHoy").checked){
    params.append("soloHoy", "1");
  }

  cargarModulo("Compra_Cliente_listar.php?" + params.toString());
}

/* Anular compra (venta) del cliente vía AJAX */
function anularCompraCliente(id){
  if(!confirm("¿Seguro que deseas ANULAR esta compra?")) return;

  fetch("Compra_Cliente_anular.php?id=" + id, {
    headers: { "X-Requested-With": "XMLHttpRequest" }
  })
    .then(() => cargarModulo("Compra_Cliente_listar.php"))
    .catch(() => alert("Error al anular la compra"));
}
</script>
