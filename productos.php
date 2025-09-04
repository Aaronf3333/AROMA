<?php
session_start();
include(__DIR__ . "/conexion.php"); // Conexión a MySQL

if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

$mensaje = "";
$tipoMensaje = "";

// -------------------------
// PROCESAMIENTO DE ACCIONES (POST y GET)
// -------------------------

// AGREGAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombreProducto'])) {
    $nombreProducto = trim($_POST['nombreProducto']);
    $precio = floatval($_POST['precio']);
    $idCategoria = intval($_POST['idCategoria']);

    // Validar que los campos no estén vacíos y que el precio sea válido
    if (empty($nombreProducto) || $precio <= 0 || $idCategoria <= 0) {
        $_SESSION['mensaje'] = "Por favor, completa todos los campos correctamente.";
        $_SESSION['tipo'] = "error";
    } else {
        // Verificar si el producto ya existe usando una sentencia preparada
        $checkSql = "SELECT idProducto FROM producto WHERE nombreProducto=? AND idCategoria=?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("si", $nombreProducto, $idCategoria);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $_SESSION['mensaje'] = "Este producto ya existe en la categoría seleccionada.";
            $_SESSION['tipo'] = "warning";
        } else {
            // Insertar el nuevo producto
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
            $stmt->close();
        }
        $stmtCheck->close();
    }
}

// ACTIVAR/DESACTIVAR PRODUCTO
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
        $stmtToggle->close();
    }
    $stmtEstado->close();
}

// Redirección unificada al final del procesamiento de acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['toggle'])) {
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
// LISTADO DE PRODUCTOS (se mantiene igual, pero la sugerencia es usar prepared statements para la consistencia)
// -------------------------
$sqlProductos = "SELECT p.idProducto, p.nombreProducto, p.precio, c.nombreCategoria, p.estado
                  FROM producto p
                  JOIN categoria c ON p.idCategoria = c.idCategoria
                  ORDER BY p.idProducto ASC";
$resultProductos = $conn->query($sqlProductos);

// -------------------------
// LISTADO DE CATEGORÍAS
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5f27cd;
            --secondary-color: #a55eea;
            --accent-color: #0984e3;
            --text-color: #2d3436;
            --background-color: #f5f6fa;
            --card-background: rgba(255, 255, 255, 0.95);
            --border-color: #e9ecef;
            --success-color: #00b894;
            --warning-color: #fdcb6e;
            --info-color: #0984e3;
            --error-color: #d63031;
        }

        body {
            font-family: 'Poppins', sans-serif; /* Coherencia de fuente */
            background: var(--background-color);
            min-height: 100vh;
            margin: 0;
            color: var(--text-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-title {
            color: var(--text-color);
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
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        /* --- Toast --- */
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
            background: linear-gradient(135deg, var(--success-color), #00a085);
            border-left: 4px solid #00e676;
        }
        
        #toast.info { 
            background: linear-gradient(135deg, var(--info-color), #74b9ff);
            border-left: 4px solid #00bcd4;
        }
        
        #toast.warning { 
            background: linear-gradient(135deg, var(--warning-color), #e17055);
            border-left: 4px solid #ff9800;
        }
        
        #toast.error { 
            background: linear-gradient(135deg, var(--error-color), #e84393);
            border-left: 4px solid #f44336;
        }

        /* --- Formulario --- */
        .form-container {
            background: var(--card-background);
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
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--primary-color));
        }

        .form-title {
            color: var(--primary-color);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-family: inherit;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(165, 94, 234, 0.1);
            background: rgba(255, 255, 255, 1);
        }

        .form-input::placeholder {
            color: #6c757d;
            font-weight: 400;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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
            width: fit-content;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(95, 39, 205, 0.4);
        }

        /* --- Tabla --- */
        .table-container {
            background: var(--card-background);
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
            background: linear-gradient(90deg, var(--info-color), #74b9ff, var(--info-color));
        }

        .table-title {
            color: var(--info-color);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .responsive-table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            min-width: 600px; /* Asegura un ancho mínimo para evitar desbordes */
        }

        .products-table th {
            background: linear-gradient(135deg, #2d3436, #636e72);
            color: white;
            padding: 12px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .products-table th:first-child {
            border-top-left-radius: 12px;
        }

        .products-table th:last-child {
            border-top-right-radius: 12px;
        }

        .products-table td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-color);
            font-weight: 500;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .products-table tr:hover {
            background: rgba(116, 185, 255, 0.05);
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }

        .price {
            font-weight: 700;
            color: var(--success-color);
            font-size: 13px;
        }

        /* --- Estados --- */
        .status-badge {
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-active {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(0, 184, 148, 0.2);
        }

        .status-inactive {
            background: rgba(214, 48, 49, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(214, 48, 49, 0.2);
        }

        /* --- Botones de acción --- */
        .action-btn-container {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--info-color), #74b9ff);
            color: white;
        }

        .btn-toggle {
            background: linear-gradient(135deg, var(--error-color), #e84393);
            color: white;
        }

        .btn-activate {
            background: linear-gradient(135deg, var(--success-color), #00e676);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* --- Responsive --- */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .form-container, .table-container {
                padding: 25px 20px;
            }
            
            .page-title {
                font-size: 1.8rem;
            }

            .products-table {
                box-shadow: none;
                border-radius: 0;
            }
            
            thead {
                display: none;
            }
            
            tr {
                display: block;
                margin-bottom: 15px;
                background: white;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                padding: 10px;
            }
            
            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right;
                padding: 8px 10px;
                border-bottom: 1px solid var(--border-color);
                white-space: normal;
            }

            td:last-child {
                border-bottom: none;
            }
            
            td::before {
                content: attr(data-label);
                font-weight: bold;
                color: var(--primary-color);
                text-align: left;
                flex-grow: 1;
                flex-basis: 50%;
            }
            
            .action-btn-container {
                flex-direction: column;
                align-items: flex-end;
                gap: 8px;
            }

            .action-btn {
                width: 100%;
                text-align: center;
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
            <div class="responsive-table-wrapper">
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
                            <tr data-id="<?php echo $row['idProducto']; ?>">
                                <td data-label="ID"><strong><?php echo $row['idProducto']; ?></strong></td>
                                <td data-label="Nombre"><?php echo htmlspecialchars($row['nombreProducto']); ?></td>
                                <td data-label="Precio"><span class="price">S/. <?php echo number_format($row['precio'], 2); ?></span></td>
                                <td data-label="Categoría"><?php echo htmlspecialchars($row['nombreCategoria']); ?></td>
                                <td data-label="Estado">
                                    <span class="status-badge <?php echo $row['estado'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $row['estado'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                    </span>
                                </td>
                                <td data-label="Acciones">
                                    <div class="action-btn-container">
                                        <a href="editar_producto.php?id=<?php echo $row['idProducto']; ?>" class="action-btn btn-edit">Editar</a>
                                        <a href="productos.php?toggle=<?php echo $row['idProducto']; ?>" 
                                           class="action-btn <?php echo $row['estado'] ? 'btn-toggle' : 'btn-activate'; ?>">
                                            <?php echo $row['estado'] ? 'Desactivar' : 'Activar'; ?>
                                        </a>
                                    </div>
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
    </div>

    <?php include(__DIR__ . "/includes/footer.php"); ?>
</body>
</html>
