<?php
require_once 'config.php';

class Asignacion {
    private $conn;
    private $table_name = "asignaciones";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear una nueva asignación de asesor a coordinador
     * @param string $asesor_cedula
     * @param string $coordinador_cedula
     * @param string $creado_por
     * @param string $notas
     * @return array
     */
    public function crear($asesor_cedula, $coordinador_cedula, $creado_por, $notas = '') {
        try {
            // Verificar que el asesor existe y es de rol 'asesor'
            $stmt = $this->conn->prepare("SELECT cedula FROM usuarios WHERE cedula = ? AND rol = 'asesor' AND estado = 'activo'");
            $stmt->execute([$asesor_cedula]);
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'El asesor no existe o no está activo'];
            }

            // Verificar que el coordinador existe y es de rol 'coordinador'
            $stmt = $this->conn->prepare("SELECT cedula FROM usuarios WHERE cedula = ? AND rol = 'coordinador' AND estado = 'activo'");
            $stmt->execute([$coordinador_cedula]);
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'El coordinador no existe o no está activo'];
            }

            // Verificar que el asesor no tenga una asignación activa
            $stmt = $this->conn->prepare("SELECT id FROM asignaciones WHERE asesor_cedula = ? AND estado = 'activa'");
            $stmt->execute([$asesor_cedula]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El asesor ya tiene una asignación activa'];
            }

            // Crear la asignación
            $query = "INSERT INTO " . $this->table_name . " 
                     (asesor_cedula, coordinador_cedula, creado_por, notas) 
                     VALUES (?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$asesor_cedula, $coordinador_cedula, $creado_por, $notas]);

            if ($result) {
                return ['success' => true, 'message' => 'Asignación creada exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al crear la asignación'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener todas las asignaciones con información de usuarios
     * Incluye asignaciones desde la tabla 'asignaciones' (creadas por admin) 
     * y desde 'asignaciones_asesores' (tareas asignadas)
     * @return array
     */
    public function obtenerTodas() {
        try {
            // Verificar si existe la tabla 'asignaciones'
            $tabla_asignaciones_existe = false;
            try {
                $stmt_check = $this->conn->query("SHOW TABLES LIKE 'asignaciones'");
                $tabla_asignaciones_existe = $stmt_check->rowCount() > 0;
            } catch (Exception $e) {
                // Tabla no existe
            }
            
            $asignaciones = [];
            
            // Obtener asignaciones de la tabla 'asignaciones' (creadas por admin)
            if ($tabla_asignaciones_existe) {
                $query_admin = "SELECT a.id,
                                a.asesor_cedula,
                                a.coordinador_cedula,
                                a.estado,
                                a.fecha_asignacion,
                                a.fecha_creacion as created_at,
                                a.fecha_actualizacion as updated_at,
                                ua.nombre_completo as asesor_nombre,
                                uc.nombre_completo as coordinador_nombre,
                                COALESCE(uc_creador.nombre_completo, 'Sistema') as creador_nombre
                         FROM asignaciones a
                         INNER JOIN usuarios ua ON a.asesor_cedula = ua.cedula
                         INNER JOIN usuarios uc ON a.coordinador_cedula = uc.cedula
                         LEFT JOIN usuarios uc_creador ON a.creado_por = uc_creador.cedula
                         WHERE ua.rol = 'asesor'
                         AND uc.rol = 'coordinador'
                         ORDER BY a.fecha_asignacion DESC";
                
                $stmt_admin = $this->conn->prepare($query_admin);
                $stmt_admin->execute();
                $asignaciones_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);
                
                // Agregar a la lista
                $asignaciones = array_merge($asignaciones, $asignaciones_admin);
            }
            
            // Obtener asignaciones desde 'asignaciones_asesores' (tareas asignadas)
            // Solo las últimas asignaciones únicas de cada asesor-coordinador
            try {
                $query_tareas = "SELECT aa.id,
                                aa.asesor_cedula,
                                aa.coordinador_cedula,
                                aa.estado,
                                aa.fecha_asignacion,
                                aa.created_at,
                                aa.updated_at,
                                ua.nombre_completo as asesor_nombre,
                                uc.nombre_completo as coordinador_nombre,
                                'Sistema' as creador_nombre
                         FROM asignaciones_asesores aa
                         INNER JOIN usuarios ua ON CAST(aa.asesor_cedula AS CHAR) = CAST(ua.cedula AS CHAR)
                         INNER JOIN usuarios uc ON CAST(aa.coordinador_cedula AS CHAR) = CAST(uc.cedula AS CHAR)
                         WHERE ua.rol = 'asesor'
                         AND uc.rol = 'coordinador'
                         AND (
                             aa.id IN (
                                 SELECT MAX(aa2.id)
                                 FROM asignaciones_asesores aa2
                                 WHERE CAST(aa2.asesor_cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR)
                                 AND CAST(aa2.coordinador_cedula AS CHAR) = CAST(aa.coordinador_cedula AS CHAR)
                                 GROUP BY aa2.asesor_cedula, aa2.coordinador_cedula
                             )
                         )
                         ORDER BY aa.fecha_asignacion DESC";
                
                $stmt_tareas = $this->conn->prepare($query_tareas);
                $stmt_tareas->execute();
                $asignaciones_tareas = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
                
                // Agregar a la lista
                $asignaciones = array_merge($asignaciones, $asignaciones_tareas);
            } catch (PDOException $e) {
                error_log("Error al obtener asignaciones desde asignaciones_asesores: " . $e->getMessage());
            }
            
            // Eliminar duplicados basándose en la combinación asesor-coordinador
            // Mantener solo la más reciente
            $asignaciones_unicas = [];
            $keys_usados = [];
            
            foreach ($asignaciones as $asig) {
                $key = $asig['asesor_cedula'] . '_' . $asig['coordinador_cedula'];
                $fecha = strtotime($asig['fecha_asignacion'] ?? $asig['created_at'] ?? '2000-01-01');
                
                if (!isset($keys_usados[$key]) || $fecha > $keys_usados[$key]['fecha']) {
                    $keys_usados[$key] = ['fecha' => $fecha, 'asignacion' => $asig];
                }
            }
            
            // Extraer las asignaciones únicas
            foreach ($keys_usados as $item) {
                $asignaciones_unicas[] = $item['asignacion'];
            }
            
            // Ordenar por fecha de asignación descendente
            usort($asignaciones_unicas, function($a, $b) {
                $fecha_a = strtotime($a['fecha_asignacion'] ?? $a['created_at'] ?? '2000-01-01');
                $fecha_b = strtotime($b['fecha_asignacion'] ?? $b['created_at'] ?? '2000-01-01');
                return $fecha_b - $fecha_a;
            });
            
            return $asignaciones_unicas;
        } catch (PDOException $e) {
            error_log("Error al obtener todas las asignaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener asignaciones activas
     * @return array
     */
    public function obtenerActivas() {
        try {
            $query = "SELECT a.*, 
                            ua.nombre_completo as asesor_nombre,
                            uc.nombre_completo as coordinador_nombre
                     FROM " . $this->table_name . " a
                     LEFT JOIN usuarios ua ON a.asesor_cedula = ua.cedula
                     LEFT JOIN usuarios uc ON a.coordinador_cedula = uc.cedula
                     WHERE a.estado = 'activa'
                     ORDER BY a.fecha_creacion DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Obtener asesores sin asignación
     * @return array
     */
    public function obtenerAsesoresSinAsignacion() {
        try {
            // Obtener asesores que NO tienen una asignación activa en la tabla 'asignaciones'
            // Esta tabla almacena la relación directa coordinador-asesor creada por el admin
            
            // Verificar si existe la tabla 'asignaciones'
            $tabla_asignaciones_existe = false;
            try {
                $stmt_check = $this->conn->query("SHOW TABLES LIKE 'asignaciones'");
                $tabla_asignaciones_existe = $stmt_check->rowCount() > 0;
            } catch (Exception $e) {
                // Tabla no existe
            }
            
            if ($tabla_asignaciones_existe) {
                // Buscar asesores sin asignación activa en tabla 'asignaciones'
                $query = "SELECT DISTINCT u.cedula, u.nombre_completo, u.usuario
                         FROM usuarios u
                         LEFT JOIN asignaciones a ON u.cedula = a.asesor_cedula AND a.estado = 'activa'
                         WHERE u.rol = 'asesor' 
                         AND u.estado = 'activo'
                         AND a.id IS NULL
                         ORDER BY u.nombre_completo";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
            } else {
                // Fallback: buscar en asignaciones_asesores (tareas) si no existe tabla asignaciones
                $query = "SELECT DISTINCT u.cedula, u.nombre_completo, u.usuario
                         FROM usuarios u
                         LEFT JOIN asignaciones_asesores aa ON CAST(u.cedula AS CHAR) = CAST(aa.asesor_cedula AS CHAR) AND aa.estado != 'completada'
                         WHERE u.rol = 'asesor' 
                         AND u.estado = 'activo'
                         AND aa.id IS NULL
                         ORDER BY u.nombre_completo";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener asesores sin asignación: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener coordinadores disponibles
     * @return array
     */
    public function obtenerCoordinadores() {
        try {
            $query = "SELECT cedula, nombre_completo, usuario
                     FROM usuarios 
                     WHERE rol = 'coordinador' 
                     AND estado = 'activo'
                     ORDER BY nombre_completo";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Desactivar una asignación
     * @param int $id
     * @param string $usuario_cedula
     * @return array
     */
    public function desactivar($id, $usuario_cedula) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET estado = 'inactiva', 
                         fecha_actualizacion = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$id]);

            if ($result) {
                return ['success' => true, 'message' => 'Asignación desactivada exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al desactivar la asignación'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener asesores asignados a un coordinador específico
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerAsesoresPorCoordinador($coordinador_cedula) {
        try {
            // Obtener asesores únicos asignados al coordinador desde múltiples fuentes:
            // 1. Tabla 'asignaciones' (relación directa coordinador-asesor creada por admin)
            // 2. Tabla 'asignaciones_asesores' (tareas asignadas)
            
            // Verificar si existe la tabla 'asignaciones'
            $tabla_asignaciones_existe = false;
            try {
                $stmt_check = $this->conn->query("SHOW TABLES LIKE 'asignaciones'");
                $tabla_asignaciones_existe = $stmt_check->rowCount() > 0;
            } catch (Exception $e) {
                // Tabla no existe, solo usar asignaciones_asesores
            }
            
            if ($tabla_asignaciones_existe) {
                // Buscar en ambas tablas: asignaciones y asignaciones_asesores
                $query = "SELECT DISTINCT u.cedula, u.nombre_completo, u.usuario, u.estado, 
                                u.fecha_actualizacion as ultima_actividad
                         FROM usuarios u
                         WHERE u.rol = 'asesor'
                         AND (
                             -- Asesores asignados directamente en tabla 'asignaciones' (por admin)
                             u.cedula IN (
                                 SELECT asesor_cedula 
                                 FROM asignaciones 
                                 WHERE coordinador_cedula = ? 
                                 AND estado = 'activa'
                             )
                             OR
                             -- Asesores con tareas en 'asignaciones_asesores'
                             u.cedula IN (
                                 SELECT DISTINCT asesor_cedula 
                                 FROM asignaciones_asesores 
                                 WHERE coordinador_cedula = ?
                             )
                         )
                         ORDER BY u.nombre_completo";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$coordinador_cedula, $coordinador_cedula]);
            } else {
                // Solo buscar en asignaciones_asesores (fallback si no existe tabla asignaciones)
                $query = "SELECT DISTINCT u.cedula, u.nombre_completo, u.usuario, u.estado, 
                                u.fecha_actualizacion as ultima_actividad
                         FROM usuarios u
                         INNER JOIN asignaciones_asesores aa ON aa.asesor_cedula = u.cedula
                         WHERE aa.coordinador_cedula = ?
                         AND u.rol = 'asesor'
                         ORDER BY u.nombre_completo";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$coordinador_cedula]);
            }
            
            $asesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener datos reales de comercios asignados por asesor
            foreach ($asesores as &$asesor) {
                $asesor_cedula = $asesor['cedula'];
                
                // Contar comercios asignados en tareas pendientes/en_progreso desde asignaciones_asesores
                $query_comercios = "SELECT COUNT(DISTINCT JSON_EXTRACT(aa.clientes_asignados, '$.clientes[*].id')) as total_comercios
                                   FROM asignaciones_asesores aa
                                   WHERE aa.asesor_cedula = ?
                                   AND aa.estado IN ('pendiente', 'en_progreso')
                                   AND JSON_VALID(aa.clientes_asignados) = 1";
                $stmt_comercios = $this->conn->prepare($query_comercios);
                $stmt_comercios->execute([$asesor_cedula]);
                $result_comercios = $stmt_comercios->fetch(PDO::FETCH_ASSOC);
                
                $asesor['clientes_asignados'] = $result_comercios['total_comercios'] ?? 0;
                
                // Contar clientes gestionados (con gestiones registradas)
                $query_gestionados = "SELECT COUNT(DISTINCT g.cliente_id) as total 
                                     FROM gestiones g
                                     WHERE g.asesor_cedula = ?
                                     AND g.cliente_id IS NOT NULL";
                $stmt_gestionados = $this->conn->prepare($query_gestionados);
                $stmt_gestionados->execute([$asesor_cedula]);
                $result_gestionados = $stmt_gestionados->fetch(PDO::FETCH_ASSOC);
                
                $asesor['clientes_gestionados'] = $result_gestionados['total'] ?? 0;
                
                // Obtener última actividad real (la tabla gestiones usa fecha_creacion)
                try {
                    $query_actividad = "SELECT MAX(fecha_creacion) as ultima_actividad
                                      FROM gestiones
                                      WHERE asesor_cedula = ?";
                    $stmt_actividad = $this->conn->prepare($query_actividad);
                    $stmt_actividad->execute([$asesor_cedula]);
                    $result_actividad = $stmt_actividad->fetch(PDO::FETCH_ASSOC);
                    $asesor['ultima_actividad'] = $result_actividad['ultima_actividad'] ?? $asesor['ultima_actividad'] ?? null;
                } catch (PDOException $e) {
                    // Si falla, usar fecha_actualizacion del usuario como fallback
                    $asesor['ultima_actividad'] = $asesor['ultima_actividad'] ?? null;
                }
            }
            
            return $asesores;
        } catch (PDOException $e) {
            error_log("Error en obtenerAsesoresPorCoordinador: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de asignaciones
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            $stats = [];

            // Total de asignaciones activas (desde asignaciones_asesores)
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM asignaciones_asesores WHERE estado != 'completada'");
            $stmt->execute();
            $stats['asignaciones_activas'] = $stmt->fetchColumn();

            // Total de asesores asignados (únicos desde asignaciones_asesores)
            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT asesor_cedula) as total FROM asignaciones_asesores WHERE estado != 'completada'");
            $stmt->execute();
            $stats['asesores_asignados'] = $stmt->fetchColumn();

            // Total de asesores sin asignar (diferencia entre total y asignados)
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'asesor' AND estado = 'activo'");
            $stmt->execute();
            $total_asesores = $stmt->fetchColumn();
            
            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT asesor_cedula) as total FROM asignaciones_asesores WHERE estado != 'completada'");
            $stmt->execute();
            $asesores_asignados = $stmt->fetchColumn();
            
            $stats['asesores_sin_asignar'] = max(0, $total_asesores - $asesores_asignados);

            // Total de coordinadores con asesores asignados
            $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT coordinador_cedula) as total FROM asignaciones_asesores WHERE estado != 'completada'");
            $stmt->execute();
            $stats['coordinadores_con_asesores'] = $stmt->fetchColumn();

            return $stats;

        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de asignaciones: " . $e->getMessage());
            return [
                'asignaciones_activas' => 0,
                'asesores_asignados' => 0,
                'asesores_sin_asignar' => 0,
                'coordinadores_con_asesores' => 0
            ];
        }
    }

    /**
     * Eliminar una asignación por ID
     * @param int $asignacion_id
     * @return bool
     */
    public function eliminar($asignacion_id) {
        try {
            $query = "DELETE FROM asignaciones WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([$asignacion_id]);
            
            return $resultado && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error al eliminar asignación: " . $e->getMessage());
            return false;
        }
    }
}
?>
