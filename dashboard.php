<?php
session_start();

include(__DIR__ . "/conexion.php"); // Conexión a MySQL

// === CONSULTAS ===

// Ventas Hoy
$sqlHoy = "SELECT IFNULL(SUM(total),0) AS totalHoy FROM venta WHERE DATE(fechaVenta) = CURDATE()";
$resultHoy = $conn->query($sqlHoy);
$ventasHoy = $resultHoy->fetch_assoc()['totalHoy'];

// Ventas Mes
$sqlMes = "SELECT IFNULL(SUM(total),0) AS totalMes FROM venta WHERE MONTH(fechaVenta) = MONTH(NOW()) AND YEAR(fechaVenta) = YEAR(NOW())";
$resultMes = $conn->query($sqlMes);
$ventasMes = $resultMes->fetch_assoc()['totalMes'];

// Ventas Año
$sqlAnio = "SELECT IFNULL(SUM(total),0) AS totalAnio FROM venta WHERE YEAR(fechaVenta) = YEAR(NOW())";
$resultAnio = $conn->query($sqlAnio);
$ventasAnio = $resultAnio->fetch_assoc()['totalAnio'];

// Clientes
$sqlClientes = "SELECT COUNT(*) AS clientes FROM cliente";
$resultClientes = $conn->query($sqlClientes);
$totalClientes = $resultClientes->fetch_assoc()['clientes'];

// Productos
$sqlProductos = "SELECT COUNT(*) AS productos FROM producto WHERE estado = 1";
$resultProd = $conn->query($sqlProductos);
$totalProductos = $resultProd->fetch_assoc()['productos'];

// Ventas últimos 7 días
$sqlSemana = "SELECT DATE(fechaVenta) as fecha, SUM(total) as total
             FROM venta
             WHERE fechaVenta >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(fechaVenta)
             ORDER BY fecha ASC";
$dataSemana = [];
$resultSemana = $conn->query($sqlSemana);
while($row = $resultSemana->fetch_assoc()) {
    $dataSemana[] = $row;
}

// Ventas por Mes (año actual)
$sqlMeses = "SELECT MONTH(fechaVenta) as mes, SUM(total) as total
             FROM venta
             WHERE YEAR(fechaVenta) = YEAR(NOW())
             GROUP BY MONTH(fechaVenta)
             ORDER BY mes ASC";
$dataMeses = [];
$resultMeses = $conn->query($sqlMeses);
while($row = $resultMeses->fetch_assoc()) {
    $dataMeses[] = $row;
}

// Top productos
$sqlTop = "SELECT p.nombreProducto, SUM(dv.cantidad) as totalVendidos
           FROM detalleventa dv
           JOIN producto p ON dv.idProducto = p.idProducto
           GROUP BY p.nombreProducto
           ORDER BY totalVendidos DESC
           LIMIT 5";
$dataTop = [];
$resultTop = $conn->query($sqlTop);
while($row = $resultTop->fetch_assoc()) {
    $dataTop[] = $row;
}

// Ingresos por Año
$sqlAnios = "SELECT YEAR(fechaVenta) as anio, SUM(total) as total
             FROM venta
             GROUP BY YEAR(fechaVenta)
             ORDER BY anio ASC";
$dataAnios = [];
$resultAnios = $conn->query($sqlAnios);
while($row = $resultAnios->fetch_assoc()) {
    $dataAnios[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Aroma S.A.C</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Variables de color */
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

.dashboard-container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.header {
    text-align: center;
    margin-bottom: 40px;
}

.header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--secondary-color);
    position: relative;
    display: inline-block;
}

.header h1::after {
    content: '';
    display: block;
    width: 80px;
    height: 4px;
    background-color: var(--accent-color);
    margin: 10px auto 0;
    border-radius: 2px;
}

/* Sección de tarjetas */
.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.card {
    background-color: var(--card-background);
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    padding: 25px;
    display: flex;
    align-items: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
}

.card-icon {
    font-size: 2rem;
    margin-right: 20px;
    color: var(--accent-color);
}

.card-content h3 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-light);
    text-transform: uppercase;
}

.card-content p {
    margin: 5px 0 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--secondary-color);
}

/* Sección de gráficos */
.charts-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 30px;
}

.chart-container {
    background-color: var(--card-background);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}

/* Gráficos específicos */
#ventasSemana, #ventasMeses {
    height: 350px;
}

#topProductos, #ventasAnios {
    height: 350px;
}

