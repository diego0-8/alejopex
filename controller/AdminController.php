<?php
require_once 'config.php';
require_once 'models/Usuario.php';
require_once 'models/Asignacion.php';

/**
 * Controlador de Administrador
 * Maneja todas las operaciones específicas del rol administrador
 */
class AdminController {
    private $usuario_model;
    private $asignacion_model;

    public function __construct() {
        $this->usuario_model = new Usuario();
        $this->asignacion_model = new Asignacion();
    }

    /**
     * Obtener estadísticas generales del sistema
     * @return array
     */
    public function obtenerEstadisticas() {
        $conn = getDBConnection();
        $usuarios = [];
        $asignaciones_stats = [];
        $total_clientes = 0;
        $clientes_nuevos = 0;
        $total_contratos = 0;
        $total_cartera = 0;
        $clientes_gestionados = 0;
        $tareas_stats = ['tareas_realizadas' => 0, 'tareas_pendientes' => 0];
        $total_bases = 0;
        
        try {
            $usuarios = $this->usuario_model->obtenerTodos();
            $asignaciones_stats = $this->asignacion_model->obtenerEstadisticas();
        } catch (Exception $e) {
            error_log("Error al obtener usuarios/asignaciones: " . $e->getMessage());
        }
        
        // Obtener asesores sin coordinador - IMPORTANTE: obtenerlo ANTES de otras consultas que puedan fallar
        $asesores_sin_coordinador = [];
        try {
            $asesores_sin_coordinador = $this->asignacion_model->obtenerAsesoresSinAsignacion();
        } catch (Exception $e) {
            error_log("Error al obtener asesores sin coordinador: " . $e->getMessage());
        }
        
        // Obtener estadísticas de clientes (manejar errores individualmente)
        try {
            $stmt_clientes = $conn->query("SELECT COUNT(*) as total FROM clientes");
            $total_clientes = $stmt_clientes->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error al obtener total clientes: " . $e->getMessage());
        }
        
        try {
            $stmt_clientes_nuevos = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $clientes_nuevos = $stmt_clientes_nuevos->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error al obtener clientes nuevos: " . $e->getMessage());
        }
        
        // Obtener total de contratos
        try {
            $stmt_contratos = $conn->query("SELECT COUNT(*) as total FROM contratos");
            $total_contratos = $stmt_contratos->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error al obtener total contratos: " . $e->getMessage());
        }
        
        try {
            $stmt_cartera = $conn->query("SELECT SUM(`TOTAL CARTERA`) as total FROM contratos");
            $total_cartera = $stmt_cartera->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al obtener total cartera: " . $e->getMessage());
        }
        
        // Obtener clientes gestionados
        try {
            $stmt_gestionados = $conn->query("SELECT COUNT(DISTINCT cliente_id) as total FROM gestiones");
            $clientes_gestionados = $stmt_gestionados->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error al obtener clientes gestionados: " . $e->getMessage());
        }
        
        // Clientes pendientes = total - gestionados
        $clientes_pendientes = $total_clientes - $clientes_gestionados;
        
        // Obtener tareas realizadas y pendientes
        try {
            $stmt_tareas = $conn->query("SELECT 
                COUNT(*) as total_tareas,
                SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as tareas_realizadas,
                SUM(CASE WHEN estado != 'completada' THEN 1 ELSE 0 END) as tareas_pendientes
                FROM asignaciones_asesores");
            $tareas_stats = $stmt_tareas->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas de tareas: " . $e->getMessage());
        }
        
        // Obtener bases de comercios creadas
        try {
            $stmt_bases = $conn->query("SELECT COUNT(*) as total FROM bases_comercios WHERE estado = 'activo'");
            $total_bases = $stmt_bases->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (Exception $e) {
            error_log("Error al obtener total bases: " . $e->getMessage());
        }
        
        // Calcular eficiencia (porcentaje de clientes gestionados)
        $eficiencia = $total_clientes > 0 ? round(($clientes_gestionados / $total_clientes) * 100, 1) : 0;
        
        return [
            'total_usuarios' => count($usuarios),
            'usuarios_activos' => count(array_filter($usuarios, function($u) { return $u['estado'] === 'activo'; })),
            'total_coordinadores' => count(array_filter($usuarios, function($u) { return $u['rol'] === 'coordinador'; })),
            'coordinadores_disponibles' => count(array_filter($usuarios, function($u) { return $u['rol'] === 'coordinador' && $u['estado'] === 'activo'; })),
            'total_asesores' => count(array_filter($usuarios, function($u) { return $u['rol'] === 'asesor'; })),
            'asesores_asignados' => $asignaciones_stats['asesores_asignados'] ?? 0,
            'asesores_sin_coordinador' => $asesores_sin_coordinador, // SIEMPRE presente
            'total_clientes' => $total_clientes,
            'clientes_nuevos' => $clientes_nuevos,
            'clientes_gestionados' => $clientes_gestionados,
            'clientes_pendientes' => $clientes_pendientes,
            'total_contratos' => $total_contratos,
            'total_cartera' => $total_cartera,
            'total_bases' => $total_bases,
            'tareas_realizadas' => $tareas_stats['tareas_realizadas'] ?? 0,
            'tareas_pendientes' => $tareas_stats['tareas_pendientes'] ?? 0,
            'eficiencia' => $eficiencia,
            'actividad_reciente' => []
        ];
    }

    /**
     * Obtener todos los usuarios del sistema
     * @return array
     */
    public function obtenerUsuarios() {
        return $this->usuario_model->obtenerTodos();
    }

    /**
     * Obtener todos los coordinadores
     * @return array
     */
    public function obtenerCoordinadores() {
        return $this->asignacion_model->obtenerCoordinadores();
    }

    /**
     * Obtener todas las asignaciones
     * @return array
     */
    public function obtenerAsignaciones() {
        return $this->asignacion_model->obtenerTodas();
    }

    /**
     * Crear un nuevo usuario
     * @param array $datos_usuario
     * @return array
     */
    public function crearUsuario($datos_usuario) {
        // Validar datos requeridos
        $campos_requeridos = ['cedula', 'nombre_completo', 'usuario', 'contrasena', 'rol', 'estado'];
        foreach ($campos_requeridos as $campo) {
            if (empty($datos_usuario[$campo])) {
                return ['success' => false, 'message' => "El campo {$campo} es requerido"];
            }
        }

        // Verificar si el usuario ya existe
        if ($this->usuario_model->existeUsuario($datos_usuario['usuario'])) {
            return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
        }

        if ($this->usuario_model->existeCedula($datos_usuario['cedula'])) {
            return ['success' => false, 'message' => 'La cédula ya está registrada'];
        }

        // Asignar datos al modelo
        $this->usuario_model->cedula = $datos_usuario['cedula'];
        $this->usuario_model->nombre_completo = $datos_usuario['nombre_completo'];
        $this->usuario_model->usuario = $datos_usuario['usuario'];
        $this->usuario_model->contrasena = $datos_usuario['contrasena'];
        $this->usuario_model->rol = $datos_usuario['rol'];
        $this->usuario_model->estado = $datos_usuario['estado'];
        // Campos opcionales para WebRTC Softphone (solo para asesores)
        $this->usuario_model->extension = !empty($datos_usuario['extension']) ? $datos_usuario['extension'] : null;
        $this->usuario_model->sip_password = !empty($datos_usuario['sip_password']) ? $datos_usuario['sip_password'] : null;

        // Crear el usuario
        if ($this->usuario_model->crear()) {
            return ['success' => true, 'message' => 'Usuario creado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al crear el usuario'];
        }
    }

    /**
     * Actualizar un usuario existente
     * @param array $datos_usuario
     * @return array
     */
    public function actualizarUsuario($datos_usuario) {
        // Validar datos requeridos
        $campos_requeridos = ['cedula', 'nombre_completo', 'usuario', 'rol', 'estado'];
        foreach ($campos_requeridos as $campo) {
            if (empty($datos_usuario[$campo])) {
                return ['success' => false, 'message' => "El campo {$campo} es requerido"];
            }
        }

        // Verificar si el usuario existe
        $usuario_existente = $this->usuario_model->obtenerPorCedula($datos_usuario['cedula']);
        if (!$usuario_existente) {
            return ['success' => false, 'message' => 'El usuario no existe'];
        }

        // Verificar si el nombre de usuario ya existe (excluyendo el usuario actual)
        if ($this->usuario_model->existeUsuario($datos_usuario['usuario'], $datos_usuario['cedula'])) {
            return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
        }

        // Actualizar el usuario
        $resultado = $this->usuario_model->actualizar(
            $datos_usuario['cedula'],
            $datos_usuario['nombre_completo'],
            $datos_usuario['usuario'],
            $datos_usuario['contrasena'] ?? null, // Contraseña opcional
            $datos_usuario['rol'],
            $datos_usuario['estado'],
            $datos_usuario['extension'] ?? null, // Extensión SIP opcional
            $datos_usuario['sip_password'] ?? null // Contraseña SIP opcional
        );

        if ($resultado) {
            return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar el usuario'];
        }
    }

    /**
     * Obtener usuario por cédula (incluyendo extension y sip_password)
     * @param string $cedula
     * @return array
     */
    public function obtenerUsuarioPorCedula($cedula) {
        $usuario = $this->usuario_model->obtenerPorCedula($cedula);
        
        if ($usuario) {
            return [
                'success' => true,
                'usuario' => $usuario
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado'
            ];
        }
    }

    /**
     * Cambiar estado de un usuario
     * @param string $cedula
     * @param string $nuevo_estado
     * @return array
     */
    public function cambiarEstadoUsuario($cedula, $nuevo_estado) {
        $resultado = $this->usuario_model->cambiarEstado($cedula, $nuevo_estado);
        if (is_array($resultado)) {
            return $resultado;
        } else {
            return ['success' => false, 'message' => 'Error al cambiar el estado del usuario'];
        }
    }

    /**
     * Eliminar un usuario
     * @param string $cedula
     * @return array
     */
    public function eliminarUsuario($cedula) {
        $resultado = $this->usuario_model->eliminar($cedula);
        if (is_array($resultado)) {
            return $resultado;
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el usuario'];
        }
    }

    /**
     * Asignar personal (asesor a coordinador)
     * @param array $datos_asignacion
     * @return array
     */
    public function asignarPersonal($datos_asignacion) {
        // Validar datos requeridos
        $campos_requeridos = ['asesor_cedula', 'coordinador_cedula', 'creado_por'];
        foreach ($campos_requeridos as $campo) {
            if (empty($datos_asignacion[$campo])) {
                return ['success' => false, 'message' => "El campo {$campo} es requerido"];
            }
        }

        // Crear la asignación
        $resultado = $this->asignacion_model->crear(
            $datos_asignacion['asesor_cedula'],
            $datos_asignacion['coordinador_cedula'],
            $datos_asignacion['creado_por'],
            $datos_asignacion['notas'] ?? ''
        );

        if (is_array($resultado)) {
            return $resultado;
        } else {
            return ['success' => false, 'message' => 'Error al crear la asignación'];
        }
    }

    /**
     * Obtener lista de asesores
     * @return array
     */
    public function obtenerAsesores() {
        try {
            $usuario_model = new Usuario();
            $asesores = $usuario_model->obtenerPorRol('asesor');
            
            return [
                'success' => true,
                'asesores' => $asesores
            ];
        } catch (Exception $e) {
            error_log("Error al obtener asesores: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener asesores: ' . $e->getMessage(),
                'asesores' => []
            ];
        }
    }

    /**
     * Liberar una asignación de asesor
     * @param int $asignacion_id
     * @return array
     */
    public function liberarAsignacion($asignacion_id) {
        try {
            $resultado = $this->asignacion_model->eliminar($asignacion_id);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Asesor liberado exitosamente. Ahora está disponible para ser asignado a otro coordinador.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al liberar el asesor. Intente nuevamente.'
                ];
            }
        } catch (Exception $e) {
            error_log("Error al liberar asignación: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al liberar el asesor: ' . $e->getMessage()
            ];
        }
    }
}
?>
