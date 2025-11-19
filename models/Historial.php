<?php
require_once 'config.php';

class Historial {
    private $conn;
    private $table_name = "historial_actividades";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Registrar una nueva actividad
     * @param array $data Datos de la actividad
     * @return array Resultado de la operación
     */
    public function registrar($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (tipo_actividad, descripcion, archivo_tarea, usuario_id, base_id, estado, detalles) 
                     VALUES (:tipo, :descripcion, :archivo_tarea, :usuario_id, :base_id, :estado, :detalles)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tipo', $data['tipo_actividad']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':archivo_tarea', $data['archivo_tarea']);
            $stmt->bindParam(':usuario_id', $data['usuario_id']);
            $stmt->bindParam(':base_id', $data['base_id']);
            $stmt->bindParam(':estado', $data['estado']);
            $stmt->bindParam(':detalles', $data['detalles']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Actividad registrada exitosamente',
                    'id' => $this->conn->lastInsertId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al registrar actividad'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener historial de actividades
     * @param array $filtros Filtros opcionales
     * @return array
     */
    public function obtenerHistorial($filtros = []) {
        try {
            $query = "SELECT h.*, u.nombre_completo as usuario_nombre, b.nombre as base_nombre
                     FROM " . $this->table_name . " h
                     LEFT JOIN usuarios u ON CAST(h.usuario_id AS CHAR) = CAST(u.cedula AS CHAR)
                     LEFT JOIN bases_comercios b ON h.base_id = b.id
                     WHERE 1=1";

            $params = [];

            // Aplicar filtros
            if (!empty($filtros['tipo_actividad'])) {
                $query .= " AND h.tipo_actividad = :tipo";
                $params[':tipo'] = $filtros['tipo_actividad'];
            }

            if (!empty($filtros['usuario_id'])) {
                $query .= " AND h.usuario_id = :usuario";
                $params[':usuario'] = $filtros['usuario_id'];
            }

            if (!empty($filtros['fecha_desde'])) {
                $query .= " AND h.fecha_actividad >= :fecha_desde";
                $params[':fecha_desde'] = $filtros['fecha_desde'];
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query .= " AND h.fecha_actividad <= :fecha_hasta";
                $params[':fecha_hasta'] = $filtros['fecha_hasta'];
            }

            $query .= " ORDER BY h.fecha_actividad DESC";

            if (!empty($filtros['limite'])) {
                $query .= " LIMIT :limite";
                $params[':limite'] = (int)$filtros['limite'];
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return [
                'success' => true,
                'historial' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            error_log("Error en Historial::obtenerHistorial: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'historial' => []
            ];
        }
    }

    /**
     * Limpiar historial
     * @param array $filtros Filtros para la limpieza
     * @return array
     */
    public function limpiarHistorial($filtros = []) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE 1=1";
            $params = [];

            // Aplicar filtros de limpieza
            if (!empty($filtros['fecha_antes_de'])) {
                $query .= " AND fecha_actividad < :fecha_antes";
                $params[':fecha_antes'] = $filtros['fecha_antes_de'];
            }

            if (!empty($filtros['tipo_actividad'])) {
                $query .= " AND tipo_actividad = :tipo";
                $params[':tipo'] = $filtros['tipo_actividad'];
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            $registros_eliminados = $stmt->rowCount();

            return [
                'success' => true,
                'message' => "Historial limpiado exitosamente. Se eliminaron {$registros_eliminados} registros.",
                'registros_eliminados' => $registros_eliminados
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al limpiar historial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exportar historial a CSV
     * @param array $filtros Filtros opcionales
     * @return array
     */
    public function exportarHistorial($filtros = []) {
        try {
            $historial_data = $this->obtenerHistorial($filtros);
            
            if (!$historial_data['success']) {
                return $historial_data;
            }

            // Crear contenido CSV
            $csv_content = "ID,Tipo de Actividad,Descripción,Archivo/Tarea,Fecha,Estado,Usuario,Base,Detalles\n";
            
            foreach ($historial_data['historial'] as $actividad) {
                $csv_content .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $actividad['id'],
                    $actividad['tipo_actividad'],
                    $actividad['descripcion'],
                    $actividad['archivo_tarea'] ?? '',
                    $actividad['fecha_actividad'],
                    $actividad['estado'],
                    $actividad['usuario_nombre'] ?? '',
                    $actividad['base_nombre'] ?? '',
                    $actividad['detalles'] ?? ''
                );
            }

            return [
                'success' => true,
                'csv_content' => $csv_content,
                'total_registros' => count($historial_data['historial'])
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al exportar historial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas del historial
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_actividades,
                        COUNT(CASE WHEN estado = 'exitoso' THEN 1 END) as actividades_exitosas,
                        COUNT(CASE WHEN estado = 'error' THEN 1 END) as actividades_con_error,
                        COUNT(CASE WHEN tipo_actividad = 'carga_csv' THEN 1 END) as cargas_csv,
                        COUNT(CASE WHEN tipo_actividad = 'asignacion_tarea' THEN 1 END) as asignaciones_tareas,
                        COUNT(CASE WHEN tipo_actividad = 'completar_tarea' THEN 1 END) as tareas_completadas
                     FROM " . $this->table_name;

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Historial::obtenerEstadisticas: " . $e->getMessage());
            return [
                'total_actividades' => 0,
                'actividades_exitosas' => 0,
                'actividades_con_error' => 0,
                'cargas_csv' => 0,
                'asignaciones_tareas' => 0,
                'tareas_completadas' => 0
            ];
        }
    }

    /**
     * Obtener actividades desde una fecha específica
     * @param string $timestamp Fecha desde la cual obtener actividades
     * @return array
     */
    public function getActivitiesSince($timestamp) {
        try {
            $query = "SELECT h.*, u.nombre_completo as usuario_nombre, b.nombre as base_nombre
                     FROM " . $this->table_name . " h
                     LEFT JOIN usuarios u ON CAST(h.usuario_id AS CHAR) = CAST(u.cedula AS CHAR)
                     LEFT JOIN bases_comercios b ON h.base_id = b.id
                     WHERE h.fecha_actividad > ?
                     ORDER BY h.fecha_actividad DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$timestamp]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Historial::getActivitiesSince: " . $e->getMessage());
            return [];
        }
    }
}
?>
