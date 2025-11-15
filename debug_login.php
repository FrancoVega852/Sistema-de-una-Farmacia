<?php
session_start();
require_once 'Conexion.php';
require_once 'Usuario.php';

$conn = new Conexion();
$usr  = new Usuario($conn->conexion);

$usuarios = $conn->conexion->query("SELECT email, CONCAT(nombre,' ',apellido) AS nombre FROM Usuario ORDER BY nombre ASC");
?>
<div class="contenido-modulo p-4" style="animation:fadeIn .4s ease both;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="titulo-modulo">
      <i class="fa-solid fa-shield-halved"></i> Diagnóstico de Login
    </h2>
    <button class="btnVolver" onclick="location.href='Menu.php'">
      <i class="fa-solid fa-arrow-left"></i> Volver al Menú
    </button>
  </div>

  <!-- BUSCADOR / SELECTOR -->
  <form id="formBusqueda" class="d-flex align-items-center gap-2 mb-4" onsubmit="return false" style="max-width:520px;">
    <select id="usuarioSelect" class="form-control" style="flex:1;border-radius:12px;border:1px solid #bfe3cf;padding:10px;font-size:15px;">
      <option value="">Seleccionar usuario...</option>
      <?php while($u = $usuarios->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($u['email']) ?>">
          <?= htmlspecialchars($u['nombre'])." (".$u['email'].")" ?>
        </option>
      <?php endwhile; ?>
    </select>
    <button id="buscarBtn" type="button" class="btnBuscar">
      <i class="fa-solid fa-magnifying-glass"></i> Buscar
    </button>
  </form>

  <!-- RESULTADO -->
  <div id="resultadoLogin" class="card shadow" style="
    background:#fff;
    border-radius:22px;
    box-shadow:0 8px 22px rgba(0,0,0,.08);
    padding:32px;
    max-width:820px;
    margin:auto;
  ">
    <p style="color:#5c6f65;text-align:center;">Seleccione un usuario para ver el diagnóstico.</p>
  </div>
</div>

<style>
@keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}
.titulo-modulo{
  color:#005c3f;
  font-size:1.6rem;
  font-weight:800;
  margin-bottom:0;
  text-shadow:0 0 5px #8df5ca;
}
.btnVolver{
  background:linear-gradient(90deg,#00794f,#00a86b);
  color:#fff;
  border:none;
  border-radius:12px;
  padding:10px 18px;
  font-weight:600;
  cursor:pointer;
  box-shadow:0 6px 18px rgba(0,121,79,.25);
  transition:.25s;
}
.btnVolver:hover{transform:translateY(-2px);filter:brightness(1.05);}
.btnBuscar{
  background:linear-gradient(90deg,#00794f,#00a86b);
  color:#fff;
  border:none;
  border-radius:12px;
  padding:10px 16px;
  font-weight:600;
  cursor:pointer;
  box-shadow:0 6px 18px rgba(0,121,79,.25);
  transition:.25s;
}
.btnBuscar:hover{transform:translateY(-2px);filter:brightness(1.05);}
</style>

<script>
// --- BUSCADOR AJAX ---
document.getElementById('buscarBtn').addEventListener('click', () => {
  const email = document.getElementById('usuarioSelect').value;
  const result = document.getElementById('resultadoLogin');
  if (!email) {
    result.innerHTML = "<p style='color:#5c6f65;text-align:center;'>Seleccione un usuario válido.</p>";
    return;
  }
  result.innerHTML = "<p style='color:#00794f;text-align:center;'>🔍 Cargando diagnóstico...</p>";

  fetch('debug_login_ajax.php?email=' + encodeURIComponent(email))
    .then(r => r.text())
    .then(html => result.innerHTML = html)
    .catch(e => result.innerHTML = "<p style='color:#b93142;text-align:center;'>Error al cargar: " + e.message + "</p>");
});
</script>
