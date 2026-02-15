<?php
// ==============================
// CONFIG GLOBAL VALÍRICA
// ==============================

// Producción: errores al log, no al navegador
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ------------------------------
// Verificación de extensiones críticas
// ------------------------------
if (!function_exists('json_encode')) {
    http_response_code(500);
    die('Error: La extensión PHP "json" no está habilitada. Contacta a tu proveedor de hosting.');
}

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

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        error_log("DB connect error: " . $conn->connect_error);
        http_response_code(500);
        die("Error de conexión a la base de datos. Intenta de nuevo más tarde.");
    }
} catch (Exception $e) {
    error_log("DB exception: " . $e->getMessage());
    http_response_code(500);
    die("Error de conexión a la base de datos. Intenta de nuevo más tarde.");
}

// ------------------------------
// Charset seguro
// ------------------------------
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");
$conn->query("SET CHARACTER SET utf8mb4");
$conn->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");

// ------------------------------
// Polyfill: stmt_get_result()
// Reemplaza $stmt->get_result() que requiere mysqlnd.
// Usar como: stmt_get_result($stmt) en lugar de $stmt->get_result()
// ------------------------------
function stmt_get_result(mysqli_stmt $stmt) {
    // Si mysqlnd está disponible, usar el método nativo
    if (method_exists($stmt, 'get_result')) {
        return $stmt->get_result();
    }

    // Polyfill: usar bind_result + result_metadata
    $stmt->store_result();
    $meta = $stmt->result_metadata();

    if (!$meta) {
        return false;
    }

    $fields = [];
    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
    }
    $meta->free();

    $row = array_fill_keys($fields, null);
    $refs = [];
    foreach ($fields as $name) {
        $refs[] = &$row[$name];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }

    return new class($rows) {
        public int $num_rows;
        private array $rows;
        private int $pos = 0;

        public function __construct(array $rows) {
            $this->rows = $rows;
            $this->num_rows = count($rows);
        }

        public function fetch_assoc(): ?array {
            if ($this->pos < $this->num_rows) {
                return $this->rows[$this->pos++];
            }
            return null;
        }

        public function fetch_all(int $mode = MYSQLI_ASSOC): array {
            return $this->rows;
        }

        public function free(): void {
            $this->rows = [];
        }
    };
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
