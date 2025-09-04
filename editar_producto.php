<?php
session_start();
include(__DIR__ . "/conexion.php"); // conexión a MySQL

if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

$mensaje = "";
$tipoMensaje = ""; // success, info, warning, error

// -------------------------
// OBTENER PRODUCTO POR ID
// -------------------------
if (!isset($_GET['id'])) {
    header("Location: productos.php");
    exit();
}

$idProducto = intval($_GET['id']);

// Obtener producto
$sql = "SELECT * FROM producto WHERE idProducto = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idProducto);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    $_SESSION['mensaje'] = "Producto no encontrado.";
    $_SESSION['tipo'] = "error";
    $stmt->close();
    $conn->close();
    header("Location: productos.php");
    exit();
}
$stmt->close();

// Obtener categorías
$sqlCategorias = "SELECT idCategoria, nombreCategoria FROM categoria ORDER BY nombreCategoria ASC";
$resultCategorias = $conn->query($sqlCategorias);
$categorias = [];
while ($rowCat = $resultCategorias->fetch_assoc()) {
    $categorias[] = $rowCat;
}

// -------------------------
// ACTUALIZAR PRODUCTO
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreProducto = trim($_POST['nombreProducto']);
    $precio = floatval($_POST['precio']);
    $idCategoria = intval($_POST['idCategoria']);

    // Verificar si ya existe otro producto con el mismo nombre y categoría
    $checkSql = "SELECT idProducto FROM producto WHERE nombreProducto = ? AND idCategoria = ? AND idProducto != ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("sii", $nombreProducto, $idCategoria, $idProducto);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $rowCheck = $resultCheck->fetch_assoc();

    if ($rowCheck) {
        $_SESSION['mensaje'] = "Ya existe otro producto con ese nombre en la categoría seleccionada.";
        $_SESSION['tipo'] = "warning";
        $stmtCheck->close();
    } else {
        $stmtCheck->close();
        
        $sqlUpdate = "UPDATE producto SET nombreProducto = ?, precio = ?, idCategoria = ? WHERE idProducto = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("sdii", $nombreProducto, $precio, $idCategoria, $idProducto);

        if ($stmtUpdate->execute()) {
            $_SESSION['mensaje'] = "Producto actualizado correctamente.";
            $_SESSION['tipo'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al actualizar el producto: " . $conn->error;
            $_SESSION['tipo'] = "error";
        }

        $stmtUpdate->close();
        $conn->close();
        header("Location: productos.php");
        exit();
    }
}

// -------------------------
// MOSTRAR MENSAJE
// -------------------------
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
<title>Editar Producto - Aroma S.A.C</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* Estilos Generales */
:root {
    --primary-color: #3b3b98; /* Azul */
    --secondary-color: #2c2c54; /* Azul oscuro */
    --background-color: #f4f4f4; /* Gris claro */
    --card-background: #ffffff;
    --button-bg: #3b3b98;
    --button-hover: #2d2d7c;
    --link-bg: #7f8c8d;
    --link-color: #fff;
    --border-color: #ddd;
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
input[type="number"],
select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-sizing: border-box;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

input[type="text"]:focus,
input[type="number"]:focus,
select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 5px rgba(59, 59, 152, 0.2);
}

/* Botones y enlaces */
.button-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

button[type="submit"],
.back-link {
    display: block;
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
    color: var(--link-color);
}

button[type="submit"] {
    background-color: var(--button-bg);
}

button[type="submit"]:hover {
    background-color: var(--button-hover);
}

.back-link {
    background-color: var(--link-bg);
}

/* Mensajes de notificación (Toast) */
#toast {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    min-width: 300px;
    max-width: 90%;
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
    .button-container {
        flex-direction: row;
        justify-content: flex-end;
    }
    button[type="submit"],
    .back-link {
        width: auto;
        padding: 12px 25px;
    }
}
</style>
</head>
<body>
<?php include(__DIR__ . "/includes/header.php"); ?>

<main class="container">
    <h2>Editar Producto</h2>

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
            <label for="nombreProducto">Nombre del Producto</label>
            <input type="text" id="nombreProducto" name="nombreProducto" value="<?php echo htmlspecialchars($producto['nombreProducto']); ?>" required>
        </div>

        <div class="form-group">
            <label for="precio">Precio</label>
            <input type="number" step="0.01" min="0" id="precio" name="precio" value="<?php echo number_format($producto['precio'],2, '.', ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="idCategoria">Categoría</label>
            <select id="idCategoria" name="idCategoria" required>
                <option value="">Seleccione categoría</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?php echo $cat['idCategoria']; ?>" <?php echo $producto['idCategoria'] == $cat['idCategoria'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nombreCategoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="button-container">
            <button type="submit">Actualizar Producto</button>
            <a href="productos.php" class="back-link">Cancelar</a>
        </div>
    </form>
</main>

<?php include(__DIR__ . "/includes/footer.php"); ?>
</body>
</html>
