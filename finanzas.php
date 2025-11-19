<?php
session_start();
require_once 'Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new Conexion();
$db   = $conn->conexion;
$db->set_charset("utf8mb4");

/* ============================
   FILTROS
============================ */
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

$where = "";
$params = [];
$types  = "";

if ($desde !== "") { 
    $where .= " AND fecha >= ? "; 
    $params[] = $desde;
    $types   .= "s";
}
if ($hasta !== "") { 
    $where .= " AND fecha <= ? "; 
    $params[] = $hasta . " 23:59:59";
    $types   .= "s";
}

/* ============================
   CONSULTA DE MOVIMIENTOS
============================ */
$sql = "SELECT id, tipo, descripcion, monto, fecha 
        FROM Movimiento
        WHERE 1=1 $where
        ORDER BY fecha DESC";

$st = $db->prepare($sql);
if ($params) $st->bind_param($types, ...$params);
$st->execute();
$res = $st->get_result();

/* ============================
   SALDO ACTUAL
============================ */
$saldoQ = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN tipo='Ingreso' THEN monto END),0) -
        COALESCE(SUM(CASE WHEN tipo='Egreso' THEN monto END),0) AS saldo
    FROM Movimiento
");
$saldoActual = 0;
if ($saldoQ && $r = $saldoQ->fetch_assoc()) {
    $saldoActual = (float)$r['saldo'];
}

function nfmt($n,$d=2){ return number_format((float)$n,$d,',','.'); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Finanzas - FARVEC</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
  --verde:#00a86b;
  --osc:#00794f;
  --danger:#b93142;
  --warn:#b17012;
  --panel:#ffffff;
  --border:#e7eceb;
  --shadow:0 8px 20px rgba(0,0,0,.08);
}
body{
  font-family:"Inter",Segoe UI,Arial; margin:0;
  background:#f4faf7;
}
.wrap{
  max-width:1200px; margin:20px auto; padding:0 14px;
}
.card{
  background:var(--panel); border:1px solid var(--border);
  border-radius:16px; padding:16px; box-shadow:var(--shadow);
  margin-bottom:18px;
}
h1{ color:var(--osc); margin-top:0; }

/* SALDO */
.saldo-box{
  background:linear-gradient(90deg,#00794f,#00a86b);
  color:#fff; padding:18px; border-radius:16px;
  display:flex; justify-content:space-between; align-items:center;
  box-shadow:var(--shadow);
}
.saldo-box h2{ margin:0; font-size:26px }
.saldo-box small{ opacity:.85 }

/* BOTONES */
.btn{
  padding:10px 14px; border-radius:12px; border:1px solid var(--border);
  background:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:8px;
}
.btn.primary{
  background:var(--osc); color:#fff; border-color:var(--osc);
  box-shadow:var(--shadow);
}
.btn.excel{
  background:#22c55e; color:#fff; border-color:#16a34a;
}
.btn.print{
  background:#2563eb; color:#fff; border-color:#1d4ed8;
}

/* TABLA */
.table{
  width:100%; border-collapse:collapse; margin-top:14px;
}
.table th,.table td{
  padding:12px 10px; border-bottom:1px solid var(--border);
  font-size:14px; text-align:left;
}
.table th{
  background:#e8f7ef; color:#064e3b;
}

/* TAGS */
.tag{
  padding:6px 10px; border-radius:999px; font-size:12px;
  font-weight:700;
}
.tag.ingreso{
  background:#d1fae5; color:#065f46;
}
.tag.egreso{
  background:#fee2e2; color:#991b1b;
}

/* FILTROS */
.filter-box{
  display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;
}
.filter-box input{
  padding:8px 10px; border-radius:10px; border:1px solid var(--border);
}

</style>
</head>

<body>

<div class="wrap">

  <div class="saldo-box">
    <div>
      <small>Saldo actual</small>
      <h2>$<?= nfmt($saldoActual) ?></h2>
    </div>
    <button class="btn primary" onclick="nuevoMovimiento()">
      <i class="fa-solid fa-plus"></i> Nuevo movimiento
    </button>
  </div>

  <!-- FILTROS -->
  <div class="card">
    <h3>Filtrar movimientos</h3>
    <form method="GET" class="filter-box">
      <div>
        <label>Desde:</label><br>
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
      </div>
      <div>
        <label>Hasta:</label><br>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
      </div>
      <button class="btn primary" style="height:38px">
        <i class="fa-solid fa-filter"></i> Aplicar
      </button>
      <button class="btn" type="button" onclick="location.href='finanzas.php'" style="height:38px">
        <i class="fa-solid fa-eraser"></i> Limpiar
      </button>

      <button type="button" onclick="exportarExcel()" class="btn excel" style="margin-left:auto; height:38px">
        <i class="fa-solid fa-file-excel"></i> Exportar Excel
      </button>

      <button type="button" onclick="window.print()" class="btn print" style="height:38px">
        <i class="fa-solid fa-print"></i> Imprimir
      </button>
    </form>
  </div>

  <!-- TABLA -->
  <div class="card">
    <h3>Movimientos</h3>

    <table class="table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Tipo</th>
          <th>Descripción</th>
          <th>Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$res->num_rows): ?>
          <tr><td colspan="4">No hay movimientos en este rango.</td></tr>
        <?php else: ?>
          <?php while($m = $res->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($m['fecha']) ?></td>
              <td>
                <span class="tag <?= strtolower($m['tipo']) ?>">
                  <?= htmlspecialchars($m['tipo']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($m['descripcion']) ?></td>
              <td>$<?= nfmt($m['monto']) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>

function nuevoMovimiento(){
    cargarModulo('movimiento_nuevo.php','Nuevo movimiento',{wrapTitle:true});
}

function exportarExcel(){
    window.location.href = "finanzas_excel.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>";
}

</script>

</body>
</html>
