<?php
session_start();
include(__DIR__ . "/conexion.php");

// Verificar la conexión a la base de datos
if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

// Verificar usuario logueado
if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener listado de boletas
$sql = "SELECT b.idBoleta, b.numeroBoleta, b.fechaGeneracion, 
                v.total, 
                u.usuario AS cajero,
                p.nombres AS clienteNombres,
                p.apellidos AS clienteApellidos
        FROM boleta b
        JOIN venta v ON b.idVenta = v.idVenta
        JOIN usuario u ON v.idUsuario = u.idUsuario
        JOIN cliente c ON v.idCliente = c.idCliente
        JOIN persona p ON c.idPersona = p.idPersona
        ORDER BY b.fechaGeneracion DESC";

$result = $conn->query($sql);

// Manejar errores en la consulta
if ($result === false) {
    die("❌ Error en la consulta SQL: " . $conn->error);
}

// Toast mensaje
$mensaje = "";
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipoMensaje = $_SESSION['tipo'] ?? "success";
    unset($_SESSION['mensaje'], $_SESSION['tipo']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletas - Aroma S.A.C</title>
    <style>
        /*
        * A R Q U I T E C T U R A   C S S   M O D U L A R
        * ----------------------------------------------
        * He organizado el CSS en secciones para mejor lectura y mantenimiento.
        * Las variables facilitan la modificación global de la paleta de colores.
        */
        :root {
            --color-primary: #6c5ce7;
            --color-secondary: #00b894;
            --color-accent: #f0932b;
            --color-background: #f5f6fa;
            --color-card-bg: rgba(255, 255, 255, 0.95);
            --color-text: #2d3436;
            --color-light-text: #636e72;
            --color-border: #e9ecef;
            --shadow-md: 0 4px 15px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.08);
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 20px;
        }

        /* === Base Styles === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--color-background);
            min-height: 100vh;
            color: var(--color-text);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* === Page Header === */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            color: var(--color-text);
            font-size: 2.2rem;
            font-weight: 600;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--color-primary), var(--color-primary), var(--color-primary));
            border-radius: 2px;
        }

        .add-button {
            background: linear-gradient(135deg, var(--color-secondary), #00e676);
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: var(--border-radius-md);
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap; /* Evita que el texto se rompa */
        }

        .add-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.4);
        }

        /* === Toast Notifications === */
        #toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            min-width: 350px;
            max-width: 500px;
            padding: 18px 25px;
            border-radius: var(--border-radius-md);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999;
            backdrop-filter: blur(10px);
        }
        
        #toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        #toast.success { 
            background: linear-gradient(135deg, var(--color-secondary), #00a085);
            border-left: 4px solid #00e676;
        }
        
        #toast.error { 
            background: linear-gradient(135deg, #d63031, #e84393);
            border-left: 4px solid #f44336;
        }

        /* === Search Section === */
        .search-section {
            background: var(--color-card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 35px;
            margin-bottom: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .search-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary), #a29bfe, var(--color-primary));
        }

        .search-container {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 16px 55px 16px 20px;
            border: 2px solid var(--color-border);
            border-radius: 25px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
            background: rgba(255, 255, 255, 1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-light-text);
            font-size: 18px;
            pointer-events: none; /* No interfiere con el input */
        }

        .clear-search {
            position: absolute;
            right: 55px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--color-light-text);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: none;
            transition: all 0.2s ease;
        }

        .clear-search:hover {
            background: rgba(108, 92, 231, 0.1);
            color: var(--color-primary);
        }

        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid var(--color-border);
            border-radius: var(--border-radius-md);
            background: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        .search-stats {
            margin-top: 20px;
            padding: 15px 20px;
            background: rgba(108, 92, 231, 0.1);
            border-radius: var(--border-radius-md);
            color: var(--color-primary);
            font-size: 14px;
            font-weight: 600;
            display: none;
            border-left: 4px solid var(--color-primary);
        }

        /* === Table Section === */
        .table-container {
            background: var(--color-card-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 35px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: auto; /* Permite scroll horizontal en móviles */
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0984e3, #74b9ff, #0984e3);
        }

        .boletas-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: var(--border-radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            min-width: 700px; /* Asegura un ancho mínimo para la tabla */
        }

        .boletas-table th {
            background: linear-gradient(135deg, var(--color-text), #636e72);
            color: white;
            padding: 18px 15px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .boletas-table th:first-child { border-top-left-radius: var(--border-radius-md); }
        .boletas-table th:last-child { border-top-right-radius: var(--border-radius-md); }

        .boletas-table td {
            padding: 16px 15px;
            text-align: center;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
            color: var(--color-text);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .boletas-table tr:hover {
            background: rgba(116, 185, 255, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .boletas-table tr:last-child td { border-bottom: none; }

        .numero-boleta {
            font-weight: 700;
            color: var(--color-primary);
            font-size: 15px;
        }

        .total-amount {
            font-weight: 700;
            color: var(--color-secondary);
            font-size: 16px;
            white-space: nowrap;
        }

        .download-btn {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .download-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(9, 132, 227, 0.3);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #74b9ff;
            font-size: 18px;
            background: var(--color-card-bg);
            border-radius: var(--border-radius-lg);
            margin-top: 20px;
            display: none;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-lg);
        }

        .no-results h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--color-text);
        }

        .highlight {
            background: linear-gradient(120deg, var(--color-accent) 0%, #f0932b 100%);
            padding: 2px 4px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
        }

        /* === Responsive Design === */
        @media (max-width: 1024px) {
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .search-filters {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.8rem;
                margin-bottom: 15px;
            }
            
            .page-title::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .search-section, .table-container {
                padding: 25px 20px;
            }
            
            .boletas-table {
                font-size: 14px;
            }
            
            .boletas-table th,
            .boletas-table td {
                padding: 12px 8px;
            }
            
            .search-filters {
                flex-direction: column;
                gap: 10px;
                width: 100%; /* Ocupan todo el ancho */
            }
            
            .filter-select {
                min-width: auto;
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .boletas-table th,
            .boletas-table td {
                padding: 10px 6px;
                font-size: 12px;
            }
            
            .download-btn {
                padding: 8px 14px;
                font-size: 11px;
            }

            .search-input {
                padding-right: 45px;
            }

            .clear-search {
                right: 15px;
            }
            
            .search-icon {
                right: auto;
                left: 15px;
                display: none; /* Ocultar el icono de la lupa para ganar espacio */
            }

            .search-input:focus ~ .search-icon {
                display: none;
            }
        }

    </style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">📄 Gestión de Boletas</h1>
            <a href="agregar_boleta.php" class="add-button">➕ Nueva Boleta</a>
        </div>

        <?php if($mensaje): ?>
        <div id="toast" class="<?php echo htmlspecialchars($tipoMensaje); ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <script>
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 4500);
        </script>
        <?php endif; ?>

        <div class="search-section">
            <div class="search-container">
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar por número, cliente o cajero..." autocomplete="off">
                    <span class="search-icon">🔍</span>
                    <button class="clear-search" id="clearSearch">✕</button>
                </div>
                <div class="search-filters">
                    <select id="filterBy" class="filter-select">
                        <option value="all">📋 Todos los campos</option>
                        <option value="numero">🔢 Número de Boleta</option>
                        <option value="cliente">👤 Cliente</option>
                        <option value="cajero">🏪 Cajero</option>
                        <option value="fecha">📅 Fecha</option>
                    </select>
                    <select id="sortBy" class="filter-select">
                        <option value="fecha-desc">⬇️ Más recientes</option>
                        <option value="fecha-asc">⬆️ Más antiguos</option>
                        <option value="total-desc">💰 Mayor monto</option>
                        <option value="total-asc">💸 Menor monto</option>
                        <option value="cliente">👥 Por cliente A-Z</option>
                    </select>
                </div>
            </div>
            <div class="search-stats" id="searchStats"></div>
        </div>

        <div class="table-container">
            <table class="boletas-table" id="boletasTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Número de Boleta</th>
                        <th>Fecha / Hora</th>
                        <th>Cajero</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="boletasTableBody">
                    <?php 
                    // Bucle para mostrar los resultados de MySQLi
                    if ($result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                    ?>
                    <tr data-numero="<?php echo htmlspecialchars($row['numeroBoleta']); ?>" 
                        data-cliente="<?php echo htmlspecialchars($row['clienteNombres'].' '.$row['clienteApellidos']); ?>" 
                        data-cajero="<?php echo htmlspecialchars($row['cajero']); ?>" 
                        data-fecha="<?php echo htmlspecialchars($row['fechaGeneracion']); ?>"
                        data-total="<?php echo $row['total']; ?>">
                        <td><strong><?php echo htmlspecialchars($row['idBoleta']); ?></strong></td>
                        <td class="numero-boleta"><?php echo htmlspecialchars($row['numeroBoleta']); ?></td>
                        <td class="fecha-boleta"><?php echo htmlspecialchars($row['fechaGeneracion']); ?></td>
                        <td class="cajero-boleta"><?php echo htmlspecialchars($row['cajero']); ?></td>
                        <td class="cliente-boleta"><?php echo htmlspecialchars($row['clienteNombres'].' '.$row['clienteApellidos']); ?></td>
                        <td><span class="total-amount">S/. <?php echo number_format($row['total'], 2); ?></span></td>
                        <td>
                            <a href="descargar_boleta.php?id=<?php echo htmlspecialchars($row['idBoleta']); ?>" class="download-btn">📥 PDF</a>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr>
                        <td colspan="7">
                            <div class="no-results-table">
                                <h3>No hay boletas registradas</h3>
                                <p>Comienza generando tu primera boleta.</p>
                            </div>
                        </td>
                    </tr>
                    <?php
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
        <div class="no-results" id="noResults">
            <h3>😔 No se encontraron resultados</h3>
            <p>Intenta con otros términos de búsqueda o ajusta los filtros.</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const clearSearch = document.getElementById('clearSearch');
        const filterBy = document.getElementById('filterBy');
        const sortBy = document.getElementById('sortBy');
        const searchStats = document.getElementById('searchStats');
        const tableBody = document.getElementById('boletasTableBody');
        const noResults = document.getElementById('noResults');
        const table = document.getElementById('boletasTable');
        
        let allRows = Array.from(tableBody.querySelectorAll('tr'));
        const totalRows = allRows.length;
        
        // Función para resaltar texto
        function highlightText(text, search) {
            if (!search) return text;
            const regex = new RegExp(`(${search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }
        
        // Función para limpiar highlights
        function clearHighlights() {
            document.querySelectorAll('.highlight').forEach(el => {
                const parent = el.parentNode;
                parent.innerHTML = parent.textContent;
            });
        }
        
        // Función principal para filtrar y ordenar
        function filterAndSort() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const filterType = filterBy.value;
            const sortType = sortBy.value;
            
            clearHighlights();
            
            // Filtrar filas
            let visibleRows = allRows.filter(row => {
                if (!searchTerm) return true;
                
                let searchText = '';
                switch(filterType) {
                    case 'numero':
                        searchText = row.dataset.numero.toLowerCase();
                        break;
                    case 'cliente':
                        searchText = row.dataset.cliente.toLowerCase();
                        break;
                    case 'cajero':
                        searchText = row.dataset.cajero.toLowerCase();
                        break;
                    case 'fecha':
                        searchText = row.dataset.fecha.toLowerCase();
                        break;
                    default:
                        // Busca en todos los data-attributes si no hay filtro
                        searchText = `${row.dataset.numero} ${row.dataset.cliente} ${row.dataset.cajero} ${row.dataset.fecha}`.toLowerCase();
                }
                
                return searchText.includes(searchTerm);
            });
            
            // Ordenar filas
            visibleRows.sort((a, b) => {
                switch(sortType) {
                    case 'fecha-asc':
                        return new Date(a.dataset.fecha) - new Date(b.dataset.fecha);
                    case 'fecha-desc':
                        return new Date(b.dataset.fecha) - new Date(a.dataset.fecha);
                    case 'total-asc':
                        return parseFloat(a.dataset.total) - parseFloat(b.dataset.total);
                    case 'total-desc':
                        return parseFloat(b.dataset.total) - parseFloat(a.dataset.total);
                    case 'cliente':
                        return a.dataset.cliente.localeCompare(b.dataset.cliente);
                    default:
                        return new Date(b.dataset.fecha) - new Date(a.dataset.fecha);
                }
            });
            
            // Limpiar tabla
            tableBody.innerHTML = '';
            
            // Mostrar resultados o mensaje de "no encontrado"
            if (visibleRows.length === 0) {
                table.style.display = 'none';
                noResults.style.display = 'block';
            } else {
                table.style.display = 'table';
                noResults.style.display = 'none';
                
                // Agregar filas filtradas y resaltar texto
                visibleRows.forEach(row => {
                    if (searchTerm) {
                        // Resaltar en la columna adecuada
                        ['numero-boleta', 'cliente-boleta', 'cajero-boleta', 'fecha-boleta'].forEach(className => {
                            const cell = row.querySelector(`.${className}`);
                            if (cell) {
                                // Aplicar el resaltado solo si el filtro es 'all' o coincide con la columna
                                if (filterType === 'all' || (filterType === 'numero' && className === 'numero-boleta') || (filterType === 'cliente' && className === 'cliente-boleta') || (filterType === 'cajero' && className === 'cajero-boleta') || (filterType === 'fecha' && className === 'fecha-boleta')) {
                                    cell.innerHTML = highlightText(cell.textContent, searchTerm);
                                }
                            }
                        });
                    }
                    tableBody.appendChild(row);
                });
            }
            
            // Actualizar estadísticas
            updateStats(visibleRows.length, searchTerm);
            
            // Mostrar/ocultar botón de limpiar
            clearSearch.style.display = searchTerm ? 'block' : 'none';
        }
        
        // Función para actualizar estadísticas
        function updateStats(visible, searchTerm) {
            if (searchTerm) {
                searchStats.textContent = `📊 Mostrando ${visible} de ${totalRows} boletas`;
                searchStats.style.display = 'block';
            } else {
                searchStats.style.display = 'none';
            }
        }
        
        // Event listeners
        searchInput.addEventListener('input', filterAndSort);
        filterBy.addEventListener('change', filterAndSort);
        sortBy.addEventListener('change', filterAndSort);
        
        clearSearch.addEventListener('click', function() {
            searchInput.value = '';
            filterAndSort();
            searchInput.focus();
        });
        
        // Focus automático en el campo de búsqueda
        searchInput.focus();
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F para enfocar búsqueda
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            // Escape para limpiar búsqueda
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                filterAndSort();
            }
        });
    });
    </script>

    <?php include(__DIR__ . "/includes/footer.php"); ?>
</body>
</html>
