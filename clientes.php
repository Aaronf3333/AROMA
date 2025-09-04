<?php
session_start();
include(__DIR__ . "/conexion.php"); // Conexión a MySQL

// Verificar la conexión
if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

// Variables para los mensajes de notificación
$mensaje = "";
$tipoMensaje = ""; // success, info, warning, error

// ------------------
// PROCESAR FORMULARIO
// ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombres'])) {
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $tipoDocumento = $_POST['tipoDocumento'];
    $numeroDocumento = trim($_POST['numeroDocumento']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);

    // Iniciar una transacción para asegurar la integridad de los datos
    $conn->begin_transaction();

    try {
        // Verificar si la persona ya existe en la tabla `Persona`
        $checkSql = "SELECT idPersona FROM persona WHERE tipoDocumento = ? AND numeroDocumento = ?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("ss", $tipoDocumento, $numeroDocumento);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $rowCheck = $resultCheck->fetch_assoc();

        if ($rowCheck) {
            $idPersona = $rowCheck['idPersona'];

            // Si la persona existe, verificar si ya es un cliente
            $checkClienteSql = "SELECT idCliente FROM cliente WHERE idPersona = ?";
            $stmtCheckCliente = $conn->prepare($checkClienteSql);
            $stmtCheckCliente->bind_param("i", $idPersona);
            $stmtCheckCliente->execute();
            $resultCheckCliente = $stmtCheckCliente->get_result();
            $rowCheckCliente = $resultCheckCliente->fetch_assoc();

            if ($rowCheckCliente) {
                $_SESSION['mensaje'] = "Este cliente ya está registrado.";
                $_SESSION['tipo'] = "warning";
            } else {
                // Agregar la persona existente como cliente
                $sqlCliente = "INSERT INTO cliente (idPersona) VALUES (?)";
                $stmtCliente = $conn->prepare($sqlCliente);
                $stmtCliente->bind_param("i", $idPersona);
                $stmtCliente->execute();
                $_SESSION['mensaje'] = "Cliente existente agregado correctamente.";
                $_SESSION['tipo'] = "info";
            }
        } else {
            // Si la persona no existe, insertarla primero en la tabla `Persona`
            $sqlPersona = "INSERT INTO persona (nombres, apellidos, tipoDocumento, numeroDocumento, direccion, telefono)
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmtPersona = $conn->prepare($sqlPersona);
            $stmtPersona->bind_param("ssssss", $nombres, $apellidos, $tipoDocumento, $numeroDocumento, $direccion, $telefono);
            $stmtPersona->execute();

            // Obtener el ID de la persona recién insertada (MySQL)
            $idPersona = $conn->insert_id;

            // Insertar el nuevo cliente usando el ID de la persona
            $sqlCliente = "INSERT INTO cliente (idPersona) VALUES (?)";
            $stmtCliente = $conn->prepare($sqlCliente);
            $stmtCliente->bind_param("i", $idPersona);
            $stmtCliente->execute();
            $_SESSION['mensaje'] = "Cliente agregado correctamente.";
            $_SESSION['tipo'] = "success";
        }

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['mensaje'] = "Error al procesar la solicitud: " . $e->getMessage();
        $_SESSION['tipo'] = "error";
    }

    header("Location: clientes.php");
    exit();
}

// ------------------
// MANEJO DE ACTIVAR/DESACTIVAR
// ------------------
if (isset($_GET['toggle'])) {
    $idCliente = intval($_GET['toggle']);
    
    // Iniciar una transacción para la operación de toggle
    $conn->begin_transaction();

    try {
        $sql = "SELECT activo FROM cliente WHERE idCliente = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $idCliente);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row) {
            $nuevoEstado = $row['activo'] ? 0 : 1;
            $sqlUpdate = "UPDATE cliente SET activo = ? WHERE idCliente = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("ii", $nuevoEstado, $idCliente);
            $stmtUpdate->execute();

            $_SESSION['mensaje'] = $nuevoEstado ? "Cliente activado correctamente." : "Cliente desactivado correctamente.";
            $_SESSION['tipo'] = $nuevoEstado ? "success" : "warning";
        } else {
            $_SESSION['mensaje'] = "Cliente no encontrado.";
            $_SESSION['tipo'] = "error";
        }
        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['mensaje'] = "Error al cambiar estado: " . $e->getMessage();
        $_SESSION['tipo'] = "error";
    }

    header("Location: clientes.php");
    exit();
}

// ------------------
// OBTENER CLIENTES
// ------------------
$sqlClientes = "SELECT c.idCliente, p.nombres, p.apellidos, p.tipoDocumento, p.numeroDocumento, 
                        p.direccion, p.telefono, c.activo
                FROM cliente c
                JOIN persona p ON c.idPersona = p.idPersona
                ORDER BY c.idCliente ASC";
