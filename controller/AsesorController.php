<?php
require_once 'config.php';
require_once 'models/Usuario.php';
require_once 'models/Cliente.php';
require_once 'models/Contrato.php';
require_once 'models/Asignacion.php';
require_once 'models/Gestion.php';

class AsesorController {
    private $usuario_model;
    private $cliente_model;
    private $contrato_model;
    private $asignacion_model;

    public function __construct() {
        $this->usuario_model = new Usuario();
        $this->cliente_model = new Cliente();
        $this->contrato_model = new Contrato();
        $this->asignacion_model = new Asignacion();
    }

    /**
     * Detecta qué columnas celN existen en la tabla clientes (cel1..cel10 por defecto).
     * Evita errores si el esquema todavía no tiene todas las columnas.
     *
     * @param PDO $conn
     * @param int $max
     * @return array<string>
     */
    /** Cache de columnas cel para no consultar INFORMATION_SCHEMA en cada request */
    private static $columnasCelCache = null;

    /**
     * El asesor solo debe ver/gestionar clientes cuya base tiene acceso activo y la base está activa en el sistema.
     */
    private function asesorTieneAccesoActivoABase(PDO $conn, string $asesor_cedula, $base_id): bool {
        if ($base_id === null || $base_id === '' || (int) $base_id <= 0) {
            return false;
        }
        $sql = "SELECT 1 FROM asignaciones_base_clientes ab
                INNER JOIN bases_clientes bc ON bc.id = ab.base_id AND bc.estado = 'activo'
                WHERE ab.base_id = ?
                  AND CAST(ab.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                  AND ab.estado = 'activa'
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([(int) $base_id, $asesor_cedula]);
        return (bool) $stmt->fetch();
    }

    private function getColumnasCelDisponibles($conn, $max = 10) {
        if (self::$columnasCelCache !== null) {
            return array_slice(self::$columnasCelCache, 0, $max);
        }
        $max = (int)$max;
        if ($max < 1) { $max = 1; }
        if ($max > 50) { $max = 50; }

        $candidatas = [];
        for ($i = 1; $i <= 50; $i++) {
            $candidatas[] = "cel{$i}";
        }

        $placeholders = implode(',', array_fill(0, count($candidatas), '?'));
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'clientes'
                  AND COLUMN_NAME IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($candidatas);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $set = [];
        foreach ($rows as $col) {
            $set[(string)$col] = true;
        }

        $existentes = [];
        foreach ($candidatas as $col) {
            if (isset($set[$col])) {
                $existentes[] = $col;
            }
        }
        self::$columnasCelCache = $existentes;
        return array_slice($existentes, 0, $max);
    }

    /**
     * Obtener estadísticas del asesor
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerEstadisticas($asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Estadísticas básicas
            $stats = [
                'clientes_asignados' => 0,
                'clientes_gestionados' => 0,
                'clientes_pendientes' => 0,
                'tareas_completadas' => 0,
                'llamadas_realizadas' => 0,
                'contactos_exitosos' => 0,
                'promesas_pago' => 0,
                'valor_recuperado' => 0,
                'meta_mensual' => 0,
                'puntualidad' => 0
            ];

            // 1. Obtener clientes asignados por TAREAS PENDIENTES (no por acceso a bases)
            $query_tareas_clientes = "SELECT COUNT(DISTINCT c.id) as total_clientes
                                        FROM clientes c
                                        INNER JOIN asignaciones_asesores aa 
                                          ON aa.clientes_asignados IS NOT NULL
                                         AND JSON_VALID(aa.clientes_asignados) = 1
                                         AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(c.id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                                        WHERE CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR) 
                                          AND aa.estado != 'completada'";
            
            $stmt_tareas_clientes = $conn->prepare($query_tareas_clientes);
            $stmt_tareas_clientes->execute([$asesor_cedula]);
            $result_tareas_clientes = $stmt_tareas_clientes->fetch(PDO::FETCH_ASSOC);
            $stats['clientes_asignados'] = $result_tareas_clientes['total_clientes'] ?? 0;

            // 2. Obtener tareas completadas (REAL)
            $query_tareas_completadas = "SELECT COUNT(*) as total_tareas 
                                       FROM asignaciones_asesores aa
                                       WHERE CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR) AND aa.estado = 'completada'";
            $stmt_tareas_completadas = $conn->prepare($query_tareas_completadas);
            $stmt_tareas_completadas->execute([$asesor_cedula]);
            $result_tareas_completadas = $stmt_tareas_completadas->fetch(PDO::FETCH_ASSOC);
            $stats['tareas_completadas'] = $result_tareas_completadas['total_tareas'] ?? 0;

            // 3. Obtener llamadas realizadas (REAL) - usando canal_contacto
            $query_llamadas = "SELECT COUNT(*) as total_llamadas 
                             FROM gestiones g
                             WHERE g.asesor_cedula = ? AND g.canal_contacto = 'llamada'";
            $stmt_llamadas = $conn->prepare($query_llamadas);
            $stmt_llamadas->execute([$asesor_cedula]);
            $result_llamadas = $stmt_llamadas->fetch(PDO::FETCH_ASSOC);
            $stats['llamadas_realizadas'] = $result_llamadas['total_llamadas'] ?? 0;

            // 4. Obtener contactos exitosos (REAL) - usando nivel1_tipo = '1' (contactado)
            $query_contactos = "SELECT COUNT(*) as total_contactos 
                              FROM gestiones g
                              WHERE g.asesor_cedula = ? AND g.nivel1_tipo = '1'";
            $stmt_contactos = $conn->prepare($query_contactos);
            $stmt_contactos->execute([$asesor_cedula]);
            $result_contactos = $stmt_contactos->fetch(PDO::FETCH_ASSOC);
            $stats['contactos_exitosos'] = $result_contactos['total_contactos'] ?? 0;

            // 5. Obtener promesas de pago (REAL) - usando nivel2_clasificacion que contenga 'promesa'
            $query_promesas = "SELECT COUNT(*) as total_promesas 
                             FROM gestiones g
                             WHERE g.asesor_cedula = ? AND g.nivel2_clasificacion LIKE '%promesa%'";
            $stmt_promesas = $conn->prepare($query_promesas);
            $stmt_promesas->execute([$asesor_cedula]);
            $result_promesas = $stmt_promesas->fetch(PDO::FETCH_ASSOC);
            $stats['promesas_pago'] = $result_promesas['total_promesas'] ?? 0;

            // 6. Obtener valor recuperado - calcular desde contratos si hay datos
            // Por ahora usar 0 ya que no hay campo específico en gestiones
            $stats['valor_recuperado'] = 0;

            // 7. CORRECCIÓN: Obtener clientes gestionados (clientes únicos con al menos una gestión)
            // Usar la misma lógica que en CoordinadorController para consistencia
            $query_gestionados = "SELECT COUNT(DISTINCT g.cliente_id) as gestionados
                                 FROM gestiones g
                                 WHERE CAST(g.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                                 AND g.cliente_id IS NOT NULL";
            $stmt_gestionados = $conn->prepare($query_gestionados);
            $stmt_gestionados->execute([$asesor_cedula]);
            $result_gestionados = $stmt_gestionados->fetch(PDO::FETCH_ASSOC);
            $total_gestionados = $result_gestionados['gestionados'] ?? 0;
            
            // 8. Calcular clientes gestionados de los ASIGNADOS (para calcular pendientes correctamente)
            // Necesitamos contar solo los clientes gestionados que están en las tareas asignadas
            $query_gestionados_asignados = "SELECT COUNT(DISTINCT g.cliente_id) as gestionados
                                           FROM gestiones g
                                           INNER JOIN asignaciones_asesores aa 
                                             ON aa.clientes_asignados IS NOT NULL
                                            AND JSON_VALID(aa.clientes_asignados) = 1
                                            AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(g.cliente_id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                                           WHERE CAST(g.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                                           AND CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                                           AND aa.estado != 'completada'
                                           AND g.cliente_id IS NOT NULL";
            $stmt_gestionados_asignados = $conn->prepare($query_gestionados_asignados);
            $stmt_gestionados_asignados->execute([$asesor_cedula, $asesor_cedula]);
            $result_gestionados_asignados = $stmt_gestionados_asignados->fetch(PDO::FETCH_ASSOC);
            $clientes_gestionados_asignados = $result_gestionados_asignados['gestionados'] ?? 0;
            
            // Mostrar el total de gestionados (puede incluir clientes no asignados)
            $stats['clientes_gestionados'] = $total_gestionados;
            
            // Calcular pendientes: asignados - gestionados (solo de los asignados)
            $stats['clientes_pendientes'] = max(0, $stats['clientes_asignados'] - $clientes_gestionados_asignados);

            // 9. Meta mensual (configuración fija)
            $stats['meta_mensual'] = 3000000;

            // 10. Puntualidad (simulado por ahora)
            $stats['puntualidad'] = rand(85, 100);

            return $stats;

        } catch (Exception $e) {
            error_log("Error al obtener estadísticas del asesor: " . $e->getMessage());
            return [
                'clientes_asignados' => 0,
                'clientes_gestionados' => 0,
                'clientes_pendientes' => 0,
                'tareas_completadas' => 0,
                'llamadas_realizadas' => 0,
                'contactos_exitosos' => 0,
                'promesas_pago' => 0,
                'valor_recuperado' => 0,
                'meta_mensual' => 0,
                'puntualidad' => 0
            ];
        }
    }

    /**
     * Obtener clientes asignados al asesor por TAREAS (no por acceso a bases)
     * @param string $asesor_cedula
     * @param string $tipo_acceso
     * @param array $filtros
     * @return array
     */
    public function obtenerClientes($asesor_cedula, $tipo_acceso = 'todos', $filtros = []) {
        try {
            $conn = getDBConnection();
            
            if ($tipo_acceso === 'solo_tareas') {
                // Clientes asignados por tareas específicas
                // Por defecto, solo mostrar clientes NO gestionados si no hay filtros específicos
                $mostrar_solo_no_gestionados = empty($filtros['gestionado']) && empty($filtros['contactado']) && empty($filtros['fecha']);
                
                $query = "SELECT c.id as ID_COMERCIO,
                               c.id as id,
                               c.cc as NIT_CXC,
                               c.cc as nit_cxc,
                               c.nombre as NOMBRE_COMERCIO,
                               c.nombre as nombre_comercio,
                               c.cel1 as CEL,
                               c.cel1 as cel,
                                MAX(aa.fecha_asignacion) as fecha_tarea,
                                MAX(CASE WHEN g.id IS NOT NULL THEN 1 ELSE 0 END) as gestionado_flag,
                                MAX(CASE WHEN g.id IS NOT NULL AND g.nivel1_tipo = '1' THEN 1 ELSE 0 END) as contactado_flag,
                                MAX(CASE WHEN ? IS NULL OR (g.fecha_creacion >= ? AND g.fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)) THEN 1 ELSE 0 END) as en_fecha_flag
                        FROM clientes c
                        INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                        INNER JOIN asignaciones_asesores aa
                          ON aa.clientes_asignados IS NOT NULL
                         AND JSON_VALID(aa.clientes_asignados) = 1
                         AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(c.id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                         AND CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                         AND aa.estado != 'completada'
                        INNER JOIN asignaciones_base_clientes ab
                          ON ab.base_id = c.base_id
                         AND CAST(ab.asesor_cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR)
                         AND ab.estado = 'activa'
                        LEFT JOIN gestiones g ON g.cliente_id = c.id AND CAST(g.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                        WHERE 1=1";
                
                // Si no hay filtros específicos, filtrar solo clientes NO gestionados
                if ($mostrar_solo_no_gestionados) {
                    $query .= " AND g.id IS NULL";
                }
                
                $query .= " GROUP BY c.id, c.cc, c.nombre, c.cel1
                        HAVING 1=1";

                // Construir filtros
                $havingClauses = [];
                $params = [
                    // para en_fecha_flag: ? ? ?
                    ($filtros['fecha'] ?? null) ?: null,
                    ($filtros['fecha'] ?? null) ?: null,
                    ($filtros['fecha'] ?? null) ?: null,
                    // join gestiones param asesor
                    $asesor_cedula,
                    // where asesor
                    $asesor_cedula
                ];
                if (!empty($filtros['gestionado'])) {
                    if ($filtros['gestionado'] === 'gestionado') { $havingClauses[] = 'gestionado_flag = 1'; }
                    if ($filtros['gestionado'] === 'no_gestionado') { $havingClauses[] = 'gestionado_flag = 0'; }
                }
                if (!empty($filtros['contactado'])) {
                    if ($filtros['contactado'] === 'contactado') { $havingClauses[] = 'contactado_flag = 1'; }
                    if ($filtros['contactado'] === 'no_contactado') { $havingClauses[] = 'contactado_flag = 0'; }
                }
                if (!empty($filtros['fecha'])) {
                    $havingClauses[] = 'en_fecha_flag = 1';
                }
                if (count($havingClauses) > 0) {
                    $query .= ' AND ' . '1=1'; // noop
                }
                $query .= ' ORDER BY c.nombre';
                $limit = defined('ASESOR_DASHBOARD_LIMIT_CLIENTES') ? (int) ASESOR_DASHBOARD_LIMIT_CLIENTES : 500;
                if ($limit > 0) {
                    $query .= ' LIMIT ' . $limit;
                }

                // Agregar HAVING si hay
                if (count($havingClauses) > 0) {
                    $query = str_replace('HAVING 1=1', 'HAVING ' . implode(' AND ', $havingClauses), $query);
                } else {
                    $query = str_replace('HAVING 1=1', '', $query);
                }

                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                // Todos los clientes (tareas específicas + acceso a bases)
                $query = "(SELECT DISTINCT c.id as ID_CLIENTE,
                                   'CC' as TIPO_IDENTIFICACION,
                                   c.cc as IDENTIFICACION,
                                   c.nombre as `NOMBRE CONTRATANTE`,
                                   '' as CIUDAD,
                                   c.cel1 as `TEL 1`,
                                   c.cel2 as `TEL 2`,
                                   c.cel3 as `TEL 3`,
                                   c.cel4 as `TEL 4`,
                                   '' as `EMAIL CONTRATANTE`,
                                   c.fecha_creacion as `FECHA CREACION`,
                                   c.fecha_actualizacion as `FECHA ACTUALIZACION`,
                                   c.estado as ESTADO,
                                   co.`TOTAL CARTERA` as `TOTAL CARTERA`,
                                   aa.fecha_asignacion as fecha_tarea
                            FROM clientes c
                            INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                            INNER JOIN asignaciones_asesores aa 
                              ON aa.clientes_asignados IS NOT NULL
                             AND JSON_VALID(aa.clientes_asignados) = 1
                             AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(c.id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                             AND CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                             AND aa.estado != 'completada'
                            INNER JOIN asignaciones_base_clientes ab
                              ON ab.base_id = c.base_id
                             AND CAST(ab.asesor_cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR)
                             AND ab.estado = 'activa'
                            LEFT JOIN contratos co ON c.id = co.`ID_CLIENTE`
                            WHERE 1=1)
                            
                            UNION ALL
                            
                            (SELECT DISTINCT c.id as ID_CLIENTE,
                                   'CC' as TIPO_IDENTIFICACION,
                                   c.cc as IDENTIFICACION,
                                   c.nombre as `NOMBRE CONTRATANTE`,
                                   '' as CIUDAD,
                                   c.cel1 as `TEL 1`,
                                   c.cel2 as `TEL 2`,
                                   c.cel3 as `TEL 3`,
                                   c.cel4 as `TEL 4`,
                                   '' as `EMAIL CONTRATANTE`,
                                   c.fecha_creacion as `FECHA CREACION`,
                                   c.fecha_actualizacion as `FECHA ACTUALIZACION`,
                                   c.estado as ESTADO,
                                   co.`TOTAL CARTERA` as `TOTAL CARTERA`,
                                   NULL as fecha_tarea
                            FROM clientes c
                            INNER JOIN asignaciones_base_clientes ab ON c.base_id = ab.base_id
                            INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                            LEFT JOIN contratos co ON c.id = co.`ID_CLIENTE`
                            WHERE CAST(ab.asesor_cedula AS CHAR) = CAST(? AS CHAR) 
                            AND ab.estado = 'activa')
                            
                            ORDER BY `NOMBRE CONTRATANTE`";
                
                $stmt = $conn->prepare($query);
                $stmt->execute([$asesor_cedula, $asesor_cedula]);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error al obtener clientes del asesor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener tareas asignadas al asesor
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerTareas($asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Obtener tareas pendientes (no completadas), limitado para rendimiento
            $limit = defined('ASESOR_DASHBOARD_LIMIT_TAREAS') ? (int) ASESOR_DASHBOARD_LIMIT_TAREAS : 100;
            $limit = $limit > 0 ? $limit : 100;
            $query = "SELECT aa.id, 
                            aa.estado,
                            aa.fecha_asignacion,
                            aa.fecha_completada,
                            aa.clientes_asignados,
                            u.nombre_completo as coordinador_nombre
                     FROM asignaciones_asesores aa
                     LEFT JOIN usuarios u ON CAST(aa.coordinador_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                     WHERE CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                     AND aa.estado != 'completada'
                     ORDER BY aa.fecha_asignacion DESC
                     LIMIT " . $limit;
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error al obtener tareas del asesor: " . $e->getMessage());
        return [];
        }
    }

    /**
     * Buscar cliente por criterios
     * @param string $asesor_cedula
     * @param string $criterio
     * @param string $termino
     * @return array
     */
    public function buscarCliente($asesor_cedula, $criterio, $termino) {
        try {
            $conn = getDBConnection();
            
            $where_clause = "";
            $params = [$asesor_cedula];
            
            // Auto-detectar el tipo de búsqueda si es 'auto'
            if ($criterio === 'auto') {
                $criterio = $this->detectarTipoBusqueda($termino);
            }
            
            switch($criterio) {
                case 'cc':
                case 'identificacion':
                    // Usar CAST para comparar como string y evitar problemas de tipo
                    $where_clause = "AND CAST(c.cc AS CHAR) = CAST(? AS CHAR)";
                    $params[] = $termino;
                    break;
                case 'numero_factura':
                case 'numero_obligacion':
                    $where_clause = "AND o.numero_obligacion = ?";
                    $params[] = $termino;
                    break;
                case 'mixto':
                default:
                    // Usar CAST para comparar CC como string y evitar problemas de tipo
                    $where_clause = "AND (CAST(c.cc AS CHAR) = CAST(? AS CHAR) OR c.nombre LIKE ? OR CAST(c.cel1 AS CHAR) LIKE ? OR o.numero_obligacion = ?)";
                    $params[] = $termino;
                    $params[] = "%{$termino}%";
                    $params[] = "%{$termino}%";
                    $params[] = $termino;
                    break;
            }
            
            // Unir resultados: (1) clientes asignados por tareas + (2) clientes accesibles por base asignada
            // Ambos respetan el mismo filtro where_clause (identificacion / numero_obligacion / mixto)
            // Incluimos numero_obligacion desde la tabla obligaciones
                $query = "(
                            SELECT DISTINCT c.id as ID_CLIENTE,
                               c.cc as IDENTIFICACION,
                               c.nombre as `NOMBRE CONTRATANTE`,
                               c.cel1 as CELULAR,
                               bc.nombre as NOMBRE_BASE,
                               GROUP_CONCAT(DISTINCT o.numero_obligacion ORDER BY o.numero_obligacion SEPARATOR ', ') as `NUMERO OBLIGACION`
                        FROM clientes c
                            INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                            INNER JOIN asignaciones_asesores aa 
                              ON aa.clientes_asignados IS NOT NULL
                             AND JSON_VALID(aa.clientes_asignados) = 1
                             AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(c.id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                            INNER JOIN asignaciones_base_clientes ab
                              ON ab.base_id = c.base_id
                             AND CAST(ab.asesor_cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR)
                             AND ab.estado = 'activa'
                        LEFT JOIN obligaciones o ON o.cliente_id = c.id
                        WHERE CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                        AND aa.estado != 'completada'
                        {$where_clause}
                        GROUP BY c.id, c.cc, c.nombre, c.cel1, bc.nombre
                    )
                    UNION ALL
                    (
                        SELECT DISTINCT c.id as ID_CLIENTE,
                               c.cc as IDENTIFICACION,
                               c.nombre as `NOMBRE CONTRATANTE`,
                               c.cel1 as CELULAR,
                               bc.nombre as NOMBRE_BASE,
                               GROUP_CONCAT(DISTINCT o.numero_obligacion ORDER BY o.numero_obligacion SEPARATOR ', ') as `NUMERO OBLIGACION`
                        FROM clientes c
                        INNER JOIN asignaciones_base_clientes ab ON c.base_id = ab.base_id
                        INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                        LEFT JOIN obligaciones o ON o.cliente_id = c.id
                        WHERE CAST(ab.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                        AND ab.estado = 'activa'
                        {$where_clause}
                        GROUP BY c.id, c.cc, c.nombre, c.cel1, bc.nombre
                    )
                    ORDER BY `NOMBRE CONTRATANTE`";

            // Duplicar parámetro de asesor para ambos SELECT
            $stmt = $conn->prepare($query);
            // $params ya tiene asesor como primer elemento, y luego los términos del where_clause
            // Necesitamos construir params2 con el mismo patrón para el segundo SELECT
            $params2 = $params; // copiar
            $params2[0] = $params[0]; // asegurar posición 0 es asesor para el segundo SELECT también

            // Combinar: primero params del primer SELECT, luego para el segundo
            $combined = array_merge($params, $params2);
            $stmt->execute($combined);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error al buscar cliente: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Detectar automáticamente el tipo de búsqueda basado en el término
     * @param string $termino
     * @return string
     */
    private function detectarTipoBusqueda($termino) {
        $termino = trim($termino);
        
        // Si es solo números, tratar como CC (Cédula)
        if (preg_match('/^\d+$/', $termino)) {
            return 'cc';
        }
        
        // Si contiene letras o guiones, número de obligación
        if (preg_match('/[a-zA-Z\-]/', $termino)) {
            return 'numero_factura';
        }
        
        return 'mixto';
    }

    /**
     * Registrar nueva gestión
     * @param string $asesor_cedula
     * @param string $cliente_id
     * @param string $tipo_gestion
     * @param string $resultado
     * @return array
     */
    public function registrarGestion($asesor_cedula, $cliente_id, $tipo_gestion, $resultado) {
        try {
            // Por ahora simular el guardado
            // En el futuro se puede implementar una tabla de gestiones
            return [
                'success' => true,
                'message' => 'Gestión registrada correctamente',
                'gestion_id' => rand(1000, 9999)
            ];

        } catch (Exception $e) {
            error_log("Error al registrar gestión: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar la gestión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generar reporte del asesor
     * @param string $asesor_cedula
     * @param string $tipo_reporte
     * @param string $fecha_desde
     * @param string $fecha_hasta
     * @return array
     */
    public function generarReporte($asesor_cedula, $tipo_reporte, $fecha_desde, $fecha_hasta) {
        try {
            // Por ahora simular la generación del reporte
            $reporte = [
                'tipo' => $tipo_reporte,
                'periodo' => "{$fecha_desde} a {$fecha_hasta}",
                'resumen' => [
                    'clientes_contactados' => rand(20, 50),
                    'llamadas_realizadas' => rand(100, 200),
                    'gestiones_exitosas' => rand(15, 40),
                    'valor_recuperado' => rand(500000, 2000000)
                ],
                'archivo' => "reporte_{$tipo_reporte}_{$asesor_cedula}_" . date('YmdHis') . ".pdf"
            ];

            return [
                'success' => true,
                'message' => 'Reporte generado correctamente',
                'reporte' => $reporte
            ];

        } catch (Exception $e) {
            error_log("Error al generar reporte: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener datos completos de un cliente específico
     * @param string $cliente_id
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerDatosCliente($cliente_id, $asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Obtener datos del cliente (tolerante al esquema: incluir solo celN existentes hasta cel10)
            $colsCel = $this->getColumnasCelDisponibles($conn, 10);
            $selectCel = '';
            if (!empty($colsCel)) {
                $selectCel = ', ' . implode(', ', array_map(fn($c) => "c.$c", $colsCel));
            }

            $sql_cliente = "SELECT c.id, c.cc, c.nombre{$selectCel}, c.email, c.base_id, bc.nombre AS nombre_base
                            FROM clientes c
                            LEFT JOIN bases_clientes bc ON bc.id = c.base_id
                            WHERE c.id = ?";
            $stmt_cliente = $conn->prepare($sql_cliente);
            $stmt_cliente->execute([$cliente_id]);
            $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Solo clientes de bases con acceso activo (alineado con Coord_gestion → Dar acceso)
            $tiene_acceso_base = $this->asesorTieneAccesoActivoABase($conn, $asesor_cedula, $cliente['base_id'] ?? null);
            if (!$tiene_acceso_base) {
                return [
                    'success' => false,
                    'message' => 'No tiene acceso a este cliente (la base no está asignada o está inactiva)'
                ];
            }
            
            // Formatear datos del cliente para la vista.
            // Siempre retornamos cel1..cel10 (null si no existe la columna o no hay dato)
            $datos_cliente = [
                'id' => $cliente['id'],
                'nombre' => $cliente['nombre'] ?? 'N/A',
                'cc' => $cliente['cc'] ?? 'N/A',
                'identificacion' => $cliente['cc'] ?? 'N/A', // Alias para compatibilidad
                'email' => $cliente['email'] ?? null,
                'base_id' => $cliente['base_id'] ?? null,
                'nombre_base' => $cliente['nombre_base'] ?? null,
            ];

            for ($i = 1; $i <= 10; $i++) {
                $k = "cel{$i}";
                $datos_cliente[$k] = $cliente[$k] ?? null;
            }
            
            return [
                'success' => true,
                'cliente' => $datos_cliente
            ];
            
        } catch (Exception $e) {
            error_log("Error al obtener datos del cliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener datos del cliente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener datos del contrato de un cliente
     * @param string $cliente_id
     * @return array
     */
    public function obtenerDatosContrato($cliente_id) {
        try {
            $conn = getDBConnection();
            
            // Tabla contratos puede usar ID_CLIENTE o cliente_id según esquema
            $sql = "SELECT * FROM contratos WHERE `ID_CLIENTE` = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cliente_id]);
            $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$contrato) {
                $sql = "SELECT * FROM contratos WHERE cliente_id = ? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$cliente_id]);
                $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$contrato) {
                return [
                    'success' => false,
                    'message' => 'Contrato no encontrado'
                ];
            }
            
            return [
                'success' => true,
                'contrato' => $contrato
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener datos del contrato: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener historial de gestiones de un cliente
     * @param string $cliente_id
     * @return array
     */
    public function obtenerHistorialCliente($cliente_id) {
        try {
            $conn = getDBConnection();
            
            // Por ahora retornar datos simulados
            // En el futuro se implementaría una tabla de historial
            $historial = [
                [
                    'fecha' => '2024-01-20 14:30:00',
                    'tipo' => 'Llamada',
                    'resultado' => 'Contactado',
                    'comentario' => 'Cliente confirmó pago para el 25/01/2024. Muy colaborativo.',
                    'asesor' => 'Fernanda Cortez'
                ],
                [
                    'fecha' => '2024-01-18 10:15:00',
                    'tipo' => 'Email',
                    'resultado' => 'Enviado',
                    'comentario' => 'Recordatorio de pago enviado por email',
                    'asesor' => 'Fernanda Cortez'
                ],
                [
                    'fecha' => '2024-01-15 09:00:00',
                    'tipo' => 'SMS',
                    'resultado' => 'Enviado',
                    'comentario' => 'Mensaje de recordatorio de pago',
                    'asesor' => 'Fernanda Cortez'
                ]
            ];
            
            return [
                'success' => true,
                'historial' => $historial
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener contratos de un cliente específico
     * @param int $cliente_id
     * @param string $asesor_cedula
     * @return array
     */
    /**
     * Obtener obligaciones de un cliente (reemplaza obtenerFacturasComercio)
     * @param string $cliente_id ID del cliente
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerObligacionesCliente($cliente_id, $asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Verificar que el asesor tiene acceso a este cliente
            $sql_cliente = "SELECT c.id, c.cc as IDENTIFICACION, c.nombre as `NOMBRE CONTRATANTE`, c.cel1 as `TEL 1`, c.base_id 
                            FROM clientes c 
                            WHERE c.id = ?";
            $stmt_cliente = $conn->prepare($sql_cliente);
            $stmt_cliente->execute([$cliente_id]);
            $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ];
            }
            
            if (!$this->asesorTieneAccesoActivoABase($conn, $asesor_cedula, $cliente['base_id'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'No tiene acceso a este cliente (la base no está asignada o está inactiva)'
                ];
            }
            
            // Obtener todas las obligaciones del cliente relacionadas por su identificación
            $sql_obligaciones = "SELECT o.id as ID_OBLIGACION,
                                   o.numero_obligacion as NUMERO_OBLIGACION,
                                   o.producto as PRODUCTO,
                                   o.dias_mora as DIAS_MORA,
                                   o.saldo_total as SALDO_TOTAL,
                                   o.saldo_capital as SALDO_CAPITAL,
                                   o.estado as ESTADO_OBLIGACION,
                                   o.estado as ESTADO,
                                   o.fecha_creacion as FECHA_CREACION
                            FROM obligaciones o
                            INNER JOIN clientes c ON o.cliente_id = c.id
                            WHERE c.id = ?
                            ORDER BY o.dias_mora DESC, o.numero_obligacion";
            
            $stmt_obligaciones = $conn->prepare($sql_obligaciones);
            $stmt_obligaciones->execute([$cliente_id]);
            $obligaciones = $stmt_obligaciones->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'obligaciones' => $obligaciones,
                'cliente' => [
                    'id' => $cliente['id'],
                    'identificacion' => $cliente['IDENTIFICACION'] ?? 'N/A',
                    'nombre_cliente' => $cliente['NOMBRE CONTRATANTE'] ?? 'N/A',
                    'tel' => $cliente['TEL 1'] ?? ''
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error al obtener obligaciones del cliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener obligaciones: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Método de compatibilidad - redirige a obtenerObligacionesCliente
     * @deprecated Use obtenerObligacionesCliente en su lugar
     */
    public function obtenerContratosCliente($cliente_id, $asesor_cedula) {
        return $this->obtenerObligacionesCliente($cliente_id, $asesor_cedula);
    }
    
    /**
     * Método de compatibilidad - redirige a obtenerObligacionesCliente
     * @deprecated Use obtenerObligacionesCliente en su lugar
     */
    public function obtenerFacturasComercio($cliente_id, $asesor_cedula) {
        return $this->obtenerObligacionesCliente($cliente_id, $asesor_cedula);
    }
    
    /**
     * Actualizar información del cliente (email, teléfonos)
     */
    public function actualizarInformacionCliente($cliente_id, $asesor_cedula, $datos) {
        try {
            $conn = getDBConnection();
            
            // Verificar que el cliente existe
            $sql_verificar = "SELECT * FROM clientes WHERE id = ?";
            $stmt_verificar = $conn->prepare($sql_verificar);
            $stmt_verificar->execute([$cliente_id]);
            $cliente = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ];
            }
            
            if (!$this->asesorTieneAccesoActivoABase($conn, $asesor_cedula, $cliente['base_id'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'No tiene acceso a este cliente (la base no está asignada o está inactiva)'
                ];
            }
            
            // Actualizar información del cliente (todos los campos son opcionales)
            $updates = [];
            $params = [];
            $necesita_update = false;
            
            // Email: Actualizar columna email si se proporcionó
            if (isset($datos['email']) && $datos['email'] !== null && $datos['email'] !== '') {
                $updates[] = "`email` = ?";
                $params[] = trim($datos['email']);
                $necesita_update = true;
            }
            
            // Dirección: No existe en la tabla clientes, se ignora
            // La columna DIRECCION no existe en la tabla clientes
            // if (isset($datos['direccion']) && $datos['direccion'] !== null && $datos['direccion'] !== '') {
            //     $updates[] = "`DIRECCION` = ?";
            //     $params[] = $datos['direccion'];
            //     $necesita_update = true;
            // }
            
            // Si hay actualizaciones de estos campos, ejecutarlas
            if ($necesita_update && count($updates) > 0) {
                $params[] = $cliente_id;
                $sql_update = "UPDATE clientes SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->execute($params);
            }
            
            // Actualizar teléfonos si se proporcionaron
            // REGLA: No reemplazar ni eliminar teléfonos existentes.
            // Solo agregar nuevos teléfonos en columnas vacías (cel1..cel10).
            if (isset($datos['telefonos']) && is_array($datos['telefonos']) && count($datos['telefonos']) > 0) {
                // Columnas realmente disponibles (hasta cel10)
                $columnas_disponibles = $this->getColumnasCelDisponibles($conn, 10);
                if (empty($columnas_disponibles)) {
                    return [
                        'success' => false,
                        'message' => 'Contacta al administrador para agregar más números'
                    ];
                }

                // Teléfonos existentes (para no duplicar)
                $telefonos_existentes = [];
                foreach ($columnas_disponibles as $columna) {
                    $valor = $cliente[$columna] ?? null;
                    $t = $valor === null ? '' : trim((string)$valor);
                    if ($t !== '' && $t !== '0' && strtolower($t) !== 'null') {
                        $telefonos_existentes[$t] = true;
                    }
                }

                // Posiciones vacías reales
                $posiciones_vacias = [];
                foreach ($columnas_disponibles as $columna) {
                    $valor = $cliente[$columna] ?? null;
                    $t = $valor === null ? '' : trim((string)$valor);
                    if ($t === '' || $t === '0' || strtolower($t) === 'null') {
                        $posiciones_vacias[] = $columna;
                    }
                }

                // Normalizar/deduplicar teléfonos entrantes, ignorando los que ya existen
                $telefonos_nuevos = [];
                foreach ($datos['telefonos'] as $telefono) {
                    $num = isset($telefono['numero']) ? trim((string)$telefono['numero']) : '';
                    if ($num === '' || $num === '0' || strtolower($num) === 'null') {
                        continue;
                    }
                    if (isset($telefonos_existentes[$num])) {
                        continue;
                    }
                    $telefonos_nuevos[$num] = true;
                }
                $telefonos_nuevos = array_keys($telefonos_nuevos);

                // Si no hay cupo suficiente, no tocar nada
                if (count($telefonos_nuevos) > 0 && count($posiciones_vacias) < count($telefonos_nuevos)) {
                    return [
                        'success' => false,
                        'message' => 'Contacta al administrador para agregar más números'
                    ];
                }

                // Insertar en vacías sin reemplazar
                $telefonos_agregados = 0;
                foreach ($telefonos_nuevos as $num) {
                    $columna = $posiciones_vacias[$telefonos_agregados];
                    $sql_tel = "UPDATE clientes SET `$columna` = ? WHERE id = ?";
                    $stmt_tel = $conn->prepare($sql_tel);
                    $stmt_tel->execute([$num, $cliente_id]);
                    $telefonos_agregados++;
                }
            }
            
            return [
                'success' => true,
                'message' => 'Información actualizada correctamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar información: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Guardar gestión del cliente
     */
    public function guardarGestion($asesor_cedula, $cliente_id, $datos) {
        try {
            // Verificar acceso del asesor al cliente
            $sql_verificar = "SELECT * FROM clientes WHERE id = ?";
            $conn = getDBConnection();
            $stmt_verificar = $conn->prepare($sql_verificar);
            $stmt_verificar->execute([$cliente_id]);
            $cliente = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ];
            }
            
            if (!$this->asesorTieneAccesoActivoABase($conn, $asesor_cedula, $cliente['base_id'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'No tiene acceso a este cliente (la base no está asignada o está inactiva)'
                ];
            }
            
            // Preparar datos para la gestión
            $datosGestion = [
                'asesor_cedula' => $asesor_cedula,
                'cliente_id' => $cliente_id,
                'canal_contacto' => $datos['canal_contacto'] ?? null,
                'contrato_id' => $datos['contrato_id'] ?? null,
                'nivel1_tipo' => (!empty($datos['nivel1_tipo']) && trim($datos['nivel1_tipo']) !== '') ? trim($datos['nivel1_tipo']) : null,
                'nivel2_clasificacion' => (!empty($datos['nivel2_clasificacion']) && trim($datos['nivel2_clasificacion']) !== '') ? trim($datos['nivel2_clasificacion']) : null,
                'nivel3_detalle' => (!empty($datos['nivel3_detalle']) && trim($datos['nivel3_detalle']) !== '') ? trim($datos['nivel3_detalle']) : null,
                'observaciones' => $datos['observaciones'] ?? null,
                'telefono_contacto' => isset($datos['telefono_contacto']) && trim((string)$datos['telefono_contacto']) !== '' 
                    ? substr(preg_replace('/\D/', '', (string)$datos['telefono_contacto']), 0, 10) 
                    : null,
                'llamada_telefonica' => isset($datos['canales']['llamada']) && $datos['canales']['llamada'] ? 'si' : 'no',
                'whatsapp' => isset($datos['canales']['whatsapp']) && $datos['canales']['whatsapp'] ? 'si' : 'no',
                'correo_electronico' => isset($datos['canales']['email']) && $datos['canales']['email'] ? 'si' : 'no',
                'sms' => isset($datos['canales']['sms']) && $datos['canales']['sms'] ? 'si' : 'no',
                'correo_fisico' => isset($datos['canales']['correo']) && $datos['canales']['correo'] ? 'si' : 'no',
                'mensajeria_aplicacion' => isset($datos['canales']['mensajeria']) && $datos['canales']['mensajeria'] ? 'si' : 'no',
                'duracion_segundos' => $datos['duracion_segundos'] ?? 0,
                'fecha_pago' => $datos['fecha_pago'] ?? null,
                'valor_pago' => $datos['valor_pago'] ?? null
            ];
            
            // Guardar gestión
            $resultado = Gestion::crear($datosGestion);
            
            return $resultado;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar gestión: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener resumen de tareas del asesor
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerResumenTareas($asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Obtener tareas activas del asesor con metadatos básicos; los conteos se calculan abajo
            $query = "SELECT 
                        aa.id as tarea_id,
                        aa.fecha_asignacion,
                        aa.estado,
                        aa.clientes_asignados,
                        aa.base_id,
                        bc.nombre as base_nombre,
                        u.nombre_completo as coordinador_nombre,
                        aa.coordinador_cedula
                      FROM asignaciones_asesores aa
                      LEFT JOIN bases_clientes bc ON aa.base_id = bc.id
                      LEFT JOIN usuarios u ON aa.coordinador_cedula = u.cedula
                      WHERE CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR) 
                        AND aa.estado != 'completada'
                      ORDER BY aa.fecha_asignacion DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Procesar cada tarea para calcular estadísticas
            $resumen_tareas = [];
            foreach ($tareas as $tarea) {
                // Parsear clientes asignados del JSON: formato { clientes: [ {id: N}, ... ] }
                $clientes_ids = [];
                $clientes_asignados = json_decode($tarea['clientes_asignados'], true);
                if (is_array($clientes_asignados) && isset($clientes_asignados['clientes']) && is_array($clientes_asignados['clientes'])) {
                    foreach ($clientes_asignados['clientes'] as $it) {
                        if (isset($it['id'])) { $clientes_ids[] = (int)$it['id']; }
                    }
                }
                $clientes_ids = array_values(array_unique(array_filter($clientes_ids, fn($x)=>$x>0)));
                $total_asignados = count($clientes_ids);

                // Calcular gestionados/contactados desde gestiones
                $clientes_gestionados = 0; $clientes_contactados = 0;
                if ($total_asignados > 0) {
                    $place = implode(',', array_fill(0, count($clientes_ids), '?'));
                    // Gestionados (cualquier gestión del asesor sobre esos clientes)
                    $sqlG = "SELECT 
                                COUNT(DISTINCT CASE WHEN g.id IS NOT NULL THEN g.cliente_id END) as gestionados,
                                COUNT(DISTINCT CASE WHEN g.id IS NOT NULL AND g.nivel1_tipo = '1' THEN g.cliente_id END) as contactados
                             FROM gestiones g
                             WHERE CAST(g.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                               AND g.cliente_id IN ($place)";
                    $stmtG = $conn->prepare($sqlG);
                    $params = array_merge([$asesor_cedula], $clientes_ids);
                    $stmtG->execute($params);
                    $rowG = $stmtG->fetch(PDO::FETCH_ASSOC) ?: [];
                    $clientes_gestionados = (int)($rowG['gestionados'] ?? 0);
                    $clientes_contactados = (int)($rowG['contactados'] ?? 0);
                }

                $resumen_tareas[] = [
                    'tarea_id' => $tarea['tarea_id'],
                    'fecha_asignacion' => $tarea['fecha_asignacion'],
                    'estado' => $tarea['estado'],
                    'base_nombre' => $tarea['base_nombre'] ?? 'Sin nombre',
                    'coordinador_nombre' => $tarea['coordinador_nombre'] ?? 'Desconocido',
                    'total_clientes_asignados' => $total_asignados,
                    'clientes_gestionados' => $clientes_gestionados,
                    'clientes_contactados' => $clientes_contactados,
                    'clientes_pendientes' => max(0, $total_asignados - $clientes_gestionados),
                    'porcentaje_progreso' => $total_asignados > 0 ? round(($clientes_gestionados / $total_asignados) * 100, 1) : 0
                ];
            }
            
            return $resumen_tareas;
            
        } catch (Exception $e) {
            error_log("Error al obtener resumen de tareas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de gestiones de un cliente
     */
    public function obtenerHistorialGestiones($cliente_id, $asesor_cedula) {
        try {
            // Verificar acceso del asesor al cliente
            $conn = getDBConnection();
            $sql_verificar = "SELECT c.id, c.cc, c.nombre, c.base_id
                            FROM clientes c 
                            WHERE c.id = ?";
            $stmt_verificar = $conn->prepare($sql_verificar);
            $stmt_verificar->execute([$cliente_id]);
            $cliente = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                    'gestiones' => []
                ];
            }
            
            if (!$this->asesorTieneAccesoActivoABase($conn, $asesor_cedula, $cliente['base_id'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'No tiene acceso a este cliente (la base no está asignada o está inactiva)',
                    'gestiones' => []
                ];
            }
            
            // Obtener TODO el historial del cliente por cédula (CC), aunque exista en varias bases.
            // No se filtra por estado de base (activo/inactivo) para conservar trazabilidad.
            $gestiones = Gestion::obtenerHistorialPorCC($cliente['cc'] ?? '');
            
            return [
                'success' => true,
                'gestiones' => $gestiones
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
                'gestiones' => []
            ];
        }
    }
    
    /**
     * Obtener bases de clientes a las que tiene acceso el asesor
     */
    public function obtenerBasesAcceso($asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Solo bases activas: las deshabilitadas no se muestran ni se pueden asignar; el historial se conserva
            $sql = "SELECT 
                        bc.id as base_id,
                        bc.nombre as nombre_base,
                        bc.fecha_creacion,
                        COUNT(DISTINCT c.id) as total_clientes,
                        ab.estado as estado_acceso
                    FROM bases_clientes bc
                    LEFT JOIN clientes c ON c.base_id = bc.id
                    INNER JOIN asignaciones_base_clientes ab ON bc.id = ab.base_id
                    WHERE ab.asesor_cedula = ? 
                        AND ab.estado = 'activa'
                        AND bc.estado = 'activo'
                    GROUP BY bc.id, bc.nombre, bc.fecha_creacion, ab.estado
                    ORDER BY bc.nombre";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$asesor_cedula]);
            $bases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log para debugging
            error_log("AsesorController::obtenerBasesAcceso - Asesor: {$asesor_cedula}, Bases encontradas: " . count($bases));
            
            return [
                'success' => true,
                'bases' => $bases
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener bases: ' . $e->getMessage(),
                'bases' => []
            ];
        }
    }

    /**
     * Obtener el siguiente cliente pendiente de gestionar
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerSiguienteCliente($asesor_cedula) {
        try {
            $conn = getDBConnection();
            
            // Obtener el siguiente cliente no gestionado de las tareas del asesor
            // Usar la misma lógica que obtenerClientes para mantener consistencia
            $query = "SELECT 
                        c.id as ID_CLIENTE,
                        c.cc as IDENTIFICACION,
                        c.nombre as `NOMBRE CONTRATANTE`,
                        c.cel1 as `TEL 1`,
                        c.cel2 as `TEL 2`,
                        c.cel3 as `TEL 3`,
                        c.cel4 as `TEL 4`,
                        aa.id as tarea_id,
                        bc.nombre as base_nombre
                      FROM clientes c
                      INNER JOIN bases_clientes bc ON bc.id = c.base_id AND bc.estado = 'activo'
                      INNER JOIN asignaciones_asesores aa 
                        ON aa.clientes_asignados IS NOT NULL
                       AND JSON_VALID(aa.clientes_asignados) = 1
                       AND JSON_SEARCH(aa.clientes_asignados, 'one', CAST(c.id AS CHAR), NULL, '$.clientes[*].id') IS NOT NULL
                       AND CAST(aa.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                       AND aa.estado != 'completada'
                      INNER JOIN asignaciones_base_clientes ab
                        ON ab.base_id = c.base_id
                       AND CAST(ab.asesor_cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR)
                       AND ab.estado = 'activa'
                      LEFT JOIN gestiones g ON g.cliente_id = c.id AND CAST(g.asesor_cedula AS CHAR) = CAST(? AS CHAR)
                      WHERE g.id IS NULL
                      ORDER BY aa.fecha_asignacion ASC, c.id ASC
                      LIMIT 1";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$asesor_cedula, $asesor_cedula]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente) {
                return [
                    'success' => true,
                    'cliente' => $cliente,
                    'message' => 'Cliente encontrado'
                ];
            } else {
                return [
                    'success' => false,
                    'cliente' => null,
                    'message' => 'No hay clientes pendientes por gestionar'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error al obtener siguiente cliente: " . $e->getMessage());
            return [
                'success' => false,
                'cliente' => null,
                'message' => 'Error al obtener siguiente cliente'
            ];
        }
    }
}
?>