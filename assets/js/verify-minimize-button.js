/**
 * Script de verificación del botón de minimizar del softphone
 * Verifica que el botón esté presente, visible y funcional
 */

(function() {
    'use strict';
    
    console.log('🔍 [Verificación] Iniciando verificación del botón de minimizar...');
    
    // Esperar a que el DOM esté listo
    function verificarBotónMinimizar() {
        const resultados = {
            contenedorExiste: false,
            botonMinimizarExiste: false,
            botonMinimizarVisible: false,
            vistaMinimizadaExiste: false,
            funcionToggleExiste: false,
            estilosAplicados: false,
            errores: []
        };
        
        // 1. Verificar que el contenedor del softphone existe
        const container = document.getElementById('webrtc-softphone');
        if (container) {
            resultados.contenedorExiste = true;
            console.log('✅ [Verificación] Contenedor #webrtc-softphone encontrado');
        } else {
            resultados.errores.push('❌ Contenedor #webrtc-softphone NO encontrado');
            console.error('❌ [Verificación] Contenedor #webrtc-softphone NO encontrado');
        }
        
        // 2. Verificar que el botón de minimizar existe
        const btnMinimize = document.getElementById('btn-minimize');
        if (btnMinimize) {
            resultados.botonMinimizarExiste = true;
            console.log('✅ [Verificación] Botón #btn-minimize encontrado');
            
            // Verificar visibilidad
            const estilos = window.getComputedStyle(btnMinimize);
            if (estilos.display !== 'none' && estilos.visibility !== 'hidden' && estilos.opacity !== '0') {
                resultados.botonMinimizarVisible = true;
                console.log('✅ [Verificación] Botón de minimizar es visible');
            } else {
                resultados.errores.push('⚠️ Botón de minimizar existe pero NO es visible');
                console.warn('⚠️ [Verificación] Botón de minimizar existe pero NO es visible', {
                    display: estilos.display,
                    visibility: estilos.visibility,
                    opacity: estilos.opacity
                });
            }
            
            // Verificar que tiene el onclick
            const onclick = btnMinimize.getAttribute('onclick');
            if (onclick && onclick.includes('toggleMinimize')) {
                console.log('✅ [Verificación] Botón tiene onclick correcto:', onclick);
            } else {
                resultados.errores.push('⚠️ Botón NO tiene onclick o es incorrecto');
                console.warn('⚠️ [Verificación] Botón NO tiene onclick correcto');
            }
            
            // Verificar estilos del botón
            const btnEstilos = window.getComputedStyle(btnMinimize);
            console.log('📊 [Verificación] Estilos del botón:', {
                width: btnEstilos.width,
                height: btnEstilos.height,
                display: btnEstilos.display,
                visibility: btnEstilos.visibility,
                opacity: btnEstilos.opacity,
                backgroundColor: btnEstilos.backgroundColor,
                color: btnEstilos.color
            });
            
        } else {
            resultados.errores.push('❌ Botón #btn-minimize NO encontrado');
            console.error('❌ [Verificación] Botón #btn-minimize NO encontrado');
        }
        
        // 3. Verificar que la vista minimizada existe
        const minimizedView = document.getElementById('softphone-minimized');
        if (minimizedView) {
            resultados.vistaMinimizadaExiste = true;
            console.log('✅ [Verificación] Vista minimizada #softphone-minimized encontrada');
            
            // Verificar el botón de expandir dentro de la vista minimizada
            const btnExpand = minimizedView.querySelector('.softphone-expand-btn');
            if (btnExpand) {
                console.log('✅ [Verificación] Botón de expandir encontrado en vista minimizada');
            } else {
                resultados.errores.push('⚠️ Botón de expandir NO encontrado en vista minimizada');
                console.warn('⚠️ [Verificación] Botón de expandir NO encontrado en vista minimizada');
            }
        } else {
            resultados.errores.push('❌ Vista minimizada #softphone-minimized NO encontrada');
            console.error('❌ [Verificación] Vista minimizada #softphone-minimized NO encontrada');
        }
        
        // 4. Verificar que la función toggleMinimize existe
        if (window.webrtcSoftphone && typeof window.webrtcSoftphone.toggleMinimize === 'function') {
            resultados.funcionToggleExiste = true;
            console.log('✅ [Verificación] Función toggleMinimize() existe');
        } else {
            resultados.errores.push('❌ Función toggleMinimize() NO existe en window.webrtcSoftphone');
            console.error('❌ [Verificación] Función toggleMinimize() NO existe', {
                webrtcSoftphone: !!window.webrtcSoftphone,
                toggleMinimize: window.webrtcSoftphone ? typeof window.webrtcSoftphone.toggleMinimize : 'N/A'
            });
        }
        
        // 5. Verificar estilos CSS aplicados
        const header = container?.querySelector('.softphone-header');
        const headerActions = container?.querySelector('.softphone-header-actions');
        if (header && headerActions) {
            const headerEstilos = window.getComputedStyle(header);
            const actionsEstilos = window.getComputedStyle(headerActions);
            
            console.log('📊 [Verificación] Estilos del header:', {
                display: headerEstilos.display,
                justifyContent: headerEstilos.justifyContent,
                alignItems: headerEstilos.alignItems
            });
            
            console.log('📊 [Verificación] Estilos de header-actions:', {
                display: actionsEstilos.display,
                gap: actionsEstilos.gap
            });
            
            if (actionsEstilos.display !== 'none') {
                resultados.estilosAplicados = true;
                console.log('✅ [Verificación] Estilos CSS aplicados correctamente');
            } else {
                resultados.errores.push('⚠️ .softphone-header-actions tiene display: none');
                console.warn('⚠️ [Verificación] .softphone-header-actions tiene display: none');
            }
        }
        
        // 6. Verificar wrapper
        const wrapper = document.querySelector('.seccion-softphone-wrapper');
        if (wrapper) {
            console.log('✅ [Verificación] Wrapper .seccion-softphone-wrapper encontrado');
            const wrapperEstilos = window.getComputedStyle(wrapper);
            console.log('📊 [Verificación] Estilos del wrapper:', {
                position: wrapperEstilos.position,
                bottom: wrapperEstilos.bottom,
                right: wrapperEstilos.right,
                width: wrapperEstilos.width,
                zIndex: wrapperEstilos.zIndex
            });
        } else {
            resultados.errores.push('⚠️ Wrapper .seccion-softphone-wrapper NO encontrado');
            console.warn('⚠️ [Verificación] Wrapper .seccion-softphone-wrapper NO encontrado');
        }
        
        // Resumen
        console.log('\n📋 [Verificación] RESUMEN:');
        console.log('  ✅ Contenedor existe:', resultados.contenedorExiste);
        console.log('  ✅ Botón minimizar existe:', resultados.botonMinimizarExiste);
        console.log('  ✅ Botón minimizar visible:', resultados.botonMinimizarVisible);
        console.log('  ✅ Vista minimizada existe:', resultados.vistaMinimizadaExiste);
        console.log('  ✅ Función toggleMinimize existe:', resultados.funcionToggleExiste);
        console.log('  ✅ Estilos aplicados:', resultados.estilosAplicados);
        
        if (resultados.errores.length > 0) {
            console.warn('\n⚠️ [Verificación] ERRORES ENCONTRADOS:');
            resultados.errores.forEach(error => console.warn('  ', error));
        } else {
            console.log('\n✅ [Verificación] TODOS LOS ELEMENTOS ESTÁN CORRECTOS');
        }
        
        // Prueba funcional del botón
        if (resultados.botonMinimizarExiste && resultados.funcionToggleExiste) {
            console.log('\n🧪 [Verificación] Probando funcionalidad del botón...');
            try {
                const estadoInicial = window.webrtcSoftphone.isMinimized;
                console.log('  Estado inicial (minimizado):', estadoInicial);
                
                // Simular click en el botón
                if (btnMinimize) {
                    console.log('  Simulando click en el botón...');
                    btnMinimize.click();
                    
                    setTimeout(() => {
                        const estadoDespues = window.webrtcSoftphone.isMinimized;
                        console.log('  Estado después del click (minimizado):', estadoDespues);
                        
                        if (estadoDespues !== estadoInicial) {
                            console.log('  ✅ [Verificación] El botón funciona correctamente');
                            
                            // Restaurar estado original
                            setTimeout(() => {
                                if (window.webrtcSoftphone.isMinimized !== estadoInicial) {
                                    btnMinimize.click();
                                    console.log('  Estado restaurado');
                                }
                            }, 500);
                        } else {
                            console.warn('  ⚠️ [Verificación] El botón NO cambió el estado');
                        }
                    }, 300);
                }
            } catch (error) {
                console.error('  ❌ [Verificación] Error al probar funcionalidad:', error);
            }
        }
        
        return resultados;
    }
    
    // Ejecutar verificación cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Esperar un poco más para que el softphone se inicialice
            setTimeout(verificarBotónMinimizar, 2000);
        });
    } else {
        // Esperar un poco más para que el softphone se inicialice
        setTimeout(verificarBotónMinimizar, 2000);
    }
    
    // También exponer función global para ejecutar manualmente
    window.verificarBotonMinimizar = verificarBotónMinimizar;
    
    console.log('💡 [Verificación] Ejecuta verificarBotonMinimizar() en la consola para verificar manualmente');
})();

