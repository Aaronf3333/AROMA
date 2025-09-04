<?php
session_start();
include(__DIR__ . "/conexion.php"); // conexión a MySQL

if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

// Obtener ID del cliente
if (!isset($_GET['id'])) {
    header("Location: clientes.php");
    exit();
}
$idCliente = intval($_GET['id']);

// Traer datos del cliente y persona
$sql = "SELECT c.idCliente, c.activo, p.nombres, p.apellidos, p.tipoDocumento, p.numeroDocumento, p.direccion, p.telefono
        FROM cliente c
        JOIN persona p ON c.idPersona = p.idPersona
        WHERE c.idCliente = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();

if (!$cliente) {
    $_SESSION['mensaje'] = "Cliente no encontrado.";
    $_SESSION['tipo'] = "error";
    $stmt->close();
    $conn->close();
    header("Location: clientes.php");
    exit();
}
$stmt->close();

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $tipoDocumento = $_POST['tipoDocumento'];
    $numeroDocumento = trim($_POST['numeroDocumento']);
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);

    // Actualizar Persona
    $sqlUpdate = "UPDATE persona p 
                  JOIN cliente c ON p.idPersona = c.idPersona
                  SET p.nombres = ?, p.apellidos = ?, p.tipoDocumento = ?, p.numeroDocumento = ?, p.direccion = ?, p.telefono = ?
                  WHERE c.idCliente = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("ssssssi", $nombres, $apellidos, $tipoDocumento, $numeroDocumento, $direccion, $telefono, $idCliente);
    
    if ($stmtUpdate->execute()) {
        $_SESSION['mensaje'] = "Cliente actualizado correctamente.";
        $_SESSION['tipo'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error al actualizar cliente: " . $conn->error;
        $_SESSION['tipo'] = "error";
    }
    
    $stmtUpdate->close();
    $conn->close();
    header("Location: clientes.php");
    exit();
}

// Mostrar mensaje si existe en sesión
$mensaje = "";
$tipoMensaje = "";
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
<title>Editar Cliente - Aroma S.A.C</title>
<style>
#toast {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(-20px);
    min-width: 300px;
    max-width: 400px;
    padding: 15px 20px;
    border-radius: 10px;
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    opacity: 0;
    transition: opacity 0.5s ease, transform 0.5s ease;
    z-index: 9999;
}
#toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
#toast.success { background-color: #27ae60; }
#toast.info { background-color: #2980b9; }
#toast.warning { background-color: #f39c12; }
#toast.error { background-color: #c0392b; }
form input, form select { margin-bottom:10px; width:100%; padding:8px; }
form button { background:#3b3b98; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; }
</style>
</head>
<body>
<?php include(__DIR__ . "/includes/header.php"); ?>

<main style="padding:20px; font-family:Arial, sans-serif;">
<h2 style="color:#2c2c54;">Editar Cliente</h2>

<!-- Toast mensaje -->
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

<form method="POST" style="margin-bottom:30px; background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.2);">
    <input type="text" name="nombres" value="<?php echo htmlspecialchars($cliente['nombres']); ?>" placeholder="Nombres" required>
    <input type="text" name="apellidos" value="<?php echo htmlspecialchars($cliente['apellidos']); ?>" placeholder="Apellidos" required>
    <select name="tipoDocumento" required>
        <option value="DNI" <?php if($cliente['tipoDocumento']=='DNI') echo 'selected'; ?>>DNI</option>
        <option value="CE" <?php if($cliente['tipoDocumento']=='CE') echo 'selected'; ?>>CE</option>
    </select>
    <input type="text" name="numeroDocumento" value="<?php echo htmlspecialchars($cliente['numeroDocumento']); ?>" placeholder="Número de documento" required>
    <input type="text" name="direccion" value="<?php echo htmlspecialchars($cliente['direccion']); ?>" placeholder="Dirección">
    <input type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" placeholder="Teléfono">
    <button type="submit">Actualizar Cliente</button>
</form>

<a href="clientes.php" style="color:white; background:#7f8c8d; padding:8px 15px; border-radius:5px; text-decoration:none;">Volver a Clientes</a>

</main>
<?php include(__DIR__ . "/includes/footer.php"); ?>
</body>

</html>

