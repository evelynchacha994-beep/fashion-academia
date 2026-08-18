<?php
header('Content-Type: application/json');
// IMPORTANTE: En producción, cambia '*' por la URL de tu frontend (ej: https://academia-fashion.netlify.app)
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Configuración DB Robusta
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'academia_fashion';
$user = getenv('DB_USER') ?: 'evelyn';
$pass = getenv('DB_PASS') ?: 'fashion2026';

try {
    // Añadimos sslmode=require para compatibilidad con Render/Neon
    $dsn = "pgsql:host=$host;dbname=$db;sslmode=require";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error crítico de conexión a BD']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['usuario']) || empty($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            break;
        }
        
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = :u AND contraseña = :p AND estado = 'Activo'");
        $stmt->execute([':u' => $data['usuario'], ':p' => $data['password']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['usuario'];
            $_SESSION['user_rol'] = $user['rol'];
            // Actualizar último acceso
            $update = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id");
            $update->execute([':id' => $user['id_usuario']]);
            
            echo json_encode(['success' => true, 'user' => [
                'usuario' => $user['usuario'], 
                'rol' => $user['rol']
            ]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas o usuario inactivo']);
        }
        break;

    case 'get_students':
        if (!isset($_SESSION['user_id'])) { 
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado']); 
            exit; 
        }
        $sql = "SELECT e.id_estudiante, e.cedula, e.nombres, e.apellidos, e.telefono, e.estado as est_estado,
                       c.nombre as curso_nombre, m.estado as matricula_estado
                FROM estudiantes e
                LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante
                LEFT JOIN cursos c ON m.id_curso = c.id_curso
                ORDER BY e.id_estudiante DESC";
        $students = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'students' => $students]);
        break;

    case 'get_courses':
        if (!isset($_SESSION['user_id'])) { 
            http_response_code(401);
            echo json_encode(['success' => false]); 
            exit; 
        }
        $courses = $conn->query("SELECT id_curso, nombre FROM cursos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;

    case 'upload_excel':
        if (!isset($_SESSION['user_id'])) { 
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado']); 
            exit; 
        }
        if (!isset($_FILES['results_file'])) { 
            echo json_encode(['success' => false, 'message' => 'No se recibió archivo']); 
            exit; 
        }
        
        try {
            $file_tmp = $_FILES['results_file']['tmp_name'];
            $spreadsheet = IOFactory::load($file_tmp);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $updated = 0; 
            $not_found = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                // Saltar filas vacías
                if (empty($row[1]) && empty($row[2])) continue;
                
                // Limpieza robusta de cédula
                $cedula_raw = trim((string)$row[1]);
                $cedula = preg_replace('/[^0-9]/', '', $cedula_raw);
                if (strlen($cedula) !== 10) {
                    $errors[] = "Fila $i: Cédula inválida ($cedula_raw)";
                    continue;
                }
                
                $nuevo_estado = trim((string)$row[5]);
                $nuevo_estado_lower = strtolower($nuevo_estado);
                
                // Mapeo seguro de estados
                $estado_final = null;
                if (in_array($nuevo_estado_lower, ['en curso', 'encurso'])) $estado_final = 'En Curso';
                elseif (in_array($nuevo_estado_lower, ['aprobado', 'aprobados'])) $estado_final = 'Aprobado';
                elseif (in_array($nuevo_estado_lower, ['reprobado', 'reprobados'])) $estado_final = 'Reprobado';
                
                if (!$estado_final) continue; // Estado no reconocido, saltar
                
                // Actualizar matrícula
                $stmt = $conn->prepare("
                    UPDATE matriculas m 
                    SET estado = :estado 
                    FROM estudiantes e 
                    WHERE m.id_estudiante = e.id_estudiante 
                    AND e.cedula = :cedula
                ");
                $stmt->execute([':estado' => $estado_final, ':cedula' => $cedula]);
                
                if ($stmt->rowCount() > 0) {
                    $updated++;
                    // Generar certificado si aprobó
                    if ($estado_final === 'Aprobado') {
                        $check_cert = $conn->prepare("
                            SELECT m.id_matricula 
                            FROM matriculas m 
                            JOIN estudiantes e ON m.id_estudiante = e.id_estudiante 
                            WHERE e.cedula = :c
                        ");
                        $check_cert->execute([':c' => $cedula]);
                        $mat_id = $check_cert->fetchColumn();
                        
                        if ($mat_id) {
                            $exist_cert = $conn->prepare("SELECT id_certificado FROM certificados WHERE id_matricula = :mid");
                            $exist_cert->execute([':mid' => $mat_id]);
                            
                            if (!$exist_cert->fetchColumn()) {
                                $sql_insert_cert = "INSERT INTO certificados (id_matricula, fecha_emision, numero_certificado, qr_code) 
                                                    VALUES (:mid, CURRENT_DATE, 'CERT-2026-' || LPAD(:mid::TEXT, 4, '0'), 'QR-GEN')";
                                $conn->prepare($sql_insert_cert)->execute([':mid' => $mat_id]);
                            }
                        }
                    }
                } else { 
                    $not_found++; 
                }
            }
            
            $msg = "✅ Proceso completado: $updated actualizados, $not_found no encontrados.";
            if (!empty($errors)) $msg .= " | ⚠️ Errores: " . implode(", ", array_slice($errors, 0, 3));
            
            echo json_encode(['success' => true, 'message' => $msg]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '❌ Error al procesar Excel: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>