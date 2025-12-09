<?php
/**
 * Helper para incluir el navbar de forma unificada
 * Evita duplicación de lógica de inclusión condicional
 */

/**
 * Incluye el navbar si no estamos en modo iframe
 * 
 * @param string $action La acción actual para resaltar el item activo en el navbar
 * @return void
 */
function incluirNavbarSiNoEsIframe($action = '') {
    // NO incluir navbar cuando estamos en modo iframe (el layout principal ya lo tiene)
    // El navbar solo debe estar en el layout principal, no en el iframe
    if (!isset($_GET['iframe']) || $_GET['iframe'] != '1') {
        // Establecer la acción para que el navbar sepa qué item resaltar
        // La variable $action será accesible en el scope del include
        if (empty($action) && isset($GLOBALS['action'])) {
            $action = $GLOBALS['action'];
        }
        // Incluir el navbar (tendrá acceso a $action como variable local)
        include __DIR__ . '/../Navbar.php';
    }
}

/**
 * Incluye el navbar siempre (para layouts principales como layout_asesor.php)
 * 
 * @param string $action La acción actual para resaltar el item activo en el navbar
 * @return void
 */
function incluirNavbar($action = '') {
    // Establecer la acción para que el navbar sepa qué item resaltar
    // La variable $action será accesible en el scope del include
    if (empty($action) && isset($GLOBALS['action'])) {
        $action = $GLOBALS['action'];
    }
    // Incluir el navbar (tendrá acceso a $action como variable local)
    include __DIR__ . '/../Navbar.php';
}

