<header>
    <div class="logo">
        <img src="logo.png" alt="Logo Aroma" class="logo-img">
        <div>
            <h1>Aroma S.A.C</h1>
            <span class="gestion">Gestión 2025</span>
        </div>
    </div>
    <nav>
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
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Imagen redonda */
    .logo-img {
        width: 60px;        /* ancho fijo */
        height: 60px;       /* alto igual al ancho */
        border-radius: 50%; /* redonda total */
        object-fit: cover;  /* recorta para llenar el círculo */
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

    nav ul {
        list-style: none;
        display: flex;
        gap: 15px;
        margin: 0;
        padding: 0;
        align-items: center;
    }

    nav a {
        color: white;
        text-decoration: none;
        font-weight: bold;
        padding: 8px 12px;
        border-radius: 5px;
        transition: 0.3s;
    }

    nav a:hover {
        background: #575fcf;
    }

    .logout {
        background: crimson;
        color: white;
    }

    .logout:hover {
        background: darkred;
    }
</style>
