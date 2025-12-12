<?php
require_once 'config.php';
require_once 'models/Usuario.php';
require_once 'models/Asignacion.php';
require_once 'models/Cliente.php';
require_once 'models/Obligacion.php';
require_once 'models/AsignacionAsesor.php';
require_once 'models/Historial.php';

/**
 * Controlador de Coordinador
 * Maneja todas las operaciones específicas del rol coordinador
 * Incluye gestión de comercios, facturas y carga de archivos CSV
 */
class CoordinadorController {
    private $usuario_model;
    private $asignacion_model;
    private $cliente_model;
    private $obligacion_model;
    private $asignacion_asesor_model;
    private $historial_model;

    public function __construct() {
        $this->usuario_model = new Usuario();
        $this->asignacion_model = new Asignacion();
        $this->cliente_model = new Cliente();
        $this->obligacion_model = new Obligacion();
        $this->asignacion_asesor_model = new AsignacionAsesor();
        $this->historial_model = new Historial();
    }

    /**
     * Obtener estadísticas del coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerEstadisticas($coordinador_cedula) {
        try {
            $conn = getDBConnection();
            
            // Obtener asesores asignados
            $asesores = $this->asignacion_model->obtenerAsesoresPorCoordinador($coordinador_cedula);
            
            // Obtener estadísticas de bases_clientes
            $query = "SELECT COUNT(*) as total_bases FROM bases_clientes WHERE estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $bases_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total_bases'] ?? 0;
            
            // Obtener estadísticas de clientes
            $query = "SELECT COUNT(*) as total_comercios FROM clientes";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $total_comercios = $stmt->fetch(PDO::FETCH_ASSOC)['total_comercios'] ?? 0;
            
            // Clientes activos
            $query = "SELECT COUNT(*) as comercios_activos FROM clientes WHERE estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $comercios_activos = $stmt->fetch(PDO::FETCH_ASSOC)['comercios_activos'] ?? 0;
            
            // Clientes nuevos (últimos 30 días)
            $query = "SELECT COUNT(*) as comercios_nuevos FROM clientes 
                     WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $comercios_nuevos = $stmt->fetch(PDO::FETCH_ASSOC)['comercios_nuevos'] ?? 0;
            
            // Obtener estadísticas de facturas
            $query = "SELECT COUNT(*) as total_facturas, 
                     COALESCE(SUM(saldo_total), 0) as total_valor_obligacions
                     FROM obligaciones";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $facturas_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_facturas = $facturas_data['total_facturas'] ?? 0;
            $total_valor_obligacions = $facturas_data['total_valor_obligacions'] ?? 0;
            
            // Obtener estadísticas de tareas
            $tareas_stats = $this->obtenerEstadisticasTareas($coordinador_cedula);
            
            // Obtener clientes gestionados (con al menos una gestión)
            $query = "SELECT COUNT(DISTINCT g.cliente_id) as gestionados
                     FROM gestiones g
                     WHERE g.cliente_id IS NOT NULL";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $comercios_gestionados = $stmt->fetch(PDO::FETCH_ASSOC)['gestionados'] ?? 0;
            
            // Calcular comercios pendientes
            $comercios_pendientes = max(0, $total_comercios - $comercios_gestionados);
            
            // Calcular eficiencia
            $eficiencia = $total_comercios > 0 ? round(($comercios_gestionados / $total_comercios) * 100, 1) : 0;
            
            return [
                'asesores_asignados' => count($asesores),
                'asesores_activos' => count(array_filter($asesores, function($a) { return ($a['estado'] ?? 'activo') === 'activo'; })),
                'bases_clientes' => $bases_clientes,
                'tareas_realizadas' => $tareas_stats['tareas_completadas'] ?? 0,
                'total_clientes' => $total_comercios, // Para compatibilidad con la vista
                'total_comercios' => $total_comercios,
                'comercios_activos' => $comercios_activos,
                'comercios_inactivos' => ($total_comercios - $comercios_activos),
                'tareas_pendientes' => $tareas_stats['tareas_pendientes'] ?? 0,
                'comercios_nuevos' => $comercios_nuevos,
                'tareas_en_progreso' => $tareas_stats['tareas_en_progreso'] ?? 0,
                'total_facturas' => $total_facturas,
                'total_contratos' => $total_facturas, // Para compatibilidad con la vista
                'total_valor_obligacions' => $total_valor_obligacions,
                'total_cartera' => $total_valor_obligacions, // Para compatibilidad con la vista
                'clientes_gestionados' => $comercios_gestionados,
                'clientes_pendientes' => $comercios_pendientes,
                'eficiencia' => $eficiencia
            ];
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas del coordinador: " . $e->getMessage());
            return [
                'asesores_asignados' => 0,
                'asesores_activos' => 0,
                'bases_clientes' => 0,
                'tareas_realizadas' => 0,
                'total_clientes' => 0,
                'total_comercios' => 0,
                'comercios_activos' => 0,
                'comercios_inactivos' => 0,
                'tareas_pendientes' => 0,
                'comercios_nuevos' => 0,
                'tareas_en_progreso' => 0,
                'total_facturas' => 0,
                'total_contratos' => 0,
                'total_valor_obligacions' => 0,
                'total_cartera' => 0,
                'clientes_gestionados' => 0,
                'clientes_pendientes' => 0,
                'eficiencia' => 0
            ];
        }
    }

    /**
     * Obtener número de bases de comercios registradas
     * @return int
     */
    private function obtenerNumeroBases() {
        try {
            $conn = getDBConnection();
            $query = "SELECT COUNT(*) as total_bases FROM bases_clientes WHERE estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_bases'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al obtener número de bases: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas de comercios
     * @return array
     */
    public function obtenerEstadisticasComercios() {
        try {
            $conn = getDBConnection();
            
            // Contar total de clientes
            $query = "SELECT COUNT(*) as total_comercios FROM clientes";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $total_comercios = $stmt->fetch(PDO::FETCH_ASSOC)['total_comercios'] ?? 0;
            
            // Contar total de facturas
            $query = "SELECT COUNT(*) as total_facturas FROM obligaciones";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total_facturas'] ?? 0;
            
            // Contar comercios activos
            $query = "SELECT COUNT(*) as comercios_activos FROM clientes WHERE estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $comercios_activos = $stmt->fetch(PDO::FETCH_ASSOC)['comercios_activos'] ?? 0;
            
            return [
                'success' => true,
                'total_comercios' => $total_comercios,
                'total_facturas' => $total_facturas,
                'comercios_activos' => $comercios_activos
            ];
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas de comercios: " . $e->getMessage());
            return [
                'success' => false,
                'total_comercios' => 0,
                'total_facturas' => 0,
                'comercios_activos' => 0
            ];
        }
    }

    /**
     * Obtener comercios registrados
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerComercios($coordinador_cedula) {
        try {
            return $this->cliente_model->obtenerTodos();
        } catch (Exception $e) {
            error_log("Error al obtener clientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener facturas registradas
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerFacturas($coordinador_cedula) {
        try {
            return $this->obligacion_model->obtenerTodos();
        } catch (Exception $e) {
            error_log("Error al obtener obligaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Procesar archivo CSV para comercios y facturas (OPTIMIZADO PARA ARCHIVOS GRANDES)
     * Procesa en lotes para manejar archivos de más de 1 millón de registros
     * @param string $file_path Ruta del archivo CSV
     * @param string $tipo_carga Tipo de carga: 'nueva' o 'existente'
     * @param string $nombre_archivo Nombre del archivo (para carga nueva)
     * @param int $batch_size Tamaño del lote para procesamiento (default: 1000)
     * @return array Resultado del procesamiento
     */
    public function procesarCSVClientesObligaciones($file_path, $tipo_carga = 'nueva', $nombre_archivo = '', $batch_size = 1000, $base_datos_id = null) {
        // Aumentar límites de memoria y tiempo para archivos grandes
        ini_set('memory_limit', '1024M');
        set_time_limit(7200); // 2 horas
        
        $resultado = [
            'success' => false,
            'total_filas' => 0,
            'comercios_creados' => 0,
            'comercios_unicos' => 0, // Total de comercios únicos procesados
            'facturas_creadas' => 0,
            'facturas_unicas' => 0, // Total de facturas únicas procesadas
            'errores' => [],
            'mensaje' => '',
            'tipo_carga' => $tipo_carga,
            'nombre_archivo' => $nombre_archivo,
            'procesado' => 0,
            'procesando' => true
        ];

        try {
            // Contar total de filas primero (sin cargar en memoria)
            $total_filas = $this->contarFilasCSV($file_path);
            $resultado['total_filas'] = $total_filas;
            error_log("CoordinadorController: Procesando {$total_filas} filas del CSV en lotes de {$batch_size}");

            // Si es carga nueva, crear la base en bases_clientes
            $base_id = null;
            if ($tipo_carga === 'nueva') {
                if (empty($nombre_archivo)) {
                    $resultado['errores'][] = 'El nombre del archivo es requerido para carga nueva';
                    return $resultado;
                }
                
                // Crear nueva base en bases_clientes
                $conn = getDBConnection();
                $query = "INSERT INTO bases_clientes (nombre, descripcion, creado_por, estado) VALUES (?, ?, ?, 'activo')";
                $stmt = $conn->prepare($query);
                $descripcion = "Base creada desde archivo CSV: {$nombre_archivo}";
                $creado_por = $_SESSION['usuario_id'] ?? $_SESSION['cedula'] ?? 'sistema';
                
                if ($stmt->execute([$nombre_archivo, $descripcion, $creado_por])) {
                    $base_id = $conn->lastInsertId();
                    $resultado['base_id'] = $base_id;
                    error_log("CoordinadorController: Base creada en bases_clientes - ID: {$base_id}, Nombre: {$nombre_archivo}");
                } else {
                    $resultado['errores'][] = 'Error al crear la base de datos';
                    error_log("CoordinadorController: Error al crear base - " . implode(', ', $stmt->errorInfo()));
                    return $resultado;
                }
            } elseif ($tipo_carga === 'existente') {
                // Para carga existente, obtener base_id desde parámetro o POST
                if ($base_datos_id !== null) {
                    $base_id = (int)$base_datos_id;
                } else {
                    $base_datos_id_param = $_POST['base_datos_id'] ?? null;
                    if ($base_datos_id_param) {
                        $base_id = (int)$base_datos_id_param;
                    } else {
                        $base_id = null;
                    }
                }
                
                if ($base_id) {
                    error_log("CoordinadorController: Carga existente - Base ID: {$base_id}");
                } else {
                    error_log("CoordinadorController: Carga existente - No se proporcionó base_id");
                }
            }

            // Procesar datos del CSV en lotes (batch processing)
            $comercios_procesados = []; // Cache de comercios ya procesados en este lote
            $comercios_cache = []; // Cache de comercios existentes para evitar consultas repetidas
            $facturas_cache = []; // Cache de facturas existentes
            $conn = getDBConnection();
            
            // Iniciar transacción para mejor rendimiento
            $conn->beginTransaction();
            
            // Preparar statements para mejor rendimiento
            $stmt_comercio_exists = $conn->prepare("SELECT id FROM clientes WHERE cc = ? LIMIT 1");
            $stmt_factura_exists = $conn->prepare("SELECT id FROM obligaciones WHERE numero_obligacion = ? LIMIT 1");
            $stmt_insert_comercio = $conn->prepare("INSERT INTO clientes (cc, nombre, cel1, cel2, cel3, cel4, cel5, cel6, estado, base_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert_factura = $conn->prepare("INSERT INTO obligaciones (numero_obligacion, cliente_id, producto, saldo_capital, saldo_total, dias_mora, estado, base_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Procesar CSV línea por línea en lotes
            $handle = fopen($file_path, 'r');
            if ($handle === false) {
                throw new Exception("No se pudo abrir el archivo CSV");
            }
            
            // Leer encabezados
            $encabezados = fgetcsv($handle);
            if (!$encabezados) {
                fclose($handle);
                throw new Exception("No se pudieron leer los encabezados del CSV");
            }

            error_log("CoordinadorController: Encabezados crudos del CSV: " . json_encode($encabezados));

            // Normalizar encabezados - crear un mapa flexible que ignore espacios y case
            $encabezados_normalizados = [];
            $encabezados_map = []; // Mapa para buscar campos por nombre normalizado

            foreach ($encabezados as $index => $header) {
                $header_limpio = trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B");
                $header_normalizado = strtolower(preg_replace('/\s+/', ' ', $header_limpio));
                $encabezados_normalizados[$index] = $header_limpio;
                $encabezados_map[$header_normalizado] = $index;

                error_log("CoordinadorController: Encabezado {$index}: '{$header_limpio}' -> normalizado: '{$header_normalizado}'");
            }

            // Verificar que tenemos los encabezados requeridos
            $encabezados_requeridos = ['cc', 'nombre', 'numero_obligacion'];
            $encabezados_encontrados = [];
            foreach ($encabezados_requeridos as $req) {
                $req_normalizado = strtolower(preg_replace('/\s+/', ' ', trim($req)));
                if (!isset($encabezados_map[$req_normalizado])) {
                    error_log("CoordinadorController: Encabezado requerido no encontrado: '{$req}' (normalizado: '{$req_normalizado}')");
                    $resultado['errores'][] = "Encabezado requerido no encontrado: '{$req}'";
                } else {
                    $encabezados_encontrados[] = $req;
                }
            }

            if (empty($encabezados_encontrados)) {
                error_log("CoordinadorController: Ningún encabezado requerido encontrado");
                $resultado['errores'][] = "El archivo CSV no contiene los encabezados requeridos (CC, nombre, numero_obligacion)";
                $resultado['mensaje'] = "Error: El archivo CSV no contiene los encabezados requeridos";
                fclose($handle);
                return $resultado;
            }
            
            // Función helper para buscar campo ignorando espacios y case
            $buscarCampo = function($fila_actual, $nombres_posibles) use ($encabezados_map) {
                foreach ($nombres_posibles as $nombre) {
                    $nombre_normalizado = strtolower(preg_replace('/\s+/', ' ', trim($nombre)));
                    if (isset($encabezados_map[$nombre_normalizado])) {
                        $index = $encabezados_map[$nombre_normalizado];
                        if (isset($fila_actual[$index])) {
                            $valor = trim($fila_actual[$index]);
                            if ($valor !== '' && $valor !== '#N/D') {
                                return $valor;
                            }
                        }
                    }
                }
                return '';
            };
            
            $batch = [];
            $fila_numero = 0;
            
            // Procesar línea por línea
            while (($fila = fgetcsv($handle)) !== false) {
                $fila_numero++;

                if ($fila_numero <= 3) {
                    error_log("CoordinadorController: Procesando fila {$fila_numero}: " . json_encode($fila));
                }

                if (count($fila) < 7) {
                    $resultado['errores'][] = "Fila {$fila_numero}: Fila incompleta (menos de 7 columnas)";
                    error_log("CoordinadorController: Fila {$fila_numero} incompleta - columnas: " . count($fila));
                    continue; // Saltar filas incompletas
                }

                // Mapear campos directamente desde el array $fila usando función helper
                // No necesitamos normalizar la fila, usamos directamente el índice del encabezado
                $cc = $buscarCampo($fila, ['cc', 'cedula', 'identificacion']);
                $nombre = $buscarCampo($fila, ['nombre', 'nombre completo', 'nombre_completo']);
                $numero_obligacion = $buscarCampo($fila, ['numero_obligacion', 'numero obligacion', 'num_obligacion']);
                $producto = $buscarCampo($fila, ['producto', 'tipo_producto']);
                $saldo_capital_str = $buscarCampo($fila, ['saldo_capital', 'saldo capital', 'capital']);
                $saldo_capital = (float)str_replace([',', '#N/D', '#N/A', 'N/D', 'N/A'], ['', '0', '0', '0', '0'], $saldo_capital_str);
                $saldo_total_str = $buscarCampo($fila, ['saldo_total', 'saldo total', 'total']);
                $saldo_total = (float)str_replace([',', '#N/D', '#N/A', 'N/D', 'N/A'], ['', '0', '0', '0', '0'], $saldo_total_str);
                $dias_mora_str = $buscarCampo($fila, ['dias_mora', 'dias mora', 'diasmora', 'mora']);
                $dias_mora = (int)str_replace(['#N/D', '#N/A', 'N/D', 'N/A'], '0', $dias_mora_str);
                $cel1 = $buscarCampo($fila, ['cel1', 'celular1', 'telefono1', 'tel1']);
                $cel2 = $buscarCampo($fila, ['cel2', 'celular2', 'telefono2', 'tel2']);
                $cel3 = $buscarCampo($fila, ['cel3', 'celular3', 'telefono3', 'tel3']);
                $cel4 = $buscarCampo($fila, ['cel4', 'celular4', 'telefono4', 'tel4']);
                $cel5 = $buscarCampo($fila, ['cel5', 'celular5', 'telefono5', 'tel5']);
                $cel6 = $buscarCampo($fila, ['cel6', 'celular6', 'telefono6', 'tel6']);

                if ($fila_numero <= 3) {
                    error_log("CoordinadorController: Fila {$fila_numero} campos mapeados - CC: '{$cc}', Obligacion: '{$numero_obligacion}', Nombre: '{$nombre}'");
                }

                // Validar campos requeridos
                if (empty($cc)) {
                    $resultado['errores'][] = "Fila {$fila_numero}: CC vacío";
                    error_log("CoordinadorController: Fila {$fila_numero} - CC vacío");
                    continue;
                }
                if (empty($numero_obligacion)) {
                    $resultado['errores'][] = "Fila {$fila_numero}: Número de obligación vacío";
                    error_log("CoordinadorController: Fila {$fila_numero} - Número de obligación vacío");
                    continue;
                }
                
                $batch[] = [
                    'cc' => $cc,
                    'nombre' => $nombre,
                    'numero_obligacion' => $numero_obligacion,
                    'producto' => $producto,
                    'saldo_capital' => $saldo_capital,
                    'saldo_total' => $saldo_total,
                    'dias_mora' => $dias_mora,
                    'cel1' => $cel1,
                    'cel2' => $cel2,
                    'cel3' => $cel3,
                    'cel4' => $cel4,
                    'cel5' => $cel5,
                    'cel6' => $cel6
                ];
                
                // Procesar cuando el lote esté lleno
                if (count($batch) >= $batch_size) {
                    $this->procesarLote($batch, $base_id, $conn, $stmt_comercio_exists, $stmt_factura_exists, 
                                       $stmt_insert_comercio, $stmt_insert_factura, 
                                       $comercios_procesados, $comercios_cache, $facturas_cache, $resultado);
                    $batch = [];
                    
                    // Commit intermedio para evitar transacciones muy largas
                    if ($fila_numero % ($batch_size * 10) == 0) {
                        $conn->commit();
                        $conn->beginTransaction();
                        error_log("CoordinadorController: Procesadas {$fila_numero} filas...");
                    }
                }
            }
            
            // Procesar lote restante
            if (!empty($batch)) {
                $this->procesarLote($batch, $base_id, $conn, $stmt_comercio_exists, $stmt_factura_exists, 
                                   $stmt_insert_comercio, $stmt_insert_factura, 
                                   $comercios_procesados, $comercios_cache, $facturas_cache, $resultado);
            }
            
            fclose($handle);
            
            // Commit final
            $conn->commit();
            
            $resultado['procesado'] = $fila_numero;
            $resultado['procesando'] = false;
            
            // Asegurar que comercios_unicos esté correcto (contar únicos en comercios_procesados)
            if ($resultado['comercios_unicos'] == 0 && count($comercios_procesados) > 0) {
                $resultado['comercios_unicos'] = count($comercios_procesados);
            }
            
            // Asegurar que facturas_unicas esté correcto (contar únicos en facturas_cache)
            if ($resultado['facturas_unicas'] == 0 && count($facturas_cache) > 0) {
                $resultado['facturas_unicas'] = count($facturas_cache);
            }

            // Actualizar estadísticas de la base si se creó una nueva o es carga existente
            if ($base_id && ($resultado['comercios_creados'] > 0 || $resultado['facturas_creadas'] > 0)) {
                $conn = getDBConnection();
                
                // Contar clientes en la base
                $query = "SELECT COUNT(*) as total FROM clientes WHERE base_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$base_id]);
                $total_comercios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Contar obligaciones en la base
                $query = "SELECT COUNT(*) as total FROM obligaciones WHERE base_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$base_id]);
                $total_facturas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Calcular valor total de obligaciones
                $query = "SELECT COALESCE(SUM(saldo_total), 0) as total FROM obligaciones WHERE base_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$base_id]);
                $valor_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Actualizar base
                $query = "UPDATE bases_clientes SET 
                         total_comercios = ?, 
                         total_facturas = ?, 
                         valor_total = ?,
                         fecha_actualizacion = NOW()
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$total_comercios, $total_facturas, $valor_total, $base_id]);
                
                error_log("CoordinadorController: Estadísticas de base actualizadas - ID: {$base_id}, Comercios: {$total_comercios}, Facturas: {$total_facturas}");
            }

            // Registrar actividad en historial
            $this->registrarHistorialCarga($nombre_archivo, $resultado);

            // Determinar éxito - usar clientes_unicos y obligaciones_unicas para el mensaje
            if ($resultado['comercios_unicos'] > 0 || $resultado['facturas_unicas'] > 0) {
                $resultado['success'] = true;
                // Actualizar nombres para compatibilidad
                $resultado['clientes_unicos'] = $resultado['comercios_unicos'];
                $resultado['obligaciones_unicas'] = $resultado['facturas_unicas'];
                $resultado['clientes_creados'] = $resultado['comercios_creados'];
                $resultado['obligaciones_creadas'] = $resultado['facturas_creadas'];
                
                if ($tipo_carga === 'nueva' && $base_id) {
                    $resultado['mensaje'] = "Base nueva creada exitosamente. Clientes únicos: {$resultado['clientes_unicos']}, Obligaciones: {$resultado['obligaciones_unicas']}";
                } else {
                    $resultado['mensaje'] = "Procesamiento completado. Clientes únicos: {$resultado['clientes_unicos']}, Obligaciones: {$resultado['obligaciones_unicas']}";
                }
            } else {
                $resultado['mensaje'] = "No se procesaron datos válidos";
                error_log("CoordinadorController: No se procesaron datos válidos. Comercios: {$resultado['comercios_creados']}, Facturas: {$resultado['facturas_creadas']}, Errores: " . count($resultado['errores']));
                error_log("CoordinadorController: Primeros 5 errores: " . json_encode(array_slice($resultado['errores'], 0, 5)));

                // Si se creó una base pero no hay datos, eliminar la base vacía
                if ($base_id) {
                    try {
                        $conn = getDBConnection();
                        $query = "DELETE FROM bases_clientes WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$base_id]);
                        error_log("CoordinadorController: Base vacía eliminada - ID: {$base_id}");
                    } catch (Exception $e) {
                        error_log("CoordinadorController: Error al eliminar base vacía - " . $e->getMessage());
                    }
                }
            }

            error_log("CoordinadorController: Procesamiento completado - Éxito: " . ($resultado['success'] ? 'Sí' : 'No'));

        } catch (Exception $e) {
            $resultado['errores'][] = "Error general: " . $e->getMessage();
            error_log("CoordinadorController: Error general - " . $e->getMessage());
        }

        return $resultado;
    }

    /**
     * Procesar un lote de datos del CSV (batch processing)
     * @param array $batch Lote de datos a procesar
     * @param int|null $base_id ID de la base (si aplica)
     * @param PDO $conn Conexión a la base de datos
     * @param PDOStatement $stmt_comercio_exists Statement para verificar comercio
     * @param PDOStatement $stmt_factura_exists Statement para verificar factura
     * @param PDOStatement $stmt_insert_comercio Statement para insertar comercio
     * @param PDOStatement $stmt_insert_factura Statement para insertar factura
     * @param array $comercios_procesados Referencia a array de comercios procesados en este lote
     * @param array $comercios_cache Referencia a cache de comercios existentes
     * @param array $facturas_cache Referencia a cache de facturas existentes
     * @param array $resultado Referencia al resultado del procesamiento
     */
    private function procesarLote($batch, $base_id, $conn, $stmt_comercio_exists, $stmt_factura_exists, 
                                 $stmt_insert_comercio, $stmt_insert_factura, 
                                 &$comercios_procesados, &$comercios_cache, &$facturas_cache, &$resultado) {
        
        foreach ($batch as $fila) {
            try {
                $cc = trim($fila['cc']);
                $nombre = trim($fila['nombre']);
                $cel1 = trim($fila['cel1']);
                $cel2 = trim($fila['cel2']);
                $cel3 = trim($fila['cel3']);
                $cel4 = trim($fila['cel4']);
                $cel5 = trim($fila['cel5']);
                $cel6 = trim($fila['cel6']);
                
                // Verificar cliente en cache primero
                $cliente_id = null;
                
                if (isset($comercios_cache[$cc])) {
                    $cliente_id = $comercios_cache[$cc];
                } else {
                    // Verificar en base de datos
                    $stmt_comercio_exists->execute([$cc]);
                    $cliente_existente = $stmt_comercio_exists->fetch(PDO::FETCH_ASSOC);
                    
                    if ($cliente_existente) {
                        $cliente_id = $cliente_existente['id'];
                        $comercios_cache[$cc] = $cliente_id;
                    } else {
                        // Crear nuevo cliente
                        $stmt_insert_comercio->execute([$cc, $nombre, $cel1, $cel2, $cel3, $cel4, $cel5, $cel6, 'activo', $base_id]);
                        $cliente_id = $conn->lastInsertId();
                        $comercios_cache[$cc] = $cliente_id;
                        $resultado['comercios_creados']++;
                    }
                }
                
                // Contar clientes únicos procesados (todos, nuevos y existentes)
                if (!isset($comercios_procesados[$cc])) {
                    $resultado['comercios_unicos']++;
                    $comercios_procesados[$cc] = $cliente_id;
                }
                
                // Procesar obligacion
                $numero_obligacion = trim($fila['numero_obligacion']);
                if (empty($numero_obligacion)) {
                    continue; // Saltar si no hay número de obligación
                }
                
                // Verificar obligacion en cache
                if (!isset($facturas_cache[$numero_obligacion])) {
                    $stmt_factura_exists->execute([$numero_obligacion]);
                    $obligacion_existente = $stmt_factura_exists->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$obligacion_existente) {
                        // Crear nueva obligación
                        $producto = trim($fila['producto']);
                        $saldo_capital = (float)$fila['saldo_capital'];
                        $saldo_total = (float)$fila['saldo_total'];
                        $dias_mora = (int)$fila['dias_mora'];
                        $estado = $dias_mora <= 0 ? 'vigente' : 'vencida';
                        
                        $stmt_insert_factura->execute([
                            $numero_obligacion, $cliente_id, $producto, $saldo_capital, $saldo_total, $dias_mora, $estado, $base_id
                        ]);
                        $resultado['facturas_creadas']++;
                    }
                    // Contar obligación única (tanto nuevas como existentes)
                    $resultado['facturas_unicas']++;
                    $facturas_cache[$numero_obligacion] = true;
                }
                
            } catch (Exception $e) {
                $resultado['errores'][] = "Error procesando fila: " . $e->getMessage();
                if (count($resultado['errores']) <= 100) { // Limitar errores en log
                    error_log("CoordinadorController: Error en fila - " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Contar filas de un archivo CSV sin cargarlo en memoria
     * @param string $file_path Ruta del archivo
     * @return int Número de filas
     */
    private function contarFilasCSV($file_path) {
        $count = 0;
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return 0;
        }
        
        // Leer encabezado
        fgetcsv($handle);
        
        // Contar filas
        while (fgetcsv($handle) !== false) {
            $count++;
        }
        
        fclose($handle);
        return $count;
    }

    /**
     * Leer archivo CSV y convertir a array (MANTENER PARA COMPATIBILIDAD)
     * @param string $file_path Ruta del archivo
     * @return array|false Datos del CSV o false en caso de error
     */
    private function leerCSV($file_path) {
        try {
            $datos = [];
            $handle = fopen($file_path, 'r');
            
            if ($handle === false) {
                return false;
            }

            // Leer encabezados
            $encabezados = fgetcsv($handle);
            if (!$encabezados) {
                fclose($handle);
                return false;
            }

            // Normalizar encabezados (eliminar BOM y espacios)
            $encabezados_normalizados = array_map(function($header) {
                // Eliminar BOM UTF-8 y espacios
                $header = trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B");
                return $header;
            }, $encabezados);
            
            // Leer datos
            while (($fila = fgetcsv($handle)) !== false) {
                if (count($fila) >= 7) { // Verificar que tenga al menos 7 columnas
                    $fila_normalizada = [];
                    foreach ($fila as $index => $valor) {
                        $clave = $encabezados_normalizados[$index] ?? "columna_{$index}";
                        $fila_normalizada[$clave] = trim($valor);
                    }
                    
                    // Mapear campos según el formato esperado (nuevo formato)
                    $datos[] = [
                        'cc' => $fila_normalizada['CC'] ?? $fila_normalizada['cc'] ?? $fila_normalizada['cedula'] ?? '',
                        'nombre' => $fila_normalizada['nombre'] ?? $fila_normalizada['nombre completo'] ?? '',
                        'numero_obligacion' => $fila_normalizada['numero_obligacion'] ?? $fila_normalizada['numero obligacion'] ?? '',
                        'producto' => $fila_normalizada['producto'] ?? '',
                        'saldo_capital' => $fila_normalizada['saldo_capital'] ?? $fila_normalizada['saldo capital'] ?? 0,
                        'saldo_total' => $fila_normalizada['saldo_total'] ?? $fila_normalizada['saldo total'] ?? 0,
                        'dias_mora' => $fila_normalizada['dias_mora'] ?? $fila_normalizada['dias mora'] ?? 0,
                        'cel1' => $fila_normalizada['cel1'] ?? '',
                        'cel2' => $fila_normalizada['cel2'] ?? '',
                        'cel3' => $fila_normalizada['cel3'] ?? '',
                        'cel4' => $fila_normalizada['cel4'] ?? '',
                        'cel5' => $fila_normalizada['cel5'] ?? '',
                        'cel6' => $fila_normalizada['cel6'] ?? ''
                    ];
                }
            }

            fclose($handle);
            return $datos;
        } catch (Exception $e) {
            error_log("CoordinadorController: Error al leer CSV - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar archivo CSV
     * @param string $file_path Ruta del archivo
     * @return array Resultado de la validación
     */
    public function validarCSV($file_path) {
        $resultado = [
            'success' => false,
            'total_filas' => 0,
            'errores' => [],
            'preview' => []
        ];

        try {
            $datos = $this->leerCSV($file_path);
            if (!$datos) {
                $resultado['errores'][] = 'Error al leer el archivo CSV';
                return $resultado;
            }

            $resultado['total_filas'] = count($datos);
            $resultado['success'] = true;

            // Mostrar preview de las primeras 5 filas
            $resultado['preview'] = array_slice($datos, 0, 5);

            // Validar estructura básica
            $campos_requeridos = ['nit_cxc', 'numero_factura', 'nombre_comercio', 'franja'];
            foreach ($campos_requeridos as $campo) {
                $fila_vacia = false;
                foreach ($datos as $fila) {
                    if (empty($fila[$campo])) {
                        $fila_vacia = true;
                        break;
                    }
                }
                if ($fila_vacia) {
                    $resultado['errores'][] = "Campo requerido '{$campo}' tiene valores vacíos";
                }
            }

        } catch (Exception $e) {
            $resultado['errores'][] = "Error de validación: " . $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Generar plantilla CSV
     * @return string Contenido de la plantilla
     */
    public function generarPlantilla() {
        $campos = [
            'Nit CXC',
            'NUMERO FACTURA', 
            'Nombre del comercio',
            'Dias de Mora Factura',
            'Valor de Factura por Pagar',
            'FRANJA',
            'CEL',
            'MAIL'
        ];

        $ejemplo = [
            '901327994',
            'CBFE8968268',
            'CALMM S A S',
            '299',
            '7350307',
            '7. De 181 a 360 Dias',
            '3052982232',
            'andrea.rojas@saana.com.co'
        ];

        $contenido = implode(',', $campos) . "\n";
        $contenido .= implode(',', $ejemplo) . "\n";

        return $contenido;
    }

    /**
     * Registrar carga CSV en historial
     * @param string $nombre_archivo
     * @param array $resultado
     */
    private function registrarHistorialCarga($nombre_archivo, $resultado) {
        try {
            $datos_historial = [
                'tipo_actividad' => 'carga_csv',
                'descripcion' => "Carga CSV de comercios y facturas: {$nombre_archivo}",
                'archivo_tarea' => $nombre_archivo,
                'usuario_id' => $_SESSION['usuario_id'] ?? 'sistema',
                'base_id' => null,
                'estado' => $resultado['success'] ? 'exitoso' : 'error',
                'detalles' => json_encode([
                    'total_filas' => $resultado['total_filas'],
                    'comercios_creados' => $resultado['comercios_creados'],
                    'facturas_creadas' => $resultado['facturas_creadas'],
                    'errores' => count($resultado['errores'])
                ])
            ];
            
            $this->historial_model->registrar($datos_historial);
        } catch (Exception $e) {
            error_log("CoordinadorController: Error al registrar historial de carga - " . $e->getMessage());
        }
    }

    /**
     * Obtener facturas por comercio
     * @param int $comercio_id
     * @param string|null $estado
     * @return array
     */
    public function obtenerFacturasPorComercio($comercio_id, $estado = null) {
        try {
            return $this->obligacion_model->obtenerPorCliente($comercio_id, $estado);
        } catch (Exception $e) {
            error_log("Error al obtener obligaciones por cliente: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar comercios por término
     * @param string $termino
     * @return array
     */
    public function buscarComercios($termino) {
        try {
            return $this->cliente_model->buscar($termino);
        } catch (Exception $e) {
            error_log("Error al buscar clientes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener facturas con mayor mora
     * @param int $limite
     * @return array
     */
    public function obtenerFacturasMayorMora($limite = 10) {
        try {
            return $this->obligacion_model->obtenerMayorMora($limite);
        } catch (Exception $e) {
            error_log("Error al obtener obligaciones con mayor mora: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener facturas por franja de mora
     * @param string $franja
     * @param int|null $limite
     * @return array
     */
    public function obtenerFacturasPorFranja($franja, $limite = null) {
        try {
            return $this->obligacion_model->obtenerPorFranja($franja, $limite);
        } catch (Exception $e) {
            error_log("Error al obtener obligaciones por franja: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener bases de clientes (comercios)
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerBases($coordinador_cedula) {
        try {
            $conn = getDBConnection();
            $query = "SELECT b.id,
                        b.nombre,
                        b.fecha_creacion,
                        COUNT(DISTINCT c.id) as total_clientes,
                        b.estado
                     FROM bases_clientes b
                     LEFT JOIN clientes c ON b.id = c.base_id
                     WHERE b.estado = 'activo'
                     GROUP BY b.id, b.nombre, b.fecha_creacion, b.estado
                     ORDER BY b.fecha_creacion DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener bases: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de bases
     * @return array
     */
    public function obtenerEstadisticasBases() {
        try {
            $conn = getDBConnection();
            
            // Contar total de bases activas
            $query = "SELECT COUNT(*) as total_bases FROM bases_clientes WHERE estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $total_bases = $stmt->fetch(PDO::FETCH_ASSOC)['total_bases'] ?? 0;
            
            // Contar bases activas (también total_bases en este caso)
            $bases_activas = $total_bases;
            
            // Contar clientes (comercios) totales en todas las bases
            $query = "SELECT COUNT(DISTINCT c.id) as total_clientes 
                     FROM clientes c
                     INNER JOIN bases_clientes b ON c.base_id = b.id
                     WHERE b.estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $total_clientes = $stmt->fetch(PDO::FETCH_ASSOC)['total_clientes'] ?? 0;
            
            return [
                'success' => true,
                'total_bases' => $total_bases,
                'bases_activas' => $bases_activas,
                'total_clientes' => $total_clientes
            ];
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas de bases: " . $e->getMessage());
            return [
                'success' => false,
                'total_bases' => 0,
                'bases_activas' => 0,
                'total_clientes' => 0
            ];
        }
    }

    /**
     * Obtener estadísticas de tareas
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerEstadisticasTareasCompletas($coordinador_cedula) {
        try {
            $resultado = $this->asignacion_asesor_model->obtenerPorCoordinador($coordinador_cedula);
            
            if (!$resultado['success']) {
                return [
                    'success' => false,
                    'total_tareas' => 0,
                    'tareas_pendientes' => 0,
                    'tareas_en_progreso' => 0,
                    'tareas_completadas' => 0,
                    'tareas_canceladas' => 0
                ];
            }
            
            $tareas = $resultado['asignaciones'] ?? [];
            $total_tareas = count($tareas);
            
            $estados = [
                'pendiente' => 0,
                'en_progreso' => 0,
                'completada' => 0,
                'cancelada' => 0
            ];
            
            foreach ($tareas as $tarea) {
                $estado = $tarea['estado'] ?? 'pendiente';
                if (isset($estados[$estado])) {
                    $estados[$estado]++;
                }
            }
            
            return [
                'success' => true,
                'total_tareas' => $total_tareas,
                'tareas_pendientes' => $estados['pendiente'],
                'tareas_en_progreso' => $estados['en_progreso'],
                'tareas_completadas' => $estados['completada'],
                'tareas_canceladas' => $estados['cancelada']
            ];
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas de tareas: " . $e->getMessage());
            return [
                'success' => false,
                'total_tareas' => 0,
                'tareas_pendientes' => 0,
                'tareas_en_progreso' => 0,
                'tareas_completadas' => 0,
                'tareas_canceladas' => 0
            ];
        }
    }

    /**
     * Obtener asesores asignados al coordinador con estadísticas reales
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerAsesores($coordinador_cedula) {
        try {
            $asesores_base = $this->asignacion_model->obtenerAsesoresPorCoordinador($coordinador_cedula);
            $conn = getDBConnection();
            
            $asesores_con_stats = [];
            
            foreach ($asesores_base as $asesor) {
                $asesor_cedula = $asesor['cedula'];
                
                // Contar comercios asignados en tareas pendientes/en_progreso
                $query = "SELECT COUNT(DISTINCT JSON_EXTRACT(aa.clientes_asignados, '$.clientes[*].id')) as asignados
                         FROM asignaciones_asesores aa
                         WHERE aa.asesor_cedula = ?
                         AND aa.estado IN ('pendiente', 'en_progreso')
                         AND JSON_VALID(aa.clientes_asignados) = 1";
                $stmt = $conn->prepare($query);
                $stmt->execute([$asesor_cedula]);
                $clientes_asignados = $stmt->fetch(PDO::FETCH_ASSOC)['asignados'] ?? 0;
                
                // Contar clientes gestionados (con gestiones)
                $query = "SELECT COUNT(DISTINCT g.cliente_id) as gestionados
                         FROM gestiones g
                         WHERE g.asesor_cedula = ?
                         AND g.cliente_id IS NOT NULL";
                $stmt = $conn->prepare($query);
                $stmt->execute([$asesor_cedula]);
                $clientes_gestionados = $stmt->fetch(PDO::FETCH_ASSOC)['gestionados'] ?? 0;
                
                // Obtener última actividad (la tabla gestiones usa fecha_creacion)
                $query = "SELECT MAX(fecha_creacion) as ultima_actividad
                         FROM gestiones
                         WHERE asesor_cedula = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$asesor_cedula]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $ultima_actividad = $result['ultima_actividad'] ?? null;
                
                $asesores_con_stats[] = array_merge($asesor, [
                    'clientes_asignados' => $clientes_asignados,
                    'clientes_gestionados' => $clientes_gestionados,
                    'ultima_actividad' => $ultima_actividad
                ]);
            }
            
            return $asesores_con_stats;
        } catch (Exception $e) {
            error_log("Error al obtener asesores con estadísticas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener detalles completos de un asesor
     * @param string $asesor_cedula
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerDetallesAsesor($asesor_cedula, $coordinador_cedula) {
        try {
            $conn = getDBConnection();
            
            // Verificar que el asesor pertenece al coordinador
            $asesores = $this->asignacion_model->obtenerAsesoresPorCoordinador($coordinador_cedula);
            $asesor_valido = false;
            $asesor_info = null;
            
            foreach ($asesores as $asesor) {
                if ($asesor['cedula'] === $asesor_cedula) {
                    $asesor_valido = true;
                    $asesor_info = $asesor;
                    break;
                }
            }
            
            if (!$asesor_valido) {
                return [
                    'success' => false,
                    'message' => 'Asesor no encontrado o no asignado a este coordinador'
                ];
            }
            
            // Contar comercios asignados
            $query = "SELECT COUNT(DISTINCT JSON_EXTRACT(aa.clientes_asignados, '$.clientes[*].id')) as asignados
                     FROM asignaciones_asesores aa
                     WHERE aa.asesor_cedula = ?
                     AND aa.estado IN ('pendiente', 'en_progreso')
                     AND JSON_VALID(aa.clientes_asignados) = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $clientes_asignados = $stmt->fetch(PDO::FETCH_ASSOC)['asignados'] ?? 0;
            
            // Contar clientes gestionados
            $query = "SELECT COUNT(DISTINCT g.cliente_id) as gestionados
                     FROM gestiones g
                     WHERE g.asesor_cedula = ?
                     AND g.cliente_id IS NOT NULL";
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $clientes_gestionados = $stmt->fetch(PDO::FETCH_ASSOC)['gestionados'] ?? 0;
            
            // Obtener últimas gestiones (usando comercio si existe, sino cliente_id como id de comercio)
            // La tabla gestiones usa fecha_creacion, no fecha_gestion
            $query = "SELECT g.*, 
                     c.nombre as cliente_nombre,
                     c.cc as cliente_cc,
                     g.cliente_id as cliente_id_real
                     FROM gestiones g
                     LEFT JOIN clientes c ON g.cliente_id = c.id
                     WHERE g.asesor_cedula = ?
                     ORDER BY g.fecha_creacion DESC
                     LIMIT 50";
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $gestiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear gestiones con información del cliente
            $gestiones_formateadas = [];
            foreach ($gestiones as $gestion) {
                $cliente_id = $gestion['cliente_id_real'];
                // Si aún no tenemos nombre, obtenerlo del cliente
                if (empty($gestion['cliente_nombre']) && $cliente_id) {
                    $query_cliente = "SELECT nombre, cc FROM clientes WHERE id = ?";
                    $stmt_cliente = $conn->prepare($query_cliente);
                    $stmt_cliente->execute([$cliente_id]);
                    $cliente_data = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
                    if ($cliente_data) {
                        $gestion['cliente_nombre'] = $cliente_data['nombre'];
                        $gestion['cliente_cc'] = $cliente_data['cc'];
                    }
                }
                
                $gestiones_formateadas[] = array_merge($gestion, [
                    'cliente_info' => [
                        'id' => $cliente_id,
                        'nombre' => $gestion['cliente_nombre'] ?? 'N/A',
                        'identificacion' => $gestion['cliente_cc'] ?? 'N/A'
                    ]
                ]);
            }
            
            // Obtener última actividad (la tabla gestiones usa fecha_creacion)
            $query = "SELECT MAX(fecha_creacion) as ultima_actividad
                     FROM gestiones
                     WHERE asesor_cedula = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $ultima_actividad = $result['ultima_actividad'] ?? null;
            
            return [
                'success' => true,
                'asesor' => array_merge($asesor_info, [
                    'clientes_asignados' => $clientes_asignados,
                    'clientes_gestionados' => $clientes_gestionados,
                    'ultima_actividad' => $ultima_actividad
                ]),
                'gestiones' => $gestiones_formateadas
            ];
        } catch (Exception $e) {
            error_log("Error al obtener detalles del asesor: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener detalles: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener clientes disponibles (no asignados) de una base
     * @param int $base_id
     * @return array
     */
    public function obtenerClientesDisponibles($base_id) {
        try {
            $conn = getDBConnection();
            
            // Obtener total de clientes en la base (activos)
            $query = "SELECT COUNT(*) as total FROM clientes WHERE base_id = ? AND estado = 'activo'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Obtener todos los IDs de clientes ya asignados en tareas pendientes o en progreso
            $query = "SELECT clientes_asignados 
                     FROM asignaciones_asesores 
                     WHERE estado IN ('pendiente', 'en_progreso')
                     AND clientes_asignados IS NOT NULL
                     AND JSON_VALID(clientes_asignados) = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $asignaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Extraer todos los IDs de clientes asignados
            $clientes_asignados_ids = [];
            foreach ($asignaciones as $asignacion_json) {
                $asignacion = json_decode($asignacion_json, true);
                if (isset($asignacion['clientes']) && is_array($asignacion['clientes'])) {
                    foreach ($asignacion['clientes'] as $cliente) {
                        if (isset($cliente['id'])) {
                            $clientes_asignados_ids[] = $cliente['id'];
                        }
                    }
                }
            }
            
            // Contar clientes asignados de esta base específica
            $clientes_asignados_base = 0;
            if (!empty($clientes_asignados_ids)) {
                $placeholders = str_repeat('?,', count($clientes_asignados_ids) - 1) . '?';
                $query = "SELECT COUNT(*) as asignados
                         FROM clientes 
                         WHERE base_id = ? 
                         AND id IN ($placeholders)";
                $params = array_merge([$base_id], $clientes_asignados_ids);
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $clientes_asignados_base = $stmt->fetch(PDO::FETCH_ASSOC)['asignados'] ?? 0;
            }
            
            // Calcular disponibles (total - asignados)
            $disponibles = max(0, $total - $clientes_asignados_base);
            
            return [
                'success' => true,
                'total_clientes' => $total,
                'clientes_asignados' => $clientes_asignados_base,
                'clientes_disponibles' => $disponibles
            ];
        } catch (Exception $e) {
            error_log("Error al obtener clientes disponibles: " . $e->getMessage());
            return [
                'success' => false,
                'total_clientes' => 0,
                'clientes_asignados' => 0,
                'clientes_disponibles' => 0
            ];
        }
    }

    /**
     * Crear asignación de clientes a asesor
     * @param array $datos_asignacion
     * @return array
     */
    public function crearAsignacionClientes($datos_asignacion) {
        try {
            $base_id = $datos_asignacion['base_id'] ?? null;
            $asesor_cedula = $datos_asignacion['asesor_cedula'] ?? null;
            $coordinador_cedula = $datos_asignacion['coordinador_cedula'] ?? null;
            // Forzar entero para uso en LIMIT y evitar marcador parametrizado
            $cantidad_clientes = isset($datos_asignacion['cantidad_clientes']) ? (int)$datos_asignacion['cantidad_clientes'] : 0;
            
            if (!$base_id || !$asesor_cedula || !$coordinador_cedula || $cantidad_clientes <= 0) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos para la asignación'
                ];
            }
            
            // Obtener clientes disponibles sin asignar
            $conn = getDBConnection();
            
            // Obtener todos los IDs de clientes ya asignados
            $query = "SELECT clientes_asignados 
                     FROM asignaciones_asesores 
                     WHERE estado IN ('pendiente', 'en_progreso')
                     AND clientes_asignados IS NOT NULL
                     AND JSON_VALID(clientes_asignados) = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $asignaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Extraer todos los IDs de clientes asignados
            $clientes_asignados_ids = [];
            foreach ($asignaciones as $asignacion_json) {
                $asignacion = json_decode($asignacion_json, true);
                if (isset($asignacion['clientes']) && is_array($asignacion['clientes'])) {
                    foreach ($asignacion['clientes'] as $cliente) {
                        if (isset($cliente['id'])) {
                            $clientes_asignados_ids[] = $cliente['id'];
                        }
                    }
                }
            }
            
            // Obtener IDs de clientes disponibles (no asignados)
            // Asegurar que cantidad_clientes sea un entero para usar en LIMIT
            $cantidad_clientes = (int)$cantidad_clientes;
            
            if (!empty($clientes_asignados_ids)) {
                // Crear placeholders para NOT IN
                $placeholders = str_repeat('?,', count($clientes_asignados_ids) - 1) . '?';
                $query = "SELECT c.id 
                         FROM clientes c
                         WHERE c.base_id = ?
                         AND c.estado = 'activo'
                         AND c.id NOT IN ($placeholders)
                         LIMIT $cantidad_clientes";
                $params = array_merge([$base_id], $clientes_asignados_ids);
            } else {
                $query = "SELECT c.id 
                         FROM clientes c
                         WHERE c.base_id = ?
                         AND c.estado = 'activo'
                         LIMIT $cantidad_clientes";
                $params = [$base_id];
            }
            
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $clientes_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($clientes_ids) < $cantidad_clientes) {
                return [
                    'success' => false,
                    'message' => 'No hay suficientes clientes disponibles. Disponibles: ' . count($clientes_ids)
                ];
            }
            
            // Formatear clientes asignados como JSON
            $clientes_asignados = ['clientes' => array_map(function($id) {
                return ['id' => $id];
            }, $clientes_ids)];
            
            // Crear la asignación
            $datos_tarea = [
                'coordinador_cedula' => $coordinador_cedula,
                'asesor_cedula' => $asesor_cedula,
                'estado' => 'pendiente',
                'clientes_asignados' => json_encode($clientes_asignados),
                'base_id' => $base_id
            ];
            
            return $this->crearTarea($datos_tarea);
            
        } catch (Exception $e) {
            error_log("Error al crear asignación de clientes: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear asignación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener asignaciones existentes para un coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerAsignacionesExistentes($coordinador_cedula) {
        try {
            return $this->obtenerTareas($coordinador_cedula);
        } catch (Exception $e) {
            error_log("Error al obtener asignaciones existentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener clientes de una base
     * @param int $base_id
     * @return array
     */
    public function obtenerClientesPorBase($base_id) {
        try {
            $conn = getDBConnection();
            $query = "SELECT c.id, c.cc AS IDENTIFICACION, c.nombre AS 'NOMBRE CONTRATANTE', 
                     c.cel1 AS CELULAR, c.cel2, c.cel3, c.cel4, c.cel5, c.cel6, c.estado
                     FROM clientes c
                     WHERE c.base_id = ?
                     ORDER BY c.nombre ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'clientes' => $clientes
            ];
        } catch (Exception $e) {
            error_log("Error al obtener clientes por base: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage(),
                'clientes' => []
            ];
        }
    }

    /**
     * Guardar acceso de asesores a una base
     * @param array $datos_acceso
     * @return array
     */
    public function guardarAccesoBase($datos_acceso) {
        try {
            $base_id = $datos_acceso['base_id'] ?? null;
            $asesores = $datos_acceso['asesores'] ?? [];
            
            error_log("guardarAccesoBase - Datos recibidos: " . json_encode($datos_acceso));
            
            if (!$base_id) {
                error_log("guardarAccesoBase - Error: base_id no proporcionado");
                return [
                    'success' => false,
                    'message' => 'ID de base no proporcionado'
                ];
            }
            
            // Asegurar que asesores sea un array
            if (!is_array($asesores)) {
                if (is_string($asesores)) {
                    $asesores = json_decode($asesores, true);
                    if ($asesores === null) {
                        // Si falla el decode, intentar como string simple
                        $asesores = [$asesores];
                    }
                } else {
                    $asesores = [];
                }
            }
            
            // Filtrar valores vacíos
            $asesores = array_filter($asesores, function($a) {
                return !empty($a);
            });
            
            error_log("guardarAccesoBase - Base ID: {$base_id}, Asesores procesados: " . json_encode($asesores));
            
            $conn = getDBConnection();
            
            // Usar siempre asignaciones_base_clientes (la tabla asignaciones_base fue eliminada por ser obsoleta)
            $tabla_nombre = 'asignaciones_base_clientes';
            
            // Verificar que la tabla existe
            $query = "SHOW TABLES LIKE '{$tabla_nombre}'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $tabla_existe = $stmt->rowCount() > 0;
            
            if (!$tabla_existe) {
                error_log("guardarAccesoBase - ERROR: La tabla {$tabla_nombre} no existe");
                return [
                    'success' => false,
                    'message' => "La tabla {$tabla_nombre} no existe. Ejecute el script SQL para crearla."
                ];
            }
            
            error_log("guardarAccesoBase - Usando tabla: {$tabla_nombre}");
            
            // Si no hay asesores para activar, retornar aquí (no desactivar nada)
            if (empty($asesores)) {
                error_log("guardarAccesoBase - No hay asesores para activar");
                return [
                    'success' => false,
                    'message' => "Por favor seleccione al menos un asesor para otorgar acceso"
                ];
            }
            
            // Activar o crear los accesos seleccionados (SIN desactivar los existentes)
            // Esto permite múltiples asesores simultáneamente
            $asesores_actualizados = 0;
            $asesores_creados = 0;
            $asesores_activados = 0;
            $errores = [];
            
            foreach ($asesores as $asesor_cedula) {
                try {
                    // Verificar que el asesor existe
                    $query = "SELECT cedula FROM usuarios WHERE cedula = ? AND rol = 'asesor'";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$asesor_cedula]);
                    $asesor_existe = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$asesor_existe) {
                        error_log("guardarAccesoBase - Asesor {$asesor_cedula} no existe");
                        $errores[] = "Asesor {$asesor_cedula} no encontrado";
                        continue;
                    }
                    
                    // Verificar si ya existe el acceso
                    $query = "SELECT id, estado FROM {$tabla_nombre} 
                             WHERE base_id = ? AND asesor_cedula = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$base_id, $asesor_cedula]);
                    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existe) {
                        // Si ya existe y está activo, no hacer nada (ya tiene acceso)
                        if ($existe['estado'] === 'activa') {
                            error_log("guardarAccesoBase - Asesor {$asesor_cedula} ya tiene acceso activo, manteniendo acceso");
                            $asesores_actualizados++;
                        } else {
                            // Si existe pero está inactivo, activarlo
                            $query = "UPDATE {$tabla_nombre} 
                                     SET estado = 'activa', fecha_actualizacion = NOW()
                                     WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $resultado = $stmt->execute([$existe['id']]);
                            if ($resultado) {
                                $asesores_activados++;
                                $asesores_actualizados++;
                                error_log("guardarAccesoBase - Reactivado acceso para asesor {$asesor_cedula}");
                            } else {
                                error_log("guardarAccesoBase - ERROR al reactivar acceso para asesor {$asesor_cedula}");
                                $errores[] = "Error al reactivar acceso para asesor {$asesor_cedula}";
                            }
                        }
                    } else {
                        // Crear nuevo acceso
                        $query = "INSERT INTO {$tabla_nombre} (base_id, asesor_cedula, estado, fecha_asignacion)
                                 VALUES (?, ?, 'activa', NOW())";
                        $stmt = $conn->prepare($query);
                        $resultado = $stmt->execute([$base_id, $asesor_cedula]);
                        if ($resultado) {
                            $asesores_creados++;
                            $asesores_actualizados++;
                            error_log("guardarAccesoBase - Creado nuevo acceso para asesor {$asesor_cedula}");
                        } else {
                            error_log("guardarAccesoBase - ERROR al crear acceso para asesor {$asesor_cedula}");
                            $errores[] = "Error al crear acceso para asesor {$asesor_cedula}";
                        }
                    }
                } catch (Exception $e) {
                    error_log("guardarAccesoBase - Error procesando asesor {$asesor_cedula}: " . $e->getMessage());
                    $errores[] = "Error con asesor {$asesor_cedula}: " . $e->getMessage();
                }
            }
            
            // Construir mensaje detallado
            $mensaje = "Acceso otorgado exitosamente. ";
            if ($asesores_creados > 0) {
                $mensaje .= "{$asesores_creados} nuevo(s) acceso(s) creado(s). ";
            }
            if ($asesores_activados > 0) {
                $mensaje .= "{$asesores_activados} acceso(s) reactivado(s). ";
            }
            if ($asesores_actualizados > 0 && $asesores_creados == 0 && $asesores_activados == 0) {
                $mensaje .= "{$asesores_actualizados} asesor(es) ya tenían acceso activo. ";
            }
            $mensaje = rtrim($mensaje, ' ');
            
            if (!empty($errores)) {
                $mensaje .= ". Errores: " . implode(", ", $errores);
            }
            
            error_log("guardarAccesoBase - Resultado final: {$asesores_actualizados} asesores procesados ({$asesores_creados} nuevos, {$asesores_activados} reactivados)");
            
            return [
                'success' => true,
                'message' => $mensaje,
                'asesores_actualizados' => $asesores_actualizados,
                'asesores_creados' => $asesores_creados,
                'asesores_reactivados' => $asesores_activados,
                'errores' => $errores
            ];
        } catch (Exception $e) {
            error_log("Error al guardar acceso a base: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Error al guardar acceso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener asesores con acceso a una base
     * @param int $base_id
     * @return array
     */
    public function obtenerAsesoresConAccesoBase($base_id) {
        try {
            $conn = getDBConnection();
            
            // Usar siempre asignaciones_base_clientes (la tabla asignaciones_base fue eliminada)
            $tabla_nombre = 'asignaciones_base_clientes';
            
            $query = "SELECT ab.asesor_cedula, u.nombre_completo, u.usuario
                     FROM {$tabla_nombre} ab
                     INNER JOIN usuarios u ON ab.asesor_cedula = u.cedula
                     WHERE ab.base_id = ? AND ab.estado = 'activa'";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            $asesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'asesores' => $asesores
            ];
        } catch (Exception $e) {
            error_log("Error al obtener asesores con acceso: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener asesores: ' . $e->getMessage(),
                'asesores' => []
            ];
        }
    }

    /**
     * Eliminar una base de datos
     * @param int $base_id
     * @return array
     */
    public function eliminarBase($base_id) {
        try {
            $conn = getDBConnection();
            
            // Verificar que la base existe
            $query = "SELECT nombre FROM bases_clientes WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$base) {
                return [
                    'success' => false,
                    'message' => 'Base no encontrada'
                ];
            }
            
            // Eliminar asignaciones de acceso (usar siempre asignaciones_base_clientes)
            $tabla_nombre = 'asignaciones_base_clientes';
            
            $query = "DELETE FROM {$tabla_nombre} WHERE base_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            
            // Eliminar facturas relacionadas (cascade desde comercio)
            // Eliminar comercios (que eliminará facturas por CASCADE)
            $query = "DELETE FROM clientes WHERE base_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            
            // Eliminar la base
            $query = "DELETE FROM bases_clientes WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$base_id]);
            
            // Registrar en historial
            $this->historial_model->registrar([
                'usuario_id' => $_SESSION['usuario_id'] ?? 'sistema',
                'tipo' => 'eliminar_base',
                'descripcion' => "Base '{$base['nombre']}' eliminada con todos sus clientes",
                'tabla_afectada' => 'bases_clientes',
                'registro_id' => $base_id
            ]);
            
            return [
                'success' => true,
                'message' => 'Base eliminada exitosamente'
            ];
        } catch (Exception $e) {
            error_log("Error al eliminar base: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar base: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Obtener tareas del coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerTareas($coordinador_cedula) {
        try {
            $resultado = $this->asignacion_asesor_model->obtenerPorCoordinador($coordinador_cedula);
            // Retornar array plano de asignaciones
            return $resultado['asignaciones'] ?? [];
        } catch (Exception $e) {
            error_log("Error al obtener tareas del coordinador: " . $e->getMessage());
            return [];
        }
    }




    /**
     * Generar reporte del coordinador
     * @param string $coordinador_cedula
     * @param string $tipo_reporte
     * @return array
     */
    public function generarReporte($coordinador_cedula, $tipo_reporte = 'general') {
        $estadisticas = $this->obtenerEstadisticas($coordinador_cedula);
        $asesores = $this->obtenerAsesores($coordinador_cedula);
        $comercios = $this->obtenerComercios($coordinador_cedula);
        $facturas = $this->obtenerFacturas($coordinador_cedula);
        
        return [
            'success' => true,
            'data' => [
                'estadisticas' => $estadisticas,
                'asesores' => $asesores,
                'comercios' => $comercios,
                'facturas' => $facturas,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'tipo_reporte' => $tipo_reporte
            ]
        ];
    }



    /**
     * Crear una nueva tarea para un asesor
     * @param array $datos_tarea
     * @return array
     */
    public function crearTarea($datos_tarea) {
        try {
            // Validar que el asesor esté asignado al coordinador
            $asesores = $this->asignacion_model->obtenerAsesoresPorCoordinador($datos_tarea['coordinador_cedula']);
            $asesor_valido = false;
            $asesor_nombre = '';
            
            foreach ($asesores as $asesor) {
                if ($asesor['cedula'] === $datos_tarea['asesor_cedula']) {
                    $asesor_valido = true;
                    $asesor_nombre = $asesor['nombre_completo'];
                    break;
                }
            }

            if (!$asesor_valido) {
                return [
                    'success' => false,
                    'message' => 'El asesor no está asignado a este coordinador'
                ];
            }

            $resultado = $this->asignacion_asesor_model->crear($datos_tarea);
            
            // Si la tarea se creó exitosamente, registrar en historial
            if ($resultado['success']) {
                $clientes_asignados = json_decode($datos_tarea['clientes_asignados'], true);
                $total_clientes = isset($clientes_asignados['clientes']) ? count($clientes_asignados['clientes']) : 0;
                
                $this->registrarHistorialActividad('asignacion_tarea', 
                    "Tarea asignada a {$asesor_nombre} con {$total_clientes} clientes", 
                    'Tarea de Asignación', 
                    null, 
                    'exitoso',
                    json_encode([
                        'asesor_cedula' => $datos_tarea['asesor_cedula'],
                        'asesor_nombre' => $asesor_nombre,
                        'total_clientes' => $total_clientes,
                        'estado' => $datos_tarea['estado']
                    ])
                );
            }
            
            return $resultado;

        } catch (Exception $e) {
            error_log("Error al crear tarea: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear tarea: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar una tarea existente
     * @param int $tarea_id
     * @param array $datos_actualizacion
     * @return array
     */
    public function actualizarTarea($tarea_id, $datos_actualizacion) {
        try {
            return $this->asignacion_asesor_model->actualizar($tarea_id, $datos_actualizacion);
        } catch (Exception $e) {
            error_log("Error al actualizar tarea: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar tarea: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una tarea
     * @param int $tarea_id
     * @return array
     */
    public function eliminarTarea($tarea_id) {
        try {
            return $this->asignacion_asesor_model->eliminar($tarea_id);
        } catch (Exception $e) {
            error_log("Error al eliminar tarea: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar tarea: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cambiar estado de una tarea
     * @param int $tarea_id
     * @param string $nuevo_estado
     * @param string $resultado Opcional
     * @return array
     */
    public function cambiarEstadoTarea($tarea_id, $nuevo_estado, $resultado = null) {
        try {
            // El modelo solo acepta 2 parámetros, el tercero ($resultado) se ignora por ahora
            return $this->asignacion_asesor_model->cambiarEstado($tarea_id, $nuevo_estado);
        } catch (Exception $e) {
            error_log("Error al cambiar estado de tarea: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de tareas del coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerEstadisticasTareas($coordinador_cedula) {
        try {
            $resultado = $this->asignacion_asesor_model->obtenerEstadisticas($coordinador_cedula);
            
            if (!$resultado['success']) {
                return [
                    'total_tareas' => 0,
                    'tareas_pendientes' => 0,
                    'tareas_en_progreso' => 0,
                    'tareas_completadas' => 0,
                    'tareas_canceladas' => 0,
                    'tareas_pausadas' => 0,
                    'tareas_vencidas' => 0,
                    'tareas_por_vencer' => 0,
                    'tiempo_promedio_minutos' => 0,
                    'valor_total_logrado' => 0,
                    'valor_total_objetivo' => 0
                ];
            }
            
            $estadisticas = $resultado['estadisticas'] ?? [];
            
            // Mapear los campos del modelo al formato esperado
            return [
                'total_tareas' => (int)($estadisticas['total_asignaciones'] ?? 0),
                'tareas_pendientes' => (int)($estadisticas['pendientes'] ?? 0),
                'tareas_en_progreso' => (int)($estadisticas['en_progreso'] ?? 0),
                'tareas_completadas' => (int)($estadisticas['completadas'] ?? 0),
                'tareas_canceladas' => (int)($estadisticas['canceladas'] ?? 0),
                'tareas_pausadas' => 0,
                'tareas_vencidas' => 0,
                'tareas_por_vencer' => 0,
                'tiempo_promedio_minutos' => 0,
                'valor_total_logrado' => 0,
                'valor_total_objetivo' => 0
            ];
        } catch (Exception $e) {
            error_log("Error al obtener estadísticas de tareas: " . $e->getMessage());
            return [
                'total_tareas' => 0,
                'tareas_pendientes' => 0,
                'tareas_en_progreso' => 0,
                'tareas_completadas' => 0,
                'tareas_canceladas' => 0,
                'tareas_pausadas' => 0,
                'tareas_vencidas' => 0,
                'tareas_por_vencer' => 0,
                'tiempo_promedio_minutos' => 0,
                'valor_total_logrado' => 0,
                'valor_total_objetivo' => 0
            ];
        }
    }

    /**
     * Obtener una tarea específica por ID
     * @param int $tarea_id
     * @return array|null
     */
    public function obtenerTareaPorId($tarea_id) {
        try {
            return $this->asignacion_asesor_model->obtenerPorId($tarea_id);
        } catch (Exception $e) {
            error_log("Error al obtener tarea por ID: " . $e->getMessage());
            return null;
        }
    }


    /**
     * Obtener todas las tareas asignadas por el coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerTareasCoordinador($coordinador_cedula) {
        try {
            return $this->asignacion_asesor_model->obtenerPorCoordinador($coordinador_cedula);
        } catch (Exception $e) {
            error_log("Error al obtener tareas del coordinador: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'asignaciones' => []
            ];
        }
    }
    
    /**
     * Completar una asignación de tarea
     */
    public function completarAsignacion($asignacion_id) {
        try {
            // Obtener información de la asignación antes de completarla
            $asignacion_info = $this->asignacion_asesor_model->obtenerPorId($asignacion_id);
            
            $resultado = $this->asignacion_asesor_model->completar($asignacion_id);
            
            // Si se completó exitosamente, registrar en historial
            if ($resultado['success'] && $asignacion_info['success']) {
                $asignacion = $asignacion_info['asignacion'];
                
                // Obtener nombre del asesor
                $query_asesor = "SELECT nombre_completo FROM usuarios WHERE cedula = ?";
                $stmt_asesor = getDBConnection()->prepare($query_asesor);
                $stmt_asesor->execute([$asignacion['asesor_cedula']]);
                $asesor = $stmt_asesor->fetch(PDO::FETCH_ASSOC);
                $asesor_nombre = $asesor ? $asesor['nombre_completo'] : 'Asesor';
                
                $this->registrarHistorialActividad('completar_tarea', 
                    "Tarea completada por {$asesor_nombre}", 
                    'Tarea Completada', 
                    null, 
                    'exitoso',
                    json_encode([
                        'asignacion_id' => $asignacion_id,
                        'asesor_cedula' => $asignacion['asesor_cedula'],
                        'asesor_nombre' => $asesor_nombre,
                        'fecha_completada' => date('Y-m-d H:i:s')
                    ])
                );
            }
            
            return $resultado;
        } catch (Exception $e) {
            error_log("Error al completar asignación: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar actividad en el historial
     * @param string $tipo_actividad
     * @param string $descripcion
     * @param string $archivo_tarea
     * @param int $base_id
     * @param string $estado
     * @param string $detalles
     */
    private function registrarHistorialActividad($tipo_actividad, $descripcion, $archivo_tarea = '', $base_id = null, $estado = 'exitoso', $detalles = '') {
        try {
            $historial_model = new Historial();
            
            $historial_data = [
                'tipo_actividad' => $tipo_actividad,
                'descripcion' => $descripcion,
                'archivo_tarea' => $archivo_tarea,
                'usuario_id' => $_SESSION['usuario_id'] ?? 'sistema',
                'base_id' => $base_id,
                'estado' => $estado,
                'detalles' => $detalles
            ];
            
            $historial_model->registrar($historial_data);
        } catch (Exception $e) {
            error_log("Error al registrar historial: " . $e->getMessage());
        }
    }

    
    
    /**
     * Generar reporte CSV de comercios
     * @return void
     */
    public function generarReporteComercios() {
        try {
            $comercios = $this->cliente_model->obtenerTodos();
            
            // Generar nombre del archivo
            $nombre_archivo = 'reporte_comercios_' . date('Y-m-d_His') . '.csv';
            
            // Configurar headers para descarga
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Abrir salida para CSV
            $output = fopen('php://output', 'w');
            
            // Agregar BOM para Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados del CSV
            $encabezados = [
                'ID',
                'NIT CXC',
                'Nombre del Comercio',
                'Celular',
                'Email',
                'Estado',
                'Fecha Creación',
                'Fecha Actualización'
            ];
            fputcsv($output, $encabezados);
            
            // Datos
            foreach ($comercios as $comercio) {
                fputcsv($output, [
                    $comercio['id'] ?? '',
                    $comercio['nit_cxc'] ?? '',
                    $comercio['nombre_comercio'] ?? '',
                    $comercio['cel'] ?? '',
                    $comercio['email'] ?? '',
                    $comercio['estado'] ?? '',
                    $comercio['fecha_creacion'] ?? '',
                    $comercio['fecha_actualizacion'] ?? ''
                ]);
            }
            
            fclose($output);
            exit();
            
        } catch (Exception $e) {
            error_log("Error al generar reporte de comercios: " . $e->getMessage());
            echo "Error al generar reporte: " . $e->getMessage();
        }
    }

    /**
     * Generar reporte CSV de facturas
     * @return void
     */
    public function generarReporteFacturas() {
        try {
            $facturas = $this->obligacion_model->obtenerTodos();
            
            // Generar nombre del archivo
            $nombre_archivo = 'reporte_facturas_' . date('Y-m-d_His') . '.csv';
            
            // Configurar headers para descarga
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Abrir salida para CSV
            $output = fopen('php://output', 'w');
            
            // Agregar BOM para Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Encabezados del CSV
            $encabezados = [
                'ID',
                'Número Factura',
                'Comercio ID',
                'Nombre Comercio',
                'Días Mora',
                'Franja',
                'Valor Factura',
                'Estado',
                'Fecha Creación',
                'Fecha Actualización'
            ];
            fputcsv($output, $encabezados);
            
            // Datos
            foreach ($facturas as $factura) {
                fputcsv($output, [
                    $factura['id'] ?? '',
                    $factura['numero_obligacion'] ?? '',
                    $factura['cliente_id'] ?? '',
                    $factura['nombre_cliente'] ?? '',
                    $factura['dias_mora'] ?? '',
                    $factura['franja'] ?? '',
                    $factura['saldo_total'] ?? '',
                    $factura['estado'] ?? '',
                    $factura['fecha_creacion'] ?? '',
                    $factura['fecha_actualizacion'] ?? ''
                ]);
            }
            
            fclose($output);
            exit();
            
        } catch (Exception $e) {
            error_log("Error al generar reporte de facturas: " . $e->getMessage());
            echo "Error al generar reporte: " . $e->getMessage();
        }
    }
}
?>
