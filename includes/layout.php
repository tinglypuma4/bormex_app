<?php
// Layout principal para todas las páginas del sistema
if (!defined('APP_NAME')) {
    die('Acceso directo no permitido');
}

// Variables por defecto si no están definidas
$page_title = $page_title ?? 'Sistema BORMEX';
$page_description = $page_description ?? 'Sistema de gestión BORMEX';
$current_page = $current_page ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="robots" content="noindex,nofollow">
    
    <!-- CSS Principal - Orden importante -->
<link rel="stylesheet" href="public_html/assets/css/style.css">
<link rel="stylesheet" href="public_html/assets/css/layout.css">
<link rel="stylesheet" href="public_html/assets/css/dashboard.css">
    
    <!-- CSS adicionales si se necesitan -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="public_html/assets/images/favicon.ico">
</head>
<body data-page="<?php echo htmlspecialchars($current_page); ?>">
    <!-- Incluir Navbar -->
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Incluir Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Contenido Principal -->
    <main class="main-content" id="mainContent">
        <?php 
        // Aquí se incluirá el contenido específico de cada página
        // El contenido se define en el archivo que incluye este layout
        ?>
        
        <!-- Overlay para móvil -->
        <div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>
    </main>
    
    <!-- Container para notificaciones toast -->
    <div class="notification-container" id="notificationContainer"></div>
    
    <!-- JavaScript Principal -->
    <script src="public_html/assets/js/dashboard.js"></script>
    
    <!-- JavaScript adicionales si se necesitan -->
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo htmlspecialchars($js_file); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- JavaScript inline si se necesita -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?php echo $inline_js; ?>
        </script>
    <?php endif; ?>
</body>
</html>