<?php
// ========================================================================
//  PROVEEDORES – MÓDULO DINÁMICO FARVEC PRO 2025 + BOOTSTRAP + ANIMACIONES
// ========================================================================

session_start();
require_once "Conexion.php";

$conn = new Conexion();
$db = $conn->conexion;
$db->set_charset("utf8mb4");

/* ============================
   DETECTAR CARGA DINÁMICA (fetch)
============================ */
$isAjax = (
    isset($_SERVER["HTTP_SEC_FETCH_MODE"]) &&
    ($_SERVER["HTTP_SEC_FETCH_MODE"] === "cors" || $_SERVER["HTTP_SEC_FETCH_MODE"] === "no-cors")
);

/* ============================
   CONTROL DE SESIÓN
============================ */
if (!isset($_SESSION['usuario_id'])) {
    if ($isAjax) {
        echo "<div class='alert alert-danger m-3'>Sesión expirada. Volvé a iniciar sesión.</div>";
        return;
    } else {
        header("Location: login.php");
        exit();
    }
}

function safe($v){ return htmlspecialchars($v ?? "", ENT_QUOTES, "UTF-8"); }

$msg = "";

/* ============================
   INSERT / UPDATE
============================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["razonSocial"])) {

    $id  = $_POST["id"] ?? "";
    $rs  = trim($_POST["razonSocial"]);
    $cuit = trim($_POST["cuit"]);
    $tel = trim($_POST["telefono"]);
    $email = trim($_POST["email"]);
    $dir = trim($_POST["direccion"]);

    if ($id === "") {
        $st = $db->prepare("INSERT INTO Proveedor(razonSocial,cuit,telefono,email,direccion)
                            VALUES (?,?,?,?,?)");
        $st->bind_param("sssss", $rs, $cuit, $tel, $email, $dir);
        $st->execute();
        $msg = "Proveedor agregado correctamente.";
    } else {
        $st = $db->prepare("UPDATE Proveedor 
                            SET razonSocial=?,cuit=?,telefono=?,email=?,direccion=? 
                            WHERE id=?");
        $st->bind_param("sssssi", $rs, $cuit, $tel, $email, $dir, $id);
        $st->execute();
        $msg = "Proveedor actualizado.";
    }
}

/* ============================
   ELIMINAR
============================ */
if (isset($_GET["del"])) {
    $id = (int)$_GET["del"];
    $db->query("DELETE FROM Proveedor WHERE id=$id");
    $msg = "Proveedor eliminado.";
}

/* ============================
   LISTADO
============================ */
$q = $_GET["q"] ?? "";
$qSql = ($q !== "") ? "WHERE razonSocial LIKE '%$q%' OR cuit LIKE '%$q%'" : "";

$res = $db->query("SELECT * FROM Proveedor $qSql ORDER BY razonSocial ASC");
$proveedores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

?>

<!-- BOOTSTRAP 5.3 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* PANEL PREMIUM */
.prov-panel{
    background:#ffffff;
    border-radius:20px;
    padding:30px;
    border:1px solid #e1e7e4;
    box-shadow:0 15px 45px rgba(0,121,79,.15);
    animation:fadeIn .6s ease;
}

/* TITULO */
.prov-title{
    font-size:30px;
    font-weight:900;
    color:#00794f;
    margin-bottom:25px;
    display:flex;
    align-items:center;
    gap:12px;
}

/* TARJETA FORMULARIO */
.form-card{
    background:#f8fbfa;
    border-radius:16px;
    padding:22px;
    border:1px solid #d9e3df;
    box-shadow:0 8px 25px rgba(0,121,79,.10);
    animation:fadeIn .7s ease;
}

/* INPUT */
.form-control:focus{
    border-color:#00a86b;
    box-shadow:0 0 0 3px rgba(0,168,107,.2);
}

