<?php
/**
 * Clase de validación para inputs del sistema
 * Centraliza todas las validaciones para mantener consistencia
 */
class Validator {
    
    /**
     * Validar cédula colombiana
     * @param string $cedula
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarCedula($cedula) {
        // Limpiar espacios y caracteres no numéricos
        $cedula = preg_replace('/[^0-9]/', '', $cedula);
        
        if (empty($cedula)) {
            return ['valid' => false, 'message' => 'La cédula es requerida'];
        }
        
        if (strlen($cedula) < 6 || strlen($cedula) > 10) {
            return ['valid' => false, 'message' => 'La cédula debe tener entre 6 y 10 dígitos'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar email
     * @param string $email
     * @param bool $required
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarEmail($email, $required = false) {
        if (empty($email)) {
            if ($required) {
                return ['valid' => false, 'message' => 'El email es requerido'];
            }
            return ['valid' => true, 'message' => ''];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'El email no es válido'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar teléfono colombiano
     * @param string $telefono
     * @param bool $required
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarTelefono($telefono, $required = false) {
        // Limpiar espacios y caracteres no numéricos
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        if (empty($telefono)) {
            if ($required) {
                return ['valid' => false, 'message' => 'El teléfono es requerido'];
            }
            return ['valid' => true, 'message' => ''];
        }
        
        if (strlen($telefono) < 7 || strlen($telefono) > 10) {
            return ['valid' => false, 'message' => 'El teléfono debe tener entre 7 y 10 dígitos'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar contraseña
     * @param string $password
     * @param int $minLength
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarContrasena($password, $minLength = 6) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'La contraseña es requerida'];
        }
        
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'message' => "La contraseña debe tener al menos {$minLength} caracteres"];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar nombre de usuario
     * @param string $usuario
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarUsuario($usuario) {
        if (empty($usuario)) {
            return ['valid' => false, 'message' => 'El nombre de usuario es requerido'];
        }
        
        if (strlen($usuario) < 3) {
            return ['valid' => false, 'message' => 'El nombre de usuario debe tener al menos 3 caracteres'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $usuario)) {
            return ['valid' => false, 'message' => 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar nombre completo
     * @param string $nombre
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarNombre($nombre) {
        if (empty($nombre)) {
            return ['valid' => false, 'message' => 'El nombre es requerido'];
        }
        
        if (strlen($nombre) < 3) {
            return ['valid' => false, 'message' => 'El nombre debe tener al menos 3 caracteres'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar rol de usuario
     * @param string $rol
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarRol($rol) {
        $roles_validos = ['administrador', 'coordinador', 'asesor'];
        
        if (empty($rol)) {
            return ['valid' => false, 'message' => 'El rol es requerido'];
        }
        
        if (!in_array($rol, $roles_validos)) {
            return ['valid' => false, 'message' => 'El rol no es válido. Debe ser: ' . implode(', ', $roles_validos)];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar estado
     * @param string $estado
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarEstado($estado) {
        $estados_validos = ['activo', 'inactivo'];
        
        if (empty($estado)) {
            return ['valid' => false, 'message' => 'El estado es requerido'];
        }
        
        if (!in_array($estado, $estados_validos)) {
            return ['valid' => false, 'message' => 'El estado no es válido. Debe ser: activo o inactivo'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar fecha
     * @param string $fecha
     * @param bool $required
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarFecha($fecha, $required = false) {
        if (empty($fecha)) {
            if ($required) {
                return ['valid' => false, 'message' => 'La fecha es requerida'];
            }
            return ['valid' => true, 'message' => ''];
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return ['valid' => false, 'message' => 'La fecha no es válida. Formato requerido: YYYY-MM-DD'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar número entero positivo
     * @param mixed $numero
     * @param bool $required
     * @param int $min
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarNumeroPositivo($numero, $required = false, $min = 1) {
        if ($numero === '' || $numero === null) {
            if ($required) {
                return ['valid' => false, 'message' => 'El número es requerido'];
            }
            return ['valid' => true, 'message' => ''];
        }
        
        if (!is_numeric($numero)) {
            return ['valid' => false, 'message' => 'Debe ser un número válido'];
        }
        
        $numero = (int)$numero;
        if ($numero < $min) {
            return ['valid' => false, 'message' => "El número debe ser mayor o igual a {$min}"];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Sanitizar string (prevenir XSS)
     * @param string $string
     * @return string
     */
    public static function sanitizarString($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitizar array de strings
     * @param array $array
     * @return array
     */
    public static function sanitizarArray($array) {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::sanitizarArray($value);
            } else {
                $result[$key] = self::sanitizarString($value);
            }
        }
        return $result;
    }
    
    /**
     * Validar archivo CSV
     * @param array $file $_FILES['nombre']
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validarArchivoCSV($file) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => false, 'message' => 'No se ha seleccionado ningún archivo'];
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Error al subir el archivo'];
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt'])) {
            return ['valid' => false, 'message' => 'El archivo debe ser CSV'];
        }
        
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $maxMB = UPLOAD_MAX_SIZE / (1024 * 1024);
            return ['valid' => false, 'message' => "El archivo no debe superar {$maxMB}MB"];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Validar múltiples campos a la vez
     * @param array $validations Array de validaciones: ['campo' => ['validator' => 'metodo', 'value' => valor, 'required' => bool]]
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validarMultiple($validations) {
        $errors = [];
        
        foreach ($validations as $campo => $config) {
            $metodo = $config['validator'];
            $valor = $config['value'];
            $required = $config['required'] ?? false;
            
            if (method_exists(self::class, $metodo)) {
                $resultado = self::$metodo($valor, $required);
                if (!$resultado['valid']) {
                    $errors[$campo] = $resultado['message'];
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>

