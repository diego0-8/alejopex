<?php
/**
 * Funciones de utilidad del sistema IPS
 * Contiene funciones generales de utilidad
 */

// Función para validar datos de entrada
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Función para generar hash de contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Función para verificar contraseña
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para generar token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para mostrar mensajes de error
function showError($message) {
    return "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> " . htmlspecialchars($message) . "</div>";
}

// Función para mostrar mensajes de éxito
function showSuccess($message) {
    return "<div class='alert alert-success'><i class='fas fa-check-circle'></i> " . htmlspecialchars($message) . "</div>";
}

// Función para redirigir con mensaje
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

// Función para mostrar mensajes flash
function showFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        $icon = 'info-circle';
        switch ($type) {
            case 'success':
                $icon = 'check-circle';
                break;
            case 'error':
                $icon = 'exclamation-triangle';
                break;
            case 'warning':
                $icon = 'exclamation-circle';
                break;
        }
        
        return "<div class='alert alert-$type'><i class='fas fa-$icon'></i> " . htmlspecialchars($message) . "</div>";
    }
    return '';
}
?>
