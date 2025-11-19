<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Hash de Contraseña - APEX CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            font-family: 'Courier New', monospace;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
            background: #f8f9fa;
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .result {
            margin-top: 30px;
            padding: 20px;
            background: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 10px;
            display: none;
        }
        
        .result.show {
            display: block;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .result h3 {
            color: #2e7d32;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .hash-display {
            background: white;
            padding: 15px;
            border-radius: 8px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            border: 1px solid #4caf50;
            color: #333;
            font-size: 0.95em;
        }
        
        .copy-btn {
            margin-top: 10px;
            width: auto;
            padding: 10px 20px;
            background: #4caf50;
            font-size: 0.9em;
        }
        
        .copy-btn:hover {
            background: #45a049;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #555;
            line-height: 1.6;
        }
        
        .sql-example {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
        
        .warning h4 {
            color: #856404;
            margin-bottom: 8px;
        }
        
        .icon {
            font-size: 1.5em;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Generador de Hash</h1>
            <p>Genera hashes seguros para contraseñas de usuarios</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <h4><span class="icon">ℹ️</span>¿Cómo usar esta herramienta?</h4>
                <p>
                    1. Ingresa la contraseña que deseas hashear<br>
                    2. Haz clic en "Generar Hash"<br>
                    3. Copia el hash generado<br>
                    4. Úsalo en tu base de datos o sistema
                </p>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">🔑 Contraseña a hashear:</label>
                    <input 
                        type="text" 
                        id="password" 
                        name="password" 
                        placeholder="Ingresa la contraseña (ej: admin123)"
                        required
                        autofocus
                    >
                </div>
                
                <button type="submit" name="generar">
                    🔒 Generar Hash Seguro
                </button>
            </form>
            
            <?php
            if (isset($_POST['generar']) && !empty($_POST['password'])) {
                $password = $_POST['password'];
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $verify = password_verify($password, $hash) ? 'Válido ✅' : 'Inválido ❌';
                
                echo '<div class="result show">';
                echo '<h3><span class="icon">✅</span>Hash generado exitosamente</h3>';
                
                echo '<div class="form-group">';
                echo '<label>Contraseña original:</label>';
                echo '<div class="hash-display" style="background: #fff3cd;">' . htmlspecialchars($password) . '</div>';
                echo '</div>';
                
                echo '<div class="form-group">';
                echo '<label>Hash generado (copia esto):</label>';
                echo '<textarea id="hashOutput" readonly class="hash-display">' . htmlspecialchars($hash) . '</textarea>';
                echo '<button type="button" class="copy-btn" onclick="copyHash()">📋 Copiar Hash</button>';
                echo '</div>';
                
                echo '<div class="form-group">';
                echo '<label>Verificación:</label>';
                echo '<div class="hash-display" style="background: #e8f5e9; color: #2e7d32; font-weight: bold;">' . $verify . '</div>';
                echo '</div>';
                
                echo '<div class="sql-example">';
                echo '<strong>📝 Ejemplo de uso en SQL:</strong><br><br>';
                echo "UPDATE usuarios SET password = '" . htmlspecialchars($hash) . "' WHERE cedula = 'TU_CEDULA';";
                echo '</div>';
                
                echo '</div>';
            }
            ?>
            
            <div class="warning">
                <h4>⚠️ Importante</h4>
                <p>
                    • Nunca compartas tu contraseña hasheada públicamente<br>
                    • Cada vez que generes un hash de la misma contraseña, será diferente (esto es normal y seguro)<br>
                    • Guarda este hash en la base de datos en el campo 'password'<br>
                    • El sistema verificará automáticamente la contraseña al iniciar sesión
                </p>
            </div>
            
            <div class="info-box" style="margin-top: 20px;">
                <h4>🗄️ Ejemplo completo de INSERT SQL</h4>
                <div class="sql-example">
INSERT INTO usuarios (<br>
&nbsp;&nbsp;&nbsp;&nbsp;cedula,<br>
&nbsp;&nbsp;&nbsp;&nbsp;nombre_completo,<br>
&nbsp;&nbsp;&nbsp;&nbsp;email,<br>
&nbsp;&nbsp;&nbsp;&nbsp;password,<br>
&nbsp;&nbsp;&nbsp;&nbsp;rol,<br>
&nbsp;&nbsp;&nbsp;&nbsp;estado<br>
) VALUES (<br>
&nbsp;&nbsp;&nbsp;&nbsp;'1234567890',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'Tu Nombre',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'tu@email.com',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'<span style="color: #e91e63;">TU_HASH_AQUI</span>',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'administrador',<br>
&nbsp;&nbsp;&nbsp;&nbsp;'activo'<br>
);
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyHash() {
            const hashOutput = document.getElementById('hashOutput');
            hashOutput.select();
            document.execCommand('copy');
            
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✅ Copiado!';
            btn.style.background = '#4caf50';
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '';
            }, 2000);
        }
    </script>
</body>
</html>

