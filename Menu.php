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
      background: var(--gris);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ====== NAV SUPERIOR ====== */
    .topbar {
      width: 100%;
      background: var(--verde);
      color: var(--blanco);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 20px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1001;
    }

    .topbar .logo {
      display: flex;
      align-items: center;
    }

    .topbar .logo img {
      width: 40px;
      margin-right: 10px;
    }

    .topbar h1 {
      font-size: 20px;
      margin: 0;
      font-weight: bold;
    }

    .topbar .user {
      font-size: 14px;
    }

    .menu-toggle {
      display: none;
      font-size: 22px;
      cursor: pointer;
    }

    /* ====== SIDEBAR ====== */
    .sidebar {
      width: 240px;
      background: var(--verde-oscuro);
      color: var(--blanco);
      height: 100%;
      position: fixed;
      top: 50px;
      left: 0;
      overflow-y: auto;
      padding-top: 20px;
      transition: all 0.3s ease;
      z-index: 1002;
    }

    .sidebar.hidden {
      left: -250px;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .sidebar ul li {
      position: relative;
    }

    .sidebar ul li a {
      display: block;
      color: var(--blanco);
      padding: 12px 20px;
      text-decoration: none;
      transition: all 0.3s;
      font-weight: 500;
    }

    .sidebar ul li a:hover {
      background: var(--verde);
      padding-left: 25px;
    }

    .sidebar ul li a i {
      margin-right: 10px;
    }

    /* SUBMENÚ */
    .sidebar ul li ul {
      display: none;
      background: #005f2b;
    }

    .sidebar ul li:hover ul {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    .sidebar ul li ul li a {
      padding: 10px 40px;
      font-size: 14px;
    }

    /* ====== OVERLAY ====== */
    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
    }

    .overlay.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    /* ====== CONTENIDO ====== */
    .contenido {
      margin-left: 240px;
      padding: 70px 20px;
      transition: margin-left 0.3s ease;
    }

    .sidebar.hidden + .contenido {
      margin-left: 0;
    }

    .card {
      background: var(--blanco);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      animation: fadeUp 0.8s ease;
    }

    /* ====== ANIMACIONES ====== */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(15px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
      .menu-toggle { display: block; }
      .sidebar { left: -250px; top: 50px; }
      .contenido { margin-left: 0; padding: 70px 10px; }
    }
  </style>
</head>
<body>
  <!-- Barra superior -->
  <div class="topbar">
    <div class="logo">
      <img src="Logo.png" alt="Logo">
      <h1>FARVEC</h1>
    </div>
    <div class="user">
      <i class="fa fa-user"></i> <?= htmlspecialchars($usuario["nombre"]) ?> (<?= htmlspecialchars($usuario["rol"]) ?>)
    </div>
    <div class="menu-toggle" onclick="toggleMenu()">
      <i class="fa fa-bars"></i>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <ul>
      <?php if ($usuario['rol'] === 'Administrador' || $usuario['rol'] === 'Farmaceutico'): ?>
        <li>
          <a href="#"><i class="fa-solid fa-capsules"></i> Stock</a>
          <ul>
            <li><a href="stock.php">Ver Stock y Lotes</a></li>
            <li><a href="Historial.php">Historial Stock</a></li>
          </ul>
        </li>
      <?php endif; ?>
      <li>
        <a href="#"><i class="fa-solid fa-cash-register"></i> Ventas</a>
        <ul>
          <li><a href="ventas.php">Nueva Venta</a></li>
          <li><a href="ventas_listar.php">Listado de Ventas</a></li>
        </ul>
      </li>
      <li>
        <a href="#"><i class="fa-solid fa-truck"></i> Compras</a>
        <ul>
          <li><a href="compras.php">Nueva Compra</a></li>
          <li><a href="compras_listar.php">Listado de Compras</a></li>
        </ul>
      </li>
      <li>
        <a href="#"><i class="fa-solid fa-users"></i> Clientes</a>
        <ul>
          <li><a href="clientes.php">Gestionar Clientes</a></li>
        </ul>
      </li>
      <li>
        <a href="#"><i class="fa-solid fa-chart-line"></i> Reportes</a>
      </li>
      <li>
        <a href="logout.php"><i class="fa-solid fa-door-open"></i> Cerrar sesión</a>
      </li>
    </ul>
  </div>

  <!-- Overlay -->
  <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

  <!-- Contenido -->
  <div class="contenido" id="contenido">
    <h1>Bienvenido, <?= htmlspecialchars($usuario["nombre"]) ?> 👋</h1>
    <div class="card">
      <h2>📊 Panel de gestión</h2>
      <p>Seleccioná una opción del menú lateral para comenzar a trabajar en el sistema de farmacia.</p>
    </div>
  </div>

  <script>
    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("hidden");
      document.getElementById("overlay").classList.toggle("active");
    }
  </script>
</body>
</html>
