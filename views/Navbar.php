<?php
/**
 * Navbar Compartido - Sistema IPS CRM
 * Contiene los navbars para todos los roles del sistema
 */

// Obtener el rol del usuario actual
$rol_usuario = $_SESSION['usuario_rol'] ?? 'guest';
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuario_inicial = substr($usuario_nombre, 0, 1);
$action = $action ?? $_GET['action'] ?? 'login';
?>

<div class="sidebar">
    <div class="sidebar-logo"><?php echo APP_NAME; ?></div>
    <nav class="sidebar-nav">
        <ul>
            <?php if ($rol_usuario === 'administrador'): ?>
                <!-- NAVBAR ADMINISTRADOR -->
                <li class="<?php echo ($action === 'dashboard' || $action === 'admin_dashboard') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=dashboard'">
                    <i class="fas fa-th-large"></i> Dashboard
                </li>
                <li class="<?php echo ($action === 'admin_usuarios') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=admin_usuarios'">
                    <i class="fas fa-users"></i> Usuarios
                </li>
                <li class="<?php echo ($action === 'admin_asignaciones') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=admin_asignaciones'">
                    <i class="fas fa-user-friends"></i> Asignaciones
                </li>
                <li onclick="window.location.href='index.php?action=admin_reportes'">
                    <i class="fas fa-chart-bar"></i> Reportes
                </li>
                <li onclick="window.location.href='index.php?action=admin_configuracion'">
                    <i class="fas fa-cog"></i> Configuración
                </li>
                
            <?php elseif ($rol_usuario === 'coordinador'): ?>
                <!-- NAVBAR COORDINADOR -->
                <li class="<?php echo ($action === 'coordinador_dashboard') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=coordinador_dashboard'">
                    <i class="fas fa-th-large"></i> Dashboard
                </li>
                <li class="<?php echo ($action === 'coordinador_gestion') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=coordinador_gestion'">
                    <i class="fas fa-cogs"></i> Gestión
                </li>
                <li class="<?php echo ($action === 'coordinador_exporte') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=coordinador_exporte'">
                    <i class="fas fa-download"></i> Exporte
                </li>
                
            <?php elseif ($rol_usuario === 'asesor'): ?>
                <!-- NAVBAR ASESOR -->
                <li class="<?php echo ($action === 'asesor_dashboard') ? 'active' : ''; ?>" 
                    onclick="window.location.href='index.php?action=asesor_dashboard'">
                    <i class="fas fa-th-large"></i> Dashboard
                </li>
                <!-- Botón de búsqueda de cliente -->
                <li id="navbar-buscar-cliente" onclick="abrirBusquedaClienteNavbar()" style="cursor: pointer;" title="Buscar cliente por cédula o celular">
                    <i class="fas fa-search"></i> Buscar Cliente
                </li>
                <!-- Botón de tiempo de sesión -->
                <li id="navbar-tiempo-sesion" onclick="toggleTiempoModal()" style="cursor: pointer;">
                    <i class="fas fa-clock"></i> Tiempo de Sesión
                </li>
                <!-- Botón de Cerrar Sesión -->
                <li class="logout-menu-item" style="cursor: pointer;" onclick="window.location.href='index.php?action=logout'" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </li>
            <?php else: ?>
                <!-- NAVBAR GUEST (No autenticado) -->
                <li onclick="window.location.href='index.php?action=login'">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </li>
            <?php endif; ?>
            
            <?php if ($rol_usuario === 'administrador'): ?>
                <!-- Botón de Cerrar Sesión para Administrador -->
                <li class="logout-menu-item" style="cursor: pointer;" onclick="window.location.href='index.php?action=logout'" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </li>
            <?php elseif ($rol_usuario === 'coordinador'): ?>
                <!-- Botón de Cerrar Sesión para Coordinador -->
                <li class="logout-menu-item" style="cursor: pointer;" onclick="window.location.href='index.php?action=logout'" title="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
