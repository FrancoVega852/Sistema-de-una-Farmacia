<?php
require_once 'Conexion.php';
$conn = new Conexion();

$email = $_GET['email'] ?? '';
$pass = '1234';

if (!$email) { echo "<p>No se recibió ningún email.</p>"; exit; }

$stmt = $conn->conexion->prepare("SELECT * FROM Usuario WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<p style='color:#b93142;font-weight:600;text-align:center;'>❌ No se encontró ningún usuario con ese email.</p>";
    exit;
}

$usuario = $res->fetch_assoc();
$genero = (strtolower(substr($usuario['nombre'], -1)) === 'a') ? 'f' : 'm';
$avatar = $genero === 'f'
    ? 'https://cdn-icons-png.flaticon.com/512/4140/4140048.png'
    : 'https://cdn-icons-png.flaticon.com/512/4140/4140037.png';

echo "<div style='text-align:center;'>";
echo "<div style='width:120px;height:120px;margin:0 auto 10px;border-radius:50%;overflow:hidden;box-shadow:0 0 0 5px #00a86b55,0 0 25px #00a86b44;'>
        <img src='{$avatar}' style='width:100%;height:100%;object-fit:cover;'>
      </div>";
echo "<h3 style='color:#00794f;font-weight:800;margin:6px 0 2px;'>".htmlspecialchars($usuario['nombre'].' '.$usuario['apellido'])."</h3>";
echo "<div><strong>Rol:</strong> ".htmlspecialchars($usuario['rol'])."</div>";
echo "<div><strong>Email:</strong> ".htmlspecialchars($usuario['email'])."</div>";
echo "<div><strong>DNI:</strong> ".htmlspecialchars($usuario['dni'])."</div>";
echo "<div><strong>Creación:</strong> ".htmlspecialchars($usuario['fecha_creacion'])."</div>";
echo "</div><hr>";

$hash = $usuario['password'];
echo "<p><strong>Contraseña guardada:</strong> <code>$hash</code> (".strlen($hash)." caracteres)</p>";

if (password_get_info($hash)['algo'] !== 0) {
    echo "<p><span style='background:#e6f8ee;color:#00794f;padding:5px 12px;border-radius:10px;font-weight:600;'>Hash detectado</span> Verificando...</p>";
    $verifica = password_verify($pass, $hash);
    echo $verifica
        ? "<p><span style='background:#e6f8ee;color:#00794f;padding:5px 12px;border-radius:10px;font-weight:600;'>✅ Coincide correctamente</span></p>"
        : "<p><span style='background:#fde9e9;color:#b93142;padding:5px 12px;border-radius:10px;font-weight:600;'>❌ No coincide</span></p>";
} else {
    echo "<p><span style='background:#fffbe5;color:#7a6200;padding:5px 12px;border-radius:10px;font-weight:600;'>Texto plano</span> Comparando directamente...</p>";
    $iguales = trim($pass) === trim($hash);
    echo $iguales
        ? "<p><span style='background:#e6f8ee;color:#00794f;padding:5px 12px;border-radius:10px;font-weight:600;'>✅ Coinciden exactamente</span></p>"
        : "<p><span style='background:#fde9e9;color:#b93142;padding:5px 12px;border-radius:10px;font-weight:600;'>❌ No coinciden</span></p>";
}

echo "<div style='background:#e6f9ed;border:1px solid #bfe3cf;border-radius:10px;padding:10px;font-family:monospace;font-size:13px;color:#064e3b;overflow:auto;max-height:250px;'><pre>";
print_r($usuario);
echo "</pre></div>";
echo "<p style='text-align:center;margin-top:14px;color:#5c6f65;font-size:13px;'>FARVEC • Sistema de Diagnóstico</p>";
