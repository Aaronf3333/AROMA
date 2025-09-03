<?php
session_start();
// Asegúrate de que este archivo 'conexion.php' sea para MySQL.
include(__DIR__ . "/conexion.php"); 

if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

$mensaje = "";
$tipoMensaje = ""; // success, info, warning, error

// -------------------------
// AGREGAR PRODUCTO
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombreProducto'])) {
    $nombreProducto = trim($_POST['nombreProducto']);
    $precio = floatval($_POST['precio']);
    $idCategoria = intval($_POST['idCategoria']);

    // Verificar si el producto ya existe
    $checkSql = "SELECT idProducto FROM producto WHERE nombreProducto=? AND idCategoria=?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("si", $nombreProducto, $idCategoria);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        $_SESSION['mensaje'] = "Este producto ya existe en la categoría seleccionada.";
        $_SESSION['tipo'] = "warning";
    } else {
        $sql = "INSERT INTO producto (nombreProducto, precio, idCategoria) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdi", $nombreProducto, $precio, $idCategoria);

        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Producto agregado correctamente.";
            $_SESSION['tipo'] = "success";
        } else {
            $_SESSION['mensaje'] = "Error al agregar el producto: " . $stmt->error;
            $_SESSION['tipo'] = "error";
        }
    }

    header("Location: productos.php");
    exit();
}

// -------------------------
// ACTIVAR/DESACTIVAR PRODUCTO
// -------------------------
if (isset($_GET['toggle'])) {
    $idProducto = intval($_GET['toggle']);
    
    // Obtener estado actual
    $sqlEstado = "SELECT estado FROM producto WHERE idProducto=?";
    $stmtEstado = $conn->prepare($sqlEstado);
    $stmtEstado->bind_param("i", $idProducto);
    $stmtEstado->execute();
    $resultEstado = $stmtEstado->get_result();
    $rowEstado = $resultEstado->fetch_assoc();

    if ($rowEstado) {
        $nuevoEstado = $rowEstado['estado'] ? 0 : 1;
        $sqlToggle = "UPDATE producto SET estado=? WHERE idProducto=?";
        $stmtToggle = $conn->prepare($sqlToggle);
        $stmtToggle->bind_param("ii", $nuevoEstado, $idProducto);
        $stmtToggle->execute();

        $_SESSION['mensaje'] = $nuevoEstado ? "Producto activado." : "Producto desactivado.";
        $_SESSION['tipo'] = $nuevoEstado ? "success" : "info";
    }

    header("Location: productos.php");
    exit();
}

// -------------------------
// MOSTRAR MENSAJE
// -------------------------
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipoMensaje = $_SESSION['tipo'] ?? "success";
    unset($_SESSION['mensaje'], $_SESSION['tipo']);
}

// -------------------------
// LISTADO DE PRODUCTOS
// -------------------------
$sqlProductos = "SELECT p.idProducto, p.nombreProducto, p.precio, c.nombreCategoria, p.estado
                 FROM producto p
                 JOIN categoria c ON p.idCategoria = c.idCategoria
                 ORDER BY p.idProducto ASC";
$resultProductos = $conn->query($sqlProductos);

