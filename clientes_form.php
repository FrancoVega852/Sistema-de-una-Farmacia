<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorClientes.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$ctl = new ControladorClientes($conn->conexion);
$id = $_GET['id'] ?? null;
$cliente = $id ? $ctl->obtener((int)$id) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?> - FARVEC</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --verde:#16d490;
  --verde-osc:#0ca678;
  --verde-claro:#22eaaa;
  --fondo:#083d2d;
  --borde:#16d49066;
  --texto:#ffffff;
}

/* ===== FONDO ===== */
html, body {
  margin:0; padding:0; height:100%;
  font-family:"Segoe UI", system-ui;
  background:linear-gradient(180deg,#046c50,#0d8d67,#11b383);
  background-attachment:fixed;
  color:var(--texto);
  overflow-x:hidden;
}

.bg-pastillas{
  position:fixed;inset:0;z-index:0;pointer-events:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cg fill='%2316d49022'%3E%3Cellipse cx='30' cy='40' rx='12' ry='5' transform='rotate(25 30 40)'/%3E%3Cellipse cx='130' cy='130' rx='10' ry='4' transform='rotate(-25 130 130)'/%3E%3Ccircle cx='60' cy='90' r='3'/%3E%3C/g%3E%3C/svg%3E");
  background-size:180px 180px;
  animation:pillsMove 50s linear infinite alternate;
  opacity:.4;
}
@keyframes pillsMove{
  0%{background-position:0 0;}
  100%{background-position:300px 200px;}
}

/* ===== CARD FORM ===== */
.card{
  background:rgba(18, 61, 47, 0.78);
  border-radius:18px;
  box-shadow:0 10px 35px rgba(0,0,0,.35);
  border:1px solid var(--borde);
  backdrop-filter:blur(12px);
  animation:fadeIn .8s ease both;
}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}

/* ===== TITULOS ===== */
h3{
  color:#ffffff;
  font-weight:700;
  text-shadow:0 0 6px #22eaaa77;
}

/* ===== INPUTS ===== */
.form-control, .form-select{
  background-color:rgba(255,255,255,0.1);
  border:1px solid #16d49077;
  color:#fff;
  border-radius:10px;
}
.form-control::placeholder{color:#ffffffb3;}
.form-control:focus, .form-select:focus{
  box-shadow:0 0 0 0.25rem #22eaaa66;
  border-color:#22eaaa;
  background-color:rgba(255,255,255,0.15);
}
label.form-label{
  font-weight:500;
  color:#fff;
}

/* ===== SELECT FORZADO (100% visible) ===== */
.form-select {
  background-color: #084c39 !important;
  color: #ffffff !important;
  border: 1px solid #22eaaa !important;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  text-shadow: 0 0 2px #000;
}

.form-select option {
  background-color: #0b5a45 !important;
  color: #ffffff !important;
}

.form-select option:hover,
.form-select option:checked,
.form-select option:focus {
  background-color: #0ca678 !important;
  color: #ffffff !important;
}

/* ===== BOTONES ===== */
.btn-farvec{
  background:linear-gradient(90deg,var(--verde-osc),var(--verde));
  color:#fff;
  border:none;
  font-weight:600;
  padding:10px 18px;
  border-radius:10px;
  box-shadow:0 6px 14px rgba(22,212,144,.3);
  transition:.25s;
}
.btn-farvec:hover{
  transform:translateY(-2px);
  filter:brightness(1.15);
}
.btn-volver{
  background:linear-gradient(90deg,var(--verde),var(--verde-claro));
  color:#fff;
  border:none;
  font-weight:600;
  border-radius:8px;
  padding:8px 14px;
  box-shadow:0 6px 14px rgba(22,212,144,.3);
  transition:.25s;
}
.btn-volver:hover{
  transform:translateY(-2px);
  filter:brightness(1.15);
}
.btn-secondary{
  background:#444;
  color:#fff;
  border:none;
  transition:.3s;
}
.btn-secondary:hover{
  background:#666;
}

/* ===== FOOTER ===== */
.footer{
  text-align:center;
  color:#ffffff;
  font-size:13px;
  margin-top:20px;
  font-weight:600;
}
</style>
</head>
<body>
<div class="bg-pastillas" aria-hidden="true"></div>

<!-- BOTÓN VOLVER -->
<div class="p-3">
  <button class="btn-volver" onclick="location.href='clientes_listar.php'">
    <i class="fa-solid fa-arrow-left"></i> Volver
  </button>
</div>

<!-- FORMULARIO -->
<div class="container py-4">
  <div class="card p-4 mx-auto" style="max-width:600px;">
    <h3 class="text-center mb-4">
      <i class="fa-solid fa-user-plus"></i> <?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?>
    </h3>

    <form method="post" action="clientes_guardar.php">
      <input type="hidden" name="id" value="<?= $cliente['id'] ?? '' ?>">

      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" required value="<?= $cliente['nombre'] ?? '' ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" class="form-control" required value="<?= $cliente['apellido'] ?? '' ?>">
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Tipo Documento</label>
          <select name="tipoDocumento" class="form-select" required>
            <?php
            $tipos = ['DNI','CUIT','CUIL'];
            foreach($tipos as $t){
              $sel = ($cliente['tipoDocumento'] ?? '') === $t ? 'selected' : '';
              echo "<option $sel>$t</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Número</label>
          <input type="text" name="nroDocumento" class="form-control" required value="<?= $cliente['nroDocumento'] ?? '' ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= $cliente['telefono'] ?? '' ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= $cliente['email'] ?? '' ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Dirección</label>
        <input type="text" name="direccion" class="form-control" value="<?= $cliente['direccion'] ?? '' ?>">
      </div>

      <div class="text-center mt-4">
        <button type="submit" class="btn btn-farvec">
          <i class="fa-solid fa-floppy-disk"></i> Guardar
        </button>
        <a href="clientes_listar.php" class="btn btn-secondary ms-2">
          <i class="fa-solid fa-xmark"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
  <div class="footer">FARVEC • Módulo de Clientes • <?= date('Y') ?></div>
</div>
</body>
</html>
