<?php
// -------------------------
// Conexion segura a MySQL Aiven con SSL para Render
// -------------------------

// Variables de conexión desde entorno o defaults
$host     = getenv('DB_HOST') ?: "mysql-27249fc-aaronf3rnandez-c423.g.aivencloud.com";
$port     = getenv('DB_PORT') ?: 14139;
$user     = getenv('DB_USER') ?: "avnadmin";
$password = getenv('DB_PASSWORD') ?: "AVNS_-jzj4NzFXcNLw0ufC_r";
$dbname   = getenv('DB_NAME') ?: "restaurantearoma";

// Ruta al certificado SSL incluido en Docker
$ssl_ca   = __DIR__ . "/certs/ca.pem";

// Inicializar conexión mysqli con SSL
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL);
mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

if (mysqli_connect_errno()) {
    die("❌ Error de conexión: " . mysqli_connect_error());
}

// echo "✅ Conexión exitosa!";
?>
