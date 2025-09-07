<?php
session_start();
include(__DIR__ . "/conexion.php");
require(__DIR__ . "/fpdf/fpdf.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

$idUsuario = $_SESSION['idUsuario'];
$usuarioNombre = $_SESSION['nombres'] . ' ' . $_SESSION['apellidos'];
$mensaje = $_SESSION['mensaje'] ?? '';
$tipoMensaje = $_SESSION['tipo'] ?? '';
unset($_SESSION['mensaje'], $_SESSION['tipo']);

// Obtener clientes activos
$sqlClientes = "SELECT c.idCliente, p.nombres, p.apellidos, p.tipoDocumento, p.numeroDocumento
                FROM cliente c
                JOIN persona p ON c.idPersona = p.idPersona
                WHERE c.activo = 1
                ORDER BY p.nombres ASC";
$resultClientes = mysqli_query($conn, $sqlClientes);

// Obtener productos activos
$sqlProductos = "SELECT idProducto, nombreProducto, precio FROM producto WHERE estado=1 ORDER BY nombreProducto ASC";
$resultProductos = mysqli_query($conn, $sqlProductos);

// -------------------------
// PROCESAR VENTA
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCliente = isset($_POST['idCliente']) ? intval($_POST['idCliente']) : null;
    $productosSeleccionados = $_POST['productos'] ?? [];
    $montoPagado = isset($_POST['monto_pagado']) ? floatval($_POST['monto_pagado']) : 0;
    $cambio = isset($_POST['cambio_hidden']) ? floatval($_POST['cambio_hidden']) : 0;
    $total = 0;
    $detalleProductos = [];

    // Validaciones iniciales
    if ($idCliente === null || empty($productosSeleccionados)) {
        $_SESSION['mensaje'] = "Error: Por favor, seleccione un cliente y al menos un producto.";
        $_SESSION['tipo'] = "warning";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $validacionExitosa = true;
    foreach ($productosSeleccionados as $idProd => $cantData) {
        $cantidad = intval($cantData['cantidad']);
        $precioUnitario = floatval($cantData['precioUnitario']);

        if ($cantidad <= 0 || $precioUnitario <= 0) {
            $validacionExitosa = false;
            break;
        }

        $subtotal = $cantidad * $precioUnitario;
        $total += $subtotal;
        $detalleProductos[$idProd] = [
            'cantidad' => $cantidad,
            'precioUnitario' => $precioUnitario,
            'nombre' => htmlspecialchars($cantData['nombre'])
        ];
    }

    if (!$validacionExitosa) {
        $_SESSION['mensaje'] = "Error: La cantidad y el precio del producto deben ser mayores a cero.";
        $_SESSION['tipo'] = "warning";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Iniciar una transacción
    mysqli_begin_transaction($conn);
    try {
        // 1. Insertar Venta
        $sqlVenta = "INSERT INTO venta (idUsuario, idCliente, total) VALUES (?, ?, ?)";
        $stmtVenta = mysqli_prepare($conn, $sqlVenta);
        if (!$stmtVenta) throw new Exception("Error al preparar venta: " . mysqli_error($conn));
        
        $idClienteDB = ($idCliente == 0) ? null : $idCliente;
        mysqli_stmt_bind_param($stmtVenta, "iid", $idUsuario, $idClienteDB, $total);
        mysqli_stmt_execute($stmtVenta);
        $idVenta = mysqli_insert_id($conn);
        mysqli_stmt_close($stmtVenta);

        // 2. Insertar Detalle de Venta
        $sqlDetalle = "INSERT INTO detalleventa (idVenta, idProducto, cantidad, precioUnitario) VALUES (?, ?, ?, ?)";
        $stmtDetalle = mysqli_prepare($conn, $sqlDetalle);
        if (!$stmtDetalle) throw new Exception("Error al preparar detalle: " . mysqli_error($conn));
        foreach ($detalleProductos as $idProd => $data) {
            mysqli_stmt_bind_param($stmtDetalle, "iiid", $idVenta, $idProd, $data['cantidad'], $data['precioUnitario']);
            mysqli_stmt_execute($stmtDetalle);
        }
        mysqli_stmt_close($stmtDetalle);

        // 3. Generar PDF (código FPDF)
        $pdf = new FPDF('P', 'mm', array(80, 200));
        $pdf->AddPage();
        $pdf->SetMargins(3, 5, 3);
        $pdf->SetAutoPageBreak(true, 10);
        $anchoEfectivo = 74;

        // Encabezado
        $pdf->SetFillColor(60, 60, 60);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Rect(3, 5, $anchoEfectivo, 13, 'F');
        $pdf->SetXY(3, 6);
        $pdf->Cell($anchoEfectivo, 7, 'NOTA DE VENTA', 0, 1, 'C', false);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX(3);
        $pdf->Cell($anchoEfectivo, 6, 'Aroma S.A.C', 0, 1, 'C', false);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Ln(3);
        $pdf->Cell($anchoEfectivo, 4, 'Cajero: ' . $usuarioNombre, 0, 1, 'C');
        $pdf->Ln(1);

        $ruc = "10345678901";
        $numeroBoleta = 'BOL' . str_pad($idVenta, 5, '0', STR_PAD_LEFT);
        $pdf->Cell($anchoEfectivo, 4, 'RUC: ' . $ruc, 0, 1, 'C');
        $pdf->Cell($anchoEfectivo, 4, 'Nro: ' . $numeroBoleta, 0, 1, 'C');

        date_default_timezone_set('America/Lima');
        $fechaEmision = date('d/m/Y H:i:s');
        $pdf->Cell($anchoEfectivo, 4, 'Fecha: ' . $fechaEmision, 0, 1, 'C');

        $pdf->Ln(5);

        // Info cliente
        if ($idCliente == 0) {
            $clienteNombre = "Cliente Indocumentado";
            $clienteDoc = "N/A: -----";
        } else {
            $sqlCliente = "SELECT p.* FROM cliente c JOIN persona p ON c.idPersona=p.idPersona WHERE c.idCliente=?";
            $stmtCliente = mysqli_prepare($conn, $sqlCliente);
            mysqli_stmt_bind_param($stmtCliente, "i", $idCliente);
            mysqli_stmt_execute($stmtCliente);
            $resultCliente = mysqli_stmt_get_result($stmtCliente);
            $cliente = mysqli_fetch_assoc($resultCliente);
            mysqli_stmt_close($stmtCliente);
            $clienteNombre = $cliente['nombres'] . ' ' . $cliente['apellidos'];
            $clienteDoc = $cliente['tipoDocumento'] . ': ' . $cliente['numeroDocumento'];
        }

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($anchoEfectivo, 5, 'CLIENTE', 0, 1, 'L');
        $pdf->Ln(1);
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell($anchoEfectivo, 4, $clienteNombre, 0, 1, 'L');
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo, 4, $clienteDoc, 0, 1, 'L');
        if (isset($cliente['direccion']) && !empty($cliente['direccion'])) {
            $pdf->Ln(1);
            $pdf->Cell($anchoEfectivo, 4, 'Dir: ' . $cliente['direccion'], 0, 1, 'L');
        }
        if (isset($cliente['telefono']) && !empty($cliente['telefono'])) {
            $pdf->Ln(1);
            $pdf->Cell($anchoEfectivo, 4, 'Tel: ' . $cliente['telefono'], 0, 1, 'L');
        }
        $pdf->Ln(5);

        // TABLA PRODUCTOS
        $anchoProducto = 38;
        $anchoCantidad = 10;
        $anchoPrecio = 13;
        $anchoSubtotal = 13;

        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($anchoProducto, 6, 'PRODUCTO', 1, 0, 'C', true);
        $pdf->Cell($anchoCantidad, 6, 'CANT', 1, 0, 'C', true);
        $pdf->Cell($anchoPrecio, 6, 'PRECIO', 1, 0, 'C', true);
        $pdf->Cell($anchoSubtotal, 6, 'TOTAL', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetFillColor(248, 248, 248);
        $alternar = false;

        foreach ($detalleProductos as $data) {
            $nombreProducto = $data['nombre'];
            if (strlen($nombreProducto) > 22) {
                $nombreProducto = substr($nombreProducto, 0, 19) . '...';
            }
            $pdf->Cell($anchoProducto, 6, $nombreProducto, 1, 0, 'L', $alternar);
            $pdf->Cell($anchoCantidad, 6, $data['cantidad'], 1, 0, 'C', $alternar);
            $pdf->Cell($anchoPrecio, 6, 'S/ ' . number_format($data['precioUnitario'], 2), 1, 0, 'R', $alternar);
            $pdf->Cell($anchoSubtotal, 6, 'S/ ' . number_format($data['cantidad'] * $data['precioUnitario'], 2), 1, 1, 'R', $alternar);
            $alternar = !$alternar;
        }

        $pdf->Ln(4);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(3, $pdf->GetY(), 77, $pdf->GetY());
        $pdf->Ln(4);

        // TOTAL
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(60, 60, 60);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);
        $anchoTextoTotal = $anchoEfectivo - 20;
        $pdf->Cell($anchoTextoTotal, 8, 'TOTAL A PAGAR', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'S/ ' . number_format($total, 2), 1, 1, 'R', true);

        // Sección de pago
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($anchoEfectivo, 5, 'DETALLE DE PAGO', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($anchoEfectivo / 2, 4, 'Monto Pagado:', 0, 0, 'L');
        $pdf->Cell($anchoEfectivo / 2, 4, 'S/ ' . number_format($montoPagado, 2), 0, 1, 'R');

        $pdf->Cell($anchoEfectivo / 2, 4, 'Cambio:', 0, 0, 'L');
        $pdf->Cell($anchoEfectivo / 2, 4, 'S/ ' . number_format($cambio, 2), 0, 1, 'R');

        // Pie
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($anchoEfectivo, 4, utf8_decode('¡Gracias por su compra!'), 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo, 4, 'www.aromasac.com', 0, 1, 'C');
        $pdf->Ln(3);

        // NOTA LEGAL
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(170, 170, 170);
        $pdf->Cell($anchoEfectivo, 4, utf8_decode('Esta nota no tiene valor tributario.'), 0, 1, 'C');
        $pdf->Cell($anchoEfectivo, 4, utf8_decode('Solicite su Boleta de Venta o Factura.'), 0, 1, 'C');

        $pdfContent = $pdf->Output('S');

        // Guardar en MySQL como BLOB
        $sqlBoleta = "INSERT INTO boleta (idVenta, numeroBoleta, archivoPDF) VALUES (?, ?, ?)";
        $stmtBoleta = mysqli_prepare($conn, $sqlBoleta);
        if (!$stmtBoleta) throw new Exception("Error al preparar boleta: " . mysqli_error($conn));
        mysqli_stmt_bind_param($stmtBoleta, "iss", $idVenta, $numeroBoleta, $pdfContent);
        
        $null = NULL;
        mysqli_stmt_send_long_data($stmtBoleta, 2, $pdfContent);
        
        if (!mysqli_stmt_execute($stmtBoleta)) {
            throw new Exception("Error al insertar boleta: " . mysqli_stmt_error($stmtBoleta));
        }
        mysqli_stmt_close($stmtBoleta);

        mysqli_commit($conn);

        $_SESSION['mensaje'] = "Boleta generada correctamente.";
        $_SESSION['tipo'] = "success";

        header("Location: boletas.php");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['mensaje'] = "Error en la transacción: " . $e->getMessage();
        $_SESSION['tipo'] = "warning";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Boleta - Aroma S.A.C</title>
    <style>
        :root {
            --primary-color: #00b894;
            --secondary-color: #00e676;
            --text-dark: #2d3436;
            --text-light: #6c757d;
            --bg-light: #f5f6fa;
            --bg-card: rgba(255, 255, 255, 0.95);
            --border-color: #e9ecef;
            --shadow-light: 0 5px 20px rgba(0, 0, 0, 0.05);
            --shadow-strong: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .main-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-strong);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }
        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--primary-color));
        }
        .page-title {
            color: var(--text-dark);
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
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
        .section-title {
            color: var(--primary-color);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-section {
            margin-bottom: 35px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
            background: rgba(255, 255, 255, 1);
        }
        .products-list {
            display: grid;
            gap: 15px;
            max-height: 450px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(245, 246, 250, 0.5);
            border-radius: 12px;
            border: 2px solid var(--border-color);
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .product-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .product-item.selected {
            border-color: var(--primary-color);
            background: rgba(0, 184, 148, 0.05);
        }
        .product-checkbox {
            min-width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        .product-details {
            flex-grow: 1;
        }
        .product-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 15px;
        }
        .product-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 14px;
        }
        .product-quantity {
            width: 80px;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .product-quantity:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 184, 148, 0.1);
        }
        .product-quantity:disabled {
            background: #f8f9fa;
            color: var(--text-light);
            cursor: not-allowed;
        }
        .summary-panel {
            background: rgba(0, 184, 148, 0.1);
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
            border: 2px solid rgba(0, 184, 148, 0.2);
            display: none;
        }
        .summary-list {
            list-style: none;
            padding: 0;
            margin: 0 0 15px 0;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(0, 184, 148, 0.3);
            font-size: 15px;
            color: var(--text-dark);
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .total-row {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
            padding-top: 15px;
        }
        .payment-panel {
            background: #f8f9fa;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .payment-panel h4 {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        .payment-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .payment-info #cambioAmount {
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.3);
            width: 100%;
            margin-top: 30px;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.4);
        }
        .btn-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
            z-index: 1000;
        }
        .toast.show {
            opacity: 1;
            visibility: visible;
        }
        .toast.success {
            background-color: #4CAF50;
        }
        .toast.warning {
            background-color: #ff9800;
        }
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        .search-container #clienteSearch {
            width: 100%;
            padding-right: 40px;
        }
        .search-container #search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0bec5;
        }
        #clienteList {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            max-height: 250px;
            overflow-y: auto;
            background: white;
        }
        #clienteList li {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
        }
        #clienteList li:last-child {
            border-bottom: none;
        }
        #clienteList li:hover {
            background-color: var(--bg-light);
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <h1 class="page-title">💼 Generar Nueva Boleta</h1>
        
        <?php if ($mensaje): ?>
            <div class="toast show <?php echo htmlspecialchars($tipoMensaje); ?>" style="position: static; transform: none; margin-bottom: 20px; opacity: 1; visibility: visible;">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="main-card">
            <form method="POST" id="formBoleta">
                <div class="form-section">
                    <h3 class="section-title">👤 Seleccionar Cliente</h3>
                    <div class="form-group">
                        <label for="clienteSearch" class="form-label">Buscar Cliente</label>
                        <div class="search-container">
                            <input type="text" id="clienteSearch" class="form-input" placeholder="Nombre o DNI del cliente">
                            <i class="fas fa-search" id="search-icon"></i>
                        </div>
                        <ul id="clienteList">
                            <li data-id="0">
                                <input type="radio" name="idCliente" value="0" id="cliente-0" class="cliente-radio" style="display:none;" required>
                                <label for="cliente-0">
                                    <span class="cliente-nombre">Cliente Indocumentado</span>
                                    <span class="cliente-doc">(-----)</span>
                                </label>
                            </li>
                            <?php while($cli = mysqli_fetch_assoc($resultClientes)): ?>
                                <li data-id="<?php echo $cli['idCliente']; ?>">
                                    <input type="radio" name="idCliente" value="<?php echo $cli['idCliente']; ?>" id="cliente-<?php echo $cli['idCliente']; ?>" class="cliente-radio" style="display:none;">
                                    <label for="cliente-<?php echo $cli['idCliente']; ?>">
                                        <span class="cliente-nombre"><?php echo htmlspecialchars($cli['nombres'].' '.$cli['apellidos']); ?></span>
                                        <span class="cliente-doc">(<?php echo htmlspecialchars($cli['tipoDocumento'].' '.$cli['numeroDocumento']); ?>)</span>
                                    </label>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">📦 Seleccionar Productos</h3>
                    <div class="products-list" id="productsList">
                        <?php while($prod = mysqli_fetch_assoc($resultProductos)): ?>
                            <div class="product-item" data-id="<?php echo $prod['idProducto']; ?>">
                                <input type="checkbox"
                                       class="product-checkbox"
                                       data-nombre="<?php echo htmlspecialchars($prod['nombreProducto']); ?>"
                                       data-precio="<?php echo $prod['precio']; ?>"
                                       value="<?php echo $prod['idProducto']; ?>">
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($prod['nombreProducto']); ?></div>
                                    <div class="product-price">S/. <?php echo number_format($prod['precio'], 2); ?></div>
                                </div>
                                <input type="number"
                                       name="productos[<?php echo $prod['idProducto']; ?>][cantidad]"
                                       class="product-quantity"
                                       min="1"
                                       value="1"
                                       disabled>
                                <input type="hidden" name="productos[<?php echo $prod['idProducto']; ?>][precioUnitario]" value="<?php echo $prod['precio']; ?>">
                                <input type="hidden" name="productos[<?php echo $prod['idProducto']; ?>][nombre]" value="<?php echo htmlspecialchars($prod['nombreProducto']); ?>">
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="summary-panel" id="summaryPanel">
                    <div class="section-title">📊 Resumen de la Venta</div>
                    <ul class="summary-list" id="summaryItems"></ul>
                    <div class="summary-item total-row">
                        <span>Total a Pagar:</span>
                        <span id="totalAmount">S/. 0.00</span>
                    </div>
                    <div class="payment-panel">
                        <h4>💰 Pago</h4>
                        <div class="form-group payment-info">
                            <label for="montoPagado" class="form-label">Monto Pagado</label>
                            <input type="number" step="0.01" min="0" class="form-input" id="montoPagado" required placeholder="0.00">
                        </div>
                        <div class="form-group payment-info">
                            <span class="form-label">Cambio:</span>
                            <span id="cambioAmount">S/. 0.00</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="monto_pagado" id="montoPagadoHidden">
                <input type="hidden" name="cambio_hidden" id="cambioHidden">

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    💾 Generar Boleta
                </button>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formBoleta = document.getElementById('formBoleta');
            const clienteSearchInput = document.getElementById('clienteSearch');
            const clienteList = document.getElementById('clienteList');
            const productsList = document.getElementById('productsList');
            const summaryPanel = document.getElementById('summaryPanel');
            const summaryItems = document.getElementById('summaryItems');
            const totalAmountSpan = document.getElementById('totalAmount');
            const montoPagadoInput = document.getElementById('montoPagado');
            const cambioAmountSpan = document.getElementById('cambioAmount');
            const submitBtn = document.getElementById('submitBtn');
            const toast = document.getElementById('toast');

            let total = 0;
            const clienteRadios = document.querySelectorAll('.cliente-radio');

            function updateTotal() {
                total = 0;
                let itemsHtml = '';
                const selectedProducts = productsList.querySelectorAll('.product-item.selected');

                if (selectedProducts.length === 0) {
                    summaryPanel.style.display = 'none';
                    return;
                }

                selectedProducts.forEach(item => {
                    const checkbox = item.querySelector('.product-checkbox');
                    const quantityInput = item.querySelector('.product-quantity');
                    const productName = checkbox.dataset.nombre;
                    const productPrice = parseFloat(checkbox.dataset.precio);
                    const quantity = parseInt(quantityInput.value) || 0;
                    const subtotal = productPrice * quantity;
                    
                    if (quantity > 0) {
                        total += subtotal;
                        itemsHtml += `<li class="summary-item">
                                            <span>${productName} (${quantity}x)</span>
                                            <span>S/. ${subtotal.toFixed(2)}</span>
                                        </li>`;
                    }
                });

                summaryItems.innerHTML = itemsHtml;
                totalAmountSpan.textContent = `S/. ${total.toFixed(2)}`;
                summaryPanel.style.display = 'block';

                updatePaymentDetails();
            }

            function updatePaymentDetails() {
                const montoPagado = parseFloat(montoPagadoInput.value) || 0;
                let cambio = montoPagado - total;
                const clienteSeleccionado = document.querySelector('input[name="idCliente"]:checked');

                if (montoPagado >= total && total > 0 && clienteSeleccionado) {
                    cambioAmountSpan.textContent = `S/. ${cambio.toFixed(2)}`;
                    submitBtn.disabled = false;
                } else {
                    cambioAmountSpan.textContent = `S/. 0.00`;
                    submitBtn.disabled = true;
                }
            }

            function showToast(message, type) {
                toast.textContent = message;
                toast.className = `toast show ${type}`;
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }

            // Filtro de clientes
            clienteSearchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                const items = clienteList.querySelectorAll('li');
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(filter) ? '' : 'none';
                });
            });

            // Selección de cliente
            clienteList.addEventListener('click', function(e) {
                const listItem = e.target.closest('li');
                if (listItem) {
                    const radio = listItem.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        updatePaymentDetails();
                    }
                }
            });

            // Event Listeners
            productsList.addEventListener('change', (event) => {
                const target = event.target;
                if (target.classList.contains('product-checkbox')) {
                    const item = target.closest('.product-item');
                    const quantityInput = item.querySelector('.product-quantity');
                    
                    item.classList.toggle('selected', target.checked);
                    quantityInput.disabled = !target.checked;
                    if (!target.checked) {
                        quantityInput.value = 1;
                    }

                    updateTotal();
                }
            });

            productsList.addEventListener('input', (event) => {
                if (event.target.classList.contains('product-quantity')) {
                    updateTotal();
                }
            });
            
            montoPagadoInput.addEventListener('input', updatePaymentDetails);
            clienteRadios.forEach(radio => radio.addEventListener('change', updatePaymentDetails));

            formBoleta.addEventListener('submit', function(e) {
                const selectedProductsCount = productsList.querySelectorAll('.product-item.selected').length;
                const clienteSeleccionado = document.querySelector('input[name="idCliente"]:checked');

                if (selectedProductsCount === 0) {
                    e.preventDefault();
                    showToast('Por favor, selecciona al menos un producto.', 'warning');
                    return;
                }
                
                if (!clienteSeleccionado) {
                    e.preventDefault();
                    showToast('Por favor, selecciona un cliente.', 'warning');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Procesando...';

                document.getElementById('montoPagadoHidden').value = montoPagadoInput.value;
                document.getElementById('cambioHidden').value = parseFloat(cambioAmountSpan.textContent.replace('S/. ', '')) || 0;
            });
            
            updateTotal();

            const sessionMessage = '<?php echo $mensaje; ?>';
            const sessionType = '<?php echo $tipoMensaje; ?>';
            if (sessionMessage) {
                showToast(sessionMessage, sessionType);
            }
        });
    </script>
</body>
</html>
