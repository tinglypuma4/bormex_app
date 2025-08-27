<?php
require_once 'includes/config.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Manejar logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    $success = 'Sesión cerrada correctamente';
}

// Manejar timeout
if (isset($_GET['timeout'])) {
    $error = 'Tu sesión ha expirado. Inicia sesión nuevamente.';
}

// Procesar login
if ($_POST) {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor ingresa usuario y contraseña';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password_hash, role, first_name, last_name, active FROM users WHERE username = ? AND active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password_hash'])) {
                // Login exitoso
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['last_activity'] = time();
                
                // Registrar login en audit_logs
                $stmt = $db->prepare("INSERT INTO audit_logs (table_name, record_id, action, new_values, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    'users', 
                    $user['id'], 
                    'LOGIN', 
                    json_encode(['login_time' => date('Y-m-d H:i:s')]),
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                redirect('dashboard.php');
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error interno. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Iniciar Sesión</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2d5a3d 0%, #4a7c59 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        .login-header {
            background: linear-gradient(135deg, #2d5a3d 0%, #4a7c59 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .logo .bor {
            color: white;
        }

        .logo .mex {
            background: #c53030;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-form {
            padding: 30px 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2d5a3d;
            box-shadow: 0 0 0 3px rgba(45, 90, 61, 0.1);
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #2d5a3d 0%, #4a7c59 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(45, 90, 61, 0.3);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2d5a3d;
            border: 1px solid #9ae6b4;
        }

        .login-footer {
            background: #f7fafc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e2e8f0;
        }

        .default-credentials {
            background: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #2d3748;
        }

        .default-credentials strong {
            color: #2d5a3d;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 20px 15px;
            }
            
            .login-form {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <span class="bor">BOR</span><span class="mex">MEX</span>
            </div>
            <div class="subtitle">Sistema de Gestión</div>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Credenciales por defecto (solo para desarrollo) -->
            <div class="default-credentials">
                <strong>Credenciales por defecto:</strong><br>
                Usuario: <strong>admin</strong><br>
                Contraseña: <strong>password</strong>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           placeholder="Ingresa tu usuario" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Ingresa tu contraseña" required>
                </div>
                
                <button type="submit" class="btn-login">Iniciar Sesión</button>
            </form>
        </div>
        
        <div class="login-footer">
            <?php echo APP_NAME . ' v' . APP_VERSION; ?><br>
            © <?php echo date('Y'); ?> Todos los derechos reservados
        </div>
    </div>
</body>
</html>