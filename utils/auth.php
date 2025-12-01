<?php
/**
 * Funciones de autenticación del sistema IPS
 * Contiene funciones relacionadas con autenticación y autorización
 */

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Función para verificar rol de usuario
function hasRole($role) {
    return isAuthenticated() && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === $role;
}

// Función para obtener datos del usuario actual
function getCurrentUser() {
    if (isAuthenticated()) {
        return [
            'cedula' => $_SESSION['usuario_id'] ?? null,
            'nombre_completo' => $_SESSION['usuario_nombre'] ?? null,
            'usuario' => $_SESSION['usuario_usuario'] ?? null,
            'rol' => $_SESSION['usuario_rol'] ?? null,
            'estado' => $_SESSION['usuario_estado'] ?? null
        ];
    }
    return null;
}

// Función para requerir autenticación
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: index.php?action=login');
        exit();
    }
}

// Función para requerir rol específico
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        die('No tienes permisos para acceder a esta sección.');
    }
}
?>
