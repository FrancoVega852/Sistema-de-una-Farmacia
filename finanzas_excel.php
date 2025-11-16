<?php
session_start();
require_once "Conexion.php";

if (!isset($_SESSION['usuario_id'])) {
    die("Error: sesión expirada");
}

$conn = new Conexion();
$db   = $conn->conexion;

$desde = $_GET['desde'] ?? "";
$hasta = $_GET['hasta'] ?? "";

$where = "WHERE 1=1";

if ($desde !== "") {
    $d = DateTime::createFromFormat("Y-m-d", $desde);
    if ($d) $where .= " AND DATE(fecha) >= '{$d->format('Y-m-d')}' ";
}

if ($hasta !== "") {
    $h = DateTime::createFromFormat("Y-m-d", $hasta);
    if ($h) $where .= " AND DATE(fecha) <= '{$h->format('Y-m-d')}' ";
}

$sql = "SELECT fecha, tipo, descripcion, monto FROM movimientos $where ORDER BY fecha DESC";
$res = $db->query($sql);

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=finanzas_" . date("Ymd_His") . ".xls");

echo "<table border='1'>";
echo "<tr style='background:#0a7e56;color:#fff;font-weight:bold'>
        <th>Fecha</th>
        <th>Tipo</th>
        <th>Descripción</th>
        <th>Monto</th>
      </tr>";

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['fecha'] . "</td>";
        echo "<td>" . $row['tipo'] . "</td>";
        echo "<td>" . htmlspecialchars($row['descripcion']) . "</td>";
        echo "<td>$" . number_format($row['monto'], 2, ',', '.') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4'>No hay movimientos</td></tr>";
}

echo "</table>";
