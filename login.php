<?php
session_start();
include(__DIR__ . "/conexion.php"); // Conexión a MySQL

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);

    $sql = "SELECT u.idUsuario, u.usuario, u.contrasena, u.idRol, r.nombreRol, p.nombres, p.apellidos
            FROM usuario u
            JOIN rol r ON u.idRol = r.idRol
            JOIN persona p ON u.idPersona = p.idPersona
            WHERE u.usuario = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        // === CAMBIO CRÍTICO: USO DE password_verify() ===
        // Se asume que las contraseñas en la DB ya están hasheadas
        if (password_verify($contrasena, $row['contrasena'])) {
            $_SESSION['idUsuario'] = $row['idUsuario'];
            $_SESSION['usuario'] = $row['usuario'];
            $_SESSION['rol'] = $row['nombreRol'];
            $_SESSION['nombres'] = $row['nombres'];
            $_SESSION['apellidos'] = $row['apellidos'];

            header("Location: index.php");
            exit();
        } else {
            $mensaje = "Credenciales incorrectas.";
        }
    } else {
        $mensaje = "Credenciales incorrectas.";
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Aroma S.A.C</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* Variables y reseteo */
:root {
    --primary-color: #6a11cb;
    --secondary-color: #2575fc;
    --text-color: #2c3e50;
    --error-color: #e74c3c;
    --shadow-light: rgba(0, 0, 0, 0.1);
    --shadow-heavy: rgba(0, 0, 0, 0.25);
    --bg-gradient: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* Cuerpo de la página */
body {
    font-family: 'Poppins', sans-serif; /* Consistencia con el dashboard */
    background: var(--bg-gradient);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

/* Contenedor del formulario de login */
.login-container {
    background: rgba(255, 255, 255, 0.95);
    padding: clamp(30px, 5vw, 40px) clamp(25px, 5vw, 30px);
    border-radius: 20px;
    box-shadow: 0 8px 25px var(--shadow-heavy);
    width: 100%;
    max-width: 400px;
    text-align: center;
    backdrop-filter: blur(5px);
}

/* Logo circular */
.logo img {
    width: clamp(80px, 15vw, 100px);
    height: clamp(80px, 15vw, 100px);
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px var(--shadow-light);
}

/* Título */
h1 {
    margin-bottom: 20px;
    font-size: clamp(24px, 5vw, 28px);
    color: var(--text-color);
    font-weight: bold;
}

/* Grupos de input (usuario y contraseña) */
.input-group, .password-input-group {
    position: relative;
    margin-bottom: 20px;
}

.input-group input, .password-input-group input {
    width: 100%;
    padding: 12px 12px 12px 40px;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 16px;
    outline: none;
    transition: 0.3s;
}

/* Posición de los iconos */
.input-group .fa-user, 
.password-input-group .fa-lock {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #a0aec0;
    transition: color 0.3s;
}

.password-input-group input {
    padding-right: 50px; /* Espacio para el icono de ver/ocultar */
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #a0aec0;
    transition: color 0.3s;
    font-size: 1.1rem;
    z-index: 10;
}

.toggle-password:hover {
    color: var(--text-color);
}

/* Focus en los campos */
.input-group input:focus, .password-input-group input:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 8px rgba(37, 117, 252, 0.3);
}

/* Botón de ingresar */
.login-container button {
    width: 100%;
    padding: 12px;
    background: var(--bg-gradient);
    background-size: 200% 200%;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    transition: background-position 0.5s ease, transform 0.3s ease, box-shadow 0.3s ease;
    letter-spacing: 1px;
}

.login-container button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--shadow-light);
    background-position: right center;
}

/* Mensaje de error */
.error {
    color: var(--error-color);
    margin-bottom: 15px;
    font-size: 14px;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="login-container">
    <div class="logo">
        <img src="logo.png" alt="Logo Aroma S.A.C"> 
    </div>
    <h1>Bienvenido a Aroma S.A.C</h1>

    <?php if($mensaje): ?>
        <div class="error"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="usuario" placeholder="Usuario" required>
        </div>
        <div class="password-input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="contrasena" id="password" placeholder="Contraseña" required>
            <span class="toggle-password" onclick="togglePasswordVisibility()">
                <i class="fas fa-eye-slash" id="toggleIcon"></i>
            </span>
        </div>
        <button type="submit">Ingresar</button>
    </form>
</div>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    }
}
</script>

</body>
</html>
