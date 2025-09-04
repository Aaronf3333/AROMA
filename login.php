?php

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

        if ($contrasena === $row['contrasena']) { // En producción usar hash

            $_SESSION['idUsuario'] = $row['idUsuario'];

            $_SESSION['usuario'] = $row['usuario'];

            $_SESSION['rol'] = $row['nombreRol'];

            $_SESSION['nombres'] = $row['nombres'];

            $_SESSION['apellidos'] = $row['apellidos'];



            header("Location: index.php");

            exit();

        } else {

            $mensaje = "Contraseña incorrecta.";

        }

    } else {

        $mensaje = "Usuario no encontrado.";

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

<style>

:root {

    --primary-color: #6a11cb;

    --secondary-color: #2575fc;

    --text-color: #2c3e50;

    --error-color: #e74c3c;

    --shadow-light: rgba(0, 0, 0, 0.1);

    --shadow-heavy: rgba(0, 0, 0, 0.25);

}



* {

    box-sizing: border-box;

    margin: 0;

    padding: 0;

}



body {

    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;

    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));

    display: flex;

    justify-content: center;

    align-items: center;

    min-height: 100vh;

}



.login-container {

    background: rgba(255, 255, 255, 0.95);

    padding: 40px 30px;

    border-radius: 20px;

    box-shadow: 0 8px 25px var(--shadow-heavy);

    width: 90%;

    max-width: 400px;

    text-align: center;

    backdrop-filter: blur(5px);

}



/* --- LOGO CIRCULAR (IMAGEN) --- */

.logo img {

    width: 100px; /* Tamaño del logo */

    height: 100px; /* Tamaño del logo */

    border-radius: 50%; /* Esto hace la imagen circular */

    object-fit: cover; /* Asegura que la imagen cubra el círculo sin distorsionarse */

    margin-bottom: 20px;

    box-shadow: 0 4px 15px var(--shadow-light);

}



h1 {

    margin-bottom: 20px;

    font-size: 28px;

    color: var(--text-color);

    font-weight: bold;

}



.input-group {

    position: relative;

    margin-bottom: 20px;

}



/* Posiciona el icono de usuario */

.input-group .fa-user {

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    color: #a0aec0;

    transition: color 0.3s;

}



.input-group input {

    width: 100%;

    padding: 12px 12px 12px 40px; /* Espacio para el icono de la izquierda */

    border-radius: 10px;

    border: 1px solid #ccc;

    font-size: 16px;

    outline: none;

    transition: 0.3s;

}



.input-group input:focus {

    border-color: var(--secondary-color);

    box-shadow: 0 0 8px rgba(37, 117, 252, 0.3);

}



/* --- ESTILOS PARA EL CAMPO DE CONTRASEÑA CON DOS ÍCONOS --- */

.password-input-group {

    position: relative;

    width: 100%;

    margin-bottom: 20px;

}



.password-input-group .fa-lock { /* Posiciona el icono de candado */

    position: absolute;

    left: 15px;

    top: 50%;

    transform: translateY(-50%);

    color: #a0aec0;

    transition: color 0.3s;

}



.password-input-group input {

    width: 100%;

    padding: 12px 50px 12px 40px; /* Espacio a ambos lados */

    border-radius: 10px;

    border: 1px solid #ccc;

    font-size: 16px;

    outline: none;

    transition: 0.3s;

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



.login-container button {

    width: 100%;

    padding: 12px;

    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));

    color: white;

    border: none;

    border-radius: 10px;

    cursor: pointer;

    font-size: 16px;

    font-weight: bold;

    transition: 0.3s;

    letter-spacing: 1px;

}



.login-container button:hover {

    transform: scale(1.03);

    box-shadow: 0 5px 15px var(--shadow-light);

}



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
