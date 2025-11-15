<?php
session_start();
require_once 'Conexion.php';

// --- KPIs dinámicos desde la BD (con fallback seguro) ---
$kpis = ['productos'=>150,'clientes'=>75,'ventas'=>320,'proveedores'=>15];

try {
  $conn = new Conexion();
  $db   = $conn->conexion;

  // Productos
  $kpis['productos']   = (int)($db->query("SELECT COUNT(*) c FROM Producto")->fetch_assoc()['c'] ?? $kpis['productos']);
  // Clientes
  $kpis['clientes']    = (int)($db->query("SELECT COUNT(*) c FROM Cliente")->fetch_assoc()['c'] ?? $kpis['clientes']);
  // Ventas
  $kpis['ventas']      = (int)($db->query("SELECT COUNT(*) c FROM Venta")->fetch_assoc()['c'] ?? $kpis['ventas']);
  // Proveedores
  $kpis['proveedores'] = (int)($db->query("SELECT COUNT(*) c FROM Proveedor")->fetch_assoc()['c'] ?? $kpis['proveedores']);
} catch (\Throwable $e) {
  // Si falla la conexión, se mantienen los valores por defecto
}

function nfmt($n){ return number_format((int)$n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Farvec – Sistema de Gestión Farmacéutica</title>

  <!-- Fuentes + Iconos + Bootstrap -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      /* Paleta azul verdoso profesional */
      --teal-dark:#006d5b;
      --teal:#009e84;
      --teal-light:#00c9a7;
      --bg-gradient:linear-gradient(180deg,#009e84 0%,#00c9a7 100%);
      --white:#ffffff;
      --text:#1e293b;
      --muted:#64748b;
      --radius:18px;
      --radius-sm:12px;
      --shadow:0 10px 25px rgba(0,0,0,0.15);
      --transition:all .35s ease;
    }

    *{box-sizing:border-box;margin:0;padding:0}

    body{
      font-family:'Poppins',sans-serif;
      color:var(--text);
      background:var(--bg-gradient);
      min-height:100vh;
      background-attachment:fixed;
      background-size:cover;
      line-height:1.5;
      overflow-x:hidden;
    }

    /* === Animaciones globales === */
    @keyframes fadeInUp {
      from {opacity:0; transform:translateY(30px);}
      to {opacity:1; transform:translateY(0);}
    }
    .fadeInUp{
      opacity:0; animation:fadeInUp .8s forwards;
    }

    @keyframes pulse {
      0%,100%{transform:scale(1);}
      50%{transform:scale(1.05);}
    }

    /* === Header === */
    .header{
      position:sticky;top:0;z-index:50;
      background:rgba(255,255,255,0.9);
      backdrop-filter:blur(8px);
      box-shadow:0 2px 8px rgba(0,0,0,0.05);
    }
    .nav{
      max-width:1200px;margin:0 auto;
      padding:14px 20px;
      display:flex;align-items:center;justify-content:space-between;
    }
    .brand{
      display:flex;align-items:center;gap:10px;
      font-weight:800;font-size:1.3rem;
      color:var(--teal-dark);text-decoration:none;
      transition:var(--transition);
    }
    .brand:hover{transform:scale(1.05);}
    .brand img{width:38px;height:38px}

    .menu a{
      text-decoration:none;color:var(--text);
      font-weight:600;margin:0 12px;
      position:relative;transition:var(--transition);
    }
    .menu a:hover{
      color:var(--teal-dark);
    }
    .menu a::after{
      content:"";position:absolute;bottom:-4px;left:0;
      width:0;height:2px;background:var(--teal);
      transition:width .3s;
    }
    .menu a:hover::after{width:100%;}

    .btn-cta{
      background:var(--teal-dark);color:#fff;
      padding:10px 18px;border:none;border-radius:999px;
      font-weight:700;text-decoration:none;
      display:inline-flex;align-items:center;gap:8px;
      box-shadow:var(--shadow);
      transition:var(--transition);
    }
    .btn-cta:hover{
      background:var(--teal-light);
      transform:translateY(-2px);
    }

    /* === HERO === */
    .hero{
      color:#fff;
      padding:60px 20px 40px;
      animation:fadeInUp 1s ease forwards;
    }
    .hero-inner{
      max-width:1200px;margin:0 auto;
      display:grid;grid-template-columns:1.2fr .8fr;
      align-items:center;gap:24px;
    }
    .hero-title{
      font-size:clamp(28px,5vw,52px);
      font-weight:800;
      color:#fff;
      line-height:1.2;
      animation:fadeInUp 1.2s ease forwards;
    }
    .hero-title .accent{color:var(--teal-light);}
    .hero-sub{
      color:#e2e8f0;
      font-size:1.1rem;
      margin:14px 0 24px;
      animation:fadeInUp 1.4s ease forwards;
    }
    .hero-actions{
      display:flex;gap:12px;flex-wrap:wrap;
    }
    .btn-primary{
      background:#fff;color:var(--teal-dark);
      border:none;padding:12px 20px;
      border-radius:999px;font-weight:700;
      text-decoration:none;display:inline-flex;
      align-items:center;gap:10px;
      transition:var(--transition);
      box-shadow:var(--shadow);
    }
    .btn-primary:hover{
      background:var(--teal-light);
      color:#fff;transform:scale(1.05);
    }
    .btn-ghost{
      background:transparent;color:#fff;
      border:2px solid #fff;
      padding:10px 18px;border-radius:999px;
      font-weight:700;text-decoration:none;
      transition:var(--transition);
    }
    .btn-ghost:hover{
      background:#fff;color:var(--teal-dark);
      transform:scale(1.05);
    }
    .hero-media{
      border-radius:var(--radius);
      overflow:hidden;
      box-shadow:var(--shadow);
      transform:translateY(0);
      transition:var(--transition);
    }
    .hero-media:hover{transform:translateY(-5px) scale(1.02);}
    .hero-img{
      width:100%;height:100%;object-fit:cover;
      filter:brightness(1.05);
    }

    /* === DESTACADOS === */
    .highlights{
      max-width:1200px;margin:-24px auto 12px;
      padding:0 20px;
      display:grid;grid-template-columns:repeat(3,1fr);
      gap:16px;
    }
    .hi-card{
      background:#fff;
      padding:20px;
      border-radius:var(--radius-sm);
      box-shadow:var(--shadow);
      display:flex;gap:14px;align-items:flex-start;
      border-top:4px solid var(--teal);
      transition:var(--transition);
    }
    .hi-card:hover{
      transform:translateY(-6px);
      box-shadow:0 15px 30px rgba(0,0,0,.12);
    }
    .hi-card i{font-size:22px;color:var(--teal-dark);margin-top:3px;}
    .hi-card h4{margin:0 0 6px;font-size:1.1rem;}
    .hi-card p{margin:0;color:var(--muted);}

    /* === KPIs === */
    .kpis{
      max-width:1200px;margin:28px auto 0;padding:0 20px;
      display:grid;grid-template-columns:repeat(4,1fr);gap:16px;
    }
    .kpi{
      background:#fff;border-radius:12px;padding:22px;text-align:center;
      box-shadow:var(--shadow);transition:var(--transition);
    }
    .kpi:hover{transform:translateY(-6px);box-shadow:0 14px 30px rgba(0,0,0,.15);}
    .kpi i{color:var(--teal-dark);font-size:28px;margin-bottom:6px}
    .kpi .num{font-size:28px;font-weight:800}
    .kpi small{color:var(--muted)}

    /* === Secciones / Píldoras (manteniendo tu grid) === */
    .section{padding:56px 20px}
    .section .wrap{max-width:1200px;margin:0 auto}
    .section h2{font-size:28px;text-align:center;margin:0 0 22px;color:#0f3e3c}

    .pill-grid{
      display:grid;grid-template-columns:repeat(2,1fr);gap:16px;
    }
    /* Mantengo tu .pill pero la “profesionalizo” con look Bootstrap */
    .pill{
      background:linear-gradient(90deg,var(--teal),var(--teal-light));
      color:#fff;padding:16px 18px;border-radius:14px;font-weight:700;
      box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;letter-spacing:.3px;
      transition:var(--transition);
      border:1px solid rgba(255,255,255,.2);
    }
    .pill:hover{filter:brightness(1.08);transform:translateY(-3px);}
    .pill i{font-size:20px}

    /* === Imagen ancha === */
    .wide-media{
      margin:30px auto;max-width:1200px;border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);
    }
    .wide-media img{width:100%;height:auto;display:block}

    /* === Esencia (Misión, Visión, Valores) === */
    .cards3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .card{
      background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:22px;border-top:4px solid var(--teal);
      transition:var(--transition);
    }
    .card:hover{transform:translateY(-6px);box-shadow:0 16px 32px rgba(0,0,0,.12);}
    .card h3{margin:0 0 10px}
    .card ul{margin:10px 0 0;padding-left:18px}
    .card li{margin:6px 0;color:#4b5563}

    /* === CTA === */
    .cta{text-align:center;padding:18px 0 8px}
    .cta .grid{
      display:grid;grid-template-columns:repeat(3,1fr);gap:16px;max-width:1200px;margin:0 auto 18px;
    }
    .cta .item{background:#fff;border-radius:16px;padding:22px;box-shadow:var(--shadow);transition:var(--transition)}
    .cta .item:hover{transform:translateY(-5px)}
    .cta .item i{color:var(--teal-dark);font-size:22px}
    .btn-cta{background:#d3a357;color:#fff;border:none;cursor:pointer;padding:10px 16px;font-weight:700;border-radius:999px;box-shadow:var(--shadow);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
    .btn-cta:hover{filter:brightness(.95)}

    /* === Footer === */
    .footer{background:#1c6f6a;color:#eafaf8;margin-top:36px}
    .footer .inner{max-width:1200px;margin:0 auto;padding:28px 20px;display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
    .footer a{color:#eafaf8;text-decoration:none}
    .footer a:hover{text-decoration:underline}
    .copy{border-top:1px solid rgba(255,255,255,.2);text-align:center;padding:14px 20px;font-size:.9rem;color:#d7f3ef}

    /* === Responsive (mantengo tu comportamiento) === */
    @media (max-width:1024px){
      .hero-inner{grid-template-columns:1fr}
      .hero-media{min-height:260px}
      .highlights{grid-template-columns:1fr}
      .kpis{grid-template-columns:repeat(2,1fr)}
      .pill-grid{grid-template-columns:1fr}
      .cards3{grid-template-columns:1fr}
      .cta .grid{grid-template-columns:1fr}
      .footer .inner{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

  <!-- Header (idéntico) -->
  <header class="header">
    <div class="nav">
      <a class="brand" href="#"><img src="Logo.png" alt="Farvec"> Farvec</a>
      <nav class="menu">
        <a href="#">Home</a>
        <a href="#acerca">Acerca de Farvec</a>
        <a href="#funcionalidades">Funcionalidades</a>
        <a href="#confian">Confían en nosotros</a>
        <a href="#novedades">Novedades</a>
        <a href="#contacto">Contacto</a>
      </nav>
      <div class="actions">
        <a class="btn-cta" href="Login.php"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a>
      </div>
    </div>
  </header>

  <!-- Hero (idéntico, solo estilos nuevos aplican) -->
  <section class="hero">
    <div class="hero-inner">
      <div>
        <h1 class="hero-title">LA SALUD DE TU FARMACIA <br> ESTÁ EN <span class="accent">LA GESTIÓN</span></h1>
        <p class="hero-sub">Farvec – Sistema integral para administrar ventas, stock, lotes, recetas, compras, proveedores y reportes en tiempo real.</p>
        <div class="hero-actions">
          <a class="btn-primary" href="Registro.php"><i class="fa-solid fa-user-plus"></i> Crear cuenta</a>
          <a class="btn-ghost" href="#funcionalidades">Ver funcionalidades</a>
        </div>
      </div>
      <div class="hero-media">
        <!-- Poné tu imagen en assets/hero_farmacia.jpg -->
        <img class="hero-img" src="assets/hero_farmacia.jpg" alt="Farmacia moderna" onerror="this.style.display='none'">
      </div>
    </div>
  </section>

  <!-- 3 Destacados (idéntico) -->
  <div class="highlights" id="acerca">
    <div class="hi-card">
      <i class="fa-solid fa-layer-group"></i>
      <div>
        <h4>Todo tu negocio, en una sola plataforma</h4>
        <p>Ventas, compras, stock, lotes, recetas, proveedores, reportes y más. Multiusuario con roles.</p>
      </div>
    </div>
    <div class="hi-card">
      <i class="fa-solid fa-gauge-high"></i>
      <div>
        <h4>Datos en tiempo real, decisiones más rápidas</h4>
        <p>KPIs actualizados, alertas de vencimiento y costos. Exportá a Excel/PDF.</p>
      </div>
    </div>
    <div class="hi-card">
      <i class="fa-solid fa-diagram-project"></i>
      <div>
        <h4>Multiempresa y multisucursal</h4>
        <p>Escalá tu operación con seguridad, auditoría y permisos por perfiles.</p>
      </div>
    </div>
  </div>

  <!-- KPIs (idéntico) -->
  <section class="section" aria-label="Indicadores">
    <div class="wrap">
      <div class="kpis">
        <div class="kpi">
          <i class="fa-solid fa-pills"></i>
          <div class="num"><?= nfmt($kpis['productos']) ?></div>
          <small>Productos activos</small>
        </div>
        <div class="kpi">
          <i class="fa-solid fa-users"></i>
          <div class="num"><?= nfmt($kpis['clientes']) ?></div>
          <small>Clientes frecuentes</small>
        </div>
        <div class="kpi">
          <i class="fa-solid fa-cash-register"></i>
          <div class="num"><?= nfmt($kpis['ventas']) ?></div>
          <small>Ventas registradas</small>
        </div>
        <div class="kpi">
          <i class="fa-solid fa-truck"></i>
          <div class="num"><?= nfmt($kpis['proveedores']) ?></div>
          <small>Proveedores</small>
        </div>
      </div>
    </div>
  </section>

  <!-- Funcionalidades (idéntico; “pastillas” ahora con look bootstrap en CSS) -->
  <section class="section" id="funcionalidades">
    <div class="wrap">
      <h2>ALGUNAS DE NUESTRAS FUNCIONALIDADES</h2>
      <div class="pill-grid">
        <a class="pill" href="ventas.php"><i class="fa-solid fa-cart-shopping"></i> VENTAS</a>
        <a class="pill" href="stock.php"><i class="fa-solid fa-capsules"></i> STOCK & LOTES</a>
        <a class="pill" href="compras.php"><i class="fa-solid fa-truck"></i> COMPRAS</a>
        <a class="pill" href="reportes.php"><i class="fa-solid fa-chart-line"></i> REPORTES</a>
        <a class="pill" href="clientes_listar.php"><i class="fa-solid fa-people-group"></i> CLIENTES</a>
        <a class="pill" href="mapaProveedores.php"><i class="fa-solid fa-building"></i> PROVEEDORES</a>
        <a class="pill" href="Login.php"><i class="fa-solid fa-user-shield"></i> ROLES & PERMISOS</a>
        <a class="pill" href="#novedades"><i class="fa-solid fa-plug"></i> INTEGRACIONES</a>
      </div>
    </div>
  </section>

  <!-- Imagen ancha (idéntico) -->
  <div class="wide-media">
    <img src="assets/farmacia_interior.jpg" alt="Mostrador de farmacia" onerror="this.style.display='none'">
  </div>

  <!-- Nuestra esencia (idéntico) -->
  <section class="section" id="nosotros">
    <div class="wrap">
      <h2>NUESTRA ESENCIA</h2>
      <div class="cards3">
        <div class="card">
          <h3>🏅 Misión</h3>
          <p>Brindar soluciones modernas para la gestión farmacéutica, optimizando procesos y mejorando la atención al cliente.</p>
          <ul>
            <li>Automatización de stock y alertas de vencimiento</li>
            <li>Reportes confiables para decisiones rápidas</li>
            <li>Enfoque en experiencia simple y segura</li>
          </ul>
        </div>
        <div class="card">
          <h3>🚀 Visión</h3>
          <p>Ser el sistema de gestión líder en LATAM, con innovación constante y foco en el rubro salud.</p>
          <ul>
            <li>Escalabilidad multiempresa y multisucursal</li>
            <li>Roadmap de mejoras continuas</li>
            <li>Soporte cercano y documentación clara</li>
          </ul>
        </div>
        <div class="card">
          <h3>💚 Valores</h3>
          <p>Ética, transparencia e innovación con compromiso en la salud pública y de nuestros clientes.</p>
          <ul>
            <li>Seguridad y privacidad por diseño</li>
            <li>Integración con herramientas actuales</li>
            <li>Trabajo colaborativo con farmacias</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Banner “Especializados” (idéntico) -->
  <section class="hero" id="confian" style="margin-top: -18px;">
    <div class="hero-inner" style="grid-template-columns:1fr;">
      <div style="text-align:center">
        <h2 class="hero-title" style="font-size:clamp(26px,4vw,42px)">Especializados en la <span class="accent">industria farmacéutica</span></h2>
        <p class="hero-sub">Combinamos conocimiento del sector con tecnología práctica para resultados reales.</p>
      </div>
    </div>
  </section>

  <!-- CTA (idéntico) -->
  <section class="section cta" id="novedades">
    <div class="wrap">
      <h2>¿LISTO PARA TRANSFORMAR LA GESTIÓN DE TU FARMACIA?</h2>
      <p class="hero-sub" style="text-align:center">Descubrí cómo Farvec puede optimizar tus procesos con una demostración gratuita.</p>

      <div class="grid">
        <div class="item"><i class="fa-regular fa-clock"></i> <p><strong>30 minutos</strong> que cambiarán tu forma de trabajar</p></div>
        <div class="item"><i class="fa-solid fa-display"></i> <p>Demo personalizada según tus necesidades</p></div>
        <div class="item"><i class="fa-solid fa-chart-line"></i> <p>Conocé cómo aumentar tu productividad</p></div>
      </div>

      <a class="btn-cta" href="Registro.php"><i class="fa-solid fa-bolt"></i> Solicitar demo gratuita</a>
    </div>
  </section>

  <!-- Footer (idéntico) -->
  <footer class="footer" id="contacto">
    <div class="inner">
      <div>
        <h3>Contacto</h3>
        <p>Email: <a href="mailto:soporte@farvec.com">soporte@farvec.com</a></p>
        <p>Tel: +54 11 2233 4455</p>
        <p>Atención: Lun a Vie, 9 a 18 hs</p>
      </div>
      <div>
        <h3>Enlaces rápidos</h3>
        <p><a href="Login.php">Iniciar sesión</a></p>
        <p><a href="Registro.php">Registrarse</a></p>
        <p><a href="stock.php">Gestión de stock</a></p>
        <p><a href="reportes.php">Reportes</a></p>
      </div>
      <div>
        <h3>Seguinos</h3>
        <p><a href="#"><i class="fa-brands fa-facebook"></i> Facebook</a></p>
        <p><a href="#"><i class="fa-brands fa-twitter"></i> Twitter / X</a></p>
        <p><a href="#"><i class="fa-brands fa-linkedin"></i> LinkedIn</a></p>
      </div>
    </div>
    <div class="copy">© <?= date('Y') ?> Farvec – Sistema de Gestión de Farmacia. Todos los derechos reservados.</div>
  </footer>

  <!-- Animaciones por scroll SIN tocar tu HTML -->
  <script>
    (function(){
      const revealSel = [
        '.hero-title','.hero-sub','.hero-actions','.hero-media',
        '.hi-card','.kpi','.pill','.card','.cta .item','.wide-media img'
      ].join(',');

      const els = document.querySelectorAll(revealSel);
      const io = new IntersectionObserver((entries)=>{
        entries.forEach(e=>{
          if(e.isIntersecting){
            e.target.classList.add('fadeInUp');
            io.unobserve(e.target);
          }
        });
      },{threshold:0.2});

      els.forEach(el=>io.observe(el));
    })();
  </script>
</body>
</html>
