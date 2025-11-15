<?php
session_start();
require_once 'Conexion.php';
require_once 'ControladorClientes.php';

if (!isset($_SESSION['usuario_id'])) { header("Location: login.php"); exit(); }

$conn = new Conexion();
$ctl = new ControladorClientes($conn->conexion);
$clientes = $ctl->listar();
$total = $conn->conexion->query("SELECT COUNT(*) AS c FROM Cliente")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Clientes - FARVEC</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
.module-clientes {
  animation: slideIn .45s cubic-bezier(.25,.46,.45,.94);
  background:#fff;
  border:1px solid #e7eceb;
  border-radius:16px;
  box-shadow:0 8px 20px rgba(0,121,79,.1);
  padding:22px;
}
@keyframes slideIn {
  from { opacity:0; transform:translateX(-40px); }
  to { opacity:1; transform:translateX(0); }
}
.header-clientes {
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
  margin-bottom:20px;
}
.header-clientes h2 {
  color:#00794f; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;
}
.header-clientes .btn {
  background:#00794f; color:#fff; border:none; border-radius:10px;
  padding:10px 16px; font-weight:600; cursor:pointer; transition:.25s;
}
.header-clientes .btn:hover { filter:brightness(1.15); transform:translateY(-2px); }
.table-clientes {
  width:100%; border-collapse:collapse; font-size:14px;
}
.table-clientes th {
  background:#00794f; color:#fff; text-align:center; padding:10px; font-weight:700;
}
.table-clientes td {
  border-bottom:1px solid #e7eceb; padding:10px; text-align:center;
}
.table-clientes tr:hover { background:#f1f5f4; transition:.25s; }

.search-bar {
  display:flex; align-items:center; gap:8px;
  background:#f7faf9; border:1px solid #e7eceb; border-radius:10px;
  padding:8px 12px; box-shadow:inset 0 0 0 1px rgba(0,121,79,.1);
  margin-bottom:16px;
}
.search-bar input {
  border:none; outline:none; background:transparent; width:100%;
  font-size:14px;
}
.btn-action {
  border:none; background:transparent; cursor:pointer;
  padding:4px 6px; border-radius:8px; transition:.25s;
}
.btn-action:hover { transform:translateY(-1px); }
.btn-edit { color:#00794f; }
.btn-del { color:#b93142; }
.btn-hist { color:#1662c2; }
.badge-ok {
  display:inline-block; background:#e6f7ef; color:#00794f;
  border-radius:10px; padding:4px 8px; font-weight:600; font-size:13px;
}
.badge-warn {
  display:inline-block; background:#fdecea; color:#b93142;
  border-radius:10px; padding:4px 8px; font-weight:600; font-size:13px;
}
.form-card {
  background:#f8fbfa; border:1px solid #e7eceb;
  border-radius:12px; padding:16px; margin-top:24px;
  box-shadow:0 6px 20px rgba(0,121,79,.08);
}
.form-card h3 {
  color:#00794f; margin-bottom:16px; font-weight:700;
}
.form-card input, .form-card select {
  width:100%; padding:8px; border-radius:8px; border:1px solid #cfd7d4;
  margin-bottom:12px; font-size:14px;
}
.form-card button {
  background:#00794f; color:#fff; border:none; padding:10px 16px;
  border-radius:8px; font-weight:600; transition:.25s;
}
.form-card button:hover { filter:brightness(1.15); transform:translateY(-2px); }
.toast {
  position:fixed; right:14px; bottom:14px;
  background:#fff; border:1px solid #e7eceb; padding:12px 16px;
  border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,.15);
  font-weight:600; display:none;
}
.toast.ok { border-left:4px solid #00794f; color:#00794f; }
.toast.err { border-left:4px solid #b93142; color:#b93142; }
.toast.show { display:block; animation:fade 3s ease forwards; }
@keyframes fade { 0%{opacity:0;} 10%,90%{opacity:1;} 100%{opacity:0;} }

.toast {
  z-index: 9999; /* 👈 evita que quede tapado por el modal o el fondo */
}

</style>
</head>
<body>
<section class="module-clientes">
  <div class="header-clientes">
    <h2><i class="fa-solid fa-users"></i> Gestión de Clientes</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn" id="btnNuevo"><i class="fa-solid fa-user-plus"></i> Nuevo</button>
      <button class="btn" onclick="location.href='Menu.php'"><i class="fa-solid fa-arrow-left"></i> Volver al menú</button>
    </div>
  </div>

  <div class="search-bar">
    <i class="fa-solid fa-magnifying-glass" style="color:#00794f"></i>
    <input type="text" id="buscar" placeholder="Buscar cliente por nombre, documento o correo...">
  </div>

  <table class="table-clientes" id="tablaClientes">
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Documento</th><th>Teléfono</th>
        <th>Email</th><th>Cuenta Corriente</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while($c=$clientes->fetch_assoc()): 
        $cuenta=$conn->conexion->query("SELECT saldo_actual,limite_credito FROM CuentaCorriente WHERE cliente_id=".$c['id'])->fetch_assoc();
        $saldo=$cuenta['saldo_actual']??0; $limite=$cuenta['limite_credito']??0;
      ?>
      <tr data-id="<?= $c['id'] ?>">
        <td><?= $c['id'] ?></td>
        <td><b><?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?></b></td>
        <td><?= $c['tipoDocumento'].' '.$c['nroDocumento'] ?></td>
        <td><?= htmlspecialchars($c['telefono']) ?></td>
        <td><?= htmlspecialchars($c['email']) ?></td>
        <td>
          <?php if($limite>0): ?>
            <?php if($saldo>=$limite): ?>
              <span class="badge-warn">Excedido ($<?= number_format($saldo,2) ?>)</span>
            <?php else: ?>
              <span class="badge-ok">Saldo $<?= number_format($saldo,2) ?></span>
            <?php endif; ?>
          <?php else: ?><small style="color:#888">Sin cuenta</small><?php endif; ?>
        </td>
        <td>
          <button class="btn-action btn-hist" title="Historial" onclick="abrirHistorial(<?= $c['id'] ?>)"><i class="fa-solid fa-file-invoice"></i></button>
          <button class="btn-action btn-edit" title="Editar" onclick="editarCliente(<?= $c['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
          <button class="btn-action btn-del" title="Eliminar" onclick="eliminarCliente(<?= $c['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- Formulario inline (Nuevo / Editar) -->
  <div class="form-card" id="formCliente" style="display:none;">
    <h3 id="tituloForm"><i class="fa-solid fa-user-plus"></i> Nuevo Cliente</h3>
    <form id="formData">
      <input type="hidden" name="id" id="cid">
      <input type="text" name="nombre" id="nombre" placeholder="Nombre" required>
      <input type="text" name="apellido" id="apellido" placeholder="Apellido" required>
      <select name="tipoDocumento" id="tipoDocumento" required>
        <option value="DNI">DNI</option>
        <option value="CUIT">CUIT</option>
        <option value="CUIL">CUIL</option>
      </select>
      <input type="text" name="nroDocumento" id="nroDocumento" placeholder="N° Documento" required>
      <input type="text" name="telefono" id="telefono" placeholder="Teléfono">
      <input type="email" name="email" id="email" placeholder="Email">
      <input type="text" name="direccion" id="direccion" placeholder="Dirección">
      <div style="margin-top:10px;">
        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <button type="button" onclick="cancelarForm()" style="background:#b93142"><i class="fa-solid fa-xmark"></i> Cancelar</button>
      </div>
    </form>
  </div>

  <div class="footer" style="margin-top:25px;text-align:center;color:#777;font-size:13px;">
    FARVEC • Módulo de Clientes • <?= date('Y') ?>
  </div>
</section>

<div class="toast" id="toast"></div>

<script>
const toast=document.getElementById('toast');
function showToast(msg,type='ok'){
  const icon = type==='ok' ? 'fa-circle-check' : type==='err' ? 'fa-circle-xmark' : 'fa-circle-info';
  toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${msg}`;
  toast.className='toast '+type+' show';
  setTimeout(()=>toast.classList.remove('show'),2500);
}

/* === BUSCADOR === */
document.getElementById('buscar').addEventListener('keyup',()=>{
  const q=document.getElementById('buscar').value.toLowerCase();
  document.querySelectorAll('#tablaClientes tbody tr').forEach(tr=>{
    tr.style.display=tr.textContent.toLowerCase().includes(q)?'':'none';
  });
});

/* === FORMULARIO NUEVO === */
const form=document.getElementById('formCliente');
const fdata=document.getElementById('formData');
document.getElementById('btnNuevo').onclick=()=>{ 
  form.style.display='block';
  document.getElementById('tituloForm').innerHTML='<i class="fa-solid fa-user-plus"></i> Nuevo Cliente';
  fdata.reset(); document.getElementById('cid').value='';
  form.scrollIntoView({behavior:'smooth'});
};
function cancelarForm(){ form.style.display='none'; }

/* === GUARDAR CLIENTE (JSON + agrega al final con ID real) === */
fdata.addEventListener('submit', async e => {
  e.preventDefault();
  try {
    const res = await fetch('clientes_guardar.php', { method:'POST', body:new FormData(fdata), cache:'no-store' });
    const json = await res.json();
    if(!res.ok || !json.ok) throw new Error(json.error || 'Error al guardar');

    showToast('Cliente guardado correctamente','ok');
    form.style.display='none';

    const id=json.id, c=json.cliente;
    const tbody=document.querySelector('#tablaClientes tbody');

    // 👇 Nuevo: verificar si ya existe fila con ese ID
    let fila = document.querySelector(`#tablaClientes tbody tr[data-id="${id}"]`);
    if(fila){
      // actualizar la fila existente (no duplicar)
      fila.children[1].innerHTML = `<b>${c.nombre} ${c.apellido}</b>`;
      fila.children[2].textContent = `${c.tipoDocumento} ${c.nroDocumento}`;
      fila.children[3].textContent = c.telefono || '-';
      fila.children[4].textContent = c.email || '-';
      showToast('Cliente actualizado correctamente','ok');
    } else {
      // si no existe, agregarlo al final como hacías
      const tr=document.createElement('tr');
      tr.setAttribute('data-id',id);
      tr.innerHTML=`
        <td>${id}</td>
        <td><b>${c.nombre} ${c.apellido}</b></td>
        <td>${c.tipoDocumento} ${c.nroDocumento}</td>
        <td>${c.telefono||'-'}</td>
        <td>${c.email||'-'}</td>
        <td><small style="color:#888">Sin cuenta</small></td>
        <td>
          <button class="btn-action btn-hist" title="Historial" onclick="abrirHistorial(${id})"><i class="fa-solid fa-file-invoice"></i></button>
          <button class="btn-action btn-edit" title="Editar" onclick="editarCliente(${id})"><i class="fa-solid fa-pen"></i></button>
          <button class="btn-action btn-del" title="Eliminar" onclick="eliminarCliente(${id})"><i class="fa-solid fa-trash"></i></button>
        </td>`;
      tbody.appendChild(tr);
    }

    fdata.reset();
  } catch(err) {
    showToast(err.message,'err');
  }
});


/* === EDITAR CLIENTE === */
function editarCliente(id){
  fetch('clientes_form.php?id='+id).then(r=>r.text()).then(html=>{
    const tmp=document.createElement('div'); tmp.innerHTML=html;
    document.getElementById('cid').value=tmp.querySelector('input[name=id]').value;
    document.getElementById('nombre').value=tmp.querySelector('input[name=nombre]').value;
    document.getElementById('apellido').value=tmp.querySelector('input[name=apellido]').value;
    document.getElementById('tipoDocumento').value=tmp.querySelector('select[name=tipoDocumento]').value;
    document.getElementById('nroDocumento').value=tmp.querySelector('input[name=nroDocumento]').value;
    document.getElementById('telefono').value=tmp.querySelector('input[name=telefono]').value;
    document.getElementById('email').value=tmp.querySelector('input[name=email]').value;
    document.getElementById('direccion').value=tmp.querySelector('input[name=direccion]').value;
    document.getElementById('tituloForm').innerHTML='<i class="fa-solid fa-pen"></i> Editar Cliente';
    form.style.display='block';
    form.scrollIntoView({behavior:'smooth'});
  }).catch(()=>showToast('Error al cargar cliente','err'));
}

/* === ELIMINAR CLIENTE (JSON + animación + actualización) === */
function eliminarCliente(id){
  if(!confirm('¿Seguro que querés eliminar este cliente?')) return;
  fetch('clientes_eliminar.php?id='+id,{cache:'no-store'})
    .then(r=>r.json().then(j=>({ok:r.ok,j})))
    .then(({ok,j})=>{
      if(!ok||!j.ok) throw new Error(j.error||'No se pudo eliminar');
      const fila=document.querySelector(`#tablaClientes tbody tr[data-id="${id}"]`);
      if(fila){
        fila.style.transition='all .35s ease';
        fila.style.opacity='0'; fila.style.transform='translateX(-18px)';
        setTimeout(()=>fila.remove(),350);
      }
      showToast('Cliente eliminado correctamente','ok');
    })
    .catch(err=>showToast(err.message||'Error al eliminar','err'));
}

/* === HISTORIAL === */
function abrirHistorial(id){
  let modal=document.getElementById('modalHistorial');
  if(!modal){
    modal=document.createElement('div');
    modal.id='modalHistorial';
    modal.innerHTML=`
    <div class="overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:200;">
      <div style="background:#fff;padding:20px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-width:720px;width:90%;max-height:80vh;overflow:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <h3 style="margin:0;color:#00794f;font-weight:700"><i class="fa-solid fa-file-invoice"></i> Historial del Cliente #${id}</h3>
          <button onclick="cerrarHistorial()" style="background:#00794f;color:#fff;border:none;border-radius:8px;padding:6px 10px;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="contenidoHistorial" style="font-size:14px;color:#333;text-align:center;padding:10px;">Cargando...</div>
      </div>
    </div>`;
    document.body.appendChild(modal);
  }
  document.querySelector('#contenidoHistorial').innerHTML='Cargando...';
  modal.style.display='flex';
  fetch('clientes_historial.php?id='+id,{cache:'no-store'})
    .then(r=>r.text())
    .then(html=>document.querySelector('#contenidoHistorial').innerHTML=html||'<p>Sin registros de compras.</p>')
    .catch(()=>document.querySelector('#contenidoHistorial').innerHTML='<p>Error al cargar historial.</p>');
}
function cerrarHistorial(){const m=document.getElementById('modalHistorial');if(m)m.style.display='none';}
</script>
</body>
</html>
