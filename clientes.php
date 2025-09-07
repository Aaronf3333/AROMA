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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clientes - Aroma S.A.C</title>

<style>

:root {

--primary-color: #3b3b98;

--secondary-color: #575fcf;

--background-color: #f4f4f9;

--card-background: #fff;

--text-color: #2c2c54;

--gray-text: #6c757d;

--light-gray: #e1e8ed;

--shadow-light: rgba(0,0,0,0.1);

--shadow-medium: rgba(0,0,0,0.2);

--success-color: #27ae60;

--warning-color: #f39c12;

--info-color: #2980b9;

--error-color: #c0392b;

}



body {

font-family: 'Segoe UI', Arial, sans-serif;

padding: 20px;

background: var(--background-color);

margin: 0;

color: var(--text-color);

}


main {

max-width: 1200px;

margin: 0 auto;

padding: 0 15px;

}



.search-section, .add-client-form, .clients-table-container {

background: var(--card-background);

padding: 25px;

border-radius: 12px;

box-shadow: 0 4px 15px var(--shadow-light);

margin-bottom: 30px;

}



.search-container {

display: flex;

gap: 15px;

align-items: center;

flex-wrap: wrap;

}



.search-box {

position: relative;

flex: 1;

min-width: 250px;

}


.search-input, .form-input, .filter-select {

width: 100%;

padding: 12px 15px;

border: 2px solid var(--light-gray);

border-radius: 8px;

font-size: 16px;

transition: all 0.3s ease;

background: #f8f9fa;

box-sizing: border-box;

color: var(--text-color);

}



.search-input {

padding-right: 50px;

}



.search-input:focus, .form-input:focus, .filter-select:focus {

outline: none;

border-color: var(--primary-color);

background: var(--card-background);

box-shadow: 0 0 0 3px rgba(59, 59, 152, 0.1);

}


.search-icon {

position: absolute;

right: 15px;

top: 50%;

transform: translateY(-50%);

color: var(--gray-text);

font-size: 18px;

}


.clear-search {

position: absolute;

right: 45px;

top: 50%;

transform: translateY(-50%);

background: none;

border: none;

color: var(--gray-text);

font-size: 18px;

cursor: pointer;

padding: 5px;

border-radius: 50%;

display: none;

}


.clear-search:hover {

background: #e9ecef;

color: #495057;

}



.search-filters {

display: flex;

gap: 10px;

flex-wrap: wrap;

}



.filter-select {

padding: 10px 12px;

border-radius: 8px;

font-size: 14px;

}



.search-stats {

margin-top: 15px;

padding: 10px;

background: #e8f4fd;

border-radius: 8px;

color: #2c5aa0;

font-size: 14px;

display: none;

font-weight: 500;

}



.form-row {

display: grid;

grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));

gap: 20px;

margin-bottom: 20px;

}


.form-group {

display: flex;

flex-direction: column;

}


.form-button {

background: var(--primary-color);

color: white;

padding: 14px 30px;

border: none;

border-radius: 8px;

font-size: 16px;

font-weight: bold;

cursor: pointer;

transition: all 0.3s ease;

align-self: flex-start;

}



.form-button:hover {

background: var(--secondary-color);

transform: translateY(-2px);

box-shadow: 0 6px 15px rgba(59, 59, 152, 0.3);

}


table {

width: 100%;

border-collapse: collapse;

table-layout: fixed;

}


/* Ajuste de compacidad */

th, td {

padding: 10px 8px; /* Reducción de padding */

font-size: 13px; /* Reducción del tamaño de la fuente */

text-align: center;

border-bottom: 1px solid var(--light-gray);

white-space: nowrap;

overflow: hidden;

text-overflow: ellipsis;

}



th {

background: var(--primary-color);

color: white;

font-weight: bold;

position: sticky;

top: 0;

z-index: 10;

}


tbody tr {

transition: all 0.2s ease;

}


tbody tr:hover {

background-color: #f8f9fa;

transform: translateY(-1px);

box-shadow: 0 2px 8px var(--shadow-light);

}


.status-badge {

padding: 5px 10px; /* Reducción de padding */

border-radius: 20px;

color: white;

font-weight: bold;

font-size: 11px; /* Reducción del tamaño de la fuente */

text-transform: uppercase;

display: inline-block;

}



.status-active { background: var(--success-color); }

.status-inactive { background: var(--error-color); }


.action-buttons {

display: flex;

justify-content: center;

gap: 5px; /* Reducción de gap */

align-items: center;

flex-wrap: wrap;

}


.btn {

color: white;

padding: 6px 12px; /* Reducción de padding */

border-radius: 6px;

text-decoration: none;

font-size: 12px; /* Reducción del tamaño de la fuente */

font-weight: bold;

transition: all 0.3s ease;

border: none;

cursor: pointer;

white-space: nowrap;

}



