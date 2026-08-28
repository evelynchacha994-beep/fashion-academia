<?php
// ==========================================
// API BACKEND - FASHION ACADEMIA (FINAL VERSION + CERTIFICADO MODERNO)
// Compatible: Docker Local + Render/Neon Remote
// ==========================================
header('Content-Type: application/json');

// Configuración segura de CORS para producción
$allowedOrigin = getenv('FRONTEND_URL') ?: 'https://fashion-academiafrontend.netlify.app';
header("Access-Control-Allow-Origin: $allowedOrigin");

header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Dompdf\Dompdf;
use Dompdf\Options;

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

    // ==================== DOCENTES (CRUD COMPLETO) ====================
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

    case 'update_teacher':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $conn->prepare("UPDATE docentes SET cedula=:c, nombres=:n, apellidos=:a, especialidad=:e, telefono=:t, correo=:co WHERE id_docente=:id");
            $stmt->execute([':c' => $data['cedula'], ':n' => $data['nombres'], ':a' => $data['apellidos'], ':e' => $data['especialidad'], ':t' => $data['telefono'], ':co' => $data['correo'], ':id' => $data['id_docente']]);
            echo json_encode(['success' => true, 'message' => 'Docente actualizado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => strpos($e->getMessage(), 'duplicate') !== false ? 'La cédula ya está registrada' : $e->getMessage()]);
        }
        break;

    case 'delete_teacher':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $conn->prepare("DELETE FROM docentes WHERE id_docente = :id")->execute([':id' => $data['id_docente']]);
            echo json_encode(['success' => true, 'message' => 'Docente eliminado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar: tiene horarios asignados']);
        }
        break;

    // ==================== CURSOS Y HORARIOS (CRUD COMPLETO) ====================
    case 'get_courses':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        echo json_encode(['success' => true, 'courses' => $conn->query("SELECT id_curso, nombre, nivel, duracion_horas, costo, descripcion FROM cursos ORDER BY nombre ASC")->fetchAll()]);
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

    case 'add_course':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $conn->prepare("INSERT INTO cursos (id_categoria, nombre, descripcion, duracion_horas, costo, nivel) VALUES (5, :n, :d, :h, :c, :l)");
            $stmt->execute([':n' => $data['nombre'], ':d' => $data['descripcion'] ?? '', ':h' => $data['duracion'], ':c' => $data['costo'], ':l' => $data['nivel']]);
            echo json_encode(['success' => true, 'message' => 'Curso creado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update_course':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $conn->prepare("UPDATE cursos SET nombre=:n, descripcion=:d, duracion_horas=:h, costo=:c, nivel=:l WHERE id_curso=:id");
            $stmt->execute([':n' => $data['nombre'], ':d' => $data['descripcion'] ?? '', ':h' => $data['duracion'], ':c' => $data['costo'], ':l' => $data['nivel'], ':id' => $data['id_curso']]);
            echo json_encode(['success' => true, 'message' => 'Curso actualizado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete_course':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $conn->prepare("DELETE FROM cursos WHERE id_curso = :id")->execute([':id' => $data['id_curso']]);
            echo json_encode(['success' => true, 'message' => 'Curso eliminado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar: tiene matrículas activas']);
        }
        break;

    // ==================== MATRÍCULAS Y REGISTRO ====================
    case 'register_and_enroll':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $conn->beginTransaction();

            // 1. Insertar Estudiante
            $stmtEst = $conn->prepare("INSERT INTO estudiantes (cedula, nombres, apellidos, telefono, correo, estado) VALUES (:c, :n, :a, :t, :co, 'Activo') RETURNING id_estudiante");
            $stmtEst->execute([
                ':c' => $data['cedula'], ':n' => $data['nombres'], ':a' => $data['apellidos'], 
                ':t' => $data['telefono'], ':co' => $data['correo']
            ]);
            $new_id_est = $stmtEst->fetchColumn();

            // 2. Buscar horario disponible para el curso seleccionado
            $stmtHor = $conn->prepare("SELECT id_horario FROM horarios WHERE id_curso = :cid LIMIT 1");
            $stmtHor->execute([':cid' => $data['id_curso']]);
            $id_horario = $stmtHor->fetchColumn();

            if (!$id_horario) throw new Exception("No hay horarios disponibles para este curso");

            // 3. Insertar Matrícula
            $stmtMat = $conn->prepare("INSERT INTO matriculas (id_estudiante, id_curso, id_horario, fecha_matricula, estado) VALUES (:est, :cur, :hor, CURRENT_DATE, 'En Curso')");
            $stmtMat->execute([':est' => $new_id_est, ':cur' => $data['id_curso'], ':hor' => $id_horario]);

            // 4. Insertar Pago Automático
            $costo = $conn->query("SELECT costo FROM cursos WHERE id_curso = {$data['id_curso']}")->fetchColumn();
            $conn->prepare("INSERT INTO pagos (id_matricula, monto, metodo_pago, referencia) VALUES (currval('matriculas_id_matricula_seq'), :monto, 'Efectivo', 'MAT-NEW')")
                 ->execute([':monto' => $costo]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Estudiante registrado y matriculado exitosamente']);

        } catch (Exception $e) {
            $conn->rollBack();
            $msg = strpos($e->getMessage(), 'duplicate') !== false ? 'La cédula ya está registrada' : $e->getMessage();
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        break;

    // ==================== REPORTES Y EXPORTACIÓN ====================
    case 'get_reports':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $stats = [
            'total_students' => $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn(),
            'total_teachers' => $conn->query("SELECT COUNT(*) FROM docentes")->fetchColumn(),
            'en_curso' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'En Curso'")->fetchColumn(),
            'aprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Aprobado'")->fetchColumn(),
            'reprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Reprobado'")->fetchColumn()
        ];
        $courseStats = $conn->query("SELECT c.nombre, COUNT(m.id_matricula) as total, SUM(CASE WHEN m.estado = 'En Curso' THEN 1 ELSE 0 END) as en_curso, SUM(CASE WHEN m.estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados, SUM(CASE WHEN m.estado = 'Reprobado' THEN 1 ELSE 0 END) as reprobados FROM cursos c LEFT JOIN matriculas m ON c.id_curso = m.id_curso GROUP BY c.nombre ORDER BY c.nombre ASC")->fetchAll();
        echo json_encode(['success' => true, 'stats' => $stats, 'course_stats' => $courseStats]);
        break;

    case 'get_export_data':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false]); exit; }
        $students = $conn->query("SELECT e.id_estudiante, e.cedula, e.nombres, e.apellidos, c.nombre as curso, m.estado FROM estudiantes e LEFT JOIN matriculas m ON e.id_estudiante = m.id_estudiante LEFT JOIN cursos c ON m.id_curso = c.id_curso ORDER BY e.id_estudiante")->fetchAll();
        $teachers = $conn->query("SELECT id_docente, cedula, nombres, apellidos, especialidad, telefono, correo FROM docentes ORDER BY id_docente")->fetchAll();
        $stats = ['total_students' => $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn(), 'en_curso' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'En Curso'")->fetchColumn(), 'aprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Aprobado'")->fetchColumn(), 'reprobados' => $conn->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'Reprobado'")->fetchColumn()];
        echo json_encode(['success' => true, 'students' => $students, 'teachers' => $teachers, 'stats' => $stats]);
        break;

    // ==================== GENERAR CERTIFICADO PDF (MODERNO Y HORIZONTAL) ====================
    case 'generate_certificate':
        if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
        
        $id_est = $_GET['id_estudiante'] ?? 0;
        
        $sql = "SELECT e.nombres, e.apellidos, e.cedula, c.nombre as curso, m.fecha_matricula 
                FROM estudiantes e 
                JOIN matriculas m ON e.id_estudiante = m.id_estudiante 
                JOIN cursos c ON m.id_curso = c.id_curso 
                WHERE e.id_estudiante = :id AND m.estado = 'Aprobado' 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id_est]);
        $data = $stmt->fetch();

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado o no está Aprobado']);
            break;
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        // DISEÑO MODERNO CON FIRMAS HORIZONTALES PERFECTAS
        $html = "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <style>
                @page { margin: 0; size: A4 landscape; }
                body { 
                    font-family: 'Helvetica', 'Arial', sans-serif; 
                    margin: 0; padding: 0; 
                    background-color: #ffffff;
                    color: #333;
                }
                
                /* Marco Exterior Moderno */
                .border-frame {
                    position: absolute;
                    top: 20px; left: 20px; right: 20px; bottom: 20px;
                    border: 3px solid #6a1b9a;
                    z-index: 1;
                }
                
                /* Contenido Centrado */
                .content-wrapper {
                    position: relative;
                    z-index: 2;
                    height: 100vh;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    text-align: center;
                    padding: 0 60px;
                }

                .logo-text {
                    font-size: 14px;
                    letter-spacing: 4px;
                    text-transform: uppercase;
                    color: #888;
                    margin-bottom: 10px;
                    font-weight: bold;
                }

                h1.title {
                    font-size: 52px;
                    color: #4a148c;
                    margin: 0 0 15px 0;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                .subtitle {
                    font-size: 18px;
                    color: #555;
                    margin-bottom: 40px;
                    font-style: italic;
                }

                .intro-text {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 10px;
                }

                .student-name {
                    font-size: 46px;
                    color: #2c003e;
                    font-weight: bold;
                    margin: 10px 0 30px 0;
                    border-bottom: 2px solid #e1bee7;
                    padding-bottom: 10px;
                    display: inline-block;
                    min-width: 50%;
                }

                .course-label {
                    font-size: 16px;
                    color: #666;
                    margin-top: 20px;
                }

                .course-name {
                    font-size: 34px;
                    color: #6a1b9a;
                    font-weight: bold;
                    margin: 10px 0 40px 0;
                }

                .date-text {
                    font-size: 16px;
                    color: #777;
                    margin-bottom: 60px;
                }

                /* SECCIÓN DE FIRMAS HORIZONTALES */
                .signatures-container {
                    display: flex;
                    justify-content: space-between;
                    width: 60%;
                    margin-top: auto;
                    margin-bottom: 40px;
                }

                .signature-block {
                    text-align: center;
                    width: 45%;
                }

                .sig-line {
                    border-top: 2px solid #4a148c;
                    width: 100%;
                    margin-bottom: 10px;
                }

                .sig-role {
                    font-size: 14px;
                    font-weight: bold;
                    color: #4a148c;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
            </style>
        </head>
        <body>
            <div class='border-frame'></div>
            
            <div class='content-wrapper'>
                <div class='logo-text'>ACADEMIA FASHION</div>
                <h1 class='title'>CERTIFICADO DE APROBACIÓN</h1>
                <p class='subtitle'>Sistema de Administración Profesional</p>
                
                <p class='intro-text'>Se otorga el presente reconocimiento a:</p>
                
                <div class='student-name'>" . htmlspecialchars($data['nombres'] . ' ' . $data['apellidos']) . "</div>
                
                <p class='course-label'>Por haber culminado satisfactoriamente el programa de:</p>
                <div class='course-name'>" . htmlspecialchars($data['curso']) . "</div>
                
                <div class='date-text'>Quito, " . date('d \d\e F \d\e Y', strtotime($data['fecha_matricula'] . ' + 3 months')) . "</div>
                
                <!-- FIRMAS ALINEADAS HORIZONTALMENTE -->
                <div class='signatures-container'>
                    <div class='signature-block'>
                        <div class='sig-line'></div>
                        <div class='sig-role'>Director Académico</div>
                    </div>
                    <div class='signature-block'>
                        <div class='sig-line'></div>
                        <div class='sig-role'>Secretaria General</div>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Certificado_' . str_replace(' ', '_', $data['apellidos']) . '.pdf"');
        $dompdf->stream();
        exit;

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