<?php
require_once 'config.php';

class Contrato {
    private $conn;
    private $table_name = "contratos";

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Crear un nuevo contrato
     * @param array $data Datos del contrato
     * @return array Resultado de la operación
     */
    public function crear($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (base_id, `CONTRATO`, `ID_CLIENTE`, `MODALIDAD DE PAGO`, `FRANJA`, 
                      `DIAS EN MORA`, `EDAD MORA`, `TOTAL CARTERA`) 
                     VALUES (:base_id, :contrato, :id_cliente, :modalidad, :franja, :dias_mora, :edad_mora, :total_cartera)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':base_id', $data['base_id']);
            $stmt->bindParam(':contrato', $data['contrato']);
            $stmt->bindParam(':id_cliente', $data['id_cliente']);
            $stmt->bindParam(':modalidad', $data['modalidad_pago']);
            $stmt->bindParam(':franja', $data['franja']);
            $stmt->bindParam(':dias_mora', $data['dias_mora']);
            $stmt->bindParam(':edad_mora', $data['edad_mora']);
            $stmt->bindParam(':total_cartera', $data['total_cartera']);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Contrato creado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear contrato'
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
     * Obtener contrato por número
     * @param string $contrato
     * @return array|false
     */
    public function obtenerPorContrato($contrato) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE `CONTRATO` = :contrato";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':contrato', $contrato);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener contrato: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los contratos
     * @return array
     */
    public function obtenerTodos() {
        try {
            $query = "SELECT c.*, cl.`NOMBRE CONTRATANTE` as cliente_nombre 
                     FROM " . $this->table_name . " c 
                     INNER JOIN clientes cl ON c.`ID_CLIENTE` = cl.id 
                     ORDER BY c.`FECHA CREACION` DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener contratos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de contratos
     * @return array
     */
    public function obtenerEstadisticas() {
        try {
            // Obtener total de contratos y total de cartera
            $query = "SELECT 
                        COUNT(*) as total_contratos,
                        COALESCE(SUM(CAST(`TOTAL CARTERA` AS DECIMAL(15,2))), 0) as total_cartera,
                        COUNT(CASE WHEN CAST(`DIAS EN MORA` AS UNSIGNED) > 0 THEN 1 END) as contratos_mora
                      FROM " . $this->table_name;
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_contratos' => $resultado['total_contratos'] ?? 0,
                'contratos_activos' => $resultado['total_contratos'] ?? 0,
                'contratos_mora' => $resultado['contratos_mora'] ?? 0,
                'total_cartera' => $resultado['total_cartera'] ?? 0
            ];
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas: " . $e->getMessage());
            return [
                'total_contratos' => 0,
                'contratos_activos' => 0,
                'contratos_mora' => 0,
                'total_cartera' => 0
            ];
        }
    }

    /**
     * Procesar y limpiar datos del CSV
     * @param array $row Fila del CSV
     * @param int $id_cliente ID del cliente
     * @return array Datos procesados
     */
    public function procesarDatosCSV($row, $id_cliente) {
        // Limpiar y procesar datos
        $data = [
            'contrato' => trim($row['CONTRATO'] ?? ''),
            'id_cliente' => $id_cliente,
            'modalidad_pago' => strtolower(trim($row['MODALIDAD DE PAGO'] ?? '')),
            'franja' => trim($row['FRANJA'] ?? ''),
            'dias_mora' => (int)($row['DIAS EN MORA'] ?? 0),
            'edad_mora' => trim($row['EDAD MORA'] ?? '0-30'),
            'total_cartera' => (float)($row['TOTAL CARTERA'] ?? 0)
        ];

        // Validar modalidad de pago
        if (!in_array($data['modalidad_pago'], ['mensual', 'semanal', 'diario'])) {
            $data['modalidad_pago'] = 'mensual'; // Valor por defecto
        }

        return $data;
    }
}
?>
