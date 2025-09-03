<?php
session_start();
include(__DIR__ . "/conexion.php"); // Asegúrate de que este archivo ahora conecta a MySQL
require(__DIR__ . "/fpdf/fpdf.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

$idUsuario = $_SESSION['idUsuario'];
$usuarioNombre = $_SESSION['nombres'] . ' ' . $_SESSION['apellidos'];

// Obtener clientes
$sqlClientes = "SELECT c.idCliente, p.nombres, p.apellidos, p.tipoDocumento, p.numeroDocumento, p.direccion, p.telefono
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
    $productosSeleccionados = $_POST['productos']; // array idProducto => cantidad
    $montoPagado = floatval($_POST['monto_pagado']);
    $cambio = floatval($_POST['cambio_hidden']);
    $total = 0;
    $detalleProductos = [];

    // Calcular el total y preparar el detalle de productos
    foreach ($productosSeleccionados as $idProd => $cant) {
        $cantidad = intval($cant['cantidad']);
        $precioUnitario = floatval($cant['precioUnitario']);
        $subtotal = $cantidad * $precioUnitario;
        $total += $subtotal;
        $detalleProductos[$idProd] = [
            'cantidad' => $cantidad,
            'precioUnitario' => $precioUnitario,
            'nombre' => $cant['nombre']
        ];
    }

    // Insertar Venta
    $sqlVenta = "INSERT INTO venta (idUsuario, idCliente, total) VALUES (?, ?, ?)";
    $stmtVenta = mysqli_prepare($conn, $sqlVenta);
    mysqli_stmt_bind_param($stmtVenta, "iid", $idUsuario, $idCliente, $total);
    if (!mysqli_stmt_execute($stmtVenta)) {
        die("Error al insertar venta: " . mysqli_stmt_error($stmtVenta));
    }
    $idVenta = mysqli_insert_id($conn);
    mysqli_stmt_close($stmtVenta);

    // Insertar DetalleVenta
    $sqlDetalle = "INSERT INTO detalleventa (idVenta, idProducto, cantidad, precioUnitario) VALUES (?, ?, ?, ?)";
    $stmtDetalle = mysqli_prepare($conn, $sqlDetalle);
    foreach ($detalleProductos as $idProd => $data) {
        mysqli_stmt_bind_param($stmtDetalle, "iiid", $idVenta, $idProd, $data['cantidad'], $data['precioUnitario']);
        mysqli_stmt_execute($stmtDetalle);
    }
    mysqli_stmt_close($stmtDetalle);

    // -------------------------
    // GENERAR PDF TIPO TICKET COMPACTO
    // -------------------------
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
    $pdf->Cell($anchoEfectivo,7,'NOTA DE VENTA',0,1,'C',false);
    $pdf->SetFont('Arial','B',9);
    $pdf->SetX(3);
    $pdf->Cell($anchoEfectivo,6,'Aroma S.A.C',0,1,'C',false);

    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial','',8);
    $pdf->Ln(3);
    $pdf->Cell($anchoEfectivo,4,'Cajero: '.$usuarioNombre,0,1,'C');
    $pdf->Ln(1);
    
    // RUC y Número de Boleta
    $ruc = "10345678901";
    $numeroBoleta = 'BOL'.str_pad($idVenta,5,'0',STR_PAD_LEFT);
    $pdf->Cell($anchoEfectivo,4,'RUC: '.$ruc,0,1,'C');
    $pdf->Cell($anchoEfectivo,4,'Nro: '.$numeroBoleta,0,1,'C');
    
    // Fecha de Emisión
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
    $pdf->Cell($anchoEfectivo,4,$cliente['nombres'].' '.$cliente['apellidos'],0,1,'L');
    $pdf->Ln(1);
    $pdf->Cell($anchoEfectivo,4,$cliente['tipoDocumento'].': '.$cliente['numeroDocumento'],0,1,'L');
    if(!empty($cliente['direccion'])) {
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo,4,'Dir: '.$cliente['direccion'],0,1,'L');
    }
    if(!empty($cliente['telefono'])) {
        $pdf->Ln(1);
        $pdf->Cell($anchoEfectivo,4,'Tel: '.$cliente['telefono'],0,1,'L');
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
        $pdf->Cell($anchoProducto,6,$nombreProducto,1,0,'L',$alternar);
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
    $pdf->Cell($anchoEfectivo,5,'DETALLE DE PAGO',0,1,'L');
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
    $pdf->Cell($anchoEfectivo,4,'¡Gracias por su compra!',0,1,'C');
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
        die("Error al preparar boleta: " . mysqli_error($conn));
    }
    
    // El tipo 's' es para strings, que MySQLi maneja como BLOB si el campo de la DB es de ese tipo
    mysqli_stmt_bind_param($stmtBoleta, "iss", $idVenta, $numeroBoleta, $pdfContent);
    
    if (!mysqli_stmt_execute($stmtBoleta)) {
        die("Error al insertar boleta: " . mysqli_stmt_error($stmtBoleta));
    }
    mysqli_stmt_close($stmtBoleta);

    $_SESSION['mensaje'] = "Boleta generada correctamente.";
    $_SESSION['tipo'] = "success";

    header("Location: boletas.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Boleta - Aroma S.A.C</title>
    <style>
        /* [Aquí va el mismo CSS que proporcionaste] */
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

        .container {
            max-width: 800px;
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
            background: linear-gradient(90deg, #00b894, #00e676);
            border-radius: 2px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
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
            background: linear-gradient(90deg, #00b894, #00e676, #00b894);
        }

        .form-section {
            margin-bottom: 35px;
        }

        .form-section h3 {
            color: #00b894;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-field {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: #00b894;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1);
            background: rgba(255, 255, 255, 1);
        }

        /* Productos Section */
        .products-grid {
            display: grid;
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(245, 246, 250, 0.5);
            border-radius: 12px;
            border: 2px solid #e9ecef;
        }

        .product-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .product-row:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .product-row.selected {
            border-color: #00b894;
            background: rgba(0, 184, 148, 0.05);
        }

        .product-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #00b894;
        }

        .product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .product-name {
            font-weight: 600;
            color: #2d3436;
            font-size: 15px;
        }

        .product-price {
            color: #00b894;
            font-weight: 700;
            font-size: 14px;
        }

        .quantity-input {
            width: 80px;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #00b894;
            box-shadow: 0 0 0 2px rgba(0, 184, 148, 0.1);
        }

        .quantity-input:disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        /* Summary Panel */
        .summary-panel {
            background: rgba(0, 184, 148, 0.1);
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
            border: 2px solid rgba(0, 184, 148, 0.2);
        }

        .summary-title {
            color: #00b894;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 184, 148, 0.2);
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 18px;
            color: #00b894;
        }

        .submit-btn {
            background: linear-gradient(135deg, #00b894, #00e676);
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

        .submit-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 184, 148, 0.4);
        }

        .submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Toast de confirmación */
        .confirmation-toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 10000;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            min-width: 350px;
            max-width: 500px;
        }

        .confirmation-toast.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .toast-content {
            text-align: center;
        }

        .toast-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .toast-text {
            font-size: 18px;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 25px;
            line-height: 1.4;
        }

        .toast-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .toast-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .toast-btn.confirm {
            background: linear-gradient(135deg, #00b894, #00e676);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 184, 148, 0.3);
        }

        .toast-btn.confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 184, 148, 0.4);
        }

        .toast-btn.cancel {
            background: linear-gradient(135deg, #6c757d, #868e96);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .toast-btn.cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        /* Toast simple */
        .simple-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            min-width: 300px;
            max-width: 450px;
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

        .simple-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .simple-toast.warning {
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            border-left: 4px solid #ff9800;
        }

        /* Overlay para el modal */
        .confirmation-toast::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        /* Nuevos estilos para el panel de pago */
        .payment-panel {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .payment-panel h4 {
            color: #343a40;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .payment-field {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .payment-field .form-label {
            margin-bottom: 0;
            font-size: 16px;
        }

        .payment-field .form-input {
            width: 150px;
            text-align: right;
        }

        .payment-field #cambioAmount {
            font-weight: bold;
            font-size: 18px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .form-container {
                padding: 25px 20px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .product-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .product-info {
                text-align: center;
            }

            .quantity-input {
                width: 100%;
                max-width: 120px;
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                max-height: 300px;
            }

            .summary-panel {
                padding: 20px 15px;
            }

            .submit-btn {
                padding: 16px 30px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <?php include(__DIR__ . "/includes/header.php"); ?>

    <div class="container">
        <h1 class="page-title">💼 Generar Nueva Boleta</h1>

        <div class="form-container">
            <form method="POST" id="formBoleta">
                <div class="form-section">
                    <h3>👤 Seleccionar Cliente</h3>
                    <div class="form-field">
                        <label class="form-label">Cliente</label>
                        <select name="idCliente" class="form-select" required id="clienteSelect">
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
                    <h3>📦 Seleccionar Productos</h3>
                    <div class="products-grid" id="productsGrid">
                        <?php while($prod = mysqli_fetch_assoc($resultProductos)): ?>
                            <div class="product-row" id="row_<?php echo $prod['idProducto']; ?>">
                                <input type="checkbox"
                                        class="product-checkbox"
                                        data-nombre="<?php echo htmlspecialchars($prod['nombreProducto']); ?>"
                                        data-precio="<?php echo $prod['precio']; ?>"
                                        value="<?php echo $prod['idProducto']; ?>"
                                        id="producto_<?php echo $prod['idProducto']; ?>">

                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($prod['nombreProducto']); ?></div>
                                    <div class="product-price">S/. <?php echo number_format($prod['precio'], 2); ?></div>
                                </div>

                                <input type="number"
                                        name="productos[<?php echo $prod['idProducto']; ?>][cantidad]"
                                        class="quantity-input"
                                        min="1"
                                        value="1"
                                        disabled
                                        data-precio="<?php echo $prod['precio']; ?>"
                                        id="cantidad_<?php echo $prod['idProducto']; ?>">

                                <input type="hidden" name="productos[<?php echo $prod['idProducto']; ?>][precioUnitario]" value="<?php echo $prod['precio']; ?>">
                                <input type="hidden" name="productos[<?php echo $prod['idProducto']; ?>][nombre]" value="<?php echo htmlspecialchars($prod['nombreProducto']); ?>">
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="summary-panel" id="summaryPanel" style="display: none;">
                    <div class="summary-title">📊 Resumen de la Venta</div>
                    <div id="summaryItems"></div>
                    <div class="summary-item">
                        <span>Total a Pagar:</span>
                        <span id="totalAmount">S/. 0.00</span>
                    </div>

                    <div class="payment-panel">
                        <h4>💰 Pago</h4>
                        <div class="form-field">
                            <label for="montoPagado" class="form-label">Monto Pagado</label>
                            <input type="number" step="0.01" min="0" class="form-input" id="montoPagado" required placeholder="Ingrese monto">
                        </div>
                        <div class="form-field">
                            <span class="form-label">Cambio:</span>
                            <span id="cambioAmount">S/. 0.00</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="monto_pagado" id="montoPagadoHidden">
                <input type="hidden" name="cambio_hidden" id="cambioHidden">

                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    💾 Generar Boleta
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const submitBtn = document.getElementById('submitBtn');
            const clienteSelect = document.getElementById('clienteSelect');
            const summaryPanel = document.getElementById('summaryPanel');
            const summaryItems = document.getElementById('summaryItems');
            const totalAmountSpan = document.getElementById('totalAmount');
            const montoPagadoInput = document.getElementById('montoPagado');
            const cambioAmountSpan = document.getElementById('cambioAmount');
            const montoPagadoHidden = document.getElementById('montoPagadoHidden');
            const cambioHidden = document.getElementById('cambioHidden');
            let total = 0;

            // Función para actualizar el estado del formulario
            function updateFormState() {
                const clienteSelected = clienteSelect.value !== '';
                const productosSelected = Array.from(checkboxes).some(cb => cb.checked);

                if (clienteSelected && productosSelected) {
                    montoPagadoInput.disabled = false;
                    updateSummary();
                    summaryPanel.style.display = 'block';
                } else {
                    montoPagadoInput.disabled = true;
                    montoPagadoInput.value = '';
                    cambioAmountSpan.textContent = 'S/. 0.00';
                    submitBtn.disabled = true;
                    summaryPanel.style.display = 'none';
                    total = 0;
                }
            }

            // Función para actualizar el resumen y el cambio
            function updateSummary() {
                total = 0;
                let items = '';

                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const productId = checkbox.value;
                        const quantityInput = document.getElementById('cantidad_' + productId);
                        const productName = checkbox.dataset.nombre;
                        const productPrice = parseFloat(checkbox.dataset.precio);
                        const quantity = parseInt(quantityInput.value) || 1;
                        const subtotal = productPrice * quantity;

                        total += subtotal;

                        items += `
                            <div class="summary-item">
                                <span>${productName} (${quantity}x)</span>
                                <span>S/. ${subtotal.toFixed(2)}</span>
                            </div>
                        `;
                    }
                });

                summaryItems.innerHTML = items;
                totalAmountSpan.textContent = 'S/. ' + total.toFixed(2);

                updateCambio();
            }

            // Función para calcular el cambio
            function updateCambio() {
                const montoPagado = parseFloat(montoPagadoInput.value);
                let cambio = 0;

                if (isNaN(montoPagado) || montoPagado === 0) {
                    cambioAmountSpan.textContent = 'S/. 0.00';
                    submitBtn.disabled = true;
                } else if (montoPagado >= total) {
                    cambio = montoPagado - total;
                    cambioAmountSpan.textContent = 'S/. ' + cambio.toFixed(2);
                    submitBtn.disabled = false;
                    montoPagadoHidden.value = montoPagado.toFixed(2);
                    cambioHidden.value = cambio.toFixed(2);

                    if (montoPagado === total) {
                        cambioAmountSpan.textContent = 'S/. 0.00 (Pago exacto)';
                    }
                } else {
                    cambioAmountSpan.textContent = 'Monto insuficiente';
                    submitBtn.disabled = true;
                }
            }

            // Event listeners para checkboxes
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const productId = this.value;
                    const quantityInput = document.getElementById('cantidad_' + productId);
                    const row = document.getElementById('row_' + productId);

                    if (this.checked) {
                        quantityInput.disabled = false;
                        row.classList.add('selected');
                    } else {
                        quantityInput.disabled = true;
                        quantityInput.value = 1;
                        row.classList.remove('selected');
                    }

                    updateFormState();
                });

                const productId = checkbox.value;
                const quantityInput = document.getElementById('cantidad_' + productId);
                quantityInput.addEventListener('input', updateSummary);
            });

            // Event listener para selección de cliente
            clienteSelect.addEventListener('change', updateFormState);

            // Event listener para el monto pagado
            montoPagadoInput.addEventListener('input', updateCambio);

            // Validación del formulario antes de enviar
            document.getElementById('formBoleta').addEventListener('submit', function(e) {
                const selectedProducts = Array.from(checkboxes).filter(cb => cb.checked);
                const montoPagado = parseFloat(montoPagadoInput.value);

                if (selectedProducts.length === 0) {
                    e.preventDefault();
                    showToast('Por favor, seleccione al menos un producto.', 'warning');
                    return;
                }
            });

            // Función para mostrar toasts
            function showToast(message, type) {
                let toast = document.querySelector('.simple-toast');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.className = 'simple-toast';
                    document.body.appendChild(toast);
                }
                toast.textContent = message;
                toast.className = `simple-toast show ${type}`;
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }
        });
    </script>
</body>
</html>