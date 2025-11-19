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

// Capturar ID de origen
$fromID = $_GET['from'] ?? null;
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

.wrap{ max-width:1200px; margin:22px auto; padding:0 14px; }

/* Export buttons */
.export-bar{
  display:flex; gap:10px; margin-bottom:15px;
}
.export-btn{
  background:#fff; border:1px solid var(--borde); padding:9px 12px; border-radius:10px;
  display:flex; align-items:center; gap:6px; cursor:pointer; transition:.2s;
}
.export-btn:hover{ transform:translateY(-2px); box-shadow:var(--sombra); }

/* Tabla */
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

@keyframes fadeIn{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }

.currency{ white-space:nowrap }
</style>
</head>
<body>

<div class="top">
  <button class="back btn-volver">
      <i class="fa-solid fa-arrow-left"></i> Volver
  </button>

  <h1><i class="fa-solid fa-truck"></i> Listado de Compras</h1>

  <div class="topbar-actions">
    <button class="btn primary btn-nueva-compra">
      <i class="fa-solid fa-plus"></i> Nueva Compra
    </button>
  </div>
</div>

<div id="mod-compras-listar">

<div class="wrap">

  <!-- EXPORT BUTTONS -->
  <div class="export-bar">
      <button class="export-btn" id="exportExcel"><i class="fa-solid fa-file-excel"></i> Excel</button>
      <button class="export-btn" id="exportCSV"><i class="fa-solid fa-file-csv"></i> CSV</button>
      <button class="export-btn" id="printTable"><i class="fa-solid fa-print"></i> Imprimir</button>
  </div>

  <div class="card">
    <table id="tablaCompras" class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Proveedor</th>
          <th>Usuario</th>
          <th>Estado</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if($res && $res->num_rows):
              while($row=$res->fetch_assoc()):
                $estado = strtolower($row['estado']);
                $cls = $estado==='recibida' || $estado==='pagada' ? 'ok' : ($estado==='pendiente' ? 'pending' : 'cancel');
        ?>
          <tr>
            <td>#<?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['proveedor']) ?></td>
            <td><?= htmlspecialchars($row['usuario']) ?></td>
            <td><span class="pill <?= $cls ?>"><?= htmlspecialchars($row['estado']) ?></span></td>
            <td><?= htmlspecialchars($row['fecha']) ?></td>
            <td class="currency">$<?= number_format($row['total'],2,',','.') ?></td>
            <td>
              <button class="btn view btn-ver" data-id="<?= $row['id'] ?>">
                <i class="fa-solid fa-eye"></i> Ver
              </button>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" class="empty">No hay compras registradas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<script>
// =========================================================
// BOTÓN VOLVER → vuelve a compras_ver.php?id=[from]
// =========================================================
document.querySelector(".btn-volver").addEventListener("click", ()=>{

    let fromID = "<?= $fromID ?>";

    if (!fromID) {
        alert("No hay una compra previa para volver.");
        return;
    }

    const root = document.querySelector("#mod-compras-listar");

    if (root) {
        root.style.transition = "all .35s ease";
        root.style.opacity = "0";
        root.style.transform = "translateX(-20px)";
    }

    setTimeout(()=>{
        if (typeof cargarModulo === "function") {
            cargarModulo(`compras_ver.php?id=${fromID}&mod=1`, "Ver Compra");
        } else {
            window.location.href = `compras_ver.php?id=${fromID}`;
        }
    },350);
});

// =========================================================
// NUEVA COMPRA → compras.php
// =========================================================
document.querySelector(".btn-nueva-compra").addEventListener("click", ()=>{
    const root = document.querySelector("#mod-compras-listar");

    root.style.transition="all .35s ease";
    root.style.opacity="0";
    root.style.transform="scale(.94)";

    setTimeout(()=>{
        if (typeof cargarModulo === "function")
            cargarModulo("compras.php?mod=1","Nueva Compra");
        else
            window.location.href="compras.php";
    },350);
});

// =========================================================
// VER COMPRA → compras_ver.php
// =========================================================
document.querySelectorAll(".btn-ver").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        const id = btn.dataset.id;
        const root = document.querySelector("#mod-compras-listar");

        root.style.transition="all .35s ease";
        root.style.opacity="0";
        root.style.transform="scale(.94)";

        setTimeout(()=>{
            if (typeof cargarModulo === "function")
                cargarModulo("compras_ver.php?id="+id+"&mod=1","Ver Compra");
            else
                window.location.href="compras_ver.php?id="+id;
        },350);
    });
});

// =========================================================
// EXPORT CSV
// =========================================================
document.getElementById("exportCSV").addEventListener("click", () => {
    const table = document.querySelector("#tablaCompras");
    if (!table) return;

    let csv = [];
    table.querySelectorAll("tr").forEach(row => {
        let cols = [...row.querySelectorAll("th,td")].map(col => `"${col.innerText}"`);
        csv.push(cols.join(","));
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "compras.csv";
    link.click();
});

// =========================================================
// EXPORT EXCEL
// =========================================================
document.getElementById("exportExcel").addEventListener("click", () => {
    const table = document.querySelector("#tablaCompras");
    if (!table) return;

    const blob = new Blob([table.outerHTML], { type:"application/vnd.ms-excel" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "compras.xls";
    link.click();
});

// =========================================================
// IMPRIMIR TABLA
// =========================================================
document.getElementById("printTable").addEventListener("click", () => {
    const table = document.querySelector("#tablaCompras").outerHTML;

    const win = window.open("", "", "width=900,height=700");
    win.document.write(`
        <html>
        <head>
          <title>Imprimir Compras</title>
          <style>
            table {width:100%; border-collapse:collapse;}
            th,td {border:1px solid #555; padding:8px;}
          </style>
        </head>
        <body>${table}</body>
        </html>
    `);
    win.document.close();
    win.print();
});
</script>

</body>
</html>
