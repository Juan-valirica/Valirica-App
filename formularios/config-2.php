<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$servername = "localhost";
$username = "mevytjyn_webapp_user"; 
$password = "xydqe7-rycsux-jyBmoq";  
$database = "mevytjyn_webapp";

// **Conexión con la BD**
$conn = new mysqli($servername, $username, $password, $database);

// **Verificar si hay error de conexión**
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// **Configurar codificación para evitar problemas de tildes y caracteres especiales**
$conn->set_charset("utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET utf8mb4");
mysqli_query($conn, "SET SESSION collation_connection = 'utf8mb4_unicode_ci'");

// Crear las carpetas necesarias si no existen
$folders = ["uploads", "uploads/logos", "uploads/analisis_proposito", "uploads/analisis_valores"];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }
}

// **Función para generar y obtener el token CSRF**
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// **Función para verificar el token CSRF**
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

// Generar token al cargar el archivo config
getCsrfToken(); // Esto asegura que el token siempre está disponible para cada sesión

$destinatario_confidencial = 'denuncias@valirica.com';



// **Verificar conexión con la BD**
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}
?>
