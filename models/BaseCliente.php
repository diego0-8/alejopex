<?php
require_once 'config.php';

class BaseCliente {
    private $conn;
    private $table_name = "bases_comercios";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear una nueva base de clientes
     * @param array $data Datos de la base
     * @return array Resultado de la operación
     */
    public function crear($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (nombre, descripcion, creado_por) 
                     VALUES (:nombre, :descripcion, :creado_por)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':creado_por', $data['creado_por']);

            if ($stmt->execute()) {
                $base_id = $this->conn->lastInsertId();
                
                // Registrar en historial
                $this->registrarHistorial('crear_base', "Base '{$data['nombre']}' creada", $data['creado_por'], $base_id);
                
                return [
                    'success' => true,
                    'message' => 'Base creada exitosamente',
                    'id' => $base_id
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear base'
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
     * Obtener todas las bases
     * @return array
     */
    public function obtenerTodas() {
        try {
            $query = "SELECT b.*, 
                            COUNT(DISTINCT c.id) as total_clientes,
                            COUNT(DISTINCT co.id) as total_contratos
                     FROM " . $this->table_name . " b
                     LEFT JOIN clientes c ON b.id = c.base_id
                     LEFT JOIN contratos co ON b.id = co.base_id
                     WHERE b.estado = 'activo'
                     GROUP BY b.id
                     ORDER BY b.fecha_creacion DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en BaseCliente::obtenerTodas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener base por ID
     * @param int $id
     * @return array|false
     */
    public function obtenerPorId($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? AND estado = 'activo'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error en BaseCliente::obtenerPorId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar base
     * @param int $id
     * @param array $data
     * @return array
     */
    public function actualizar($id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET nombre = :nombre, descripcion = :descripcion, 
                         fecha_actualizacion = NOW()
                     WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':descripcion', $data['descripcion']);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Base actualizada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar base'
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
     * Eliminar base (soft delete)
     * @param int $id
     * @param string $usuario_id
     * @return array
     */
    public function eliminar($id, $usuario_id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET estado = 'inactivo', fecha_actualizacion = NOW()
                     WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                // Registrar en historial
                $this->registrarHistorial('eliminar_base', "Base eliminada (ID: {$id})", $usuario_id, $id);
                
                return [
                    'success' => true,
                    'message' => 'Base eliminada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar base'
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
     * Obtener estadísticas de bases
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_bases,
                        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as bases_activas,
                        SUM(total_clientes) as total_clientes,
                        SUM(total_contratos) as total_contratos
                     FROM " . $this->table_name;

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en BaseCliente::obtenerEstadisticas: " . $e->getMessage());
            return [
                'total_bases' => 0,
                'bases_activas' => 0,
                'total_clientes' => 0,
                'total_contratos' => 0
            ];
        }
    }

    /**
     * Registrar actividad en historial
     * @param string $tipo
     * @param string $descripcion
     * @param string $usuario_id
     * @param int $base_id
     */
    private function registrarHistorial($tipo, $descripcion, $usuario_id, $base_id = null) {
        try {
            $query = "INSERT INTO historial_actividades 
                     (tipo_actividad, descripcion, usuario_id, base_id, estado) 
                     VALUES (:tipo, :descripcion, :usuario_id, :base_id, 'exitoso')";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':usuario_id', $usuario_id);
            $stmt->bindParam(':base_id', $base_id);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al registrar historial: " . $e->getMessage());
        }
    }
}
?>
