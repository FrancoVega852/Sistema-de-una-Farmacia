<?php
/*******************************************************
 * FARVEC • Visibilidad de productos (Catálogo cliente)
 * Solo para Administrador / Farmaceutico
 *******************************************************/
session_start();
require_once 'Conexion.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$rol = $_SESSION['usuario_rol'] ?? 'Empleado';
if (!in_array($rol, ['Administrador','Farmaceutico'])) {
  header("Location: Menu.php");
  exit();
}

$conn = new Conexion();
$db   = $conn->conexion;
$db->set_charset('utf8mb4');

$sql = "
  SELECT 
    p.id,
    p.nombre,
    p.descripcion,
    p.precio_venta,
    p.stock_actual,
    COALESCE(c.nombre,'Sin categoría') AS categoria,
    p.visible_cliente
  FROM Producto p
  LEFT JOIN Categoria c ON c.id = p.categoria_id
  ORDER BY p.nombre ASC
";
$res = $db->query($sql);
$productos = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

function nfmt($n,$d=2){ return number_format((float)$n,$d,',','.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>FARVEC • Visibilidad catálogo cliente</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
  --verde:#00a86b; --verde-osc:#00794f;
  --ink:#212b36; --muted:#5e6b74;
  --panel:#ffffff; --border:#e7eceb;
  --bg:#f6f9f8; --shadow:0 8px 22px rgba(17,24,39,.08);
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;
  background:var(--bg);
  color:var(--ink);
}

/* Fondo con pastillas */
.bg-layer{
  position:fixed;inset:0;z-index:-1;pointer-events:none;
  background:
    radial-gradient(700px 340px at 100% 0, rgba(0,168,107,.12), transparent 60%),
    radial-gradient(600px 300px at 0 100%, rgba(0,121,79,.12), transparent 60%);
}
.bg-layer::before,
.bg-layer::after{
  content:"";
  position:absolute;
  width:380px;height:180px;
  border-radius:999px;
  border:1px solid rgba(0,168,107,.25);
  background:linear-gradient(135deg, rgba(255,255,255,.65), rgba(0,168,107,.08));
  filter:blur(.2px);
}
.bg-layer::before{ top:40px; right:-120px; transform:rotate(-20deg); }
.bg-layer::after{ bottom:-80px; left:-120px; transform:rotate(18deg); }

/* Tarjeta principal */
.card-farvec{
  border-radius:18px;
  border:1px solid var(--border);
  box-shadow:var(--shadow);
  background:rgba(255,255,255,.95);
  backdrop-filter:blur(8px);
  overflow:hidden;
}
.card-header-farvec{
  padding:18px 20px 12px;
  border-bottom:1px solid rgba(231,236,235,.8);
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
}
.badge-soft{
  border-radius:999px;
  padding:.25rem .75rem;
  font-size:.7rem;
  background:rgba(0,168,107,.08);
  color:var(--verde-osc);
  border:1px solid rgba(0,168,107,.25);
}
.table thead th{
  background:#f3f6f5;
  font-size:.78rem;
  text-transform:uppercase;
  letter-spacing:.04em;
  color:var(--muted);
}
.table tbody td{
  vertical-align:middle;
  font-size:.85rem;
}
.badge-cat{
  border-radius:999px;
  background:rgba(22,98,194,.08);
  border:1px solid rgba(22,98,194,.25);
  color:#0b3a79;
  font-size:.7rem;
  padding:.2rem .6rem;
}
.stock-pill{
  border-radius:999px;
  padding:.18rem .65rem;
  font-size:.7rem;
  background:#f8f9fa;
  border:1px solid #e2e7ea;
}
.stock-pill.ok{ color:#0a7e56; }
.stock-pill.low{ color:#b17012; }
.form-switch .form-check-input{
  cursor:pointer;
  box-shadow:none!important;
}
.form-switch .form-check-input:checked{
  background-color:var(--verde-osc);
  border-color:var(--verde-osc);
}

/* Toast simple */
.toast-farvec{
  position:fixed;
  right:16px;bottom:16px;
  background:#111827;
  color:#f9fafb;
  padding:10px 14px;
  border-radius:999px;
  font-size:.8rem;
  display:none;
  align-items:center;
  gap:8px;
  z-index:999;
}
.toast-farvec.show{display:flex;animation:fadeInOut 2.2s ease forwards;}
@keyframes fadeInOut{
  0%{opacity:0;transform:translateY(6px);}
  10%{opacity:1;transform:translateY(0);}
  80%{opacity:1;}
  100%{opacity:0;transform:translateY(6px);}
}
</style>
</head>
<body>
<div class="bg-layer"></div>

<div class="container py-4">
  <div class="card card-farvec">
    <div class="card-header-farvec">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge-soft">
            <i class="fa-solid fa-eye"></i> Catálogo cliente
          </span>
        </div>
        <h1 class="h5 mb-1" style="color:var(--verde-osc);">
          Visibilidad de productos
        </h1>
        <p class="text-muted mb-0" style="font-size:.85rem;">
          Activá o desactivá qué productos aparecen en el catálogo del cliente.
          <br class="d-none d-sm-block">
          Esto no afecta el stock ni las ventas internas, solo la vista del cliente.
        </p>
      </div>
      <div class="text-end small text-muted">
        <div><i class="fa-solid fa-user-shield me-1"></i> Solo Administrador / Farmacéutico</div>
        <div class="mt-1">
          <a href="Menu.php" class="btn btn-sm btn-outline-success">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
          </a>
        </div>
      </div>
    </div>

    <div class="card-body p-3 p-sm-4">
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Producto</th>
              <th class="d-none d-md-table-cell">Categoría</th>
              <th>Precio</th>
              <th>Stock</th>
              <th class="text-center">Visible al cliente</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$productos): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">
                No se encontraron productos.
              </td>
            </tr>
          <?php else: foreach($productos as $p):
            $stockClass = $p['stock_actual'] <= 0 ? 'low' : 'ok';
          ?>
            <tr>
              <td class="text-muted small">#<?= (int)$p['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></div>
                <?php if(!empty($p['descripcion'])): ?>
                  <div class="text-muted small text-truncate" style="max-width:260px;">
                    <?= htmlspecialchars($p['descripcion']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="d-none d-md-table-cell">
                <span class="badge-cat">
                  <i class="fa-solid fa-tag me-1"></i>
                  <?= htmlspecialchars($p['categoria']) ?>
                </span>
              </td>
              <td>
                <span class="fw-semibold text-success">
                  $<?= nfmt($p['precio_venta'] ?? 0) ?>
                </span>
              </td>
              <td>
                <span class="stock-pill <?= $stockClass ?>">
                  <i class="fa-solid fa-box me-1"></i>
                  <?= (int)$p['stock_actual'] ?> u.
                </span>
              </td>
              <td class="text-center">
                <div class="form-check form-switch d-inline-flex align-items-center justify-content-center">
                  <input class="form-check-input toggle-vis"
                         type="checkbox"
                         role="switch"
                         data-id="<?= (int)$p['id'] ?>"
                         <?= $p['visible_cliente'] ? 'checked' : '' ?>>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="toast-farvec" id="toastFarvec">
  <i class="fa-solid fa-circle-check text-success"></i>
  <span id="toastText">Guardado</span>
</div>

<script>
function showToast(msg){
  const t = document.getElementById('toastFarvec');
  const span = document.getElementById('toastText');
  span.textContent = msg || 'Actualizado';
  t.classList.remove('show');
  void t.offsetWidth; // reflow
  t.classList.add('show');
}

document.querySelectorAll('.toggle-vis').forEach(chk => {
  chk.addEventListener('change', async () => {
    const id = chk.dataset.id;
    const visible = chk.checked ? 1 : 0;

    try{
      const res = await fetch('producto_toggle_visible.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id) + '&visible=' + encodeURIComponent(visible)
      });
      const txt = await res.text();
      if(!res.ok || !txt.startsWith('OK')){
        throw new Error(txt || 'Error al actualizar');
      }
      showToast('Visibilidad actualizada');
    }catch(err){
      alert('No se pudo actualizar: ' + err.message);
      chk.checked = !chk.checked; // revertir
    }
  });
});
</script>
</body>
</html>