// -------------------------
// LISTADO DE CATEGORÍAS (para el formulario)
// -------------------------
$sqlCategorias = "SELECT idCategoria, nombreCategoria FROM categoria ORDER BY nombreCategoria ASC";
$resultCategorias = $conn->query($sqlCategorias);
$categorias = [];
while ($rowCat = $resultCategorias->fetch_assoc()) {
    $categorias[] = $rowCat;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Aroma S.A.C</title>
    <style>
        /* ... El CSS permanece igual ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
        }

        /* Toast flotante centrado superior */
        #toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            min-width: 350px;
            max-width: 500px;
            padding: 18px 25px;
            border-radius: 12px;
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
            background: linear-gradient(135deg, #00b894, #00a085);
            border-left: 4px solid #00e676;
        }
        
        #toast.info { 
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            border-left: 4px solid #00bcd4;
        }
        
        #toast.warning { 
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            border-left: 4px solid #ff9800;
        }
        
        #toast.error { 
            background: linear-gradient(135deg, #d63031, #e84393);
            border-left: 4px solid #f44336;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-title {
            color: #2d3436;
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, #5f27cd, #a55eea);
            border-radius: 2px;
        }

        /* Formulario */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5f27cd, #a55eea, #5f27cd);
        }

        .form-title {
            color: #5f27cd;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-family: inherit;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #a55eea;
            box-shadow: 0 0 0 3px rgba(165, 94, 234, 0.1);
            background: rgba(255, 255, 255, 1);
        }

        .form-input::placeholder {
            color: #6c757d;
            font-weight: 400;
        }

        .submit-btn {
            background: linear-gradient(135deg, #5f27cd, #a55eea);
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(95, 39, 205, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(95, 39, 205, 0.4);
        }

        /* Tabla */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
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

        .table-title {
            color: #0984e3;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .products-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .products-table th {
            background: linear-gradient(135deg, #2d3436, #636e72);
            color: white;
            padding: 18px 15px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
        }

        .products-table th:first-child {
            border-top-left-radius: 12px;
        }

        .products-table th:last-child {
            border-top-right-radius: 12px;
        }

        .products-table td {
            padding: 16px 15px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            color: #2d3436;
            font-weight: 500;
        }

        .products-table tr:hover {
            background: rgba(116, 185, 255, 0.05);
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }

        /* Estados */
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
            border: 1px solid rgba(0, 184, 148, 0.2);
        }

        .status-inactive {
            background: rgba(214, 48, 49, 0.1);
            color: #d63031;
            border: 1px solid rgba(214, 48, 49, 0.2);
        }

        /* Botones de acción */
        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            color: white;
        }

        .btn-toggle {
            background: linear-gradient(135deg, #d63031, #e84393);
            color: white;
        }

        .btn-activate {
            background: linear-gradient(135deg, #00b894, #00e676);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .price {
            font-weight: 700;
            color: #00b894;
            font-size: 15px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-container, .table-container {
                padding: 25px 20px;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .products-table {
                font-size: 14px;
            }
            
            .products-table th,
            .products-table td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 480px) {
            .action-btn {
                padding: 6px 12px;
                font-size: 12px;
                display: block;
                margin: 3px 0;
            }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <h1 class="page-title">📦 Gestión de Productos</h1>

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

        <div class="form-container">
            <h3 class="form-title">➕ Agregar Nuevo Producto</h3>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <input type="text" name="nombreProducto" class="form-input" placeholder="Nombre del producto" required>
                    </div>
                    <div class="form-group">
                        <input type="number" step="0.01" min="0" name="precio" class="form-input" placeholder="Precio (S/.)" required>
                    </div>
                    <div class="form-group">
                        <select name="idCategoria" class="form-select" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo $cat['idCategoria']; ?>"><?php echo htmlspecialchars($cat['nombreCategoria']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Agregar Producto</button>
            </form>
        </div>

        <div class="table-container">
            <h3 class="table-title">📋 Lista de Productos</h3>
            <table class="products-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($resultProductos->num_rows > 0) {
                        while($row = $resultProductos->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $row['idProducto']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nombreProducto']); ?></td>
                            <td><span class="price">S/. <?php echo number_format($row['precio'], 2); ?></span></td>
                            <td><?php echo htmlspecialchars($row['nombreCategoria']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['estado'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $row['estado'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="editar_producto.php?id=<?php echo $row['idProducto']; ?>" class="action-btn btn-edit">Editar</a>
                                <a href="productos.php?toggle=<?php echo $row['idProducto']; ?>" 
                                   class="action-btn <?php echo $row['estado'] ? 'btn-toggle' : 'btn-activate'; ?>">
                                    <?php echo $row['estado'] ? 'Desactivar' : 'Activar'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile;
                    } else {
                        echo "<tr><td colspan='6'>No hay productos registrados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include(__DIR__ . "/includes/footer.php"); ?>
</body>
</html>