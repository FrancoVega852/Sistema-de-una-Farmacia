<?php
session_start();

// Si el usuario ya está logueado, redirigir a menú principal
if (isset($_SESSION['usuario_id'])) {
    header("Location: menu.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sistema Farmacia - Farvec</title>
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --texto: #222222;
      --acento: #e85c4a;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: var(--gris);
      color: var(--texto);
      line-height: 1.6;
    }

    /* ===== HEADER ===== */
    header {
      background: var(--verde);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      font-weight: bold;
      font-size: 2rem;
      color: var(--blanco);
    }

    .logo-img {
      height: 60px;
      width: auto;
      animation: pulso 2s infinite;
    }

    @keyframes pulso {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    nav a {
      color: var(--blanco);
      margin: 0 1rem;
      text-decoration: none;
      font-weight: bold;
      transition: 0.3s;
      font-size: 1.1rem;
    }

    nav a:hover { color: var(--acento); }

    /* ===== HERO ===== */
    .hero {
      text-align: center;
      padding: 5rem 2rem;
      background: linear-gradient(135deg, #f9f9f9, #e6f4ec);
    }

    .hero h2 {
      font-size: 2.8rem;
      margin-bottom: 1rem;
      color: var(--verde-oscuro);
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .hero p { font-size: 1.3rem; color: #444; }

    .botones { margin-top: 2rem; }

    .botones a {
      display: inline-block;
      padding: 0.8rem 1.8rem;
      border-radius: 8px;
      margin: 0 0.5rem;
      text-decoration: none;
      font-weight: bold;
      font-size: 1rem;
      box-shadow: 0 3px 6px rgba(0,0,0,0.2);
    }

    .btn-login { background: var(--acento); color: var(--blanco); }
    .btn-registro { background: var(--verde); color: var(--blanco); }

    .btn-login:hover { background: #d94c3c; }
    .btn-registro:hover { background: #007a40; }

    /* ===== SECCIONES ===== */
    .seccion {
      max-width: 1100px;
      margin: 3rem auto;
      padding: 2rem;
      background: var(--blanco);
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .seccion h2 {
      color: var(--verde-oscuro);
      margin-bottom: 1rem;
      font-size: 1.8rem;
    }

    .funcionalidades {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }

    .card {
      background: var(--gris);
      padding: 1.5rem;
      border-radius: 8px;
      text-align: center;
      font-weight: bold;
      color: var(--verde-oscuro);
      transition: 0.3s;
    }

    .card:hover {
      background: var(--verde);
      color: var(--blanco);
      transform: scale(1.05);
    }

    /* ===== FOOTER ===== */
    footer {
      background: var(--verde-oscuro);
      color: var(--blanco);
      text-align: center;
      padding: 2rem 1rem;
      margin-top: 2rem;
    }
  </style>
</head>
<body>

<header>
  <div class="logo">
    <img src="Logo.png" alt="Logo Farvec" class="logo-img">
    Farvec
  </div>
  <nav>
    <a href="#">Home</a>
    <a href="#">Funcionalidades</a>
    <a href="#">Nosotros</a>
    <a href="#">Contacto</a>
  </nav>
</header>

<section class="hero">
  <h2>La salud de tu farmacia está en la gestión</h2>
  <p>Sistema integral para administrar ventas, stock, recetas y más.</p>
  <div class="botones">
    <a href="login.php" class="btn-login">Iniciar Sesión</a>
    <a href="registro.php" class="btn-registro">Registrarse</a>
  </div>
</section>

<section class="seccion">
  <h2>⚙ Funcionalidades</h2>
  <div class="funcionalidades">
    <div class="card">Ventas</div>
    <div class="card">Compras</div>
    <div class="card">Stock y Lotes</div>
    <div class="card">Recetas</div>
    <div class="card">Proveedores</div>
    <div class="card">Cuentas Corrientes</div>
    <div class="card">Alertas</div>
    <div class="card">Reportes</div>
  </div>
</section>

<section class="seccion">
  <h2>🌟 Nuestra Esencia</h2>
  <p><strong>Misión:</strong> Facilitar la gestión farmacéutica con herramientas tecnológicas modernas.</p>
  <p><strong>Visión:</strong> Ser el sistema de gestión de farmacias más confiable de la región.</p>
  <p><strong>Valores:</strong> Innovación, Transparencia, Compromiso con la salud.</p>
</section>

<footer>
  <p><strong>Integrantes del proyecto:</strong> Cáceres Facundo, Candia César, Román Mario, Vega Franco</p>
  <p>&copy; 2025 Farvec - Sistema Académico de Farmacia</p>
</footer>

</body>
</html>
