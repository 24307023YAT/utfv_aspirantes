<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';

$matricula = trim($_GET['mat'] ?? '');
$autoPrint = isset($_GET['pdf']) && $_GET['pdf'] === '1';

if (!$matricula) {
    die("Matrícula no especificada.");
}

$alumno = $coleccionAspirantes->findOne([
    'matricula' => $matricula,
    'estatus' => 'ALUMNO_ACTIVO'
]);

if (!$alumno) {
    die("Alumno no encontrado.");
}

$materias = $alumno['cargas_academicas'][0]['materias'] ?? [];
$sumaPromedios = 0;
$materiasEvaluadas = 0;

foreach ($materias as $m) {
    $final = $m['calificaciones']['final'] ?? null;
    if ($final !== null) {
        $sumaPromedios += (float)$final;
        $materiasEvaluadas++;
    }
}

$promedioGeneral = $materiasEvaluadas > 0 ? number_format($sumaPromedios / $materiasEvaluadas, 2) : 'Pendiente';

require_once __DIR__ . '/../vistas/boleta_pdf.vista.html';