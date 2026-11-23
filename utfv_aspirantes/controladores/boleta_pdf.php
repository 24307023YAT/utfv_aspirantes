<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/planes_estudio.php';

function normalizarNombreProfesorBoletaPdf(string $nombre): string {
    $s = mb_strtoupper(trim($nombre), 'UTF-8');
    $s = strtr($s, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'
    ]);
    $s = preg_replace('/^(M\.\s*EN\s*D\.|M\.\s*EN\s*C\.|DR\.|DRA\.|MTRO\.|MTRA\.|ING\.|LIC\.)\s*/u', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

$rolActual = strtoupper(trim($_SESSION['rol'] ?? ''));
$esAlumno = ($rolActual === 'ALUMNO');
$esProfesor = ($rolActual === 'PROFESOR');
$nombreDocenteSesion = normalizarNombreProfesorBoletaPdf((string)($_SESSION['nombre_usuario'] ?? ''));

$matricula = trim($_GET['mat'] ?? '');
$autoPrint = isset($_GET['pdf']) && $_GET['pdf'] === '1';

/* Si es alumno, solo puede consultar su propia matrícula */
if ($esAlumno) {
    $matriculaSesion = trim((string)($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? ''));
    if ($matricula === '' || $matricula !== $matriculaSesion) {
        $matricula = $matriculaSesion;
    }
}

if ($matricula === '') {
    die("Matrícula no especificada.");
}

$alumno = $coleccionAspirantes->findOne([
    '$or' => [
        ['matricula' => (string)$matricula],
        ['matricula' => (int)$matricula]
    ],
    'estatus' => 'ALUMNO_ACTIVO'
]);

if (!$alumno) {
    die("Alumno no encontrado.");
}

$materiasTodas = $alumno['cargas_academicas'][0]['materias'] ?? [];
$grupoAlumno = (string)($alumno['academico']['grupo'] ?? '');

/* Si es Profesor, filtra solo las materias que él imparte en ese grupo */
$materias = [];
if ($esProfesor) {
    $clavesPermitidas = [];
    foreach ($PLANES_ESTUDIO as $gruposCarrera) {
        if (isset($gruposCarrera[$grupoAlumno])) {
            foreach (($gruposCarrera[$grupoAlumno]['materias'] ?? []) as $matPlan) {
                if (normalizarNombreProfesorBoletaPdf((string)($matPlan['profesor'] ?? '')) === $nombreDocenteSesion) {
                    $clavesPermitidas[] = $matPlan['clave'];
                }
            }
        }
    }

    foreach ($materiasTodas as $m) {
        if (in_array($m['clave'] ?? '', $clavesPermitidas, true)) {
            $materias[] = $m;
        }
    }
} else {
    $materias = $materiasTodas;
}

$sumaPromedios = 0.0;
$materiasEvaluadas = 0;

foreach ($materias as $m) {
    $cal = $m['calificaciones'] ?? [];
    $tieneEgo = isset($cal['ego']) && $cal['ego'] !== null && $cal['ego'] !== '';
    $final = $tieneEgo ? (float)$cal['ego'] : ($cal['final'] ?? null);

    if ($final !== null && $final !== '') {
        $sumaPromedios += (float)$final;
        $materiasEvaluadas++;
    }
}

$promedioGeneral = $materiasEvaluadas > 0 ? number_format($sumaPromedios / $materiasEvaluadas, 2) : 'Pendiente';
$urlRegresarBoleta = $esAlumno
    ? 'alumno_boleta.php'
    : 'boletas.php?grupo=' . urlencode($grupoAlumno);

require_once __DIR__ . '/../vistas/boleta_pdf.vista.html';