/* BOTONES */
.btn-fv{
    background:linear-gradient(135deg,#00a86b,#008e5a);
    border:none;
    padding:10px 20px;
    font-weight:700;
    border-radius:14px;
    box-shadow:0 6px 15px rgba(0,168,107,.25);
    transition:0.25s;
}
.btn-fv:hover{
    transform:translateY(-3px) scale(1.03);
    box-shadow:0 12px 25px rgba(0,168,107,.35);
}

/* TABLA PREMIUM */
.table-container{
    margin-top:20px;
    animation:fadeIn .75s ease;
}

.table-premium tbody tr{
    background:white;
    box-shadow:0 3px 12px rgba(0,0,0,.06);
    border-radius:12px;
    transition:.2s;
}
.table-premium tbody tr:hover{
    transform:scale(1.01);
    box-shadow:0 6px 20px rgba(0,0,0,.12);
}

.action-edit{
    background:#00a86b;
}
.action-del{
    background:#b93142;
}

/* ANIMACIONES */
@keyframes fadeIn{
    from{opacity:0;transform:translateY(12px);}
    to{opacity:1;transform:none;}
}
</style>

<div class="prov-panel">

    <h2 class="prov-title">
        <i class="fa-solid fa-truck-field"></i> Gestión de Proveedores
    </h2>

    <?php if($msg): ?>
        <div class="alert alert-success fw-bold"><?= safe($msg) ?></div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <div class="form-card mb-4">
        <form method="POST" onsubmit="return saveProv(event)">
            <input type="hidden" name="id" id="id">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="fw-bold">Razón Social</label>
                    <input class="form-control" type="text" name="razonSocial" id="razonSocial" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">CUIT</label>
                    <input class="form-control" type="text" name="cuit" id="cuit" required>
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Teléfono</label>
                    <input class="form-control" type="text" name="telefono" id="telefono">
                </div>
                <div class="col-md-6">
                    <label class="fw-bold">Email</label>
                    <input class="form-control" type="email" name="email" id="email">
                </div>
                <div class="col-12">
                    <label class="fw-bold">Dirección</label>
                    <input class="form-control" type="text" name="direccion" id="direccion">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn-fv">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar proveedor
                </button>
                <button type="button" onclick="clearForm()" class="btn btn-secondary fw-bold px-3 rounded-3">
                    <i class="fa-solid fa-broom"></i> Limpiar
                </button>
            </div>
        </form>
    </div>

    <!-- BUSCADOR -->
    <form class="mb-3">
        <input class="form-control shadow-sm" type="text" name="q"
               placeholder="Buscar proveedor..."
               value="<?= safe($q) ?>">
    </form>

    <!-- TABLA -->
    <div class="table-container">
        <table class="table table-premium align-middle">
            <thead class="table-success text-center">
                <tr>
                    <th>Razón Social</th>
                    <th>CUIT</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Dirección</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>

            <?php if(!$proveedores): ?>
                <tr><td colspan="6" class="text-center fw-bold py-3">No hay proveedores registrados.</td></tr>
            <?php else: foreach ($proveedores as $p): ?>
                <tr>
                    <td><?= safe($p["razonSocial"]) ?></td>
                    <td><?= safe($p["cuit"]) ?></td>
                    <td><?= safe($p["telefono"]) ?></td>
                    <td><?= safe($p["email"]) ?></td>
                    <td><?= safe($p["direccion"]) ?></td>
                    <td class="text-center">

                        <!-- BOTÓN EDITAR -->
                        <button class="btn btn-sm action-edit text-white rounded-3"
                                onclick='editProv(<?= json_encode($p) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <!-- BOTÓN ELIMINAR (ARREGLADO) -->
                        <button type="button"
                                class="btn btn-sm action-del text-white rounded-3 ms-1"
                                onclick="deleteProv(<?= (int)$p['id'] ?>)">
                            <i class="fa-solid fa-trash"></i>
                        </button>

                    </td>
                </tr>
            <?php endforeach; endif; ?>

            </tbody>
        </table>
    </div>

</div>

<script>
function editProv(p){
    id.value = p.id;
    razonSocial.value = p.razonSocial;
    cuit.value = p.cuit;
    telefono.value = p.telefono;
    email.value = p.email;
    direccion.value = p.direccion;
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function clearForm(){
    id.value="";
    razonSocial.value="";
    cuit.value="";
    telefono.value="";
    email.value="";
    direccion.value="";
}

/* Guardar sin recargar */
async function saveProv(e){
    e.preventDefault();

    const form = e.target;
    const data = new FormData(form);

    const res = await fetch("proveedores.php", {
        method: "POST",
        body: data
    });

    const html = await res.text();
    document.querySelector(".module-dynamic").innerHTML = html;
}

/* =============================
   ELIMINAR (ARREGLADO)
   ============================= */
async function deleteProv(id){
    if (!confirm("¿Eliminar proveedor?")) return;

    const res = await fetch("proveedores.php?del=" + id);
    const html = await res.text();

    document.querySelector(".module-dynamic").innerHTML = html;
}
</script>
