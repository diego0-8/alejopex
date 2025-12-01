<?php
/**
 * Funciones auxiliares del sistema IPS
 * Contiene funciones de formato, URL y vista
 */

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Función para formatear número
function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, ',', '.');
}

// Función para generar URL
function url($action = '', $params = []) {
    $url = APP_URL . '/index.php';
    if ($action) {
        $url .= '?action=' . $action;
    }
    if (!empty($params)) {
        $separator = $action ? '&' : '?';
        $url .= $separator . http_build_query($params);
    }
    return $url;
}

// Función para incluir vista
function view($view, $data = []) {
    extract($data);
    $viewFile = "views/$view.php";
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        die("Vista no encontrada: $view");
    }
}
?>
