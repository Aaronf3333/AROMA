<?php
session_start();
include(__DIR__ . "/conexion.php"); // Asegúrate de que este archivo ahora conecta a MySQL

// Configurar codificación UTF-8 para MySQL
mysqli_set_charset($conn, "utf8");

require(__DIR__ . "/fpdf/fpdf.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

$idUsuario = $_SESSION['idUsuario'];
$usuarioNombre = $_SESSION['nombres'] . ' ' . $_SESSION['apellidos'];

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
    $idCliente = intval($_POST['idCliente']);
    $productosSeleccionados = $_POST['productos'] ?? []; 
    $montoPagado = floatval($_POST['monto_pagado']);
    $cambio = floatval($_POST['cambio_hidden']);
    $total = 0;
    $detalleProductos = [];

    // Filtrar solo productos con cantidad válida y calcular el total
    foreach ($productosSeleccionados as $idProd => $data) {
        // Verificar que existan las claves necesarias y que la cantidad sea válida
        if (isset($data['cantidad']) && isset($data['precioUnitario']) && isset($data['nombre'])) {
            $cantidad = intval($data['cantidad']);
            $precioUnitario = floatval($data['precioUnitario']);
            
            // Solo procesar si la cantidad es mayor a 0 (productos realmente seleccionados)
            if ($cantidad > 0) {
                $subtotal = $cantidad * $precioUnitario;
                $total += $subtotal;
                $detalleProductos[$idProd] = [
                    'cantidad' => $cantidad,
                    'precioUnitario' => $precioUnitario,
                    'nombre' => $data['nombre']
                ];
            }
        }
    }

    // Verificar que hay productos seleccionados
    if (empty($detalleProductos)) {
        $_SESSION['mensaje'] = "Debe seleccionar al menos un producto.";
        $_SESSION['tipo'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Iniciar una transacción para asegurar la integridad de los datos
    mysqli_begin_transaction($conn);
    try {
        // 1. Insertar Venta
        $sqlVenta = "INSERT INTO venta (idUsuario, idCliente, total) VALUES (?, ?, ?)";
        $stmtVenta = mysqli_prepare($conn, $sqlVenta);
        mysqli_stmt_bind_param($stmtVenta, "iid", $idUsuario, $idCliente, $total);
        mysqli_stmt_execute($stmtVenta);
        $idVenta = mysqli_insert_id($conn);
        mysqli_stmt_close($stmtVenta);

        // 2. Insertar Detalle de Venta (solo productos seleccionados)
        $sqlDetalle = "INSERT INTO detalleventa (idVenta, idProducto, cantidad, precioUnitario) VALUES (?, ?, ?, ?)";
        $stmtDetalle = mysqli_prepare($conn, $sqlDetalle);
        foreach ($detalleProductos as $idProd => $data) {
            mysqli_stmt_bind_param($stmtDetalle, "iiid", $idVenta, $idProd, $data['cantidad'], $data['precioUnitario']);
            mysqli_stmt_execute($stmtDetalle);
        }
        mysqli_stmt_close($stmtDetalle);

        // 3. Generar PDF con codificación UTF-8 corregida
        $pdf = new FPDF('P','mm',array(80,200));
        $pdf->AddPage();
        $pdf->SetMargins(3, 5, 3);
        $pdf->SetAutoPageBreak(true, 10);
        $anchoEfectivo = 74;

        // Encabezado
        $pdf->SetFillColor(60,60,60);
        $pdf->SetTextColor(255,255,255);
        $pdf->SetFont('Arial','B',11);
        $pdf->Rect(3, 5, $anchoEfectivo, 13, 'F');
        $pdf->SetXY(3, 6);
        $pdf->Cell($anchoEfectivo,7,utf8_decode('NOTA DE VENTA'),0,1,'C',false);
        $pdf->SetFont('Arial','B',9);
        $pdf->SetX(3);
        $pdf->Cell($anchoEfectivo,6,'Aroma S.A.C',0,1,'C',false);

        $pdf->SetTextColor(0,0,0);
        $pdf->SetFont('Arial','',8);
        $pdf->Ln(3);
        $pdf->Cell($anchoEfectivo,4,utf8_decode('Cajero: '.$usuarioNombre),0,1,'C');
        $pdf->Ln(1);
        
        $ruc = "10345678901";
        $numeroBoleta = 'BOL'.str_pad($idVenta,5,'0',STR_PAD_LEFT);
        $pdf->Cell($anchoEfectivo,4,'RUC: '.$ruc,0,1,'C');
        $pdf->Cell($anchoEfectivo,4,utf8_decode('Nro: '.$numeroBoleta),0,1,'C');
        
        date_default_timezone_set('America/Lima');
        $fechaEmision = date('d/m/Y H:i:s');
        $pdf->Cell($anchoEfectivo,4,'Fecha: '.$fechaEmision,0,1,'C');

        $pdf->Ln(5);

        // Info cliente
        $sqlCliente = "SELECT p.* FROM cliente c JOIN persona p ON c.idPersona=p.idPersona WHERE c.idCliente=?";
        $stmtCliente = mysqli_prepare($conn, $sqlCliente);
        mysqli_stmt_bind_param($stmtCliente, "i", $idCliente);
        mysqli_stmt_execute($stmtCliente);
        $resultCliente = mysqli_stmt_get_result($stmtCliente);
        $cliente = mysqli_fetch_assoc($resultCliente);
        mysqli_stmt_close($stmtCliente);

        $pdf->SetFont('Arial','B',8);
        $pdf->Cell($anchoEfectivo,5,'CLIENTE',0,1,'L');
        $pdf->Ln(1);
        $pdf->SetFont('Arial','',7);
        $pdf->Cell($anchoEfectivo,4,utf8_decode($cliente['nombres'].' '.$cliente['apellidos']),0,1,'L');
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo,4,utf8_decode($cliente['tipoDocumento'].': '.$cliente['numeroDocumento']),0,1,'L');
        if(!empty($cliente['direccion'])) {
            $pdf->Ln(1);
            $pdf->Cell($anchoEfectivo,4,utf8_decode('Dir: '.$cliente['direccion']),0,1,'L');
        }
        if(!empty($cliente['telefono'])) {
            $pdf->Ln(1);
            $pdf->Cell($anchoEfectivo,4,utf8_decode('Tel: '.$cliente['telefono']),0,1,'L');
        }
        $pdf->Ln(5);

        // TABLA PRODUCTOS
        $anchoProducto = 38;
        $anchoCantidad = 10;
        $anchoPrecio = 13;
        $anchoSubtotal = 13;

        $pdf->SetFillColor(230,230,230);
        $pdf->SetFont('Arial','B',7);
        $pdf->Cell($anchoProducto,6,'PRODUCTO',1,0,'C',true);
        $pdf->Cell($anchoCantidad,6,'CANT',1,0,'C',true);
        $pdf->Cell($anchoPrecio,6,'PRECIO',1,0,'C',true);
        $pdf->Cell($anchoSubtotal,6,'TOTAL',1,1,'C',true);

        $pdf->SetFont('Arial','',7);
        $pdf->SetFillColor(248,248,248);
        $alternar = false;

        foreach ($detalleProductos as $data) {
            $nombreProducto = $data['nombre'];
            if (strlen($nombreProducto) > 22) {
                $nombreProducto = substr($nombreProducto, 0, 19) . '...';
            }
            $pdf->Cell($anchoProducto,6,utf8_decode($nombreProducto),1,0,'L',$alternar);
            $pdf->Cell($anchoCantidad,6,$data['cantidad'],1,0,'C',$alternar);
            $pdf->Cell($anchoPrecio,6,'S/ '.number_format($data['precioUnitario'],2),1,0,'R',$alternar);
            $pdf->Cell($anchoSubtotal,6,'S/ '.number_format($data['cantidad']*$data['precioUnitario'],2),1,1,'R',$alternar);
            $alternar = !$alternar;
        }

        $pdf->Ln(4);
        $pdf->SetDrawColor(200,200,200);
        $pdf->Line(3, $pdf->GetY(), 77, $pdf->GetY());
        $pdf->Ln(4);

        // TOTAL
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor(60,60,60);
        $pdf->SetTextColor(255,255,255);
        $pdf->SetDrawColor(0,0,0);
        $anchoTextoTotal = $anchoEfectivo - 20;
        $pdf->Cell($anchoTextoTotal,8,'TOTAL A PAGAR',1,0,'C',true);
        $pdf->Cell(20,8,'S/ '.number_format($total,2),1,1,'R',true);

        // Sección de pago
        $pdf->Ln(5);
        $pdf->SetFont('Arial','B',8);
        $pdf->SetTextColor(0,0,0);
        $pdf->Cell($anchoEfectivo,5,utf8_decode('DETALLE DE PAGO'),0,1,'L');
        $pdf->Ln(1);

        $pdf->SetFont('Arial','',8);
        $pdf->Cell($anchoEfectivo/2,4,'Monto Pagado:',0,0,'L');
        $pdf->Cell($anchoEfectivo/2,4,'S/ '.number_format($montoPagado, 2),0,1,'R');

        $pdf->Cell($anchoEfectivo/2,4,'Cambio:',0,0,'L');
        $pdf->Cell($anchoEfectivo/2,4,'S/ '.number_format($cambio, 2),0,1,'R');

        // Pie
        $pdf->Ln(6);
        $pdf->SetFont('Arial','I',7);
        $pdf->SetTextColor(100,100,100);
        $pdf->Cell($anchoEfectivo,4,utf8_decode('¡Gracias por su compra!'),0,1,'C');
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo,4,'www.aromasac.com',0,1,'C');
        $pdf->Ln(3);

        // NOTA LEGAL
        $pdf->SetFont('Arial','B',7);
        $pdf->SetTextColor(170,170,170);
        $pdf->Cell($anchoEfectivo,4,utf8_decode('Esta nota no tiene valor tributario.'),0,1,'C');
        $pdf->Cell($anchoEfectivo,4,utf8_decode('Solicite su Boleta de Venta o Factura.'),0,1,'C');

        $pdfContent = $pdf->Output('S');

        // Guardar en MySQL como BLOB
        $sqlBoleta = "INSERT INTO boleta (idVenta, numeroBoleta, archivoPDF) VALUES (?, ?, ?)";
        $stmtBoleta = mysqli_prepare($conn, $sqlBoleta);
        
        if (!$stmtBoleta) {
            throw new Exception("Error al preparar boleta: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmtBoleta, "iss", $idVenta, $numeroBoleta, $pdfContent);
        
        if (!mysqli_stmt_execute($stmtBoleta)) {
            throw new Exception("Error al insertar boleta: " . mysqli_stmt_error($stmtBoleta));
        }
        mysqli_stmt_close($stmtBoleta);

        // Si todo es correcto, confirmar la transacción
        mysqli_commit($conn);

        $_SESSION['mensaje'] = "Boleta generada correctamente.";
        $_SESSION['tipo'] = "success";

        header("Location: boletas.php");
        exit();

    } catch (Exception $e) {
        // En caso de error, deshacer la transacción
        mysqli_rollback($conn);
        die("Error en la transacción: " . $e->getMessage());
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

        /* Base & Layout */
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

        /* Typography */
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

        /* Form Elements */
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

        /* Product List */
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

        /* Summary & Payment */
        .summary-panel {
            background: rgba(0, 184, 148, 0.1);
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
            border: 2px solid rgba(0, 184, 148, 0.2);
            display: none; /* Se activa con JS */
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

        /* Buttons & Toasts */
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

        .toast.error {
            background-color: #f44336;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .main-card {
                padding: 25px;
            }

            .page-title {
                font-size: 2rem;
            }

            .product-item {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .product-details {
                margin-top: 5px;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.6rem;
            }

            .btn-submit {
                padding: 16px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <h1 class="page-title">💼 Generar Nueva Boleta</h1>
        
        <div class="main-card">
            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="toast show <?php echo $_SESSION['tipo']; ?>" id="toast">
                    <?php 
                    echo $_SESSION['mensaje']; 
                    unset($_SESSION['mensaje'], $_SESSION['tipo']);
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="formBoleta">
                <div class="form-section">
                    <h3 class="section-title">👤 Seleccionar Cliente</h3>
                    <div class="form-group">
                        <label for="clienteSelect" class="form-label">Cliente</label>
                        <select name="idCliente" class="form-select" id="clienteSelect" required>
                            <option value="">Seleccione un cliente</option>
                            <?php while($cli = mysqli_fetch_assoc($resultClientes)): ?>
                                <option value="<?php echo $cli['idCliente']; ?>">
                                    <?php echo htmlspecialchars($cli['nombres'].' '.$cli['apellidos'].' ('.$cli['tipoDocumento'].' '.$cli['numeroDocumento'].')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
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
                                       value="<?php echo $prod['idProducto']; ?>"
                                       id="product_<?php echo $prod['idProducto']; ?>">

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
                    <ul class="summary-list" id="summaryItems">
                        </ul>
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
            const clienteSelect = document.getElementById('clienteSelect');
            const productsList = document.getElementById('productsList');
            const summaryPanel = document.getElementById('summaryPanel');
            const summaryItems = document.getElementById('summaryItems');
            const totalAmountSpan = document.getElementById('totalAmount');
            const montoPagadoInput = document.getElementById('montoPagado');
            const cambioAmountSpan = document.getElementById('cambioAmount');
            const submitBtn = document.getElementById('submitBtn');
            const toast = document.getElementById('toast');

            let total = 0;

            function updateTotal() {
                total = 0;
                let itemsHtml = '';
                const selectedProducts = productsList.querySelectorAll('.product-item.selected');

                if (selectedProducts.length === 0) {
                    summaryPanel.style.display = 'none';
                    submitBtn.disabled = true;
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

                if (montoPagado >= total && total > 0) {
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

            formBoleta.addEventListener('submit', function(e) {
                const selectedProductsCount = productsList.querySelectorAll('.product-item.selected').length;
                if (selectedProductsCount === 0) {
                    e.preventDefault();
                    showToast('Por favor, selecciona al menos un producto.', 'warning');
                    return;
                }

                if (!clienteSelect.value) {
                    e.preventDefault();
                    showToast('Por favor, selecciona un cliente.', 'warning');
                    return;
                }

                const montoPagado = parseFloat(montoPagadoInput.value) || 0;
                if (montoPagado < total) {
                    e.preventDefault();
                    showToast('El monto pagado debe ser mayor o igual al total.', 'warning');
                    return;
                }

                // Desactivar productos no seleccionados para que no se envíen
                const unselectedItems = productsList.querySelectorAll('.product-item:not(.selected)');
                unselectedItems.forEach(item => {
                    const inputs = item.querySelectorAll('input[name^="productos"]');
                    inputs.forEach(input => {
                        input.disabled = true;
                    });
                });

                // Ocultar el botón para evitar doble clic
                submitBtn.disabled = true;
                submitBtn.textContent = 'Procesando...';

                // Llenar los campos hidden antes de enviar
                document.getElementById('montoPagadoHidden').value = montoPagadoInput.value;
                document.getElementById('cambioHidden').value = parseFloat(cambioAmountSpan.textContent.replace('S/. ', '')) || 0;
            });
            
            // Auto-hide toast messages
            if (document.querySelector('.toast.show')) {
                setTimeout(() => {
                    const existingToast = document.querySelector('.toast.show');
                    if (existingToast) {
                        existingToast.classList.remove('show');
                    }
                }, 5000);
            }
            
            // Inicializar el estado al cargar la página
            updateTotal();
        });
    </script>
</body>
</html>
