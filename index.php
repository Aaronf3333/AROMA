<?php
include(__DIR__ . "/conexion.php"); // conexión a MySQL

// Validar conexión
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aroma S.A.C - Sistema de Gestión</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Variables de color (consistentes con el dashboard y el footer) */
        :root {
            --primary-color: #34495e; /* Gris oscuro */
            --secondary-color: #2c3e50; /* Gris más oscuro */
            --accent-color: #27ae60; /* Verde esmeralda */
            --background-color: #ecf0f1; /* Gris claro */
            --card-background: #ffffff; /* Blanco */
            --text-light: #7f8c8d; /* Gris para texto secundario */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
            color: var(--primary-color);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Estilos para la sección de bienvenida */
        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .welcome-section h1 {
            color: var(--secondary-color);
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: var(--text-light);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Estilos para las tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .stat-card {
            background: var(--card-background);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border-top: 4px solid var(--accent-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card .icon-circle {
            width: 60px;
            height: 60px;
            background-color: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            transition: background-color 0.3s ease;
        }

        .stat-card:hover .icon-circle {
            background-color: #2ecc71;
        }

        .stat-card .icon-circle i {
            font-size: 2rem;
            color: #fff;
        }

        .stat-card h3 {
            color: var(--secondary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--accent-color);
            margin: 0;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
            }
            .welcome-section {
                padding: 30px 15px;
            }
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            .stat-number {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

<?php include(__DIR__ . "/includes/header.php"); ?>

<div class="container">
    <div class="welcome-section">
        <h1>Bienvenido al Sistema de Gestión de Aroma S.A.C</h1>
        <p>Gestiona tu negocio de manera eficiente y profesional. Controla clientes, productos y boletas desde un solo lugar.</p>
    </div>

    <div class="stats-grid">
        <?php
        // Total Clientes
        $sqlClientes = "SELECT COUNT(*) AS totalClientes FROM cliente";
        $stmtClientes = $conn->query($sqlClientes);
        $rowClientes = $stmtClientes->fetch_assoc();

        // Total Productos
        $sqlProductos = "SELECT COUNT(*) AS totalProductos FROM producto";
        $stmtProductos = $conn->query($sqlProductos);
        $rowProductos = $stmtProductos->fetch_assoc();
        ?>

        <div class="stat-card">
            <div class="icon-circle">
                <i class="fas fa-users"></i>
            </div>
            <h3>Clientes Registrados</h3>
            <p class="stat-number"><?php echo number_format($rowClientes['totalClientes']); ?></p>
            <p class="stat-label">Total de clientes registrados</p>
        </div>

        <div class="stat-card">
            <div class="icon-circle">
                <i class="fas fa-box-open"></i>
            </div>
            <h3>Productos Disponibles</h3>
            <p class="stat-number"><?php echo number_format($rowProductos['totalProductos']); ?></p>
            <p class="stat-label">En inventario</p>
        </div>
    </div>
</div>

<?php include(__DIR__ . "/includes/footer.php"); ?>

</body>
</html>
