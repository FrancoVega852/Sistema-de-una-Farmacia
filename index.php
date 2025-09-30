<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Farvec - Sistema de Gestión Farmacéutica</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    :root {
      --verde: #008f4c;
      --verde-oscuro: #006837;
      --blanco: #ffffff;
      --gris: #f4f4f4;
      --acento: #e85c4a;
      --texto: #1f2937;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #eafdf3, #ffffff); color: var(--texto); }

    /* HEADER */
    header {
      background: var(--verde);
      padding: 1rem 2rem;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .logo { display:flex; align-items:center; gap:10px; color:var(--blanco); font-size:1.6rem; font-weight:700; }
    .logo img { width:40px; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
    nav a {
      color: var(--blanco); text-decoration:none; margin:0 12px; font-weight:600; transition:.3s;
    }
    nav a:hover { color: var(--acento); }

    /* HERO */
    .hero {
      background: transparent;
      text-align: center; padding: 4rem 2rem;
    }
    .hero h1 { font-size: 2.8rem; margin-bottom: 1rem; color: var(--verde-oscuro); }
    .hero p { font-size: 1.2rem; margin-bottom: 2rem; }
    .hero .btn {
      display:inline-block; padding:0.9rem 1.6rem; margin:0 8px; border-radius:8px;
      font-weight:700; text-decoration:none; transition:.3s; box-shadow:0 4px 8px rgba(0,0,0,0.15);
    }
    .btn-login { background: var(--acento); color: var(--blanco); }
    .btn-registro { background: var(--verde); color: var(--blanco); }
    .btn-login:hover, .btn-registro:hover { transform: scale(1.05); }

    /* KPIs */
    .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin:2rem auto; max-width:1100px; }
    .kpi {
      background:var(--blanco); padding:1.5rem; border-radius:12px; text-align:center;
      box-shadow:0 4px 10px rgba(0,0,0,0.08); transition:.3s;
    }
    .kpi:hover { transform:translateY(-4px); }
    .kpi i { font-size:2rem; color:var(--verde); margin-bottom:10px; }
    .kpi h3 { margin:0; font-size:1.5rem; }
    .kpi small { color:#6b7280; }

    /* FUNCIONALIDADES */
    .seccion { max-width: 1100px; margin: 3rem auto; padding: 2rem; background: var(--blanco);
      border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .seccion h2 { margin-bottom: 1.5rem; color: var(--verde-oscuro); text-align:center; }
    .funcionalidades { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; }
    .card {
      padding:1.5rem; background:var(--gris); border-radius:10px; text-align:center;
      font-weight:600; color:var(--verde-oscuro); cursor:pointer;
      transition:.3s; box-shadow:0 4px 10px rgba(0,0,0,0.08);
    }
    .card i { font-size:2rem; margin-bottom:8px; display:block; }
    .card:hover { background: var(--verde); color: var(--blanco); transform:translateY(-5px); }

    /* SLIDER DE NOTICIAS */
    .slider { background:var(--verde-oscuro); color:var(--blanco); padding:1rem; overflow:hidden; white-space:nowrap; }
    .slider span { display:inline-block; padding-right:3rem; animation: scroll 15s linear infinite; }
    @keyframes scroll { from { transform:translateX(100%);} to { transform:translateX(-100%);} }

    /* FOOTER */
    footer {
      background:var(--verde); color:var(--blanco); padding:2rem; margin-top:2rem;
      display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.5rem;
    }
    footer h3 { margin-bottom:1rem; }
    footer a { color:var(--blanco); text-decoration:none; display:block; margin:4px 0; }
    footer a:hover { text-decoration:underline; }

    .copy { grid-column:1/-1; text-align:center; margin-top:1rem; font-size:.9rem; color:#f1f1f1; }
  </style>
</head>
<body>

<header>
  <div class="logo"><img src="Logo.png" alt="Farvec"> Farvec</div>
  <nav>
    <a href="#">Inicio</a>
    <a href="#func">Funcionalidades</a>
    <a href="#nosotros">Nosotros</a>
    <a href="#contacto">Contacto</a>
  </nav>
</header>

<section class="hero">
  <h1>La salud de tu farmacia está en la gestión</h1>
  <p>Sistema integral para administrar ventas, stock, recetas y más.</p>
  <a href="Login.php" class="btn btn-login">Iniciar Sesión</a>
  <a href="Registro.php" class="btn btn-registro">Registrarse</a>
</section>

<!-- KPIs -->
<section class="kpis">
  <div class="kpi"><i class="fa-solid fa-pills"></i><h3>150+</h3><small>Productos activos</small></div>
  <div class="kpi"><i class="fa-solid fa-users"></i><h3>75</h3><small>Clientes frecuentes</small></div>
  <div class="kpi"><i class="fa-solid fa-cash-register"></i><h3>320</h3><small>Ventas realizadas</small></div>
  <div class="kpi"><i class="fa-solid fa-truck"></i><h3>15</h3><small>Proveedores registrados</small></div>
</section>

<!-- FUNCIONALIDADES -->
<section class="seccion" id="func">
  <h2>⚙ Funcionalidades principales</h2>
  <div class="funcionalidades">
    <div class="card"><i class="fa-solid fa-cash-register"></i> Ventas</div>
    <div class="card"><i class="fa-solid fa-truck"></i> Compras</div>
    <div class="card"><i class="fa-solid fa-capsules"></i> Stock y Lotes</div>
    <div class="card"><i class="fa-solid fa-file-prescription"></i> Recetas</div>
    <div class="card"><i class="fa-solid fa-building"></i> Proveedores</div>
    <div class="card"><i class="fa-solid fa-credit-card"></i> Cuentas Corrientes</div>
    <div class="card"><i class="fa-solid fa-bell"></i> Alertas</div>
    <div class="card"><i class="fa-solid fa-chart-line"></i> Reportes</div>
  </div>
</section>

<!-- SLIDER -->
<div class="slider">
  <span>📢 Nueva funcionalidad: Alertas de vencimiento automático | 💊 Ahora podés registrar recetas digitalmente | 📊 Reportes avanzados en tiempo real</span>
</div>

<!-- NOSOTROS -->
<section class="seccion" id="nosotros">
  <h2>🌟 Sobre nosotros</h2>
  <p><strong>Misión:</strong> Brindar a las farmacias herramientas modernas que faciliten su gestión diaria.</p>
  <p><strong>Visión:</strong> Convertirnos en el sistema de gestión farmacéutica líder en LATAM.</p>
  <p><strong>Valores:</strong> Innovación, Transparencia, Compromiso con la salud.</p>
</section>

<!-- FOOTER -->
<footer id="contacto">
  <div>
    <h3>📞 Contacto</h3>
    <p>Email: soporte@farvec.com</p>
    <p>Tel: +54 11 2233 4455</p>
  </div>
  <div>
    <h3>🔗 Enlaces rápidos</h3>
    <a href="Login.php">Iniciar Sesión</a>
    <a href="Registro.php">Registrarse</a>
    <a href="stock.php">Gestión de Stock</a>
  </div>
  <div>
    <h3>🌍 Síguenos</h3>
    <a href="#"><i class="fa-brands fa-facebook"></i> Facebook</a>
    <a href="#"><i class="fa-brands fa-twitter"></i> Twitter</a>
    <a href="#"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>
  </div>
  <div class="copy">&copy; <?= date("Y") ?> Farvec - Sistema de Gestión de Farmacia</div>
</footer>

</body>
</html>