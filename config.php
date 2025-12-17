<?php
/**
 * Configuración del Sistema IPS CRM
 * Archivo de configuración principal
 */

// Cargar configuración de optimización si existe
if (file_exists(__DIR__ . '/config_optimizacion.php')) {
    require_once __DIR__ . '/config_optimizacion.php';
}

// Configuración de sesión única para este proyecto
session_name('APEX7_SID');
session_start();

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de la aplicación
define('APP_NAME', 'IPS CRM');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/APEX7');

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'apex2');
define('DB_CHARSET', 'utf8mb4');

// Configuración de seguridad
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);

// Configuración de archivos
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['xlsx', 'xls', 'csv']);

// Configuración de roles
define('ROLES', [
    'administrador' => 'Administrador',
    'coordinador' => 'Coordinador',
    'asesor' => 'Asesor'
]);

// Configuración de estados
define('ESTADOS', [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo'
]);

/**
 * Función para obtener la conexión a la base de datos
 * @return PDO
 */
function getDBConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $connection = new PDO($dsn, DB_USER, DB_PASS);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Asegurar que la conexión use UTF-8
            $connection->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            $connection->exec("SET CHARACTER SET utf8mb4");
        } catch(PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor, contacta al administrador.");
        }
    }
    
    return $connection;
}
?>
