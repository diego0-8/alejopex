<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma Asesor - <?php echo APP_NAME; ?></title>
    <!-- Estilos Globales -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Estilos del Navbar y componentes comunes -->
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/asesor-dashboard.css">
    <!-- Estilos del Layout y Softphone -->
    <link rel="stylesheet" href="assets/css/webrtc-softphone.css">
    <link rel="stylesheet" href="assets/css/layout-asesor.css">
</head>
<body>
    <div class="app-container">
        <!-- Barra de Navegación Global -->
        <?php 
        // Incluir navbar usando helper unificado (siempre visible en el layout principal)
        require_once 'views/helpers/navbar-helper.php';
        incluirNavbar('asesor_dashboard');
        ?>
        
        <!-- Área de Contenido (Iframe donde cargará la gestión) -->
        <div class="app-content">
            <!-- Cargamos el dashboard o la gestión inicial según parámetros -->
            <?php
            $iframe_url = 'index.php?action=asesor_dashboard&iframe=1';
            // Si viene con parámetro gestionar_action, cargar la gestión de cliente
            if (isset($_GET['gestionar_action']) && $_GET['gestionar_action'] === 'asesor_gestionar') {
                $cliente_id = $_GET['cliente_id'] ?? '';
                $iframe_url = 'index.php?action=asesor_gestionar&iframe=1&cliente_id=' . urlencode($cliente_id);
            }
            ?>
            <iframe id="content-frame" name="content-frame" src="<?php echo htmlspecialchars($iframe_url); ?>"></iframe>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- INTEGRACIÓN DEL SOFTPHONE PERSISTENTE      -->
    <!-- ========================================== -->
    <?php
    // Lógica PHP para obtener credenciales (Copiada de asesor_gestionar.php)
    require_once 'models/Usuario.php';
    $usuario_model = new Usuario();
    $usuario_data = false;
    
    if (!empty($_SESSION['usuario_cedula'])) {
        $usuario_data = $usuario_model->obtenerPorCedula($_SESSION['usuario_cedula']);
    } elseif (!empty($_SESSION['usuario_id'])) {
        $usuario_data = $usuario_model->obtenerPorCedula($_SESSION['usuario_id']);
    }
    
    $mostrar_softphone = (
        isset($_SESSION['usuario_rol']) && 
        $_SESSION['usuario_rol'] === 'asesor' &&
        $usuario_data &&
        !empty($usuario_data['extension'] ?? '') &&
        !empty($usuario_data['sip_password'] ?? '')
    );
    
    if ($mostrar_softphone):
        require_once 'config/asterisk.php';
        $webrtc_config = getWebRTCConfig();
        $extension = $_SESSION['usuario_extension'] ?? '';
        $sip_password = $_SESSION['usuario_sip_password'] ?? '';
    ?>
    
    <!-- Contenedor del Softphone -->
    <div class="seccion-softphone-wrapper">
        <div id="webrtc-softphone" class="webrtc-softphone-panel"></div>
    </div>
    
    <!-- Scripts del Softphone -->
    <script src="assets/js/sip.min.js"></script>
    <script src="assets/js/softphone-web.js"></script>
    <!-- Script de verificación del botón de minimizar (solo en desarrollo) -->
    <script src="assets/js/verify-minimize-button.js"></script>
    <script>
        // Configuración JS (Misma lógica que tenías)
        const webrtcConfig = {
            wss_server: '<?php echo $webrtc_config['wss_server']; ?>',
            sip_domain: '<?php echo $webrtc_config['sip_domain']; ?>',
            extension: '<?php echo htmlspecialchars($extension, ENT_QUOTES, 'UTF-8'); ?>',
            password: '<?php echo htmlspecialchars($sip_password, ENT_QUOTES, 'UTF-8'); ?>',
            display_name: '<?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Asesor', ENT_QUOTES, 'UTF-8'); ?>',
            preferredRtpPort: <?php echo (int) ($webrtc_config['preferred_rtp_port'] ?? 10000); ?>,
            iceServers: <?php 
                $iceServers = $webrtc_config['iceServers'] ?? [];
                if (!is_array($iceServers) || empty($iceServers)) {
                    $iceServers = [];
                }
                echo json_encode($iceServers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            ?>,
            debug_mode: <?php echo $webrtc_config['debug_mode'] ? 'true' : 'false'; ?>
        };
        
        // Inicialización del softphone
        function inicializarSoftphoneConVerificacion() {
            let intentos = 0;
            const maxIntentos = 100;
            
            const intervalo = setInterval(function() {
                intentos++;
                
                // Verificar que TODO esté listo
                const sipjsListo = typeof SIP !== 'undefined' && 
                                  typeof SIP.UserAgent !== 'undefined';
                
                const softphoneListo = typeof WebRTCSoftphone !== 'undefined';
                
                if (sipjsListo && softphoneListo) {
                    clearInterval(intervalo);
                    console.log('✅ Todos los componentes listos, inicializando softphone...');
                    
                    try {
                        // Verificar que el contenedor existe
                        const container = document.getElementById('webrtc-softphone');
                        if (!container) {
                            console.warn('⚠️ [WebRTC Softphone] Contenedor del softphone no encontrado.');
                            return;
                        }
                        
                        // Verificar configuración antes de inicializar
                        console.log('🔄 [WebRTC Softphone] Inicializando softphone...');
                        console.log('📝 [WebRTC Softphone] Verificando configuración:', {
                            extension: webrtcConfig.extension || 'VACIA',
                            password: webrtcConfig.password ? 'DEFINIDA' : 'VACIA',
                            wss_server: webrtcConfig.wss_server,
                            sip_domain: webrtcConfig.sip_domain,
                            debug_mode: webrtcConfig.debug_mode
                        });
                        
                        // Validar que la extensión y password no estén vacías
                        if (!webrtcConfig.extension || webrtcConfig.extension.trim() === '') {
                            console.error('❌ [WebRTC Softphone] Error: Extension está vacía');
                            if (webrtcConfig.debug_mode) {
                                alert('Error: La extensión SIP no está configurada. Contacta al administrador.');
                            }
                            return;
                        }
                        
                        if (!webrtcConfig.password || webrtcConfig.password.trim() === '') {
                            console.error('❌ [WebRTC Softphone] Error: Password está vacía');
                            if (webrtcConfig.debug_mode) {
                                alert('Error: La contraseña SIP no está configurada. Contacta al administrador.');
                            }
                            return;
                        }
                        
                        window.webrtcSoftphone = new WebRTCSoftphone(webrtcConfig);
                        console.log('✅ [WebRTC Softphone] Softphone WebRTC inicializado correctamente');
                        console.log('📞 [WebRTC Softphone] Extensión:', webrtcConfig.extension);
                        
                        // Función para verificar estado (útil para debugging)
                        window.verificarEstadoSoftphone = function() {
                            if (window.webrtcSoftphone) {
                                console.log('📊 [WebRTC Softphone] Estado actual:', {
                                    extension: window.webrtcSoftphone.config.extension,
                                    sip_domain: window.webrtcSoftphone.config.sip_domain,
                                    wss_server: window.webrtcSoftphone.config.wss_server,
                                    isRegistered: window.webrtcSoftphone.isRegistered,
                                    isConnected: window.webrtcSoftphone.isConnected,
                                    status: window.webrtcSoftphone.status,
                                    transportState: window.webrtcSoftphone.userAgent?.transport?.state,
                                    registrationState: window.webrtcSoftphone.userAgent?.registration?.state
                                });
                            } else {
                                console.warn('⚠️ [WebRTC Softphone] El softphone no está inicializado');
                            }
                        };
                        
                        console.log('💡 [WebRTC Softphone] Tip: Ejecuta verificarEstadoSoftphone() en la consola para ver el estado actual');
                        
                    } catch (error) {
                        console.error('❌ [WebRTC Softphone] Error al inicializar softphone:', error);
                        console.error('❌ [WebRTC Softphone] Stack:', error.stack);
                        if (webrtcConfig.debug_mode) {
                            alert('Error al inicializar el softphone: ' + error.message);
                        }
                    }
                    
                } else {
                    if (intentos % 10 === 0) {
                        console.log(`⏳ Esperando componentes... (${intentos}/${maxIntentos})`);
                        console.log('  SIP.js listo:', sipjsListo);
                        console.log('  WebRTCSoftphone listo:', softphoneListo);
                    }
                    
                    if (intentos >= maxIntentos) {
                        clearInterval(intervalo);
                        console.error('❌ Timeout esperando componentes del softphone');
                        if (webrtcConfig.debug_mode) {
                            alert('El softphone no se pudo inicializar. Por favor, recarga la página.');
                        }
                    }
                }
            }, 100);
        }
        
        // Iniciar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', inicializarSoftphoneConVerificacion);
        } else {
            inicializarSoftphoneConVerificacion();
        }
        
        // --- PUENTE DE COMUNICACIÓN ---
        // Escuchar mensajes del iframe para hacer llamadas (Click-to-Call desde el hijo)
        // NOTA: Este listener maneja TODOS los mensajes del iframe (makeCall, iframeNavigation, actionChanged)
        window.addEventListener('message', function(event) {
            // Validar origen por seguridad (opcional, ajusta según tu dominio)
            // if (event.origin !== window.location.origin) return;
            
            if (!event.data || !event.data.type) return;
            
            // Manejar solicitudes de llamada
            if (event.data.type === 'makeCall') {
                console.log('📞 Solicitud de llamada desde iframe:', event.data.number);
                if (window.webrtcSoftphone && window.webrtcSoftphone.callNumber) {
                    window.webrtcSoftphone.callNumber(event.data.number);
                } else {
                    console.warn('⚠️ Softphone no disponible aún. Esperando inicialización...');
                }
                return;
            }
            
            // Manejar cambios de URL en el iframe (para actualizar título y navbar)
            if (event.data.type === 'iframeNavigation') {
                if (event.data.title) {
                    document.title = event.data.title + ' - <?php echo APP_NAME; ?>';
                }
                // Actualizar navbar si viene la acción
                if (event.data.action && typeof window.actualizarNavbarActivo === 'function') {
                    window.actualizarNavbarActivo(event.data.action);
                }
                return;
            }
            
            // Manejar cambios de acción para actualizar el navbar
            if (event.data.type === 'actionChanged') {
                if (event.data.action && typeof window.actualizarNavbarActivo === 'function') {
                    window.actualizarNavbarActivo(event.data.action);
                }
                return;
            }
        });
        
        // Función global para llamar desde cualquier parte (incluido el iframe)
        window.llamarDesdeWebRTC = function(numero) {
            if (typeof window.webrtcSoftphone !== 'undefined' && 
                window.webrtcSoftphone !== null && 
                window.webrtcSoftphone.callNumber) {
                window.webrtcSoftphone.callNumber(numero);
            } else {
                console.warn('Softphone no disponible. Por favor, espera a que se inicialice.');
            }
        };
    </script>
    <?php endif; ?>
    
    <!-- Script para manejar navegación del iframe -->
    <script>
        // Función para cambiar la URL del iframe desde el navbar o cualquier lugar
        window.navegarEnIframe = function(url) {
            const iframe = document.getElementById('content-frame');
            if (iframe) {
                // Asegurar que la URL tenga el parámetro iframe=1
                const urlObj = new URL(url, window.location.origin);
                urlObj.searchParams.set('iframe', '1');
                iframe.src = urlObj.toString();
                
                // Después de cargar, asegurar que el sidebar dentro del iframe esté oculto
                iframe.onload = function() {
                    try {
                        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        const sidebar = iframeDoc.querySelector('.sidebar');
                        if (sidebar) {
                            sidebar.style.display = 'none';
                            sidebar.style.visibility = 'hidden';
                        }
                    } catch (e) {
                        // Error de CORS o similar, ignorar
                        console.log('No se pudo acceder al contenido del iframe (normal si hay CORS)');
                    }
                };
            }
        };
        
        // Ocultar sidebar dentro del iframe cuando se carga
        function ocultarSidebarEnIframe() {
            const iframe = document.getElementById('content-frame');
            if (iframe) {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    if (iframeDoc) {
                        // Ocultar sidebar
                        const sidebar = iframeDoc.querySelector('.sidebar');
                        if (sidebar) {
                            sidebar.style.display = 'none';
                            sidebar.style.visibility = 'hidden';
                            sidebar.style.position = 'absolute';
                            sidebar.style.left = '-9999px';
                        }
                        
                        // También inyectar CSS para asegurar que esté oculto
                        const style = iframeDoc.createElement('style');
                        style.textContent = '.sidebar { display: none !important; visibility: hidden !important; position: absolute !important; left: -9999px !important; }';
                        iframeDoc.head.appendChild(style);
                    }
                } catch (e) {
                    // Error de CORS - esto es normal si el iframe está en el mismo dominio
                    // El código PHP ya debería estar evitando incluir el navbar
                    console.log('No se pudo acceder al contenido del iframe (normal si hay CORS)');
                }
            }
        }
        
        // Ejecutar cuando el iframe se carga
        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.getElementById('content-frame');
            if (iframe) {
                // Ejecutar inmediatamente si ya está cargado
                if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                    ocultarSidebarEnIframe();
                }
                
                // Ejecutar cuando se carga el iframe
                iframe.onload = function() {
                    setTimeout(ocultarSidebarEnIframe, 100);
                    // También verificar periódicamente por si el contenido se carga después
                    setTimeout(ocultarSidebarEnIframe, 500);
                    setTimeout(ocultarSidebarEnIframe, 1000);
                };
            }
            
            // Asegurar que solo haya un sidebar visible en el layout principal
            const sidebars = document.querySelectorAll('.app-container > .sidebar');
            if (sidebars.length > 1) {
                console.warn('⚠️ Se encontraron múltiples sidebars (' + sidebars.length + '), ocultando los adicionales');
                for (let i = 1; i < sidebars.length; i++) {
                    sidebars[i].style.display = 'none';
                    sidebars[i].style.visibility = 'hidden';
                    sidebars[i].remove();
                }
            }
            
            // Verificar también si hay sidebars fuera del app-container (no debería haber)
            const sidebarsFuera = document.querySelectorAll('body > .sidebar, body > div > .sidebar:not(.app-container > .sidebar)');
            if (sidebarsFuera.length > 0) {
                console.warn('⚠️ Se encontraron sidebars fuera del app-container, ocultándolos');
                sidebarsFuera.forEach(function(sidebar) {
                    sidebar.style.display = 'none';
                    sidebar.style.visibility = 'hidden';
                });
            }
        });
        
        // Interceptar clicks en el navbar que apunten a acciones del asesor
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                // Interceptar clicks en los elementos li del sidebar
                sidebar.addEventListener('click', function(e) {
                    const li = e.target.closest('li');
                    if (li) {
                        // Obtener el atributo onclick si existe
                        const onclickAttr = li.getAttribute('onclick');
                        if (onclickAttr) {
                            // Si es toggleTiempoModal, ejecutarlo en el iframe
                            if (onclickAttr.includes('toggleTiempoModal()')) {
                                e.preventDefault();
                                e.stopPropagation();
                                const iframe = document.getElementById('content-frame');
                                if (iframe && iframe.contentWindow) {
                                    try {
                                        iframe.contentWindow.toggleTiempoModal();
                                    } catch (err) {
                                        console.warn('Error al llamar toggleTiempoModal en iframe:', err);
                                    }
                                }
                                return false;
                            }
                            
                            // Si contiene index.php?action=, interceptar y navegar en iframe
                            if (onclickAttr.includes('index.php?action=')) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                // Extraer la URL del onclick
                                const match = onclickAttr.match(/index\.php\?action=([^'"]+)/);
                                if (match) {
                                    const url = 'index.php?action=' + match[1];
                                    try {
                                        const urlObj = new URL(url, window.location.origin);
                                        const actionParam = urlObj.searchParams.get('action');
                                        if (actionParam && 
                                            (actionParam.startsWith('asesor_') || 
                                             actionParam === 'asesor_dashboard')) {
                                            navegarEnIframe(url);
                                            return false;
                                        }
                                    } catch (err) {
                                        console.warn('Error al procesar URL:', err);
                                    }
                                }
                            }
                        }
                    }
                    
                    // También manejar clicks en enlaces dentro del sidebar (como logout)
                    const link = e.target.closest('a');
                    if (link && link.href) {
                        try {
                            const url = new URL(link.href, window.location.origin);
                            const action = url.searchParams.get('action');
                            // Para logout, permitir navegación normal (no interceptar)
                            if (action === 'logout') {
                                return true; // Permitir logout normal
                            }
                            if (action && 
                                (action.startsWith('asesor_') || 
                                 action === 'asesor_dashboard')) {
                                e.preventDefault();
                                navegarEnIframe(link.href);
                            }
                        } catch (err) {
                            console.warn('Error al procesar URL:', err);
                        }
                    }
                });
            }
        });
        
        // NOTA: El listener de mensajes ya está definido arriba (línea ~212)
        // No duplicar aquí - todos los mensajes se manejan en un solo lugar
    </script>
</body>
</html>

