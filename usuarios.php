<?php
session_start();
include(__DIR__ . "/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

// ------------------
// PROCESAR NUEVO USUARIO
// ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo'])) {
    $nombres = $_POST['nombres'];
    $apellidos = $_POST['apellidos'];
    $tipoDocumento = $_POST['tipoDocumento'];
    $numeroDocumento = $_POST['numeroDocumento'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $idRol = intval($_POST['idRol']);

    $conn->begin_transaction();

    try {
        $sqlPersona = "INSERT INTO persona (nombres, apellidos, tipoDocumento, numeroDocumento, direccion, telefono) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmtPersona = $conn->prepare($sqlPersona);
        $stmtPersona->bind_param("ssssss", $nombres, $apellidos, $tipoDocumento, $numeroDocumento, $direccion, $telefono);
        $stmtPersona->execute();

        $idPersona = $conn->insert_id;

        $sqlUsuario = "INSERT INTO usuario (usuario, contrasena, idPersona, idRol) VALUES (?, ?, ?, ?)";
        $stmtUsuario = $conn->prepare($sqlUsuario);
        $stmtUsuario->bind_param("ssii", $usuario, $contrasena, $idPersona, $idRol);
        $stmtUsuario->execute();

        $conn->commit();
        $_SESSION['toast_message'] = "Usuario creado correctamente";
        $_SESSION['toast_type'] = 'success';
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['toast_message'] = "Error al crear usuario. Por favor, intente de nuevo. Error: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ------------------
// PROCESAR EDICIÓN
// ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    $idUsuario = intval($_POST['idUsuario']);
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];

    $conn->begin_transaction();

    try {
        $sqlUpdateUsuario = "UPDATE usuario SET usuario = ?, contrasena = ? WHERE idUsuario = ?";
        $stmtUsuario = $conn->prepare($sqlUpdateUsuario);
        $stmtUsuario->bind_param("ssi", $usuario, $contrasena, $idUsuario);
        $stmtUsuario->execute();

        $sqlIdPersona = "SELECT idPersona FROM usuario WHERE idUsuario = ?";
        $stmtIdPersona = $conn->prepare($sqlIdPersona);
        $stmtIdPersona->bind_param("i", $idUsuario);
        $stmtIdPersona->execute();
        $resultIdPersona = $stmtIdPersona->get_result();
        $idPersona = $resultIdPersona->fetch_assoc()['idPersona'];

        $sqlUpdatePersona = "UPDATE persona SET direccion = ?, telefono = ? WHERE idPersona = ?";
        $stmtPersona = $conn->prepare($sqlUpdatePersona);
        $stmtPersona->bind_param("ssi", $direccion, $telefono, $idPersona);
        $stmtPersona->execute();

        $conn->commit();
        $_SESSION['toast_message'] = "Usuario actualizado correctamente";
        $_SESSION['toast_type'] = 'success';
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['toast_message'] = "Error al actualizar. Por favor, intente de nuevo. Error: " . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ------------------
// OBTENER USUARIOS Y ROLES
// ------------------
$sqlUsuarios = "SELECT u.idUsuario, u.usuario, u.contrasena, r.nombreRol, p.nombres, p.apellidos, p.direccion, p.telefono
                 FROM usuario u
                 INNER JOIN rol r ON u.idRol = r.idRol
                 INNER JOIN persona p ON u.idPersona = p.idPersona
                 ORDER BY u.idUsuario ASC";
$resultUsuarios = $conn->query($sqlUsuarios);

$sqlRoles = "SELECT idRol, nombreRol FROM rol ORDER BY idRol ASC";
$resultRoles = $conn->query($sqlRoles);
$roles = [];
while($r = $resultRoles->fetch_assoc()){
    $roles[] = $r;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios - Aroma S.A.C</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Variables CSS para colores consistentes */
:root {
    --primary-color: #667eea;
    --primary-dark: #5a67d8;
    --success-color: #48bb78;
    --error-color: #f56565;
    --warning-color: #ed8936;
    --background-color: #f7fafc;
    --card-background: #ffffff;
    --text-primary: #2d3748;
    --text-secondary: #718096;
    --border-color: #e2e8f0;
    --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: var(--text-primary);
    line-height: 1.6;
}

.main-wrapper {
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    background: var(--card-background);
    border-radius: 20px;
    box-shadow: var(--shadow);
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.header-section {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 2rem;
    text-align: center;
    position: relative;
}

.header-section::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #48bb78, #667eea, #f56565);
}

.header-section h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.header-section p {
    opacity: 0.9;
    font-size: 1.1rem;
}

.content-section {
    padding: 2rem;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #38a169);
    color: white;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.table-container {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color);
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

th {
    padding: 1.2rem 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-color);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 1rem;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
}

tbody tr {
    transition: background-color 0.2s ease;
}

tbody tr:hover {
    background-color: #f8f9fa;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

input[type="text"], input[type="password"], select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

input[type="text"]:focus, input[type="password"]:focus, select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.password-container {
    position: relative;
    display: flex;
    align-items: center;
}

.password-toggle {
    position: absolute;
    right: 12px;
    cursor: pointer;
    color: var(--text-secondary);
    padding: 4px;
    border-radius: 4px;
    transition: color 0.2s ease;
}

.password-toggle:hover {
    color: var(--primary-color);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1000;
    backdrop-filter: blur(5px);
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.modal-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #f1f3f4;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--text-secondary);
    transition: all 0.2s ease;
}

.close:hover {
    background: var(--error-color);
    color: white;
    transform: scale(1.1);
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    animation: slideInRight 0.3s ease;
    position: relative;
    overflow: hidden;
}

.toast.success {
    border-left-color: var(--success-color);
    background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
}

.toast.error {
    border-left-color: var(--error-color);
    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
}

.toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.toast.success .toast-icon {
    background: var(--success-color);
}

.toast.error .toast-icon {
    background: var(--error-color);
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    margin-bottom: 2px;
    font-size: 0.9rem;
}

.toast-message {
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: var(--text-secondary);
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.toast-close:hover {
    background: rgba(0,0,0,0.1);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.toast.hiding {
    animation: slideOutRight 0.3s ease;
}

/* Progress bar en toast */
.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(0,0,0,0.2);
    animation: progressBar 5s linear;
}

.toast.success .toast-progress {
    background: var(--success-color);
}

.toast.error .toast-progress {
    background: var(--error-color);
}

@keyframes progressBar {
    from { width: 100%; }
    to { width: 0%; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        margin: 10px;
        border-radius: 16px;
    }
    
    .header-section {
        padding: 1.5rem 1rem;
    }
    
    .header-section h2 {
        font-size: 2rem;
    }
    
    .content-section {
        padding: 1.5rem 1rem;
    }
    
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    table {
        font-size: 0.9rem;
    }
    
    th, td {
        padding: 0.8rem 0.5rem;
    }
    
    .modal-content {
        margin: 20px;
        width: calc(100% - 40px);
    }
    
    .toast {
        min-width: auto;
        margin: 0 10px;
    }
}

/* Loading Animation */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    color: white;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.5rem;
    background-color: #1a202c; 
    color: white;
}
</style>
</head>
<body>
<div class="main-wrapper">
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <div class="header-section">
            <h2><i class="fas fa-users-cog"></i> Gestión de Usuarios</h2>
            <p>Administra los usuarios del sistema de manera eficiente</p>
        </div>

        <div class="content-section">
            <div class="action-bar">
                <div>
                    <h3>Lista de Usuarios</h3>
                    <p class="text-secondary">Gestiona y edita los usuarios existentes</p>
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, correo, dirección..." style="margin-top: 10px; padding: 8px 12px; border: 2px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 250px;">
                </div>
                <button class="btn btn-primary" onclick="abrirModal()">
                    <i class="fas fa-user-plus"></i> Agregar Usuario
                </button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Nombre Completo</th>
                            <th><i class="fas fa-envelope"></i> Correo</th>
                            <th><i class="fas fa-lock"></i> Contraseña</th>
                            <th><i class="fas fa-map-marker-alt"></i> Dirección</th>
                            <th><i class="fas fa-phone"></i> Teléfono</th>
                            <th><i class="fas fa-user-tag"></i> Rol</th>
                            <th><i class="fas fa-cogs"></i> Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php 
                        if ($resultUsuarios && $resultUsuarios->num_rows > 0) {
                            while($row = $resultUsuarios->fetch_assoc()): ?>
                            <tr>
                                <form method="POST" onsubmit="showLoading(this)">
                                    <td>
                                        <strong><?php echo $row['idUsuario']; ?></strong>
                                        <input type="hidden" name="idUsuario" value="<?php echo $row['idUsuario']; ?>">
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($row['nombres'].' '.$row['apellidos']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="usuario" value="<?php echo htmlspecialchars($row['usuario']); ?>" required>
                                    </td>
                                    <td>
                                        <div class="password-container">
                                            <input type="password" name="contrasena" value="<?php echo htmlspecialchars($row['contrasena']); ?>" 
                                                   class="pass-input-<?php echo $row['idUsuario']; ?>" required>
                                            <i class="fas fa-eye password-toggle" onclick="togglePass(<?php echo $row['idUsuario']; ?>)"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="direccion" value="<?php echo htmlspecialchars($row['direccion']); ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($row['telefono']); ?>">
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <?php echo $row['nombreRol']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="submit" name="editar" class="btn btn-success">
                                            <i class="fas fa-save"></i> Guardar
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            <?php endwhile; 
                        } else {
                            echo "<tr><td colspan='8' style='text-align: center;'>No se encontraron usuarios.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="modalNuevo">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Agregar Nuevo Usuario</h3>
                <button class="close" onclick="cerrarModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" onsubmit="showLoadingModal(this)">
                <input type="hidden" name="nuevo" value="1">
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nombres</label>
                    <input type="text" name="nombres" required placeholder="Ingrese los nombres">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Apellidos</label>
                    <input type="text" name="apellidos" required placeholder="Ingrese los apellidos">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Tipo de Documento</label>
                    <select name="tipoDocumento" required>
                        <option value="">Seleccionar tipo</option>
                        <option value="DNI">DNI</option>
                        <option value="CE">Carnet de Extranjería</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Número de Documento</label>
                    <input type="text" name="numeroDocumento" required placeholder="Ingrese el número">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Dirección</label>
                    <input type="text" name="direccion" required placeholder="Ingrese la dirección">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Teléfono</label>
                    <input type="text" name="telefono" required placeholder="Ingrese el número de teléfono">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Correo Electrónico</label>
                    <input type="text" name="usuario" required placeholder="ejemplo@correo.com">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Contraseña</label>
                    <input type="password" name="contrasena" required placeholder="Ingrese una contraseña segura">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-tag"></i> Rol</label>
                    <select name="idRol" required>
                        <option value="">Seleccionar rol</option>
                        <?php foreach($roles as $rol): ?>
                            <option value="<?php echo $rol['idRol']; ?>"><?php echo $rol['nombreRol']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Crear Usuario
                </button>
            </form>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <?php include(__DIR__ . "/includes/footer.php"); ?>
</div>

<script>
// Función para mostrar toast notifications
function showToast(message, type = 'success', duration = 5000) {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    
    const icons = {
        success: 'fas fa-check',
        error: 'fas fa-times',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info'
    };
    
    const titles = {
        success: 'Éxito',
        error: 'Error',
        warning: 'Advertencia',
        info: 'Información'
    };
    
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="${icons[type]}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="hideToast(this)">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress"></div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto hide after duration
    setTimeout(() => {
        hideToast(toast.querySelector('.toast-close'));
    }, duration);
}

function hideToast(button) {
    const toast = button.closest('.toast');
    toast.classList.add('hiding');
    setTimeout(() => {
        toast.remove();
    }, 300);
}

// Función para toggle password visibility
function togglePass(id) {
    const input = document.querySelector('.pass-input-' + id);
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Funciones para modal
function abrirModal() {
    const modal = document.getElementById('modalNuevo');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Se agrega un pequeño retraso para asegurar que el modal esté visible antes de enfocar
    setTimeout(() => {
        const firstInput = modal.querySelector('input[name="nombres"]');
        if (firstInput) {
            firstInput.focus();
        }
    }, 200);
}

function cerrarModal() {
    const modal = document.getElementById('modalNuevo');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
    // Reset form
    const form = modal.querySelector('form');
    form.reset();
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalNuevo');
    if (event.target === modal) {
        cerrarModal();
    }
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModal();
    }
});

// Función para mostrar loading en botones
function showLoading(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Guardando...';
    submitBtn.disabled = true;
    
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 2000);
}

function showLoadingModal(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="loading"></span> Creando...';
    submitBtn.disabled = true;
}

// Mostrar toast si hay mensaje en sesión
<?php if (isset($_SESSION['toast_message'])): ?>
showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type']; ?>');
<?php 
unset($_SESSION['toast_message']);
unset($_SESSION['toast_type']);
endif; ?>

// Smooth scroll para elementos
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Validación mejorada para formularios
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = 'var(--error-color)';
                field.style.boxShadow = '0 0 0 3px rgba(245, 101, 101, 0.1)';
                isValid = false;
            } else {
                field.style.borderColor = 'var(--border-color)';
                field.style.boxShadow = 'none';
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

// FILTRADO DINÁMICO DE LA TABLA
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('keyup', function(event) {
    const filter = event.target.value.toLowerCase();
    const rows = document.getElementById('userTableBody').getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        // Iterar a través de las celdas de la fila (excepto la de acciones y la de contraseña)
        for (let j = 0; j < cells.length - 2; j++) {
            const cellText = cells[j].textContent || cells[j].innerText;
            if (cellText.toLowerCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        
        if (found) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }
});
</script>
</body>
</html>