/* Responsive */
@media (max-width: 768px) {
    .charts-section {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="dashboard-container">
        <div class="header">
            <h1>Dashboard de Ventas 📈</h1>
        </div>

        <div class="info-cards">
            <div class="card">
                <i class="fas fa-sack-dollar card-icon"></i>
                <div class="card-content">
                    <h3>Ventas Hoy</h3>
                    <p>S/. <?php echo number_format($ventasHoy, 2); ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-calendar-day card-icon"></i>
                <div class="card-content">
                    <h3>Ventas del Mes</h3>
                    <p>S/. <?php echo number_format($ventasMes, 2); ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-calendar-alt card-icon"></i>
                <div class="card-content">
                    <h3>Ventas del Año</h3>
                    <p>S/. <?php echo number_format($ventasAnio, 2); ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-user-friends card-icon"></i>
                <div class="card-content">
                    <h3>Total de Clientes</h3>
                    <p><?php echo $totalClientes; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-utensils card-icon"></i>
                <div class="card-content">
                    <h3>Productos Activos</h3>
                    <p><?php echo $totalProductos; ?></p>
                </div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-container">
                <canvas id="ventasSemana"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="ventasMeses"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="topProductos"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="ventasAnios"></canvas>
            </div>
        </div>
    </div>

    <script>
    // PHP -> JS Data
    const ventasHoy = <?php echo json_encode($ventasHoy); ?>;
    const ventasMes = <?php echo json_encode($ventasMes); ?>;
    const ventasAnio = <?php echo json_encode($ventasAnio); ?>;
    const totalClientes = <?php echo json_encode($totalClientes); ?>;
    const totalProductos = <?php echo json_encode($totalProductos); ?>;
    const dataSemana = <?php echo json_encode($dataSemana); ?>;
    const dataMeses = <?php echo json_encode($dataMeses); ?>;
    const dataTop = <?php echo json_encode($dataTop); ?>;
    const dataAnios = <?php echo json_encode($dataAnios); ?>;

    // Configuración de los gráficos
    Chart.defaults.font.family = 'Poppins, sans-serif';
    Chart.defaults.color = '#34495e';

    // Helper para meses en español
    const meses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

    // === Ventas por semana ===
    const ventasSemanaCtx = document.getElementById('ventasSemana').getContext('2d');
    new Chart(ventasSemanaCtx, {
        type: 'bar',
        data: {
            labels: dataSemana.map(d => d.fecha),
            datasets: [{
                label: 'Ventas (S/.)',
                data: dataSemana.map(d => d.total),
                backgroundColor: '#2980b9',
                borderColor: '#3498db',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Ventas Últimos 7 Días', font: { size: 16, weight: 'bold' } },
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#ecf0f1' } },
                x: { grid: { display: false } }
            }
        }
    });

    // === Ventas por meses ===
    const ventasMesesCtx = document.getElementById('ventasMeses').getContext('2d');
    new Chart(ventasMesesCtx, {
        type: 'line',
        data: {
            labels: dataMeses.map(d => meses[d.mes - 1]),
            datasets: [{
                label: 'Ventas por Mes',
                data: dataMeses.map(d => d.total),
                borderColor: '#27ae60',
                tension: 0.4,
                pointBackgroundColor: '#27ae60',
                pointBorderColor: '#fff',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Ventas por Mes (Año Actual)', font: { size: 16, weight: 'bold' } },
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#ecf0f1' } }
            }
        }
    });

    // === Top 5 Productos ===
    const topProductosCtx = document.getElementById('topProductos').getContext('2d');
    new Chart(topProductosCtx, {
        type: 'doughnut',
        data: {
            labels: dataTop.map(d => d.nombreProducto),
            datasets: [{
                data: dataTop.map(d => d.totalVendidos),
                backgroundColor: ['#e74c3c', '#3498db', '#f1c40f', '#2ecc71', '#9b59b6'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Top 5 Productos Vendidos', font: { size: 16, weight: 'bold' } },
                legend: { position: 'bottom' }
            }
        }
    });

    // === Ingresos por años ===
    const ventasAniosCtx = document.getElementById('ventasAnios').getContext('2d');
    new Chart(ventasAniosCtx, {
        type: 'bar',
        data: {
            labels: dataAnios.map(d => d.anio),
            datasets: [{
                label: 'Ingresos por Año (S/.)',
                data: dataAnios.map(d => d.total),
                backgroundColor: '#1abc9c',
                borderColor: '#16a085',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Ingresos por Año', font: { size: 16, weight: 'bold' } },
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#ecf0f1' } },
                x: { grid: { display: false } }
            }
        }
    });
    </script>

    <?php include(__DIR__ . "/includes/footer.php"); ?>

</body>

</html>

