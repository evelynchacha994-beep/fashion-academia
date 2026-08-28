<?php
// backend/index.php - Punto de entrada y Health Check para Render / Apache
header('Content-Type: application/json');

// Permitir solicitudes de origen cruzado (CORS)
$httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $httpOrigin");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Si la petición a index.php incluye la acción de la API, delegar a api.php
if (isset($_GET['action'])) {
    require_once __DIR__ . '/api.php';
    exit;
}

// Respuesta predeterminada para el Health Check de Render (Evita error 403 / Directory Index forbidden)
http_response_code(200);
echo json_encode([
    'status' => 'online',
    'service' => 'Fashion Academia Backend API',
    'version' => '1.0',
    'timestamp' => date('c')
]);
?>
