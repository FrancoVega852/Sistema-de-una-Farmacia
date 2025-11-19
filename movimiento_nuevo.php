<?php
session_start();
require_once "Conexion.php";

if (!isset($_SESSION['usuario_id'])) {
    echo "<div style='padding:20px;font-family:Arial'>
            <h3 style='color:#b93142'>Error de sesión</h3>
            <p>Debes iniciar sesión nuevamente.</p>
          </div>";
    exit;
}

$conn = new Conexion();
$db   = $conn->conexion;
$db->set_charset("utf8mb4");
?>
<style>
.form-panel{
    background:#fff;
    padding:20px;
    border-radius:16px;
    border:1px solid #e4e7e7;
    box-shadow:0 4px 18px rgba(0,0,0,.08);
    max-width:600px;
}
.form-panel h3{
    margin:0 0 14px;
    color:#00794f;
    font-size:22px;
    font-weight:800;
}
.input-group{
    margin-bottom:14px;
    display:flex;
    flex-direction:column;
}
label{
    font-size:14px;
    font-weight:600;
    margin-bottom:4px;
}
input,select,textarea{
    padding:9px 12px;
    border:1px solid #c9d3d2;
    border-radius:10px;
    font-size:14px;
}
.btn{
    padding:10px 16px;
    border-radius:10px;
    border:0;
    cursor:pointer;
    font-weight:700;
}
.btn-green{
    background:#00794f;
    color:#fff;
}
.btn-green:hover{
    background:#006a44;
}
.btn-gray{
    background:#e4e7e7;
    color:#333;
}
</style>

<div class="form-panel">
    <h3><i class="fa-solid fa-money-bill-transfer"></i> Nuevo movimiento</h3>

    <form id="formMov">
        <div class="input-group">
            <label>Tipo de movimiento</label>
            <select name="tipo" required>
                <option value="">Seleccionar…</option>
                <option value="Ingreso">Ingreso</option>
                <option value="Egreso">Egreso</option>
            </select>
        </div>

        <div class="input-group">
            <label>Monto ($)</label>
            <input type="number" name="monto" step="0.01" min="0" required>
        </div>

        <div class="input-group">
            <label>Descripción</label>
            <textarea name="descripcion" rows="3" required></textarea>
        </div>

        <div style="display:flex;gap:10px;margin-top:10px">
            <button class="btn btn-green" type="submit">
                <i class="fa-solid fa-check"></i> Guardar
            </button>

            <button class="btn btn-gray" type="button" onclick="history.back()">
                Cancelar
            </button>
        </div>
    </form>
</div>

<script>
document.querySelector("#formMov").addEventListener("submit", async e => {
    e.preventDefault();
    const fd = new FormData(e.target);

    // 🔥 ENVÍA CORRECTAMENTE AL ARCHIVO movimiento_guardar.php
    const res = await fetch("movimiento_guardar.php", {
        method: "POST",
        body: fd
    });

    const json = await res.json();

    if (json.ok) {
        // 🔥 MENSAJE + RECARGA DINÁMICA FARVEC PRO
        showToast("Movimiento registrado ✔");
        cargarModulo('finanzas.php','Finanzas',{wrapTitle:true});
    } else {
        alert("Error: " + json.msg);
    }
});
</script>
