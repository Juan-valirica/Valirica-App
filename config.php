<?php
// ==============================
// CONFIG GLOBAL VALÍRICA
// ==============================

// Mostrar errores (solo en entorno desarrollo)
// En producción puedes cambiar display_errors a 0
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ------------------------------
// Sesión segura
// ------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------
// Conexión base de datos
// ------------------------------
$servername = "localhost";
$username   = "mevytjyn_webapp_user";
$password   = "xydqe7-rycsux-jyBmoq";
$database   = "mevytjyn_webapp";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// ------------------------------
// Charset seguro
// ------------------------------
if ($conn) {
    $conn->set_charset("utf8mb4");
    $conn->query("SET NAMES utf8mb4");
    $conn->query("SET CHARACTER SET utf8mb4");
    $conn->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");
}

// ------------------------------
// CSRF
// ------------------------------
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generar token automáticamente
getCsrfToken();

// ------------------------------
// Variables globales
// ------------------------------
$destinatario_confidencial = 'denuncias@valirica.com';

// ------------------------------
// Clave de firma interna
// ------------------------------
if (!defined('APP_SIGN_KEY')) {
    define(
        'APP_SIGN_KEY',
        'coloca_aqui_una_clave_secreta_larga_y_unica_de_64_+_caracteres'
    );
}
