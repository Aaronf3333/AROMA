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
    
    // CORRECCIÓN: Verificar si la clave existe antes de usarla y manejar el trim() con un valor no-null
    $numeroDocumento = isset($_POST['numeroDocumento']) ? trim($_POST['numeroDocumento']) : '';
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);

    // Corrección: Manejar el tipo de documento "Indocumentado"
    if ($tipoDocumento === 'IND') {
        $tipoDocumentoParaBD = 'indocumentado';
        $numeroDocumentoParaBD = null; // Guardar NULL en la base de datos para el número de documento
    } else {
        $tipoDocumentoParaBD = $tipoDocumento;
        $numeroDocumentoParaBD = $numeroDocumento;
    }

    // Actualizar Persona
    $sqlUpdate = "UPDATE persona p
                  JOIN cliente c ON p.idPersona = c.idPersona
                  SET p.nombres = ?, p.apellidos = ?, p.tipoDocumento = ?, p.numeroDocumento = ?, p.direccion = ?, p.telefono = ?
                  WHERE c.idCliente = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    // Nota: El tipo de parámetro para numeroDocumento sigue siendo 's' (string) aunque el valor sea NULL
    $stmtUpdate->bind_param("ssssssi", $nombres, $apellidos, $tipoDocumentoParaBD, $numeroDocumentoParaBD, $direccion, $telefono, $idCliente);

    if ($stmtUpdate->execute()) {
        $_SESSION['mensaje'] = "Cliente actualizado correctamente.";
        $_SESSION['tipo'] = "success";
    } else {
        $_SESSION['mensaje'] = "Error al actualizar cliente: " . $conn->error;
        $_SESSION['tipo'] = "error";
    }

    $stmtUpdate->close();
    $conn->close();
    
    // CORRECCIÓN: La redirección se ejecuta después de que todo el código de PHP ha terminado de procesarse sin errores
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - Aroma S.A.C</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos generales */
        :root {
            --primary-color: #3b3b98; /* Azul */
            --secondary-color: #2c2c54; /* Azul oscuro */
            --background-color: #f4f4f4; /* Gris claro */
            --card-background: #ffffff;
            --button-bg: #3b3b98;
            --button-hover: #2d2d7c;
            --link-bg: #7f8c8d;
            --error-color: #c0392b;
            --warning-color: #f39c12;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 0 15px;
        }

        /* Título */
        h2 {
            text-align: center;
            color: var(--secondary-color);
            margin-bottom: 25px;
        }

        /* Formulario */
        form {
            background: var(--card-background);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box; /* Para que el padding no afecte el ancho */
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(59, 59, 152, 0.2);
        }

        /* Estilo para campos deshabilitados */
        input[disabled] {
            background-color: #f1f1f1;
            cursor: not-allowed;
            color: #999;
        }

        /* Botones */
        button[type="submit"],
        .back-link {
            display: block; /* Para que ocupen todo el ancho en móviles */
            width: 100%;
            text-align: center;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease;
            box-sizing: border-box;
        }

        button[type="submit"] {
            background-color: var(--button-bg);
            color: white;
            margin-bottom: 15px;
        }

        button[type="submit"]:hover {
            background-color: var(--button-hover);
        }

        .back-link {
            background-color: var(--link-bg);
            color: white;
            margin-top: 20px;
        }

        /* Mensajes de notificación (Toast) */
        #toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            min-width: 300px;
            max-width: 90%; /* Ajuste para móviles */
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
        #toast.show { opacity: 1; transform: translateX(-50%); }
        #toast.success { background-color: #27ae60; }
        #toast.info { background-color: #2980b9; }
        #toast.warning { background-color: #f39c12; }
        #toast.error { background-color: #c0392b; }

        /* Media Queries para pantallas más grandes */
        @media (min-width: 576px) {
            button[type="submit"],
            .back-link {
                display: inline-block;
                width: auto;
                padding: 12px 25px;
            }
            .back-link {
                margin-left: 15px;
            }
        }
    </style>
</head>
<body>
<?php include(__DIR__ . "/includes/header.php"); ?>

<main class="container">
    <h2>Editar Cliente</h2>

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

    <form method="POST">
        <div class="form-group">
            <label for="nombres">Nombres:</label>
            <input type="text" id="nombres" name="nombres" value="<?php echo htmlspecialchars($cliente['nombres'] ?? ''); ?>" placeholder="Nombres" required>
        </div>
        <div class="form-group">
            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($cliente['apellidos'] ?? ''); ?>" placeholder="Apellidos" required>
        </div>
        <div class="form-group">
            <label for="tipoDocumento">Tipo de Documento:</label>
            <select id="tipoDocumento" name="tipoDocumento" required>
                <option value="DNI" <?php if(($cliente['tipoDocumento'] ?? '') === 'DNI') echo 'selected'; ?>>DNI</option>
                <option value="CE" <?php if(($cliente['tipoDocumento'] ?? '') === 'CE') echo 'selected'; ?>>CE</option>
                <option value="IND" <?php if(($cliente['tipoDocumento'] ?? '') === 'indocumentado') echo 'selected'; ?>>Indocumentado</option>
            </select>
        </div>
        <div class="form-group">
            <label for="numeroDocumento">Número de Documento:</label>
            <input type="text" id="numeroDocumento" name="numeroDocumento" value="<?php echo htmlspecialchars($cliente['numeroDocumento'] ?? ''); ?>" placeholder="Número de documento">
        </div>
        <div class="form-group">
            <label for="direccion">Dirección:</label>
            <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?>" placeholder="Dirección">
        </div>
        <div class="form-group">
            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" placeholder="Teléfono">
        </div>
        <button type="submit">Actualizar Cliente</button>
        <a href="clientes.php" class="back-link">Volver a Clientes</a>
    </form>

</main>
<?php include(__DIR__ . "/includes/footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoDocumentoSelect = document.getElementById('tipoDocumento');
    const numeroDocumentoInput = document.getElementById('numeroDocumento');

    // Función para alternar el estado del campo de documento
    function toggleDocumentoField() {
        if (tipoDocumentoSelect.value === 'IND') {
            numeroDocumentoInput.value = '';
            numeroDocumentoInput.placeholder = 'No requerido';
            numeroDocumentoInput.disabled = true;
            numeroDocumentoInput.removeAttribute('required');
        } else {
            numeroDocumentoInput.placeholder = 'Número de documento';
            numeroDocumentoInput.disabled = false;
            numeroDocumentoInput.setAttribute('required', 'required');
        }
    }

    // Escuchar el cambio en el select
    tipoDocumentoSelect.addEventListener('change', toggleDocumentoField);
    
    // Ejecutar al cargar la página para reflejar el estado actual
    toggleDocumentoField();

    // Validación de números en el campo de documento
    numeroDocumentoInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});
</script>
</body>
</html>
