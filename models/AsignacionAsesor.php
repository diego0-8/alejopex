<?php
require_once 'config.php';

/**
 * Modelo para gestionar las asignaciones de tareas a asesores
 * Maneja la tabla asignaciones_asesores (versión simplificada)
 */
class AsignacionAsesor {
    private $conn;
    private $table_name = 'asignaciones_asesores';

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear una nueva asignación de tarea
     * @param array $datos
     * @return array
     */
    public function crear($datos) {
        try {
            $campos_requeridos = ['coordinador_cedula', 'asesor_cedula', 'clientes_asignados'];
            foreach ($campos_requeridos as $campo) {
                if (empty($datos[$campo])) {
                    return [
                        'success' => false,
                        'message' => "El campo {$campo} es requerido"
                    ];
                }
            }

            // Insertar con todos los campos disponibles
            $query = "INSERT INTO {$this->table_name} (
                coordinador_cedula, asesor_cedula, estado, clientes_asignados, base_id
            ) VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([
                $datos['coordinador_cedula'],
                $datos['asesor_cedula'],
                $datos['estado'] ?? 'pendiente',
                $datos['clientes_asignados'],
                $datos['base_id'] ?? null
            ]);

            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Asignación creada exitosamente',
                    'id' => $this->conn->lastInsertId()
                ];
            }

            return [
                'success' => false,
                'message' => 'Error al crear asignación'
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::crear: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener una asignación por ID
     * @param int $id
     * @return array
     */
    public function obtenerPorId($id) {
        try {
            $query = "SELECT * FROM {$this->table_name} WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            
            $asignacion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($asignacion) {
                return [
                    'success' => true,
                    'asignacion' => $asignacion
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Asignación no encontrada'
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::obtenerPorId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener asignaciones por coordinador
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerPorCoordinador($coordinador_cedula) {
        try {
            $query = "SELECT a.*, u.nombre_completo as asesor_nombre, u.usuario as asesor_usuario
                      FROM {$this->table_name} a
                      LEFT JOIN usuarios u ON CAST(a.asesor_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                      WHERE a.coordinador_cedula = ?
                      ORDER BY a.fecha_asignacion DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$coordinador_cedula]);
            $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'asignaciones' => $asignaciones
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::obtenerPorCoordinador: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'asignaciones' => []
            ];
        }
    }

    /**
     * Obtener asignaciones por asesor
     * @param string $asesor_cedula
     * @return array
     */
    public function obtenerPorAsesor($asesor_cedula) {
        try {
            $query = "SELECT a.*, u.nombre_completo as coordinador_nombre, u.usuario as coordinador_usuario
                      FROM {$this->table_name} a
                      LEFT JOIN usuarios u ON CAST(a.coordinador_cedula AS CHAR) = CAST(u.cedula AS CHAR)
                      WHERE a.asesor_cedula = ? AND a.estado != 'completada'
                      ORDER BY a.fecha_asignacion DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$asesor_cedula]);
            $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'asignaciones' => $asignaciones
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::obtenerPorAsesor: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'asignaciones' => []
            ];
        }
    }

    /**
     * Actualizar una asignación
     * @param int $id
     * @param array $datos
     * @return array
     */
    public function actualizar($id, $datos) {
        try {
            $campos_permitidos = ['estado', 'clientes_asignados'];
            $campos_actualizar = [];
            $valores = [];

            foreach ($campos_permitidos as $campo) {
                if (isset($datos[$campo])) {
                    $campos_actualizar[] = "{$campo} = ?";
                    $valores[] = $datos[$campo];
                }
            }

            if (empty($campos_actualizar)) {
                return [
                    'success' => false,
                    'message' => 'No hay campos para actualizar'
                ];
            }

            $valores[] = $id;
            $query = "UPDATE {$this->table_name} SET " . implode(', ', $campos_actualizar) . " WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute($valores);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Asignación actualizada exitosamente'
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo actualizar la asignación'
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::actualizar: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una asignación
     * @param int $id
     * @return array
     */
    public function eliminar($id) {
        try {
            $query = "DELETE FROM {$this->table_name} WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([$id]);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Asignación eliminada exitosamente'
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo eliminar la asignación'
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::eliminar: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cambiar estado de una asignación
     * @param int $id
     * @param string $nuevo_estado
     * @return array
     */
    public function cambiarEstado($id, $nuevo_estado) {
        try {
            $estados_validos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
            if (!in_array($nuevo_estado, $estados_validos)) {
                return [
                    'success' => false,
                    'message' => 'Estado no válido'
                ];
            }

            $query = "UPDATE {$this->table_name} 
                      SET estado = ?, 
                          fecha_completada = CASE WHEN ? = 'completada' THEN NOW() ELSE fecha_completada END,
                          updated_at = NOW()
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $resultado = $stmt->execute([$nuevo_estado, $nuevo_estado, $id]);

            if ($resultado && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Estado actualizado exitosamente'
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo actualizar el estado'
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::cambiarEstado: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener estadísticas de asignaciones
     * @param string $coordinador_cedula
     * @return array
     */
    public function obtenerEstadisticas($coordinador_cedula) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_asignaciones,
                        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                        SUM(CASE WHEN estado = 'en_progreso' THEN 1 ELSE 0 END) as en_progreso,
                        SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as completadas,
                        SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
                      FROM {$this->table_name} 
                      WHERE coordinador_cedula = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$coordinador_cedula]);
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'estadisticas' => $estadisticas
            ];

        } catch (PDOException $e) {
            error_log("Error en AsignacionAsesor::obtenerEstadisticas: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error de base de datos: ' . $e->getMessage(),
                'estadisticas' => []
            ];
        }
    }

    /**
     * Completar una asignación
     * @param int $id
     * @return array
     */
    public function completar($id) {
        return $this->cambiarEstado($id, 'completada');
    }
}
?>