<?php
include("conexion.php");

// Probar consulta
$result = mysqli_query($conn, "SHOW TABLES");
if (!$result) {
    die("❌ Error en consulta: " . mysqli_error($conn));
}

echo "<h2>Tablas en la BD:</h2><ul>";
while ($row = mysqli_fetch_array($result)) {
    echo "<li>" . $row[0] . "</li>";
}
echo "</ul>";

// Probar si existe la tabla Usuario
$result2 = mysqli_query($conn, "SELECT * FROM Usuario LIMIT 5");
if ($result2) {
    echo "<h2>Usuarios (primeros 5):</h2><ul>";
    while ($row = mysqli_fetch_assoc($result2)) {
        echo "<li>" . htmlspecialchars($row['usuario']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "⚠️ No se pudo leer tabla Usuario: " . mysqli_error($conn);
}
?>
