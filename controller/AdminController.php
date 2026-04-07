<?php
require_once 'config.php';
require_once 'models/Usuario.php';
require_once 'models/Asignacion.php';
require_once 'models/Gestion.php';

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
     * Obtener bases activas para selección de importación.
     * @return array
     */
    public function obtenerBasesActivas() {
        try {
            $conn = getDBConnection();
            $query = "SELECT b.id,
                             b.nombre,
                             b.fecha_creacion,
                             COUNT(DISTINCT c.id) AS total_clientes
                      FROM bases_clientes b
                      LEFT JOIN clientes c ON c.base_id = b.id
                      WHERE b.estado = 'activo'
                      GROUP BY b.id, b.nombre, b.fecha_creacion
                      ORDER BY b.fecha_creacion DESC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener bases activas para admin_reportes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Importar gestiones desde un CSV para una base activa seleccionada.
     * @param mixed $baseId
     * @param array|null $uploadedFile
     * @return array
     */
    public function importarGestionesCSV($baseId, $uploadedFile) {
        $baseId = (int) $baseId;

        if ($baseId <= 0) {
            return [
                'success' => false,
                'message' => 'Debe seleccionar una base activa.'
            ];
        }

        $base = $this->obtenerBaseActivaPorId($baseId);
        if (!$base) {
            return [
                'success' => false,
                'message' => 'La base seleccionada no existe o no está activa.'
            ];
        }

        if (!$uploadedFile || !isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            return [
                'success' => false,
                'message' => 'Debe adjuntar un archivo CSV válido.'
            ];
        }

        $delimiter = $this->detectarDelimitadorCSV($uploadedFile['tmp_name']);
        $handle = fopen($uploadedFile['tmp_name'], 'r');
        if ($handle === false) {
            return [
                'success' => false,
                'message' => 'No fue posible abrir el archivo CSV.'
            ];
        }

        try {
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false || empty($headers)) {
                fclose($handle);
                return [
                    'success' => false,
                    'message' => 'El archivo CSV no tiene encabezados válidos.'
                ];
            }

            $headers = array_map([$this, 'normalizarTextoCSV'], $headers);
            $normalizedHeaders = array_map([$this, 'normalizarEncabezadoCSV'], $headers);
            $requiredHeaders = ['fecha_gestion', 'asesor', 'cc'];
            foreach ($requiredHeaders as $requiredHeader) {
                if (!in_array($requiredHeader, $normalizedHeaders, true) &&
                    !($requiredHeader === 'fecha_gestion' && in_array('fecha_y_hora', $normalizedHeaders, true))) {
                    fclose($handle);
                    return [
                        'success' => false,
                        'message' => "Falta la columna obligatoria '{$requiredHeader}' en el CSV."
                    ];
                }
            }

            $resultado = [
                'success' => true,
                'message' => 'Importación finalizada.',
                'base' => [
                    'id' => $base['id'],
                    'nombre' => $base['nombre']
                ],
                'total_filas' => 0,
                'filas_vacias' => 0,
                'insertadas' => 0,
                'omitidas' => 0,
                'errores' => [],
                'ids_insertados' => []
            ];

            $numeroFila = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $numeroFila++;

                if ($this->filaCSVVacia($row)) {
                    $resultado['filas_vacias']++;
                    continue;
                }

                $resultado['total_filas']++;
                $row = array_pad($row, count($normalizedHeaders), '');
                $row = array_map([$this, 'normalizarTextoCSV'], array_slice($row, 0, count($normalizedHeaders)));
                $filaAsociativa = array_combine($normalizedHeaders, array_slice($row, 0, count($normalizedHeaders)));
                $filaMapeada = $this->mapearFilaImportacion($filaAsociativa);
                $filaResultado = $this->procesarFilaImportacionGestion($baseId, $filaMapeada, $numeroFila);

                if ($filaResultado['success']) {
                    $resultado['insertadas']++;
                    if (!empty($filaResultado['id'])) {
                        $resultado['ids_insertados'][] = $filaResultado['id'];
                    }
                    continue;
                }

                $resultado['omitidas']++;
                $resultado['errores'][] = [
                    'fila' => $numeroFila,
                    'cedula_cliente' => $filaMapeada['cc'] ?? '',
                    'asesor' => $filaMapeada['asesor'] ?? '',
                    'motivo' => $filaResultado['message']
                ];
            }

            fclose($handle);
            $resultado['message'] = sprintf(
                'Importación completada. %d fila(s) insertada(s), %d omitida(s), %d vacía(s).',
                $resultado['insertadas'],
                $resultado['omitidas'],
                $resultado['filas_vacias']
            );

            return $resultado;
        } catch (Exception $e) {
            fclose($handle);
            error_log("Error al importar CSV de gestiones: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al importar el CSV: ' . $e->getMessage()
            ];
        }
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

    private function obtenerBaseActivaPorId($baseId) {
        $conn = getDBConnection();
        $query = "SELECT id, nombre
                  FROM bases_clientes
                  WHERE id = ? AND estado = 'activo'
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([(int) $baseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function detectarDelimitadorCSV($filePath) {
        $sample = file_get_contents($filePath, false, null, 0, 4096);
        if ($sample === false) {
            return ';';
        }

        $semicolonCount = substr_count($sample, ';');
        $commaCount = substr_count($sample, ',');
        return $semicolonCount >= $commaCount ? ';' : ',';
    }

    private function normalizarEncabezadoCSV($value) {
        $value = $this->normalizarTextoCSV($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = trim($value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    private function normalizarTextoCSV($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        if (preg_match('//u', $value)) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252, ASCII');
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }

    private function filaCSVVacia(array $row) {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function mapearFilaImportacion(array $fila) {
        return [
            'fecha_gestion' => $this->obtenerPrimeroNoVacio($fila, ['fecha_gestion', 'fecha_y_hora', 'fecha_hora', 'fecha']),
            'asesor' => $this->obtenerPrimeroNoVacio($fila, ['asesor', 'asesor_nombre', 'nombre_asesor']),
            'cc' => $this->obtenerPrimeroNoVacio($fila, ['cc', 'cedula_cliente', 'cedula', 'identificacion', 'identificacion_cliente']),
            'canal_contacto' => $this->obtenerPrimeroNoVacio($fila, ['canal_de_contacto', 'canal_contacto']),
            'contrato_id' => $this->obtenerPrimeroNoVacio($fila, ['obligacion', 'contrato_id', 'contrato', 'factura', 'numero_obligacion']),
            'nivel1_tipo' => $this->obtenerPrimeroNoVacio($fila, ['nivel_1_tipo', 'nivel1_tipo']),
            'nivel2_clasificacion' => $this->obtenerPrimeroNoVacio($fila, ['nivel_2_clasificacion', 'nivel2_clasificacion']),
            'nivel3_detalle' => $this->obtenerPrimeroNoVacio($fila, ['nivel_3_detalle', 'nivel3_detalle']),
            'fecha_pago' => $this->obtenerPrimeroNoVacio($fila, ['fecha_pago']),
            'valor_pago' => $this->obtenerPrimeroNoVacio($fila, ['valor_pago']),
            'canales_autorizados' => $this->obtenerPrimeroNoVacio($fila, ['canales_autorizados']),
            'duracion_gestion' => $this->obtenerPrimeroNoVacio($fila, ['duracion_gestion', 'duracion']),
            'observaciones' => $this->obtenerPrimeroNoVacio($fila, ['observaciones', 'comentarios']),
            // "teléfono de contacto" normaliza a "telefono_de_contacto"
            'telefono_contacto' => $this->obtenerPrimeroNoVacio($fila, ['telefono_contacto', 'telefono_de_contacto', 'telefono', 'celular'])
        ];
    }

    private function obtenerPrimeroNoVacio(array $fila, array $keys) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $fila) && trim((string) $fila[$key]) !== '') {
                return trim($this->normalizarTextoCSV($fila[$key]));
            }
        }
        return null;
    }

    private function procesarFilaImportacionGestion($baseId, array $fila, $numeroFila) {
        $fechaGestion = $this->parseFechaHoraCSV($fila['fecha_gestion'] ?? '');
        if ($fechaGestion === null) {
            return [
                'success' => false,
                'message' => 'La columna fecha_gestion no tiene un formato válido.'
            ];
        }

        $cc = preg_replace('/\D/', '', (string) ($fila['cc'] ?? ''));
        if ($cc === '') {
            return [
                'success' => false,
                'message' => 'La fila no tiene una cédula de cliente válida.'
            ];
        }

        $cliente = $this->buscarClienteBasePorCedula($baseId, $cc);
        if (!$cliente) {
            return [
                'success' => false,
                'message' => 'No se encontró cliente para la cédula indicada dentro de la base seleccionada.'
            ];
        }

        $asesorNombre = trim((string) ($fila['asesor'] ?? ''));
        if ($asesorNombre === '') {
            return [
                'success' => false,
                'message' => 'La fila no trae nombre de asesor.'
            ];
        }

        $asesor = $this->buscarAsesorPorNombreExacto($asesorNombre);
        if (!$asesor) {
            return [
                'success' => false,
                'message' => 'No se encontró un asesor con ese nombre exacto en usuarios.'
            ];
        }

        if (!$this->asesorTieneAccesoBase($asesor['cedula'], $baseId)) {
            return [
                'success' => false,
                'message' => 'El asesor no tiene acceso activo a la base seleccionada.'
            ];
        }

        $canales = $this->parseCanalesAutorizados($fila['canales_autorizados'] ?? '');
        $datosGestion = [
            'asesor_cedula' => $asesor['cedula'],
            'cliente_id' => $cliente['id'],
            'canal_contacto' => $this->normalizarCanalContacto($fila['canal_contacto'] ?? null),
            'contrato_id' => $this->normalizarContratoId($fila['contrato_id'] ?? null),
            'nivel1_tipo' => $fila['nivel1_tipo'] ?? null,
            'nivel2_clasificacion' => $fila['nivel2_clasificacion'] ?? null,
            'nivel3_detalle' => $fila['nivel3_detalle'] ?? null,
            'observaciones' => $fila['observaciones'] ?? null,
            'telefono_contacto' => $this->normalizarTelefono($fila['telefono_contacto'] ?? null),
            'llamada_telefonica' => $canales['llamada_telefonica'],
            'whatsapp' => $canales['whatsapp'],
            'correo_electronico' => $canales['correo_electronico'],
            'sms' => $canales['sms'],
            'correo_fisico' => $canales['correo_fisico'],
            'mensajeria_aplicacion' => $canales['mensajeria_aplicacion'],
            'duracion_segundos' => $this->convertirDuracionASegundos($fila['duracion_gestion'] ?? null),
            'fecha_pago' => $this->parseFechaCSV($fila['fecha_pago'] ?? null),
            'valor_pago' => $this->normalizarValorMonetario($fila['valor_pago'] ?? null),
            'fecha_creacion' => $fechaGestion
        ];

        $resultado = Gestion::crear($datosGestion);
        if (!$resultado['success']) {
            return [
                'success' => false,
                'message' => $resultado['message'] ?? "No se pudo insertar la gestión de la fila {$numeroFila}."
            ];
        }

        return $resultado;
    }

    private function buscarClienteBasePorCedula($baseId, $cc) {
        $conn = getDBConnection();
        $query = "SELECT id, cc, nombre, base_id
                  FROM clientes
                  WHERE base_id = ? AND CAST(cc AS CHAR) = CAST(? AS CHAR)
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([(int) $baseId, (string) $cc]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function buscarAsesorPorNombreExacto($nombreCompleto) {
        $conn = getDBConnection();
        $query = "SELECT cedula, nombre_completo
                  FROM usuarios
                  WHERE rol = 'asesor' AND nombre_completo = ?
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$nombreCompleto]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function asesorTieneAccesoBase($asesorCedula, $baseId) {
        $conn = getDBConnection();
        $query = "SELECT 1
                  FROM asignaciones_base_clientes
                  WHERE base_id = ? AND asesor_cedula = ? AND estado = 'activa'
                  LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([(int) $baseId, (string) $asesorCedula]);
        return (bool) $stmt->fetchColumn();
    }

    private function normalizarCanalContacto($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = $this->normalizarEncabezadoCSV($value);
        $map = [
            'llamada' => 'llamada',
            'llamada_saliente' => 'llamada_saliente',
            'whatsapp' => 'whatsapp',
            'email' => 'email',
            'correo_electronico' => 'email',
            'sms' => 'sms',
            'recibir_llamada' => 'recibir_llamada'
        ];

        return $map[$normalized] ?? $value;
    }

    private function normalizarContratoId($value) {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'ninguna') === 0) {
            return 'ninguna';
        }
        return $value;
    }

    private function normalizarTelefono($value) {
        $digits = preg_replace('/\D/', '', (string) $value);
        return $digits !== '' ? substr($digits, 0, 20) : null;
    }

    private function parseCanalesAutorizados($value) {
        $base = [
            'llamada_telefonica' => 'no',
            'whatsapp' => 'no',
            'correo_electronico' => 'no',
            'sms' => 'no',
            'correo_fisico' => 'no',
            'mensajeria_aplicacion' => 'no'
        ];

        $value = trim((string) $value);
        if ($value === '') {
            return $base;
        }

        $tokens = preg_split('/\s*-\s*|,/', $value) ?: [];
        foreach ($tokens as $token) {
            $normalized = $this->normalizarEncabezadoCSV($token);
            if ($normalized === 'llamada') {
                $base['llamada_telefonica'] = 'si';
            } elseif ($normalized === 'whatsapp') {
                $base['whatsapp'] = 'si';
            } elseif ($normalized === 'email') {
                $base['correo_electronico'] = 'si';
            } elseif ($normalized === 'sms') {
                $base['sms'] = 'si';
            } elseif (in_array($normalized, ['correo_fisico', 'correo'], true)) {
                $base['correo_fisico'] = 'si';
            } elseif (in_array($normalized, ['mensajeria', 'mensajeria_por_aplicaciones'], true)) {
                $base['mensajeria_aplicacion'] = 'si';
            }
        }

        return $base;
    }

    private function convertirDuracionASegundos($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $parts = explode(':', $value);
        if (count($parts) === 3) {
            return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
        }
        if (count($parts) === 2) {
            return ((int) $parts[0] * 60) + (int) $parts[1];
        }

        return 0;
    }

    private function parseFechaHoraCSV($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i'
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function parseFechaCSV($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizarValorMonetario($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(['$', ' '], '', $value);
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
?>
