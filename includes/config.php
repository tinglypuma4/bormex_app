<?php
/**
 * Configuración de Base de Datos - Bormex (LOCAL)
 * Para desarrollo local con XAMPP
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de Base de Datos LOCAL
define('DB_HOST', 'localhost');
define('DB_NAME', 'bormex_db');        // Nombre de la BD local
define('DB_USER', 'root');             // Usuario por defecto de XAMPP
define('DB_PASS', '');                 // Sin contraseña en XAMPP por defecto
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'BORMEX');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/bormex_app/'); // URL local

// Configuración de seguridad
define('SECRET_KEY', 'desarrollo_local_2024_bormex_secret_key');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de errores (DESARROLLO)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Clase para conexión a base de datos - CHARSET UTF-8 CORREGIDO
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // SOLUCION CHARSET: Forzar UTF-8 para caracteres especiales
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            // Verificar la conexión y charset
            $this->connection->exec("SET NAMES utf8mb4");
            $this->connection->exec("SET CHARACTER SET utf8mb4");
            
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

/**
 * Funciones auxiliares
 */

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // UTF-8 explícito
    return $data;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function hasPermission($required_role) {
    if (!isLoggedIn()) return false;
    
    $roles = ['empleado' => 1, 'encargado' => 2, 'admin' => 3, 'super_admin' => 4];
    $user_level = $roles[$_SESSION['user_role']] ?? 0;
    $required_level = $roles[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8'); // UTF-8 explícito
    echo json_encode($data, JSON_UNESCAPED_UNICODE); // Preservar caracteres Unicode
    exit();
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function checkSessionTimeout() {
    if (isLoggedIn() && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            redirect('index.php?timeout=1');
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Funciones para generar URLs correctas - SOLUCION DE RUTAS
 */
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function asset($path = '') {
    return BASE_URL . 'public_html/assets/' . ltrim($path, '/');
}

function page_url($path = '') {
    return BASE_URL . 'public_html/pages/' . ltrim($path, '/');
}

// URLs específicas más utilizadas
function nueva_nota_url() {
    return page_url('notas/nueva.php');
}

function historial_url() {
    return page_url('notas/historial.php');
}

function clientes_url() {
    return page_url('clientes.php');
}

function empresa_url() {
    return page_url('empresa.php');
}

function usuarios_url() {
    return page_url('usuarios.php');
}

function dashboard_url() {
    return url('dashboard.php');
}

/**
 * Función para verificar y configurar charset de la base de datos
 */
function checkDatabaseCharset() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SHOW VARIABLES LIKE 'character_set_database'");
        $result = $stmt->fetch();
        
        if ($result && $result['Value'] !== 'utf8mb4') {
            error_log("ADVERTENCIA: La base de datos no está configurada con utf8mb4. Charset actual: " . $result['Value']);
            // Mostrar mensaje solo en desarrollo
            if (ini_get('display_errors')) {
                echo "<div style='background: #fff3cd; color: #856404; padding: 10px; margin: 10px; border: 1px solid #ffeaa7; border-radius: 4px;'>";
                echo "<strong>Advertencia:</strong> Para caracteres especiales, configura tu base de datos con charset utf8mb4.";
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        error_log("Error verificando charset: " . $e->getMessage());
    }
}

// Auto-check de timeout en cada página
if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
    checkSessionTimeout();
}

// Verificar charset de la BD (solo en desarrollo)
if (ini_get('display_errors')) {
    checkDatabaseCharset();
}
?>