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
<title>Editar Producto - Aroma S.A.C</title>
<style>
/* Toast flotante */
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

form { margin:30px auto; max-width:500px; background:white; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.2);}
input, select { margin-bottom:10px; width:100%; padding:8px;}
button { background:#3b3b98; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer; }
</style>
</head>
<body>
<?php include(__DIR__ . "/includes/header.php"); ?>

<main style="padding:20px; font-family:Arial, sans-serif;">
<h2 style="color:#2c2c54;">Editar Producto</h2>

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

<form method="POST">
    <label>Nombre del Producto</label>
    <input type="text" name="nombreProducto" value="<?php echo htmlspecialchars($producto['nombreProducto']); ?>" required>

    <label>Precio</label>
    <input type="number" step="0.01" min="0" name="precio" value="<?php echo number_format($producto['precio'],2); ?>" required>

    <label>Categoría</label>
    <select name="idCategoria" required>
        <option value="">Seleccione categoría</option>
        <?php foreach($categorias as $cat): ?>
            <option value="<?php echo $cat['idCategoria']; ?>" <?php echo $producto['idCategoria'] == $cat['idCategoria'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['nombreCategoria']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Actualizar Producto</button>
    <a href="productos.php" style="display:inline-block; margin-top:10px; text-decoration:none; color:#3b3b98;">Cancelar</a>
</form>
</main>

<?php include(__DIR__ . "/includes/footer.php"); ?>
</body>
</html>