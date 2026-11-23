<?php
require_once __DIR__ . '/../config/sesion.php';

if (($_SESSION['rol'] ?? '') === 'alumno') {
    header('Location: alumno_boleta.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/planes_estudio.php';

/**
 * Normaliza nombres de docentes quitando títulos académicos, acentos y espacios extra
 * para empatar el nombre de sesión con el de planes_estudio.php.
 */
function normalizarNombreDocenteBoleta(string $nombre): string {
    $s = mb_strtoupper(trim($nombre), 'UTF-8');
    $s = strtr($s, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'
    ]);
    $s = preg_replace('/^(M\.\s*EN\s*D\.|M\.\s*EN\s*C\.|DR\.|DRA\.|MTRO\.|MTRA\.|ING\.|LIC\.)\s*/u', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

$rolActual = strtoupper(trim($_SESSION['rol'] ?? ''));
$esProfesor = ($rolActual === 'PROFESOR');
$nombreDocente = normalizarNombreDocenteBoleta((string)($_SESSION['nombre_usuario'] ?? ''));

$gruposPermitidos = [];
if ($esProfesor) {
    foreach ($PLANES_ESTUDIO as $gruposCarrera) {
        foreach ($gruposCarrera as $codigoGrupo => $infoGrupo) {
            foreach (($infoGrupo['materias'] ?? []) as $mat) {
                if (normalizarNombreDocenteBoleta((string)($mat['profesor'] ?? '')) === $nombreDocente) {
                    if (!in_array($codigoGrupo, $gruposPermitidos, true)) {
                        $gruposPermitidos[] = $codigoGrupo;
                    }
                    break;
                }
            }
        }
    }
}

$filtroBoletas = ['estatus' => 'ALUMNO_ACTIVO'];
if ($esProfesor) {
    $filtroBoletas['academico.grupo'] = ['$in' => $gruposPermitidos];
}

$alumnosActivos = iterator_to_array(
    $coleccionAspirantes->find($filtroBoletas, ['sort' => ['apellido_paterno' => 1]])
);

$gruposDisponibles = [];
foreach ($alumnosActivos as $al) {
    $g = $al['academico']['grupo'] ?? '';
    if ($g !== '' && !in_array($g, $gruposDisponibles, true)) {
        $gruposDisponibles[] = $g;
    }
}
sort($gruposDisponibles);

$grupoSeleccionado = trim($_GET['grupo'] ?? '');
if ($esProfesor && $grupoSeleccionado !== '' && !in_array($grupoSeleccionado, $gruposPermitidos, true)) {
    $grupoSeleccionado = '';
}

$alumnosDelGrupo = [];
if ($grupoSeleccionado !== '') {
    $alumnosDelGrupo = array_filter($alumnosActivos, fn($al) => ($al['academico']['grupo'] ?? '') === $grupoSeleccionado);
}

require_once __DIR__ . '/../vistas/boletas.vista.html';