$resultClientes = $conn->query($sqlClientes);

// Mostrar mensaje si existe en sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipoMensaje = $_SESSION['tipo'] ?? "success";
    unset($_SESSION['mensaje'], $_SESSION['tipo']);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes - Aroma S.A.C</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f9; margin: 0; }
        .search-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .search-container { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .search-box { position: relative; flex: 1; min-width: 250px; }
        .search-input { width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #e1e8ed; border-radius: 25px; font-size: 16px; transition: all 0.3s ease; background: #f8f9fa; box-sizing: border-box; }
        .search-input:focus { outline: none; border-color: #3b3b98; background: white; box-shadow: 0 0 0 3px rgba(59, 59, 152, 0.1); }
        .search-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 18px; }
        .clear-search { position: absolute; right: 45px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; font-size: 18px; cursor: pointer; padding: 5px; border-radius: 50%; display: none; }
        .clear-search:hover { background: #e9ecef; color: #495057; }
        .search-filters { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; background: white; font-size: 14px; }
        .search-stats { margin-top: 15px; padding: 10px; background: #e8f4fd; border-radius: 5px; color: #2c5aa0; font-size: 14px; display: none; }
        .add-client-form { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; }
        .form-input { padding: 12px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #f8f9fa; }
        .form-input:focus { outline: none; border-color: #3b3b98; background: white; box-shadow: 0 0 0 3px rgba(59, 59, 152, 0.1); }
        .form-button { background: #3b3b98; color: white; padding: 12px 25px; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; align-self: flex-start; }
        .form-button:hover { background: #575fcf; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59, 59, 152, 0.3); }
        .clients-table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 8px; text-align: center; border-bottom: 1px solid #e1e8ed; }
        th { background: #3b3b98; color: white; font-weight: bold; position: sticky; top: 0; z-index: 10; }
        tbody tr { transition: all 0.2s ease; }
        tbody tr:hover { background-color: #f8f9fa; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .status-badge { padding: 5px 10px; border-radius: 15px; color: white; font-weight: bold; font-size: 12px; text-transform: uppercase; }
        .status-active { background: #27ae60; }
        .status-inactive { background: #c0392b; }
        .action-buttons { display: flex; justify-content: center; gap: 5px; align-items: center; flex-wrap: wrap; }
        .btn { color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: bold; transition: all 0.3s ease; border: none; cursor: pointer; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .btn-edit { background: #00a8ff; }
        .btn-edit:hover { background: #0097e6; }
        .btn-activate { background: #27ae60; }
        .btn-activate:hover { background: #219a52; }
        .btn-deactivate { background: #e84118; }
        .btn-deactivate:hover { background: #d63031; }
        .no-results { text-align: center; padding: 40px; color: #6c757d; font-size: 18px; background: white; border-radius: 10px; margin-top: 20px; display: none; }
        .highlight { background-color: yellow; padding: 1px 2px; border-radius: 2px; }
        #toast { position: fixed; top: 20px; left: 50%; transform: translateX(-50%) translateY(-20px); min-width: 300px; max-width: 400px; padding: 15px 20px; border-radius: 10px; color: #fff; font-size: 16px; font-weight: bold; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); opacity: 0; transition: opacity 0.5s ease, transform 0.5s ease; z-index: 9999; }
        #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        #toast.success { background-color: #27ae60; }
        #toast.info { background-color: #2980b9; }
        #toast.warning { background-color: #f39c12; }
        #toast.error { background-color: #c0392b; }
        h2 { color: #2c2c54; margin-bottom: 25px; font-size: 28px; }
        h3 { color: #3b3b98; margin-bottom: 15px; font-size: 20px; }
        @media (max-width: 768px) {
            .search-container { flex-direction: column; align-items: stretch; }
            .search-box { min-width: auto; }
            .form-row { grid-template-columns: 1fr; }
            table { font-size: 12px; }
            th, td { padding: 8px 4px; }
            .action-buttons { flex-direction: column; gap: 3px; }
            .btn { font-size: 11px; padding: 4px 8px; }
        }
    </style>
</head>
<body>
<?php include(__DIR__ . "/includes/header.php"); ?>

<main>
    <h2>Gestión de Clientes</h2>

    <?php if($mensaje): ?>
    <div id="toast" class="<?php echo htmlspecialchars($tipoMensaje); ?>">
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
    <script>
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => { toast.classList.remove('show'); }, 4000);
    </script>
    <?php endif; ?>

    <div class="add-client-form">
        <h3>Agregar Nuevo Cliente</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="nombres" placeholder="Nombres" required class="form-input">
                </div>
                <div class="form-group">
                    <input type="text" name="apellidos" placeholder="Apellidos" required class="form-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <select name="tipoDocumento" required class="form-input">
                        <option value="">Seleccione tipo de documento</option>
                        <option value="DNI">DNI</option>
                        <option value="CE">CE</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" name="numeroDocumento" placeholder="Número de documento" required class="form-input">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="direccion" placeholder="Dirección" class="form-input">
                </div>
                <div class="form-group">
                    <input type="text" name="telefono" placeholder="Teléfono" class="form-input">
                </div>
            </div>
            <button type="submit" class="form-button">Agregar Cliente</button>
        </form>
    </div>

    <div class="search-section">
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre, documento, dirección, teléfono..." autocomplete="off">
                <span class="search-icon">🔍</span>
                <button class="clear-search" id="clearSearch">✕</button>
            </div>
            <div class="search-filters">
                <select id="filterBy" class="filter-select">
                    <option value="all">Todos los campos</option>
                    <option value="nombre">Nombre completo</option>
                    <option value="documento">Documento</option>
                    <option value="direccion">Dirección</option>
                    <option value="telefono">Teléfono</option>
                    <option value="estado">Estado</option>
                </select>
                <select id="statusFilter" class="filter-select">
                    <option value="all">Todos los estados</option>
                    <option value="active">Solo activos</option>
                    <option value="inactive">Solo inactivos</option>
                </select>
                <select id="sortBy" class="filter-select">
                    <option value="id-asc">Por ID (ascendente)</option>
                    <option value="id-desc">Por ID (descendente)</option>
                    <option value="nombre-asc">Por nombre A-Z</option>
                    <option value="nombre-desc">Por nombre Z-A</option>
                </select>
            </div>
        </div>
        <div class="search-stats" id="searchStats"></div>
    </div>

    <div class="clients-table-container">
        <table id="clientsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Tipo Doc.</th>
                    <th>Número Doc.</th>
                    <th>Dirección</th>
                    <th>Teléfono</th>
                    <th>Estado / Acciones</th>
                </tr>
            </thead>
            <tbody id="clientsTableBody">
            <?php 
            if ($resultClientes && $resultClientes->num_rows > 0) {
                while($row = $resultClientes->fetch_assoc()) { 
            ?>
            <tr data-id="<?php echo $row['idCliente']; ?>"
                data-nombre="<?php echo htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']); ?>"
                data-nombres="<?php echo htmlspecialchars($row['nombres']); ?>"
                data-apellidos="<?php echo htmlspecialchars($row['apellidos']); ?>"
                data-documento="<?php echo htmlspecialchars($row['tipoDocumento'] . ' ' . $row['numeroDocumento']); ?>"
                data-direccion="<?php echo htmlspecialchars($row['direccion']); ?>"
                data-telefono="<?php echo htmlspecialchars($row['telefono']); ?>"
                data-estado="<?php echo $row['activo'] ? 'activo' : 'inactivo'; ?>">
                <td><?php echo $row['idCliente']; ?></td>
                <td class="nombres-cell"><?php echo htmlspecialchars($row['nombres']); ?></td>
                <td class="apellidos-cell"><?php echo htmlspecialchars($row['apellidos']); ?></td>
                <td class="tipo-doc-cell"><?php echo htmlspecialchars($row['tipoDocumento']); ?></td>
                <td class="numero-doc-cell"><?php echo htmlspecialchars($row['numeroDocumento']); ?></td>
                <td class="direccion-cell"><?php echo htmlspecialchars($row['direccion']); ?></td>
                <td class="telefono-cell"><?php echo htmlspecialchars($row['telefono']); ?></td>
                <td>
                    <div class="action-buttons">
                        <span class="status-badge <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                        <a href="editar_cliente.php?id=<?php echo $row['idCliente']; ?>" class="btn btn-edit">Editar</a>
                        <a href="?toggle=<?php echo $row['idCliente']; ?>" 
                           class="btn <?php echo $row['activo'] ? 'btn-deactivate' : 'btn-activate'; ?>">
                           <?php echo $row['activo'] ? 'Desactivar' : 'Activar'; ?>
                        </a>
                    </div>
                </td>
            </tr>
            <?php 
                }
            } else {
                echo "<tr><td colspan='8'>No se encontraron clientes.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

    <div class="no-results" id="noResults">
        <h3>😔 No se encontraron clientes</h3>
        <p>Intenta con otros términos de búsqueda</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');
            const filterBy = document.getElementById('filterBy');
            const statusFilter = document.getElementById('statusFilter');
            const sortBy = document.getElementById('sortBy');
            const searchStats = document.getElementById('searchStats');
            const tableBody = document.getElementById('clientsTableBody');
            const noResults = document.getElementById('noResults');
            const table = document.getElementById('clientsTable');
            
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
                    el.outerHTML = el.innerHTML;
                });
            }
            
            // Función para filtrar y ordenar
            function filterAndSort() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const filterType = filterBy.value;
                const statusFilterValue = statusFilter.value;
                const sortType = sortBy.value;
                
                clearHighlights();
                
                // Filtrar filas
                let visibleRows = allRows.filter(row => {
                    // Filtro de estado
                    if (statusFilterValue !== 'all') {
                        const isActive = row.dataset.estado === 'activo';
                        if (statusFilterValue === 'active' && !isActive) return false;
                        if (statusFilterValue === 'inactive' && isActive) return false;
                    }
                    
                    // Filtro de búsqueda
                    if (!searchTerm) return true;
                    
                    let searchText = '';
                    switch(filterType) {
                        case 'nombre':
                            searchText = row.dataset.nombre.toLowerCase();
                            break;
                        case 'documento':
                            searchText = row.dataset.documento.toLowerCase();
                            break;
                        case 'direccion':
                            searchText = row.dataset.direccion.toLowerCase();
                            break;
                        case 'telefono':
                            searchText = row.dataset.telefono.toLowerCase();
                            break;
                        case 'estado':
                            searchText = row.dataset.estado.toLowerCase();
                            break;
                        default:
                            searchText = `${row.dataset.nombre} ${row.dataset.documento} ${row.dataset.direccion} ${row.dataset.telefono} ${row.dataset.estado}`.toLowerCase();
                    }
                    
                    return searchText.includes(searchTerm);
                });
                
                // Ordenar filas
                visibleRows.sort((a, b) => {
                    switch(sortType) {
                        case 'id-asc':
                            return parseInt(a.dataset.id) - parseInt(b.dataset.id);
                        case 'id-desc':
                            return parseInt(b.dataset.id) - parseInt(a.dataset.id);
                        case 'nombre-asc':
                            return a.dataset.nombre.localeCompare(b.dataset.nombre);
                        case 'nombre-desc':
                            return b.dataset.nombre.localeCompare(a.dataset.nombre);
                        default:
                            return parseInt(a.dataset.id) - parseInt(b.dataset.id);
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
                            if (filterType === 'nombre') {
                                const nombresCell = row.querySelector('.nombres-cell');
                                const apellidosCell = row.querySelector('.apellidos-cell');
                                nombresCell.innerHTML = highlightText(nombresCell.textContent, searchTerm);
                                apellidosCell.innerHTML = highlightText(apellidosCell.textContent, searchTerm);
                            } else if (filterType === 'documento') {
                                const tipoDocCell = row.querySelector('.tipo-doc-cell');
                                const numeroDocCell = row.querySelector('.numero-doc-cell');
                                tipoDocCell.innerHTML = highlightText(tipoDocCell.textContent, searchTerm);
                                numeroDocCell.innerHTML = highlightText(numeroDocCell.textContent, searchTerm);
                            } else if (filterType === 'direccion') {
                                const direccionCell = row.querySelector('.direccion-cell');
                                direccionCell.innerHTML = highlightText(direccionCell.textContent, searchTerm);
                            } else if (filterType === 'telefono') {
                                const telefonoCell = row.querySelector('.telefono-cell');
                                telefonoCell.innerHTML = highlightText(telefonoCell.textContent, searchTerm);
                            } else if (filterType === 'all') {
                                ['nombres-cell', 'apellidos-cell', 'tipo-doc-cell', 'numero-doc-cell', 'direccion-cell', 'telefono-cell'].forEach(className => {
                                    const cell = row.querySelector(`.${className}`);
                                    if (cell) {
                                        cell.innerHTML = highlightText(cell.textContent, searchTerm);
                                    }
                                });
                            }
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
                if (searchTerm || statusFilter.value !== 'all') {
                    searchStats.textContent = `Mostrando ${visible} de ${totalRows} clientes`;
                    searchStats.style.display = 'block';
                } else {
                    searchStats.style.display = 'none';
                }
            }
            
            // Event listeners
            searchInput.addEventListener('input', filterAndSort);
            filterBy.addEventListener('change', filterAndSort);
            statusFilter.addEventListener('change', filterAndSort);
            sortBy.addEventListener('change', filterAndSort);
            
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                filterAndSort();
                searchInput.focus();
            });
            
            searchInput.focus();
            
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    filterAndSort();
                }
            });
        });
    </script>
</main>

<?php include(__DIR__ . "/includes/footer.php"); ?>
</body>

</html>

