<?php
session_start();
include(__DIR__ . "/conexion.php");

if (!isset($_SESSION['idUsuario'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("❌ ID de boleta no especificado.");
}

$idBoleta = intval($_GET['id']);

// Obtener el archivo PDF de la boleta desde la base de datos
$sql = "SELECT numeroBoleta, archivoPDF FROM boleta WHERE idBoleta = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("❌ Error al preparar la consulta: " . $conn->error);
}

$stmt->bind_param("i", $idBoleta);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($numeroBoleta, $pdfContent);

if ($stmt->fetch()) {
    if ($pdfContent === null) {
        die("❌ La boleta no tiene un PDF generado.");
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $numeroBoleta . '.pdf"');
    header('Content-Length: ' . strlen($pdfContent));
    echo $pdfContent;
    exit();
} else {
    die("❌ Boleta no encontrada.");
}

$stmt->close();
$conn->close();
?>