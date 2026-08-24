<?php
// ==========================================
// API BACKEND - FASHION ACADEMIA (FULL VERSION)
// Compatible: Docker Local + Render/Neon Remote
// ==========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- CONFIGURACIÓN Y CONEXIÓN ROBUSTA ---
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'academia_fashion';
$user = getenv('DB_USER') ?: 'evelyn';
$pass = getenv('DB_PASS') ?: 'fashion2026';

try {
    $sslParam = '';
    if (getenv('DATABASE_URL') || getenv('RENDER')) {
        $sslParam = ';sslmode=require';
    }
    
    $dsn = "pgsql:host=$host;dbname=$db$sslParam";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("DB Connection Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error crítico de conexión a BD']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    // ==================== LOGIN ====================
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['usuario']) || empty($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            break;
        }
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = :u AND estado = 'Activo'");
        $stmt->execute([':u' => $data['usuario']]);
        $userRow = $stmt->fetch();

        $validPass = false;
        if ($userRow) {
            if (password_verify($data['password'], $userRow['contraseña'])) $validPass = true;
            elseif ($data['password'] === $userRow['contraseña']) $validPass = true;
        }

        if ($validPass) {
            $_SESSION['user_id'] = $userRow['id_usuario'];
            $_SESSION['user_name'] = $userRow['usuario'];
            $_SESSION['user_rol'] = $userRow['rol'];
            $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id")->execute([':id' => $userRow['id_usuario']]);
            echo json_encode(['success' => true, 'user' => ['usuario' => $userRow['usuario'], 'rol' => $userRow['rol']]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Credenciales inválidas o usuario inactivo']);
        }
        break;

    // ==================== LOGOUT ====================
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    // ==================== ESTUDIANTES ====================
    case 'get_students':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $sql = "SELECT e.id_estudiante, e.cedula, e.nombres, e.apellidos, e.telefono, e.estado as est_estado,
                c.nombre as curso_nombre, m.estado as matricula_estado, m.porcentaje_asistencia
                FROM estudiantes e
                LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante
                LEFT JOIN cursos c ON m.id_curso = c.id_curso
                ORDER BY e.id_estudiante DESC";
        echo json_encode(['success' => true, 'students' => $conn->query($sql)->fetchAll()]);
        break;

    // ==================== DOCENTES ====================
    case 'get_teachers':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        echo json_encode(['success' => true, 'teachers' => $conn->query("SELECT * FROM docentes ORDER BY id_docente ASC")->fetchAll()]);
        break;

    case 'add_teacher':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $conn->prepare("INSERT INTO docentes (cedula, nombres, apellidos, especialidad, telefono, correo) VALUES (:c, :n, :a, :e, :t, :co)");
            $stmt->execute([':c' => $data['cedula'], ':n' => $data['nombres'], ':a' => $data['apellidos'], ':e' => $data['especialidad'], ':t' => $data['telefono'], ':co' => $data['correo']]);
            echo json_encode(['success' => true, 'message' => 'Docente registrado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => strpos($e->getMessage(), 'duplicate') !== false ? 'La cédula ya está registrada' : $e->getMessage()]);
        }
        break;

    // ==================== CURSOS Y HORARIOS ====================
    case 'get_courses':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        echo json_encode(['success' => true, 'courses' => $conn->query("SELECT id_curso, nombre, nivel, duracion_horas FROM cursos ORDER BY nombre ASC")->fetchAll()]);
        break;

    case 'get_schedules':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $sql = "SELECT h.id_horario, h.dia, CONCAT(h.hora_inicio, ' - ', h.hora_fin) as horario, 
                c.nombre as curso, d.nombres || ' ' || d.apellidos as docente, h.aula
                FROM horarios h 
                JOIN cursos c ON h.id_curso = c.id_curso 
                JOIN docentes d ON h.id_docente = d.id_docente 
                ORDER BY CASE h.dia WHEN 'Lunes' THEN 1 WHEN 'Martes' THEN 2 WHEN 'Miércoles' THEN 3 WHEN 'Jueves' THEN 4 WHEN 'Viernes' THEN 5 WHEN 'Sábado' THEN 6 ELSE 7 END, h.hora_inicio";
        echo json_encode(['success' => true, 'schedules' => $conn->query($sql)->fetchAll()]);
        break;

    // ==================== MATRÍCULAS ====================
    case 'enroll_student':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $horarioStmt = $conn->prepare("SELECT id_horario FROM horarios WHERE id_curso = :cid LIMIT 1");
            $horarioStmt->execute([':cid' => $data['id_curso']]);
            $id_horario = $horarioStmt->fetchColumn();

            if (!$id_horario) {
                echo json_encode(['success' => false, 'message' => 'No hay horarios disponibles para este curso']);
                break;
            }

            $stmt = $conn->prepare("INSERT INTO matriculas (id_estudiante, id_curso, id_horario, fecha_matricula, estado) VALUES (:est, :cur, :hor, CURRENT_DATE, 'En Curso')");
            $stmt->execute([':est' => $data['id_estudiante'], ':cur' => $data['id_curso'], ':hor' => $id_horario]);
            
            $costo = $conn->query("SELECT costo FROM cursos WHERE id_curso = {$data['id_curso']}")->fetchColumn() ?: 0;
            $conn->prepare("INSERT INTO pagos (id_matricula, monto, metodo_pago, referencia) VALUES (currval('matriculas_id_matricula_seq'), :monto, 'Efectivo', 'MAT-AUTO')")
                 ->execute([':monto' => $costo]);

            echo json_encode(['success' => true, 'message' => 'Matrícula realizada con éxito']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => strpos($e->getMessage(), 'duplicate') !== false ? 'El estudiante ya está matriculado en este curso' : $e->getMessage()]);
        }
        break;

    // ==================== REPORTES ====================
    case 'get_reports':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        
        $stats = [
            'total_students' => $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn(),
            'total_teachers' => $conn->query("SELECT COUNT(*) FROM docentes")->fetchColumn(),
            'en_curso' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'En Curso'")->fetchColumn(),
            'aprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Aprobado'")->fetchColumn(),
            'reprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Reprobado'")->fetchColumn()
        ];

        $courseStats = $conn->query("
            SELECT c.nombre, 
                   COUNT(m.id_matricula) as total,
                   SUM(CASE WHEN m.estado = 'En Curso' THEN 1 ELSE 0 END) as en_curso,
                   SUM(CASE WHEN m.estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
                   SUM(CASE WHEN m.estado = 'Reprobado' THEN 1 ELSE 0 END) as reprobados
            FROM cursos c LEFT JOIN matriculas m ON c.id_curso = m.id_curso
            GROUP BY c.nombre ORDER BY c.nombre ASC
        ")->fetchAll();

        echo json_encode(['success' => true, 'stats' => $stats, 'course_stats' => $courseStats]);
        break;

    // ==================== EXPORTAR DATOS PARA EXCEL/PDF ====================
    case 'get_export_data':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        
        $students = $conn->query("SELECT e.id_estudiante, e.cedula, e.nombres, e.apellidos, c.nombre as curso, m.estado 
            FROM estudiantes e LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante LEFT JOIN cursos c ON m.id_curso = c.id_curso ORDER BY e.id_estudiante")->fetchAll();
            
        $teachers = $conn->query("SELECT id_docente, cedula, nombres, apellidos, especialidad, telefono, correo FROM docentes ORDER BY id_docente")->fetchAll();
        
        $stats = [
            'total_students' => $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn(),
            'en_curso' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'En Curso'")->fetchColumn(),
            'aprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Aprobado'")->fetchColumn(),
            'reprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Reprobado'")->fetchColumn()
        ];

        echo json_encode(['success' => true, 'students' => $students, 'teachers' => $teachers, 'stats' => $stats]);
        break;

    // ==================== SUBIR EXCEL ====================
    case 'upload_excel':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        if (!isset($_FILES['results_file'])) { echo json_encode(['success' => false, 'message' => 'No file']); exit; }
        
        try {
            $spreadsheet = IOFactory::load($_FILES['results_file']['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $updated = 0; $not_found = 0; $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty($row[1]) && empty($row[2])) continue;
                
                $cedula = preg_replace('/[^0-9]/', '', trim((string)$row[1]));
                if (strlen($cedula) !== 10) { $errors[] = "Fila $i: Cédula inválida"; continue; }

                $estado_raw = strtolower(trim((string)$row[5]));
                $estado_final = null;
                if (in_array($estado_raw, ['en curso', 'encurso'])) $estado_final = 'En Curso';
                elseif (in_array($estado_raw, ['aprobado', 'aprobados'])) $estado_final = 'Aprobado';
                elseif (in_array($estado_raw, ['reprobado', 'reprobados'])) $estado_final = 'Reprobado';
                
                if (!$estado_final) continue;

                $stmt = $conn->prepare("UPDATE matriculas m SET estado = :estado FROM estudiantes e WHERE m.id_estudiante = e.id_estudiante AND e.cedula = :cedula");
                $stmt->execute([':estado' => $estado_final, ':cedula' => $cedula]);

                if ($stmt->rowCount() > 0) {
                    $updated++;
                    if ($estado_final === 'Aprobado') {
                        $mat = $conn->prepare("SELECT m.id_matricula FROM matriculas m JOIN estudiantes e ON m.id_estudiante = e.id_estudiante WHERE e.cedula = :c");
                        $mat->execute([':c' => $cedula]);
                        $mid = $mat->fetchColumn();
                        if ($mid) {
                            $exist = $conn->prepare("SELECT id_certificado FROM certificados WHERE id_matricula = :m");
                            $exist->execute([':m' => $mid]);
                            if (!$exist->fetchColumn()) {
                                $conn->prepare("INSERT INTO certificados (id_matricula, fecha_emision, numero_certificado, qr_code) VALUES (:m, CURRENT_DATE, 'CERT-2026-' || LPAD(:m::TEXT, 4, '0'), 'QR-AUTO')")->execute([':m' => $mid]);
                            }
                        }
                    }
                } else { $not_found++; }
            }
            echo json_encode(['success' => true, 'message' => "✅ $updated actualizados, $not_found no encontrados." . (!empty($errors) ? " | ⚠️ " . implode(", ", array_slice($errors, 0, 3)) : "")]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => '❌ Error Excel: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>