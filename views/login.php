<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APEX - Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #1e5ba8 0%, #2d74c4 100%);
        }
        
        .login-container {
            position: relative;
            width: 75vw;
            max-width: 1200px;
            min-width: 320px;
            height: 80vh;
            max-height: 700px;
            min-height: 500px;
            margin: 10vh auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .diagonal-split {
            position: absolute;
            top: 0;
            right: 0;
            width: 55%;
            height: 100%;
            background: linear-gradient(135deg, #1e5ba8 0%, #2d74c4 100%);
            clip-path: polygon(15% 0, 100% 0, 100% 100%, 0% 100%);
            z-index: 1;
        }
        
        .logo-section {
            position: relative;
            float: left;
            width: 45%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            z-index: 2;
        }
        
        .logo {
            text-align: center;
        }
        
        .logo-icon {
            margin-bottom: 20px;
        }
        
        .logo-icon img {
            max-width: 280px;
            height: auto;
        }
        
        .logo-subtitle {
            color: #666;
            font-size: 13px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .form-section {
            position: relative;
            float: right;
            width: 55%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
            z-index: 2;
        }
        
        .form-wrapper {
            width: 100%;
            max-width: 350px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: white;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
        
        input::placeholder {
            color: #999;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            color: white;
            font-size: 13px;
        }
        
        .remember-me input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: white;
        }
        
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #5b7fd4;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            background: #4a6bc3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        /* Mensajes de error */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            background: rgba(254, 226, 226, 0.95);
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .login-container {
                width: 95vw;
                height: auto;
                min-height: 600px;
                margin: 2.5vh auto;
            }
            
            .diagonal-split {
                display: none;
            }
            
            .logo-section,
            .form-section {
                float: none;
                width: 100%;
                padding: 30px;
            }
            
            .form-section {
                background: linear-gradient(135deg, #1e5ba8 0%, #2d74c4 100%);
            }
            
            .logo-icon img {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="diagonal-split"></div>
        
        <div class="logo-section">
            <div class="logo">
                <div class="logo-icon">
                    <img src="img/APEX.png" alt="APEX Logo">
                </div>
                <div class="logo-subtitle">Sistema de Gestión de Clientes</div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-wrapper">
                <?php if (isset($error)): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="index.php?action=login">
                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <input 
                            type="text" 
                            id="usuario" 
                            name="usuario"
                            placeholder="Ingrese su usuario" 
                            required
                            value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                            autocomplete="username"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="contrasena">Contraseña</label>
                        <input 
                            type="password" 
                            id="contrasena" 
                            name="contrasena"
                            placeholder="Ingrese su contraseña" 
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    
                    <div class="remember-forgot">
                        <div class="remember-me">
                            <input type="checkbox" id="remember">
                            <label for="remember" style="margin-bottom: 0; cursor: pointer;">Recordarme</label>
                        </div>
                        <a href="#" class="forgot-password" onclick="alert('Funcionalidad próximamente'); return false;">¿Olvidó su contraseña?</a>
                    </div>
                    
                    <button type="submit" class="btn-login">Iniciar Sesión</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
