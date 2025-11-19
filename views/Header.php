<?php
/**
 * Header Compartido - Sistema IPS CRM
 * Header superior para todas las vistas del sistema
 */

// Obtener el rol del usuario actual
$rol_usuario = $_SESSION['usuario_rol'] ?? 'guest';
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_inicial = substr($usuario_nombre, 0, 1);
?>

<!-- Header Superior Compartido -->
<header class="top-header">
    <div class="header-left">
        <div class="user-info">
            <i class="fas fa-<?php 
                echo $rol_usuario === 'administrador' ? 'user-shield' : 
                    ($rol_usuario === 'coordinador' ? 'user-tie' : 
                    ($rol_usuario === 'asesor' ? 'user' : 'user-circle')); 
            ?>"></i>
            <div class="user-details">
                <span class="user-role"><?php 
                    echo $rol_usuario === 'administrador' ? 'Administrador' : 
                        ($rol_usuario === 'coordinador' ? 'Coordinador' : 
                        ($rol_usuario === 'asesor' ? 'Asesor' : 'Usuario')); 
                ?></span>
                <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>
            </div>
        </div>
    </div>
    <div class="header-right">
        <div class="header-actions">
            <span class="action-icon" title="Información"><i class="fas fa-circle-info"></i></span>
            <span class="action-icon" title="Notificaciones"><i class="fas fa-bell"></i></span>
        </div>
        <div class="user-profile">
            <img src="https://placehold.co/30x30/FFFFFF/000000?text=<?php echo $usuario_inicial; ?>" 
                 class="user-avatar"
                 alt="Avatar de <?php echo htmlspecialchars($usuario_nombre); ?>">
            <div class="user-menu">
                <span class="user-name"><?php echo htmlspecialchars($usuario_nombre); ?></span>
                <i class="fas fa-caret-down"></i>
            </div>
        </div>
    </div>
</header>

<style>
/* Estilos para el header mejorado */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-role {
    font-size: 12px;
    font-weight: 600;
    color: #fafafaff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-name {
    font-size: 14px;
    font-weight: 500;
    color: #ffffffff;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-right: 15px;
}

.action-icon {
    font-size: 16px;
    color: #ffffff;
    cursor: pointer;
    transition: color 0.3s ease;
    padding: 8px;
    border-radius: 50%;
}

.action-icon:hover {
    color: #f0f0f0;
    background: rgba(255, 255, 255, 0.1);
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 20px;
    transition: background-color 0.3s ease;
}

.user-profile:hover {
    background: #f8f9fa;
}

.user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 5px;
}

.user-menu .user-name {
    font-size: 14px;
    font-weight: 500;
    color: #ebebebff;
}

.user-menu i {
    font-size: 12px;
    color: #ffffff;
    transition: transform 0.3s ease;
}

.user-profile:hover .user-menu i {
    transform: rotate(180deg);
}

/* Responsive para móviles */
@media (max-width: 768px) {
    .header-left .user-details {
        display: none;
    }
    
    .header-actions {
        margin-right: 10px;
    }
    
    .action-icon {
        font-size: 14px;
        padding: 6px;
    }
    
    .user-menu .user-name {
        display: none;
    }
}
</style>
