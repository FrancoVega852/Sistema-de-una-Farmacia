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
$modoModulo = isset($_GET['mod']); // ← modo dinámico (dashboard)

if ($id <= 0) { 
    if(!$modoModulo) header("Location: compras_listar.php");
    exit(); 
}

// ===============================
// CABECERA DE ORDEN
// ===============================
$st = $db->prepare("SELECT oc.id, oc.fecha, oc.total, oc.estado, oc.observaciones,
                           pr.razonSocial AS proveedor, u.nombre AS usuario
                    FROM OrdenCompra oc
                    INNER JOIN Proveedor pr ON pr.id = oc.proveedor_id
                    INNER JOIN Usuario u ON u.id = oc.usuario_id
                    WHERE oc.id=?");
$st->bind_param("i",$id); 
$st->execute();
$oc = $st->get_result()->fetch_assoc();
if(!$oc){ 
    if(!$modoModulo) header("Location: compras_listar.php");
    exit(); 
}

// ===============================
// DETALLE
// ===============================
$st = $db->prepare("
    SELECT 
        d.cantidad,
        d.precio_unitario,
        d.subtotal,
        p.nombre AS producto,
        p.precio AS precio_base,
        c.nombre AS categoria,
        GROUP_CONCAT(pr.nombre SEPARATOR ', ') AS presentaciones,
        l.numero_lote,
        l.fecha_vencimiento
    FROM DetalleOrdenCompra d
    INNER JOIN Producto p ON p.id = d.producto_id
    LEFT JOIN Categoria c ON c.id = p.categoria_id
    LEFT JOIN PresentacionProducto pp ON pp.producto_id = p.id
    LEFT JOIN Presentacion pr ON pr.id = pp.presentacion_id
    LEFT JOIN Lote l ON l.producto_id = p.id
    WHERE d.orden_id = ?
    GROUP BY d.id
");
$st->bind_param("i", $id);
$st->execute();
$det = $st->get_result();

?>

<?php if(!$modoModulo): ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Compra #<?= $oc['id'] ?> - Farvec</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php endif; ?>

<style>
/* =====================================================
   🎨 Colores FARVEC
   ===================================================== */
:root{
  --verde:#007C4F;
  --verde2:#00A86B;
  --fondo:#ECECEC;
  --panel:#FFFFFF;
  --borde:#D2D2D2;
  --texto:#1a1a1a;
  --muted:#6b7280;
}

*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:'Inter',system-ui,Arial;
  background:var(--fondo);
  color:var(--texto);
  overflow-x:hidden;
}

/* =============================================
   HEADER
   ============================================= */
.top{
  display:flex;
  align-items:center;
  gap:14px;
  padding:16px 20px;
  background:var(--panel);
  border-bottom:1px solid var(--borde);
  position:sticky;
  top:0;
  z-index:5;
}

/* Botón volver */
.back{
  background:var(--verde);
  color:#fff;
  border:0;
  border-radius:8px;
  padding:10px 14px;
  cursor:pointer;
  font-weight:600;
  transition:.2s ease;
}
.back:hover{
  opacity:.9;
  transform:translateY(-1px);
}

/* =============================================
   TÍTULO
   ============================================= */
.mod-title{
  background:var(--verde);
  color:white;
  padding:20px;
  border-radius:10px;
  font-size:22px;
  font-weight:800;
  margin:10px 0 20px 0;
  display:flex;
  align-items:center;
  gap:10px;
  justify-content:center;
  box-shadow:0 4px 15px rgba(0,0,0,.08);
}

/* =============================================
   CONTENIDO
   ============================================= */
.wrap{
  max-width:1100px;
  margin:24px auto;
  padding:0 18px;
  display:flex;
  flex-direction:column;
  gap:20px;
}

/* =============================================
   CARDS
   ============================================= */
.card{
  background:var(--panel);
  border:1px solid var(--borde);
  border-radius:14px;
  padding:20px;
  box-shadow:0 8px 25px rgba(0,0,0,.05);
  animation:fadeUp .35s ease both;
}
.card h3{
  margin:0 0 14px;
  color:var(--verde);
  font-size:18px;
  font-weight:800;
}
.card p{
  margin:6px 0;
  font-size:15px;
}

/* Tabla */
.table{
  width:100%;
  border-collapse:collapse;
}
.table th{
  background:#C9F7E5;
  color:var(--verde);
  padding:12px;
  text-align:left;
}
.table td{
  padding:12px;
  border-bottom:1px solid var(--borde);
}

.total{
  font-size:20px;
  font-weight:800;
  color:var(--verde);
  text-align:right;
  margin-top:10px;
}

.actions{
  display:flex;
  gap:12px;
}

.btn{
  padding:10px 14px;
  border-radius:8px;
  border:0;
  cursor:pointer;
  font-weight:600;
}
.btn.print{
  background:var(--verde2);
  color:#fff;
}
.btn.primary{
  background:var(--verde);
  color:#fff;
}

@keyframes fadeUp{
  from{opacity:0; transform:translateY(8px)}
  to{opacity:1; transform:translateY(0)}
}
</style>

<?php if(!$modoModulo): ?>
</head>
<body>
<?php endif; ?>

<div id="<?= $modoModulo ? 'mod-compras-ver' : '' ?>">

<div class="top">
  <button class="back btn-volver">
      <i class="fa-solid fa-arrow-left"></i> Volver
  </button>
  <h1><i class="fa-solid fa-file-invoice-dollar"></i> Orden de Compra #<?= $oc['id'] ?></h1>
</div>

<div class="wrap">

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
        <td><?= $d['numero_lote'] ?: '-' ?></td>
        <td><?= $d['fecha_vencimiento'] ?: '-' ?></td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>

    <div class="total">TOTAL: $<?= number_format($oc['total'],2,',','.') ?></div>
  </div>

  <div class="actions">
    <button class="btn print"><i class="fa-solid fa-print"></i> Imprimir</button>

    <!-- ➜ BOTÓN CORRECTO PARA VER EL LISTADO -->
    <button class="btn primary btn-ver-listado">
        <i class="fa-solid fa-list"></i> Ver el Listado
    </button>
  </div>

</div>
</div>

<!-- =====================================================
     JS: Animaciones + Navegación Dinámica
===================================================== -->
<script>
// =====================================================
// BOTÓN VOLVER → compras.php
// =====================================================
document.querySelectorAll(".btn-volver").forEach(btn=>{
    btn.onclick = function(e){
        e.preventDefault();

        const root = document.querySelector("#mod-compras-ver");
        if(!root){
            window.location.href = "compras.php";
            return;
        }

        root.style.transition = "all .35s ease";
        root.style.opacity = "0";
        root.style.transform = "scale(0.94)";

        setTimeout(()=>{
            if (typeof cargarModulo === "function") {
                cargarModulo("compras.php","Compras");
            } else {
                window.location.href = "compras.php";
            }
        },350);
    }
});

// =====================================================
// BOTÓN VER EL LISTADO → compras_listar.php
// =====================================================
document.querySelector(".btn-ver-listado")?.addEventListener("click", function(e){
    e.preventDefault();

    const root = document.querySelector("#mod-compras-ver");

    if (!root){
        window.location.href = "compras_listar.php";
        return;
    }

    root.style.transition = "all .35s ease";
    root.style.opacity = "0";
    root.style.transform = "scale(0.94)";

    setTimeout(()=>{
        if (typeof cargarModulo === "function") {
            cargarModulo("compras_listar.php?mod=1&from=<?= $oc['id'] ?>","Listado de Compras");
        } else {
            window.location.href = "compras_listar.php";
        }
    },350);
});
</script>

<?php if(!$modoModulo): ?>
</body>
</html>
<?php endif; ?>
