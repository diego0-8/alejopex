<?php
require_once 'config.php';
require_once 'models/Usuario.php';

/**
 * Controlador de Autenticación
 * Maneja el login, logout y sesiones de usuario
 */
class AuthController {
    private $usuario;

    public function __construct() {
        $this->usuario = new Usuario();
    }

    /**
     * Procesar el login del usuario
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            // Validar datos de entrada
            if (empty($usuario) || empty($contrasena)) {
                $this->mostrarError('Por favor, completa todos los campos.');
                return;
            }

            // Intentar autenticar
            $usuario_data = $this->usuario->autenticar($usuario, $contrasena);

            if ($usuario_data) {
                // Guardar datos del usuario en la sesión
                $_SESSION['usuario_id'] = $usuario_data['cedula'];
                $_SESSION['usuario_nombre'] = $usuario_data['nombre_completo'];
                $_SESSION['usuario_usuario'] = $usuario_data['usuario'];
                $_SESSION['usuario_rol'] = $usuario_data['rol'];
                $_SESSION['usuario_estado'] = $usuario_data['estado'];
                $_SESSION['logged_in'] = true;

                // Redirigir según el rol
                $this->redirigirSegunRol($usuario_data['rol']);
            } else {
                $this->mostrarError('Usuario o contraseña incorrectos.');
            }
        } else {
            // Mostrar formulario de login
            $this->mostrarLogin();
        }
    }

    /**
     * Cerrar sesión del usuario
     */
    public function logout() {
        // Destruir todas las variables de sesión
        $_SESSION = array();

        // Destruir la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destruir la sesión
        session_destroy();

        // Redirigir al login
        header('Location: index.php?action=login');
        exit();
    }

    /**
     * Verificar si el usuario está autenticado
     * @return bool
     */
    public function estaAutenticado() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Verificar si el usuario tiene un rol específico
     * @param string $rol
     * @return bool
     */
    public function tieneRol($rol) {
        return $this->estaAutenticado() && $_SESSION['usuario_rol'] === $rol;
    }

    /**
     * Obtener datos del usuario actual
     * @return array|null
     */
    public function obtenerUsuarioActual() {
        if ($this->estaAutenticado()) {
            return [
                'cedula' => $_SESSION['usuario_id'],
                'nombre_completo' => $_SESSION['usuario_nombre'],
                'usuario' => $_SESSION['usuario_usuario'],
                'rol' => $_SESSION['usuario_rol'],
                'estado' => $_SESSION['usuario_estado']
            ];
        }
        return null;
    }

    /**
     * Requerir autenticación
     * Redirige al login si no está autenticado
     */
    public function requerirAutenticacion() {
        if (!$this->estaAutenticado()) {
            header('Location: index.php?action=login');
            exit();
        }
    }

    /**
     * Requerir rol específico
     * @param string $rol
     */
    public function requerirRol($rol) {
        $this->requerirAutenticacion();
        
        if (!$this->tieneRol($rol)) {
            $this->mostrarError('No tienes permisos para acceder a esta sección.');
            exit();
        }
    }

    /**
     * Redirigir según el rol del usuario
     * @param string $rol
     */
    private function redirigirSegunRol($rol) {
        switch ($rol) {
            case 'administrador':
                header('Location: index.php?action=dashboard');
                break;
            case 'coordinador':
                header('Location: index.php?action=coordinador_dashboard');
                break;
            case 'asesor':
                header('Location: index.php?action=asesor_dashboard');
                break;
            default:
                header('Location: index.php?action=login');
                break;
        }
        exit();
    }

    /**
     * Mostrar formulario de login
     */
    private function mostrarLogin() {
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        
        include 'views/login.php';
    }

    /**
     * Mostrar error y redirigir al login
     * @param string $mensaje
     */
    private function mostrarError($mensaje) {
        $_SESSION['error'] = $mensaje;
        header('Location: index.php?action=login');
        exit();
    }

    /**
     * Cambiar contraseña del usuario actual
     * @param string $contrasena_actual
     * @param string $nueva_contrasena
     * @return bool
     */
    public function cambiarContrasena($contrasena_actual, $nueva_contrasena) {
        if (!$this->estaAutenticado()) {
            return false;
        }

        $usuario_data = $this->usuario->obtenerPorCedula($_SESSION['usuario_id']);
        
        if ($usuario_data && password_verify($contrasena_actual, $usuario_data['contrasena'])) {
            return $this->usuario->actualizar(
                $_SESSION['usuario_id'],
                $usuario_data['nombre_completo'],
                $usuario_data['usuario'],
                $nueva_contrasena,
                $usuario_data['rol'],
                $usuario_data['estado']
            );
        }
        
        return false;
    }
}
?>
