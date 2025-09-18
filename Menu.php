<?php
session_start();
include 'Conexion.php';

class MenuController {
    private $conn;
    private $usuario = null;

    public function __construct($conexion) {
        $this->conn = $conexion;
        $this->verificarSesion();
    }

    private function verificarSesion() {
        if (!isset($_SESSION["usuario_id"])) {
            header("Location: login.php");
            exit();
        }
    }

    public function obtenerUsuario() {
        $usuario_id = $_SESSION["usuario_id"];
        $sql = "SELECT nombre, email, rol FROM Usuario WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $this->usuario = $resultado->fetch_assoc();
        return $this->usuario;
    }
}

// =========================
// Uso del controlador
// =========================
$conn = new Conexion();
$menu = new MenuController($conn->conexion);
$usuario = $menu->obtenerUsuario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Farvec - Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      flex-direction: row;
      background-color: var(--gris);
    }

    /* ===== SIDEBAR ===== */
    .sidebar {
      width: 240px;
      background-color: var(--verde);
      color: var(--blanco);
      padding: 20px;
      box-shadow: 3px 0 10px rgba(0, 0, 0, 0.2);
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: slideIn 0.8s ease;

      height: 100vh; /* ✅ ahora la barra ocupa toda la pantalla */
    }

    .sidebar h2 {
      margin: 0 0 20px 0;
      font-size: 22px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .user-info {
      font-size: 14px;
      margin-bottom: 30px;
      text-align: center;
    }

    ul {
      list-style: none;
      padding: 0;
      width: 100%;
    }

    ul li {
      margin: 15px 0;
    }

    ul li a {
      text-decoration: none;
      color: var(--blanco);
      display: flex;
      align-items: center;
      padding: 10px;
      border-radius: 8px;
      transition: background-color 0.3s, transform 0.2s;
    }

    ul li a:hover {
      background-color: var(--verde-oscuro);
      transform: scale(1.05);
    }

    ul li a i {
      margin-right: 10px;
      font-size: 18px;
    }

    /* ===== CONTENIDO ===== */
    .contenido {
      flex: 1;
      padding: 40px;
      min-width: 0;
      box-sizing: border-box;
      animation: fadeIn 1s ease;
    }

    h1 {
      font-size: 26px;
      color: var(--verde-oscuro);
      animation: fadeUp 1s ease;
    }

    .card {
      background: var(--blanco);
      padding: 20px;
      margin: 20px 0;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }

    /* ===== ANIMACIONES ===== */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideIn {
      from { transform: translateX(-250px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      body { flex-direction: column; }
      .sidebar {
        width: 100%;
        height: auto;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: center;
        padding: 10px;
      }
      .sidebar h2, .user-info { display: none; }
      .sidebar ul { display: flex; flex-wrap: wrap; justify-content: center; }
      .sidebar ul li { margin: 5px; }
      .sidebar ul li a { padding: 8px 12px; font-size: 14px; }
      .contenido { padding: 20px; }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>Farvec</h2>
    <div class="user-info">
      <strong><?= htmlspecialchars($usuario["nombre"]) ?></strong><br>
      <small><?= htmlspecialchars($usuario["email"]) ?></small><br>
      <em><?= htmlspecialchars($usuario["rol"]) ?></em>
    </div>
    <ul>
      <?php if ($usuario['rol'] === 'Administrador' || $usuario['rol'] === 'Farmaceutico'): ?>
        <li><a href="stock.php"><i class="fa-solid fa-capsules"></i> Stock y Lotes</a></li>
        <li><a href="Historial.php"><i class="fa-solid fa-clipboard-list"></i> Historial Stock</a></li>
      <?php endif; ?>
      <li><a href="ventas.php"><i class="fa-solid fa-cash-register"></i> Ventas</a></li>
      <li><a href="compras.php"><i class="fa-solid fa-truck"></i> Compras</a></li>
      <li><a href="clientes.php"><i class="fa-solid fa-users"></i> Clientes</a></li>
      <li><a href="reportes.php"><i class="fa-solid fa-chart-line"></i> Reportes</a></li>
      <li><a href="logout.php"><i class="fa-solid fa-door-open"></i> Cerrar sesión</a></li>
    </ul>
  </div>

  <div class="contenido">
    <h1>Bienvenido, <?= htmlspecialchars($usuario["nombre"]) ?> 👋</h1>
    <div class="card">
      <h2>📊 Panel de gestión</h2>
      <p>Seleccioná una opción del menú para comenzar a trabajar en el sistema de farmacia.</p>
    </div>
  </div>
</body>
</html>
