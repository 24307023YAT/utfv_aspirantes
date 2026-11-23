<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/planes_estudio.php';

function normalizarNombreProfesor(string $nombre): string {
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
$nombreDocenteSesion = normalizarNombreProfesor((string)($_SESSION['nombre_usuario'] ?? ''));

$materiasPorGrupo = [];
$gruposPermitidosDocente = [];
$clavesPermitidasPorGrupo = [];

foreach ($PLANES_ESTUDIO as $gruposCarrera) {
    foreach ($gruposCarrera as $codigoGrupo => $infoGrupo) {
        $materiasFiltradas = [];
        foreach (($infoGrupo['materias'] ?? []) as $mat) {
            $profMateria = normalizarNombreProfesor((string)($mat['profesor'] ?? ''));

            if (!$esProfesor || ($nombreDocenteSesion !== '' && $profMateria === $nombreDocenteSesion)) {
                $materiasFiltradas[] = [
                    'clave' => $mat['clave'],
                    'nombre' => $mat['nombre']
                ];
                $clavesPermitidasPorGrupo[$codigoGrupo][] = $mat['clave'];
            }
        }

        if (!empty($materiasFiltradas)) {
            $materiasPorGrupo[$codigoGrupo] = $materiasFiltradas;
            $gruposPermitidosDocente[] = $codigoGrupo;
        }
    }
}

function recalcularPromedioFinal($coleccionAspirantes, string $matricula, string $claveMateria): void {
    $doc = $coleccionAspirantes->findOne([
        'matricula' => $matricula,
        'cargas_academicas.materias.clave' => $claveMateria
    ]);

    if (!$doc) return;

    $materias = $doc['cargas_academicas'][0]['materias'] ?? [];
    foreach ($materias as $mat) {
        if (($mat['clave'] ?? '') === $claveMateria) {
            $califs = $mat['calificaciones'] ?? [];
            $suma = 0.0;
            $capturados = 0;

            foreach (['parcial_1', 'parcial_2', 'parcial_3'] as $p) {
                if (isset($califs[$p]) && $califs[$p] !== null && $califs[$p] !== '') {
                    $suma += (float)$califs[$p];
                    $capturados++;
                }
            }

            $promedioFinal = $capturados > 0 ? round($suma / $capturados, 1) : null;

            $coleccionAspirantes->updateOne(
                [
                    'matricula' => $matricula,
                    'cargas_academicas.materias.clave' => $claveMateria
                ],
                [
                    '$set' => [
                        'cargas_academicas.0.materias.$[mat].calificaciones.final' => $promedioFinal,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ],
                ['arrayFilters' => [['mat.clave' => $claveMateria]]]
            );
            break;
        }
    }
}

function guardarParcialesMateria($coleccionAspirantes, string $matricula, string $claveMateria, array $datosParciales): void {
    $setUpdate = ['updatedAt' => new MongoDB\BSON\UTCDateTime()];
    $suma = 0.0;
    $capturados = 0;

    foreach (['parcial_1', 'parcial_2', 'parcial_3'] as $p) {
        $valorRaw = trim((string)($datosParciales[$p] ?? ''));
        if ($valorRaw !== '') {
            $nota = max(0, min(10, (float)$valorRaw));
            $setUpdate['cargas_academicas.0.materias.$[mat].calificaciones.' . $p] = $nota;
            $suma += $nota;
            $capturados++;
        } else {
            $setUpdate['cargas_academicas.0.materias.$[mat].calificaciones.' . $p] = null;
        }
    }

    $setUpdate['cargas_academicas.0.materias.$[mat].calificaciones.final'] =
        $capturados > 0 ? round($suma / $capturados, 1) : null;

    $coleccionAspirantes->updateOne(
        [
            'matricula' => $matricula,
            'cargas_academicas.materias.clave' => $claveMateria
        ],
        ['$set' => $setUpdate],
        ['arrayFilters' => [['mat.clave' => $claveMateria]]]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    /* Guardar todas las materias de un alumno en una sola acción (ADMIN) */
    if ($accion === 'guardar_alumno_completo' && !$esProfesor) {
        $matricula = trim($_POST['matricula'] ?? '');
        $notasAlumno = $_POST['notas_alumno'] ?? [];

        if ($matricula !== '' && is_array($notasAlumno)) {
            try {
                foreach ($notasAlumno as $claveMateria => $parcialesMat) {
                    if (is_array($parcialesMat)) {
                        guardarParcialesMateria(
                            $coleccionAspirantes,
                            $matricula,
                            (string)$claveMateria,
                            $parcialesMat
                        );
                    }
                }
                header('Location: calificaciones.php?modo=alumno&mat=' . urlencode($matricula) . '&msg=ok');
                exit();
            } catch (Exception $e) {
                die("Error al guardar: " . $e->getMessage());
            }
        }
    }

    /* Guardar todo el grupo en una sola acción */
    if ($accion === 'guardar_grupo_completo') {
        $grupo = trim($_POST['grupo'] ?? '');
        $claveMateria = trim($_POST['clave_materia'] ?? '');
        $notasGrupo = $_POST['notas_grupo'] ?? [];

        $permisoOk = !$esProfesor || in_array($claveMateria, $clavesPermitidasPorGrupo[$grupo] ?? [], true);

        if ($grupo !== '' && $claveMateria !== '' && is_array($notasGrupo) && $permisoOk) {
            try {
                foreach ($notasGrupo as $matricula => $parcialesAlumno) {
                    if (is_array($parcialesAlumno)) {
                        guardarParcialesMateria(
                            $coleccionAspirantes,
                            (string)$matricula,
                            $claveMateria,
                            $parcialesAlumno
                        );
                    }
                }

                $params = http_build_query([
                    'modo' => 'grupo',
                    'grupo' => $grupo,
                    'clave_mat' => $claveMateria,
                    'msg' => 'ok'
                ]);
                header("Location: calificaciones.php?$params");
                exit();
            } catch (Exception $e) {
                die("Error al guardar: " . $e->getMessage());
            }
        }
    }
}

$filtroAlumnos = ['estatus' => 'ALUMNO_ACTIVO'];
if ($esProfesor) {
    $filtroAlumnos['academico.grupo'] = ['$in' => $gruposPermitidosDocente];
}

$alumnosActivos = iterator_to_array(
    $coleccionAspirantes->find(
        $filtroAlumnos,
        ['sort' => ['apellido_paterno' => 1, 'apellido_materno' => 1, 'nombre' => 1]]
    )
);

$huboActualizacion = false;
foreach ($alumnosActivos as $al) {
    $mat = (string)($al['matricula'] ?? '');
    $materiasAl = $al['cargas_academicas'][0]['materias'] ?? [];
    foreach ($materiasAl as $m) {
        $califs = $m['calificaciones'] ?? [];
        $p1Ok = isset($califs['parcial_1']) && $califs['parcial_1'] !== null && $califs['parcial_1'] !== '';
        $p2Ok = isset($califs['parcial_2']) && $califs['parcial_2'] !== null && $califs['parcial_2'] !== '';
        $p3Ok = isset($califs['parcial_3']) && $califs['parcial_3'] !== null && $califs['parcial_3'] !== '';
        $tieneParciales = ($p1Ok || $p2Ok || $p3Ok);
        $faltaFinal = (!isset($califs['final']) || $califs['final'] === null || $califs['final'] === '');

        if ($mat !== '' && $tieneParciales && $faltaFinal && !empty($m['clave'])) {
            recalcularPromedioFinal($coleccionAspirantes, $mat, $m['clave']);
            $huboActualizacion = true;
        }
    }
}

if ($huboActualizacion) {
    $alumnosActivos = iterator_to_array(
        $coleccionAspirantes->find(
            $filtroAlumnos,
            ['sort' => ['apellido_paterno' => 1, 'apellido_materno' => 1, 'nombre' => 1]]
        )
    );
}

$gruposDisponibles = [];
foreach ($alumnosActivos as $al) {
    $g = $al['academico']['grupo'] ?? '';
    if ($g !== '' && !in_array($g, $gruposDisponibles, true)) {
        if (!$esProfesor || in_array($g, $gruposPermitidosDocente, true)) {
            $gruposDisponibles[] = $g;
        }
    }
}
sort($gruposDisponibles);

$modo = $esProfesor ? 'grupo' : ($_GET['modo'] ?? 'alumno');
$busqueda = trim($_GET['q'] ?? '');
$matriculaBuscada = trim($_GET['mat'] ?? '');
$grupoSeleccionado = trim($_GET['grupo'] ?? '');
$claveMateriaSeleccionada = trim($_GET['clave_mat'] ?? '');
$guardadoExitoso = isset($_GET['msg']) && $_GET['msg'] === 'ok';

if ($esProfesor) {
    if ($grupoSeleccionado !== '' && !in_array($grupoSeleccionado, $gruposPermitidosDocente, true)) {
        $grupoSeleccionado = '';
        $claveMateriaSeleccionada = '';
    }
    if ($claveMateriaSeleccionada !== '' && !in_array($claveMateriaSeleccionada, $clavesPermitidasPorGrupo[$grupoSeleccionado] ?? [], true)) {
        $claveMateriaSeleccionada = '';
    }
}

if ($modo === 'grupo' && $grupoSeleccionado !== '' && $claveMateriaSeleccionada === '') {
    $primeraMat = $materiasPorGrupo[$grupoSeleccionado][0]['clave'] ?? '';
    if ($primeraMat !== '') {
        $claveMateriaSeleccionada = $primeraMat;
    }
}

$alumnoSeleccionado = null;
$nombreAlumnoSeleccionado = '';
$materiasAlumnoSeleccionado = [];

if (!$esProfesor && $modo === 'alumno' && $matriculaBuscada !== '') {
    $alumnoSeleccionado = $coleccionAspirantes->findOne([
        'matricula' => $matriculaBuscada,
        'estatus' => 'ALUMNO_ACTIVO'
    ]);

    if ($alumnoSeleccionado) {
        $nombreAlumnoSeleccionado = trim(
            ($alumnoSeleccionado['nombre'] ?? '') . ' ' .
            ($alumnoSeleccionado['apellido_paterno'] ?? '') . ' ' .
            ($alumnoSeleccionado['apellido_materno'] ?? '')
        );
        $materiasAlumnoSeleccionado = $alumnoSeleccionado['cargas_academicas'][0]['materias'] ?? [];
    }
}

$alumnosDelGrupo = [];
$nombreMateriaResumen = '';

if ($modo === 'grupo' && $grupoSeleccionado !== '') {
    $alumnosDelGrupo = array_filter(
        $alumnosActivos,
        fn($al) => ($al['academico']['grupo'] ?? '') === $grupoSeleccionado
    );

    foreach (($materiasPorGrupo[$grupoSeleccionado] ?? []) as $matInfo) {
        if (($matInfo['clave'] ?? '') === $claveMateriaSeleccionada) {
            $nombreMateriaResumen = $matInfo['clave'] . ' - ' . $matInfo['nombre'];
            break;
        }
    }
}

require_once __DIR__ . '/../vistas/calificaciones.vista.html';