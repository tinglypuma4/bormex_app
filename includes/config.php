<?php
/**
 * Configuración de Base de Datos - Bormex (LOCAL) - RUTAS CORREGIDAS
 */

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuración de Base de Datos LOCAL
define('DB_HOST', 'localhost');
define('DB_NAME', 'bormex_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación - RUTAS CORREGIDAS
define('APP_NAME', 'BORMEX');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/bormex_app/'); // Ruta relativa desde localhost

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
 * Clase para conexión a base de datos
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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
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
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
 * FUNCIONES DE RUTAS CORREGIDAS - SEGÚN TU ESTRUCTURA REAL
 */

// Función base para URLs
function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// ASSETS: CSS, JS, imágenes (desde la raíz)
function asset($path = '') {
    // Los assets están en public_html/assets/ desde la raíz
    return BASE_URL . 'public_html/assets/' . ltrim($path, '/');
}

// PÁGINAS: Para archivos en public_html/pages/
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
    // Dashboard está en la raíz
    return url('dashboard.php');
}

/**
 * Función para debugging de rutas (SOLO EN DESARROLLO)
 */
function debug_routes() {
    // Solo generar debug HTML si NO es una solicitud AJAX
    if (ini_get('display_errors') && !isAjaxRequest()) {
        echo "<!-- DEBUG RUTAS:\n";
        echo "BASE_URL: " . BASE_URL . "\n";
        echo "asset('css/style.css'): " . asset('css/style.css') . "\n";
        echo "dashboard_url(): " . dashboard_url() . "\n";
        echo "nueva_nota_url(): " . nueva_nota_url() . "\n";
        echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
        echo "-->\n";
    }
}

/**
 * Función para verificar si los archivos CSS existen
 */
function check_css_files() {
    // Solo generar debug HTML si NO es una solicitud AJAX
    if (ini_get('display_errors') && !isAjaxRequest()) {
        $css_files = [
            'style.css' => $_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'public_html/assets/css/style.css',
            'layout.css' => $_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'public_html/assets/css/layout.css',
            'dashboard.css' => $_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'public_html/assets/css/dashboard.css'
        ];
        
        echo "<!-- CSS FILES CHECK:\n";
        foreach ($css_files as $name => $path) {
            $exists = file_exists($path);
            $url = asset('css/' . $name);
            echo "$name: " . ($exists ? 'EXISTS' : 'MISSING') . " - Path: $path - URL: $url\n";
        }
        echo "-->\n";
    }
}

/**
 * Detectar si es una solicitud AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
           isset($_POST['action']); // También detectar por POST action (nuestro caso)
}

// Auto-check de timeout
if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
    checkSessionTimeout();
}

// Debug en desarrollo (solo si no es AJAX)
if (ini_get('display_errors') && !isAjaxRequest()) {
    debug_routes();
    check_css_files();
}
?>