<?php
require_once 'config.php';

/**
 * Modelo de Usuario
 * Maneja todas las operaciones relacionadas con los usuarios
 */
class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public $cedula;
    public $nombre_completo;
    public $usuario;
    public $contrasena;
    public $estado;
    public $rol;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    /**
     * Autenticar usuario
     * @param string $usuario
     * @param string $contrasena
     * @return array|false
     */
    public function autenticar($usuario, $contrasena) {
        try {
            $query = "SELECT cedula, nombre_completo, usuario, contrasena, estado, rol 
                      FROM " . $this->table_name . " 
                      WHERE usuario = :usuario AND estado = 'activo'";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar la contraseña
                if (password_verify($contrasena, $row['contrasena'])) {
                    // No devolver la contraseña por seguridad
                    unset($row['contrasena']);
                    return $row;
                }
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error de autenticación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener usuario por cédula
     * @param string $cedula
     * @return array|false
     */
    public function obtenerPorCedula($cedula) {
        $query = "SELECT cedula, nombre_completo, usuario, estado, rol, fecha_creacion, fecha_actualizacion 
                  FROM " . $this->table_name . " 
                  WHERE cedula = :cedula";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cedula', $cedula);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }

    /**
     * Obtener usuario por nombre de usuario
     * @param string $usuario
     * @return array|false
     */
    public function obtenerPorUsuario($usuario) {
        $query = "SELECT cedula, nombre_completo, usuario, estado, rol, fecha_creacion, fecha_actualizacion 
                  FROM " . $this->table_name . " 
                  WHERE usuario = :usuario";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }

    /**
     * Crear nuevo usuario
     * @return bool
     */
    public function crear() {
        $query = "INSERT INTO " . $this->table_name . " 
                  (cedula, nombre_completo, usuario, contrasena, estado, rol) 
                  VALUES (:cedula, :nombre_completo, :usuario, :contrasena, :estado, :rol)";

        $stmt = $this->conn->prepare($query);

        // Hash de la contraseña
        $hashed_password = password_hash($this->contrasena, PASSWORD_DEFAULT);

        $stmt->bindParam(':cedula', $this->cedula);
        $stmt->bindParam(':nombre_completo', $this->nombre_completo);
        $stmt->bindParam(':usuario', $this->usuario);
        $stmt->bindParam(':contrasena', $hashed_password);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':rol', $this->rol);

        return $stmt->execute();
    }

    /**
     * Actualizar un usuario
     * @param string $cedula
     * @param string $nombre_completo
     * @param string $usuario
     * @param string|null $contrasena
     * @param string $rol
     * @param string $estado
     * @return array
     */
    public function actualizar($cedula, $nombre_completo, $usuario, $contrasena = null, $rol, $estado) {
        try {
            // Verificar que el usuario existe
            $stmt = $this->conn->prepare("SELECT cedula FROM " . $this->table_name . " WHERE cedula = ?");
            $stmt->execute([$cedula]);
            
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Verificar si el nombre de usuario ya existe en otro usuario
            $stmt = $this->conn->prepare("SELECT cedula FROM " . $this->table_name . " WHERE usuario = ? AND cedula != ?");
            $stmt->execute([$usuario, $cedula]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El nombre de usuario ya está en uso por otro usuario'];
            }

            // Validar rol
            if (!in_array($rol, ['administrador', 'coordinador', 'asesor'])) {
                return ['success' => false, 'message' => 'Rol inválido'];
            }

            // Validar estado
            if (!in_array($estado, ['activo', 'inactivo'])) {
                return ['success' => false, 'message' => 'Estado inválido'];
            }

            // Preparar la consulta de actualización
            if ($contrasena !== null) {
                // Actualizar con nueva contraseña
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $query = "UPDATE " . $this->table_name . " 
                         SET nombre_completo = ?, usuario = ?, contrasena = ?, rol = ?, estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP 
                         WHERE cedula = ?";
                $params = [$nombre_completo, $usuario, $contrasena_hash, $rol, $estado, $cedula];
            } else {
                // Actualizar sin cambiar contraseña
                $query = "UPDATE " . $this->table_name . " 
                         SET nombre_completo = ?, usuario = ?, rol = ?, estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP 
                         WHERE cedula = ?";
                $params = [$nombre_completo, $usuario, $rol, $estado, $cedula];
            }

            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar el usuario'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener todos los usuarios
     * @return array
     */
    public function obtenerTodos() {
        $query = "SELECT cedula, nombre_completo, usuario, estado, rol, fecha_creacion, fecha_actualizacion 
                  FROM " . $this->table_name . " 
                  ORDER BY fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si existe un usuario con el mismo nombre de usuario
     * @param string $usuario
     * @param string $cedula_excluir
     * @return bool
     */
    public function existeUsuario($usuario, $cedula_excluir = null) {
        $query = "SELECT cedula FROM " . $this->table_name . " WHERE usuario = :usuario";
        
        if ($cedula_excluir) {
            $query .= " AND cedula != :cedula_excluir";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':usuario', $usuario);
        
        if ($cedula_excluir) {
            $stmt->bindParam(':cedula_excluir', $cedula_excluir);
        }
        
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Verificar si existe una cédula
     * @param string $cedula
     * @return bool
     */
    public function existeCedula($cedula) {
        $query = "SELECT cedula FROM " . $this->table_name . " WHERE cedula = :cedula";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cedula', $cedula);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Cambiar estado de un usuario
     */
    public function cambiarEstado($cedula, $nuevo_estado) {
        try {
            // Verificar que el usuario existe
            $stmt = $this->conn->prepare("SELECT cedula FROM " . $this->table_name . " WHERE cedula = ?");
            $stmt->execute([$cedula]);
            
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            // Validar estado
            if (!in_array($nuevo_estado, ['activo', 'inactivo'])) {
                return ['success' => false, 'message' => 'Estado inválido'];
            }

            // Actualizar estado
            $query = "UPDATE " . $this->table_name . " 
                     SET estado = ?, fecha_actualizacion = CURRENT_TIMESTAMP 
                     WHERE cedula = ?";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$nuevo_estado, $cedula]);

            if ($result) {
                $accion = $nuevo_estado === 'activo' ? 'activado' : 'desactivado';
                return ['success' => true, 'message' => "Usuario {$accion} exitosamente"];
            } else {
                return ['success' => false, 'message' => 'Error al cambiar el estado del usuario'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }


    /**
     * Eliminar un usuario
     */
    public function eliminar($cedula) {
        try {
            // Verificar que el usuario existe
            $stmt = $this->conn->prepare("SELECT cedula, rol FROM " . $this->table_name . " WHERE cedula = ?");
            $stmt->execute([$cedula]);
            
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verificar si es el último administrador
            if ($usuario['rol'] === 'administrador') {
                $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE rol = 'administrador' AND estado = 'activo'");
                $stmt->execute();
                $total_admin = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                if ($total_admin <= 1) {
                    return ['success' => false, 'message' => 'No se puede eliminar el último administrador activo'];
                }
            }

            // Eliminar usuario
            $query = "DELETE FROM " . $this->table_name . " WHERE cedula = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$cedula]);

            if ($result) {
                return ['success' => true, 'message' => 'Usuario eliminado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al eliminar el usuario'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener usuarios por rol
     * @param string $rol
     * @return array
     */
    public function obtenerPorRol($rol) {
        try {
            $query = "SELECT cedula, nombre_completo, usuario, estado, fecha_actualizacion 
                     FROM " . $this->table_name . " 
                     WHERE rol = ? AND estado = 'activo'
                     ORDER BY nombre_completo";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$rol]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener usuarios por rol: " . $e->getMessage());
            return [];
        }
    }
}
?>
