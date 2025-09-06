<?php
session_start();
// ❌ Quitamos la redirección automática
// ✅ Ahora siempre mostrará la página principal
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
      animation: fadeIn 1s ease-in-out;
    }

    /* ===== HEADER ===== */
    header {
      background: var(--verde);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      animation: slideDown 1s ease;
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
      animation: fadeUp 1s ease;
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
      transition: transform 0.3s ease, background 0.3s ease;
    }

    .btn-login { background: var(--acento); color: var(--blanco); }
    .btn-registro { background: var(--verde); color: var(--blanco); }

    .btn-login:hover, .btn-registro:hover {
      transform: scale(1.1) rotate(-1deg);
    }

    /* ===== SECCIONES ===== */
    .seccion {
      max-width: 1100px;
      margin: 3rem auto;
      padding: 2rem;
      background: var(--blanco);
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      animation: fadeIn 1s ease;
    }

    .seccion h2 {
      color: var(--verde-oscuro);
      margin-bottom: 1rem;
      font-size: 1.8rem;
      animation: fadeUp 1.2s ease;
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
      opacity: 0;
      transform: translateY(20px);
      animation: fadeInUp 0.8s forwards;
    }

    .card:nth-child(1) { animation-delay: 0.2s; }
    .card:nth-child(2) { animation-delay: 0.4s; }
    .card:nth-child(3) { animation-delay: 0.6s; }
    .card:nth-child(4) { animation-delay: 0.8s; }
    .card:nth-child(5) { animation-delay: 1s; }
    .card:nth-child(6) { animation-delay: 1.2s; }
    .card:nth-child(7) { animation-delay: 1.4s; }
    .card:nth-child(8) { animation-delay: 1.6s; }

    .card:hover {
      background: var(--verde);
      color: var(--blanco);
      transform: scale(1.08);
    }

    /* ===== FOOTER ===== */
    footer {
      background: var(--verde-oscuro);
      color: var(--blanco);
      text-align: center;
      padding: 2rem 1rem;
      margin-top: 2rem;
      animation: slideUp 1s ease;
    }

    /* ===== KEYFRAMES ===== */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideDown {
      from { transform: translateY(-60px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @keyframes slideUp {
      from { transform: translateY(60px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
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
    <a href="Login.php" class="btn-login">Iniciar Sesión</a>
    <a href="Registro.php" class="btn-registro">Registrarse</a>
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
