<?php
/**
 * API de Estadísticas - BORMEX
 * Endpoint para obtener estadísticas en tiempo real
 */

// Headers para API JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Incluir configuración
require_once '../includes/config.php';

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Consultas para estadísticas
    $stats = [
        'total' => 0,
        'entregadas' => 0,
        'pendientes' => 0,
        'anticipo' => 0,
        'adeudado' => 0,
        'ingresos_mes' => 0,
        'notas_hoy' => 0,
        'clientes_activos' => 0
    ];
    
    // Total de notas
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notes WHERE 1=1");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total'] = (int)$result['total'];
    
    // Notas por estado
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            COALESCE(SUM(current_total), 0) as total_amount
        FROM notes 
        GROUP BY status
    ");
    $stmt->execute();
    $status_results = $stmt->fetchAll();
    
    foreach ($status_results as $row) {
        switch ($row['status']) {
            case 'pagada_y_entregada':
                $stats['entregadas'] = (int)$row['count'];
                break;
            case 'liquidada_pendiente_entrega':
                $stats['pendientes'] += (int)$row['count'];
                $stats['adeudado'] += (float)$row['total_amount'];
                break;
            case 'con_anticipo_trabajandose':
                $stats['anticipo'] += (int)$row['count'];
                $stats['adeudado'] += (float)$row['total_amount'];
                break;
        }
    }
    
    // Ingresos del mes actual
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total), 0) as ingresos
        FROM notes 
        WHERE status = 'pagada_y_entregada' 
        AND YEAR(created_at) = YEAR(CURDATE()) 
        AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['ingresos_mes'] = (float)$result['ingresos'];
    
    // Notas creadas hoy
    $stmt = $db->prepare("
        SELECT COUNT(*) as hoy
        FROM notes 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['notas_hoy'] = (int)$result['hoy'];
    
    // Clientes activos (con notas en los últimos 30 días)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT client_name) as clientes
        FROM notes 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['clientes_activos'] = (int)$result['clientes'];
    
    // Estadísticas adicionales para gráficos
    $stats['tendencias'] = [];
    
    // Notas por mes (últimos 6 meses)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            COUNT(*) as total,
            COALESCE(SUM(total), 0) as ingresos
        FROM notes 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY mes ASC
    ");
    $stmt->execute();
    $monthly_data = $stmt->fetchAll();
    
    $stats['tendencias']['mensual'] = [];
    foreach ($monthly_data as $row) {
        $fecha = DateTime::createFromFormat('Y-m', $row['mes']);
        $stats['tendencias']['mensual'][] = [
            'mes' => $fecha->format('M Y'),
            'notas' => (int)$row['total'],
            'ingresos' => (float)$row['ingresos']
        ];
    }
    
    // Estados de notas para gráfico circular
    $stats['estados_distribucion'] = [];
    foreach ($status_results as $row) {
        $estado_nombre = '';
        switch ($row['status']) {
            case 'pagada_y_entregada':
                $estado_nombre = 'Entregadas';
                break;
            case 'liquidada_pendiente_entrega':
                $estado_nombre = 'Pendiente Entrega';
                break;
            case 'con_anticipo_trabajandose':
                $estado_nombre = 'En Proceso';
                break;
            default:
                $estado_nombre = 'Otros';
        }
        
        $stats['estados_distribucion'][] = [
            'estado' => $estado_nombre,
            'cantidad' => (int)$row['count'],
            'porcentaje' => $stats['total'] > 0 ? round((int)$row['count'] / $stats['total'] * 100, 1) : 0
        ];
    }
    
    // Métodos de pago más usados
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COUNT(*) * 100.0 / (SELECT COUNT(*) FROM notes) as porcentaje
        FROM notes 
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll();
    
    $stats['metodos_pago'] = [];
    foreach ($payment_methods as $row) {
        $metodo_nombre = '';
        switch ($row['payment_method']) {
            case 'efectivo':
                $metodo_nombre = 'Efectivo';
                break;
            case 'transferencia':
                $metodo_nombre = 'Transferencia';
                break;
            case 'tarjeta':
                $metodo_nombre = 'Tarjeta';
                break;
            default:
                $metodo_nombre = ucfirst($row['payment_method']);
        }
        
        $stats['metodos_pago'][] = [
            'metodo' => $metodo_nombre,
            'cantidad' => (int)$row['count'],
            'porcentaje' => round((float)$row['porcentaje'], 1)
        ];
    }
    
    // Top 5 clientes por volumen
    $stmt = $db->prepare("
        SELECT 
            client_name,
            COUNT(*) as total_notas,
            COALESCE(SUM(total), 0) as total_gastado
        FROM notes 
        GROUP BY client_name
        ORDER BY total_gastado DESC, total_notas DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_clients = $stmt->fetchAll();
    
    $stats['top_clientes'] = [];
    foreach ($top_clients as $row) {
        $stats['top_clientes'][] = [
            'nombre' => $row['client_name'],
            'notas' => (int)$row['total_notas'],
            'total' => (float)$row['total_gastado']
        ];
    }
    
    // Información del sistema
    $stats['sistema'] = [
        'version' => APP_VERSION,
        'usuario_actual' => $_SESSION['full_name'],
        'rol_usuario' => $_SESSION['user_role'],
        'ultima_actualizacion' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => time(),
        'cache_ttl' => 300 // 5 minutos
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en API stats: " . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => 'No se pudieron obtener las estadísticas',
        'timestamp' => time()
    ]);
}
?>