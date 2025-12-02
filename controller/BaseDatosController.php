<?php
require_once 'config.php';
require_once 'models/Cliente.php';
require_once 'models/Contrato.php';
require_once 'models/BaseCliente.php';
require_once 'models/Historial.php';

class BaseDatosController {
    private $cliente_model;
    private $contrato_model;
    private $base_model;
    private $historial_model;

    public function __construct() {
        $this->cliente_model = new Cliente();
        $this->contrato_model = new Contrato();
        $this->base_model = new BaseCliente();
        $this->historial_model = new Historial();
    }

    /**
     * Obtener todas las bases de datos (resumen de cargas)
     * @return array
     */
    public function obtenerBases() {
        try {
            $bases = $this->base_model->obtenerTodas();
            
            return [
                'success' => true,
                'bases' => $bases,
                'total' => count($bases)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener bases: ' . $e->getMessage(),
                'bases' => [],
                'total' => 0
            ];
        }
    }

    /**
     * Obtener historial de actividades
     * @return array
     */
    public function obtenerHistorial() {
        try {
            $historial_data = $this->historial_model->obtenerHistorial();
            
            return $historial_data;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage(),
                'historial' => [],
                'total' => 0
            ];
        }
    }

    /**
     * Obtener estadísticas generales
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            $conn = getDBConnection();
            
            // Obtener estadísticas de bases (por fecha de creación)
            $query_bases = "SELECT 
                            COUNT(DISTINCT DATE(fecha_creacion)) as total_bases,
                            COUNT(*) as total_clientes,
                            COUNT(CASE WHEN estado = 'activo' THEN 1 END) as clientes_activos
                          FROM clientes";
            
            $stmt = $conn->prepare($query_bases);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener estadísticas de contratos
            $query_contratos = "SELECT COUNT(*) as total_contratos FROM contratos";
            $stmt_contratos = $conn->prepare($query_contratos);
            $stmt_contratos->execute();
            $contratos_stats = $stmt_contratos->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'total_bases' => $stats['total_bases'] ?: 0,
                'total_clientes' => $stats['total_clientes'] ?: 0,
                'bases_activas' => $stats['total_bases'] ?: 0, // Todas las bases están activas por defecto
                'total_contratos' => $contratos_stats['total_contratos'] ?: 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage(),
                'total_bases' => 0,
                'total_clientes' => 0,
                'bases_activas' => 0,
                'total_contratos' => 0
            ];
        }
    }

    /**
     * Obtener detalles de una base específica
     * @param string $fecha_carga
     * @return array
     */
    public function obtenerDetallesBase($fecha_carga) {
        try {
            $conn = getDBConnection();
            
            $query = "SELECT 
                        c.*,
                        COUNT(ct.`CONTRATO`) as total_contratos,
                        SUM(ct.`TOTAL CARTERA`) as cartera_total
                      FROM clientes c
                      LEFT JOIN contratos ct ON c.id = ct.`ID_CLIENTE`
                      WHERE DATE(c.fecha_creacion) = :fecha_carga
                      GROUP BY c.id
                      ORDER BY c.fecha_creacion DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':fecha_carga', $fecha_carga);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'clientes' => $clientes,
                'total' => count($clientes)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener detalles: ' . $e->getMessage(),
                'clientes' => [],
                'total' => 0
            ];
        }
    }

    /**
     * Limpiar historial de actividades
     * @return array
     */
    public function limpiarHistorial() {
        try {
            $conn = getDBConnection();
            
            // Limpiar la tabla historial_actividades
            $query = "DELETE FROM historial_actividades WHERE 1=1";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            $filasEliminadas = $stmt->rowCount();
            
            return [
                'success' => true,
                'message' => "Historial limpiado exitosamente. Se eliminaron {$filasEliminadas} actividades del historial.",
                'registros_eliminados' => $filasEliminadas
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al limpiar historial: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar una base de clientes
     * @param int $base_id
     * @return array
     */
    public function eliminarBase($base_id) {
        try {
            $conn = getDBConnection();
            
            // Iniciar transacción
            $conn->beginTransaction();
            
            // Obtener información de la base antes de eliminar
            $query_base = "SELECT nombre FROM bases_comercios WHERE id = ?";
            $stmt_base = $conn->prepare($query_base);
            $stmt_base->execute([$base_id]);
            $base = $stmt_base->fetch(PDO::FETCH_ASSOC);
            
            if (!$base) {
                return [
                    'success' => false,
                    'message' => 'La base de datos no existe'
                ];
            }
            
            $base_nombre = $base['nombre'];
            
            // Eliminar asignaciones de asesores a esta base
            $query_asignaciones = "DELETE FROM asignaciones_base_comercios WHERE base_id = ?";
            $stmt_asignaciones = $conn->prepare($query_asignaciones);
            $stmt_asignaciones->execute([$base_id]);
            
            // Eliminar contratos asociados
            $query_contratos = "DELETE FROM contratos WHERE base_id = ?";
            $stmt_contratos = $conn->prepare($query_contratos);
            $stmt_contratos->execute([$base_id]);
            
            // Eliminar clientes asociados
            $query_clientes = "DELETE FROM clientes WHERE base_id = ?";
            $stmt_clientes = $conn->prepare($query_clientes);
            $stmt_clientes->execute([$base_id]);
            
            // Eliminar la base (cambiar estado a 'inactivo' en lugar de eliminar físicamente)
            $query_eliminar = "UPDATE bases_comercios SET estado = 'inactivo' WHERE id = ?";
            $stmt_eliminar = $conn->prepare($query_eliminar);
            $stmt_eliminar->execute([$base_id]);
            
            // Confirmar transacción
            $conn->commit();
            
            // Registrar en historial
            $this->registrarHistorialActividad('eliminar_base', 
                "Base '{$base_nombre}' eliminada completamente", 
                $base_nombre, 
                null, 
                'exitoso',
                json_encode([
                    'base_id' => $base_id,
                    'base_nombre' => $base_nombre,
                    'fecha_eliminacion' => date('Y-m-d H:i:s')
                ])
            );
            
            return [
                'success' => true,
                'message' => "Base '{$base_nombre}' eliminada exitosamente"
            ];
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            
            error_log("Error al eliminar base: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar base: ' . $e->getMessage()
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
     * Exportar historial a CSV
     * @return array
     */
    public function exportarHistorial() {
        try {
            $conn = getDBConnection();
            
            // Obtener historial para exportar
            $historial = $this->obtenerHistorial();
            
            if (!$historial['success']) {
                return $historial;
            }
            
            // Crear contenido CSV
            $csv_content = "Actividad,Archivo/Tarea,Fecha,Estado,Detalles\n";
            
            foreach ($historial['historial'] as $actividad) {
                $csv_content .= sprintf(
                    '"%s","%s","%s","%s","%s"' . "\n",
                    $actividad['actividad'],
                    $actividad['archivo_tarea'],
                    $actividad['fecha'],
                    $actividad['estado'],
                    $actividad['detalles']
                );
            }
            
            return [
                'success' => true,
                'csv_content' => $csv_content,
                'total_registros' => count($historial['historial'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al exportar historial: ' . $e->getMessage()
            ];
        }
    }
}
?>
