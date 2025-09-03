<?php
session_start();
include(__DIR__ . "/conexion.php");

if ($conn->connect_error) {
    die("❌ No se pudo conectar a MySQL: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $idCliente = intval($_GET['id']);

    // Obtener estado actual
    $sql = "SELECT activo FROM Cliente WHERE idCliente = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        $nuevoEstado = $row['activo'] ? 0 : 1;

        $sqlUpdate = "UPDATE Cliente SET activo = ? WHERE idCliente = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("ii", $nuevoEstado, $idCliente);
        
        if ($stmtUpdate->execute()) {
            $_SESSION['mensaje'] = $nuevoEstado ? "Cliente activado correctamente." : "Cliente desactivado correctamente.";
            $_SESSION['tipo'] = $nuevoEstado ? "success" : "warning";
        } else {
            $_SESSION['mensaje'] = "Error al actualizar el cliente.";
            $_SESSION['tipo'] = "error";
        }
        
        $stmtUpdate->close();
    } else {
        $_SESSION['mensaje'] = "Cliente no encontrado.";
        $_SESSION['tipo'] = "error";
    }
    
    $stmt->close();
}

$conn->close();
header("Location: clientes.php");
exit();
?>