<header>
    <div class="logo">
        <img src="logo.png" alt="Logo Aroma" class="logo-img">
        <div>
            <h1>Aroma S.A.C</h1>
            <span class="gestion">Gestión 2025</span>
        </div>
    </div>
    
    <!-- Botón hamburguesa -->
    <div class="hamburger" onclick="toggleMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>
    
    <nav class="nav-menu">
        <ul>
            <li><a href="index.php">Inicio</a></li>
            <li><a href="usuarios.php">Usuarios</a></li>
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="productos.php">Productos</a></li>
            <li><a href="boletas.php">Boletas</a></li>
            <li><a href="dashboard.php">Resumen</a></li>
            <li><a href="logout.php" class="logout">Cerrar Sesión</a></li>
        </ul>
    </nav>
</header>

<style>
    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #3b3b98;
        padding: 15px 20px;
        border-bottom: 3px solid #2f3640;
        color: white;
        position: relative;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1001; /* Para que esté sobre el menú */
    }

    /* Imagen redonda */
    .logo-img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
        display: block;
    }

    .logo h1 {
        margin: 0;
        font-size: 20px;
    }

    .gestion {
        font-size: 14px;
        font-style: italic;
        color: #dcdde1;
    }

    /* Menú hamburguesa - oculto por defecto */
    .hamburger {
        display: none;
        flex-direction: column;
        cursor: pointer;
        z-index: 1001;
        padding: 5px;
    }

    .hamburger span {
        width: 25px;
        height: 3px;
        background: white;
        margin: 3px 0;
        border-radius: 2px;
        transition: all 0.3s ease;
        transform-origin: center;
    }

    /* Animación del hamburger a X */
    .hamburger.active span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .hamburger.active span:nth-child(2) {
        opacity: 0;
    }

    .hamburger.active span:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -6px);
    }

    .nav-menu {
        display: block;
    }

    .nav-menu ul {
        list-style: none;
        display: flex;
        gap: 15px;
        margin: 0;
        padding: 0;
        align-items: center;
    }

    .nav-menu a {
        color: white;
        text-decoration: none;
        font-weight: bold;
        padding: 8px 12px;
        border-radius: 5px;
        transition: 0.3s;
    }

    .nav-menu a:hover {
        background: #575fcf;
    }

    .logout {
        background: crimson;
        color: white;
    }

    .logout:hover {
        background: darkred;
    }

    /* Responsive - Móviles */
    @media (max-width: 768px) {
        .hamburger {
            display: flex;
        }

        .nav-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 280px;
            height: 100vh;
            background: #3b3b98;
            transition: right 0.3s ease;
            z-index: 1000;
            padding-top: 80px;
            box-shadow: -2px 0 10px rgba(0,0,0,0.3);
        }

        .nav-menu.active {
            right: 0;
        }

        .nav-menu ul {
            flex-direction: column;
            gap: 0;
            width: 100%;
            padding: 20px 0;
        }

        .nav-menu li {
            width: 100%;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-menu li:last-child {
            border-bottom: none;
            margin-top: 20px;
        }

        .nav-menu a {
            display: block;
            width: 100%;
            padding: 15px 25px;
            border-radius: 0;
            font-size: 16px;
        }

        .nav-menu a:hover {
            background: #575fcf;
            padding-left: 35px;
        }

        .logout {
            margin: 20px;
            border-radius: 8px;
            text-align: center;
            width: calc(100% - 40px);
        }

        /* Overlay para cerrar el menú */
        .nav-menu::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            z-index: -1;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nav-menu.active::before {
            opacity: 1;
            visibility: visible;
        }

        /* Ajustar logo en móviles */
        .logo h1 {
            font-size: 18px;
        }

        .logo-img {
            width: 50px;
            height: 50px;
        }

        .gestion {
            font-size: 12px;
        }

        header {
            padding: 12px 15px;
        }
    }

    /* Móviles muy pequeños */
    @media (max-width: 480px) {
        .nav-menu {
            width: 100%;
            right: -100%;
        }

        .logo h1 {
            font-size: 16px;
        }

        .logo-img {
            width: 45px;
            height: 45px;
        }

        header {
            padding: 10px 12px;
        }
    }

    /* Tablets */
    @media (min-width: 769px) and (max-width: 1024px) {
        .nav-menu ul {
            gap: 12px;
        }

        .nav-menu a {
            padding: 8px 10px;
            font-size: 14px;
        }
    }
</style>

<script>
function toggleMenu() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
}

// Cerrar menú al hacer click en el overlay
document.addEventListener('click', function(e) {
    const navMenu = document.querySelector('.nav-menu');
    const hamburger = document.querySelector('.hamburger');
    
    if (navMenu.classList.contains('active') && 
        !navMenu.contains(e.target) && 
        !hamburger.contains(e.target)) {
        hamburger.classList.remove('active');
        navMenu.classList.remove('active');
    }
});

// Cerrar menú al hacer click en un enlace (móviles)
document.querySelectorAll('.nav-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.querySelector('.hamburger').classList.remove('active');
            document.querySelector('.nav-menu').classList.remove('active');
        }
    });
});

// Cerrar menú al redimensionar ventana
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        document.querySelector('.hamburger').classList.remove('active');
        document.querySelector('.nav-menu').classList.remove('active');
    }
});
</script>