.btn:hover {

transform: translateY(-2px);

box-shadow: 0 4px 10px var(--shadow-medium);

}


.btn-edit { background: #00a8ff; }

.btn-edit:hover { background: #0097e6; }

.btn-activate { background: var(--success-color); }

.btn-activate:hover { background: #219a52; }

.btn-deactivate { background: var(--error-color); }

.btn-deactivate:hover { background: #d63031; }


.no-results {

text-align: center;

padding: 50px;

color: var(--gray-text);

font-size: 18px;

background: var(--card-background);

border-radius: 12px;

box-shadow: 0 4px 15px var(--shadow-light);

margin-top: 20px;

display: none;

}



.highlight {

background-color: #ffeb3b;

padding: 1px 2px;

border-radius: 2px;

}


#toast {

position: fixed;

top: 20px;

left: 50%;

transform: translateX(-50%) translateY(-20px);

min-width: 300px;

max-width: 90%;

padding: 18px 25px;

border-radius: 12px;

color: #fff;

font-size: 16px;

font-weight: bold;

text-align: center;

box-shadow: 0 4px 15px rgba(0,0,0,0.3);

opacity: 0;

transition: opacity 0.5s ease, transform 0.5s ease;

z-index: 9999;

}


#toast.show {

opacity: 1;

transform: translateX(-50%) translateY(0);

}


#toast.success { background-color: var(--success-color); }

#toast.info { background-color: var(--info-color); }

#toast.warning { background-color: var(--warning-color); }

#toast.error { background-color: var(--error-color); }


h2 {

color: var(--text-color);

margin-bottom: 25px;

font-size: 32px;

font-weight: 700;

}



h3 {

color: var(--primary-color);

margin-bottom: 20px;

font-size: 24px;

font-weight: 600;

}



/* Responsive Styles */

@media (max-width: 1024px) {

.clients-table-container {

overflow-x: auto;

}

table {

table-layout: auto;

min-width: 900px;

}

th, td {

padding: 10px 8px;

}

.search-filters {

flex-direction: column;

width: 100%;

}

.filter-select {

width: 100%;

}

}



@media (max-width: 768px) {

body {

padding: 10px;

}

main {

padding: 0;

}

.search-section, .add-client-form, .clients-table-container {

padding: 15px;

}

h2 {

font-size: 26px;

text-align: center;

}

h3 {

font-size: 20px;

}

.search-container {

flex-direction: column;

align-items: stretch;

gap: 15px;

}

.search-box {

min-width: auto;

}

.search-filters {

flex-direction: column;

gap: 10px;

}

.form-row {

grid-template-columns: 1fr;

gap: 15px;

}

.form-button {

width: 100%;

text-align: center;

}


.clients-table-container {

box-shadow: none;

border-radius: 0;

}


table {

display: block;

width: 100%;

}

thead {

display: none;

}

tbody, tr, td {

display: block;

width: 100%;

box-sizing: border-box;

}

tr {

margin-bottom: 15px;

background: white;

border: 1px solid var(--light-gray);

border-radius: 8px;

box-shadow: 0 2px 10px rgba(0,0,0,0.05);

padding: 10px;

}

td {

text-align: right;

position: relative;

padding-left: 50%;

border-bottom: none;

white-space: normal;

}

td::before {

content: attr(data-label);

position: absolute;

left: 10px;

width: 45%;

padding-right: 10px;

white-space: nowrap;

text-align: left;

font-weight: bold;

color: var(--primary-color);

}

td:last-child {

border-bottom: 1px solid var(--light-gray);

}

.action-buttons {

flex-direction: row;

justify-content: space-around;

gap: 5px; /* Mantener la compacidad en móviles */

}

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

<td data-label="ID"><?php echo $row['idCliente']; ?></td>

<td data-label="Nombres" class="nombres-cell"><?php echo htmlspecialchars($row['nombres']); ?></td>

<td data-label="Apellidos" class="apellidos-cell"><?php echo htmlspecialchars($row['apellidos']); ?></td>

<td data-label="Tipo Doc." class="tipo-doc-cell"><?php echo htmlspecialchars($row['tipoDocumento']); ?></td>

<td data-label="Número Doc." class="numero-doc-cell"><?php echo htmlspecialchars($row['numeroDocumento']); ?></td>

<td data-label="Dirección" class="direccion-cell"><?php echo htmlspecialchars($row['direccion']); ?></td>

<td data-label="Teléfono" class="telefono-cell"><?php echo htmlspecialchars($row['telefono']); ?></td>

<td data-label="Estado / Acciones">

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
