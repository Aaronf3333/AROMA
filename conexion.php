<?php
// -------------------------
// Conexion segura a MySQL Aiven con SSL para Render
// -------------------------

// Si estás en local, puedes usar un archivo .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        putenv(trim($line));
    }
}

// Variables de conexión desde entorno o defaults
$host     = getenv('DB_HOST') ?: "mysql-27249fc-aaronf3rnandez-c423.g.aivencloud.com";
$port     = getenv('DB_PORT') ?: 14139;
$user     = getenv('DB_USER') ?: "avnadmin";
$password = getenv('DB_PASSWORD') ?: "AVNS_-jzj4NzFXcNLw0ufC_r";
$dbname   = getenv('DB_NAME') ?: "restaurantearoma";

// Ruta al certificado descargado
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
