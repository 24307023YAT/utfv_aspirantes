<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/planes_estudio.php';

/**
 * Obtencion de la siguiente matrícula consecutiva para TSU o para Ingeniería.
 */
function obtenerSiguienteMatricula($coleccionAspirantes, bool $esIngenieria): string
{
    if ($esIngenieria) {
        $prefijo = '2430700026315';
        $patronRegex = '^2430700026315\d{3}$';
        $matriculaBase = '2430700026315001';
    } else {
        $prefijo = '24307';
        $patronRegex = '^24307\d{3}$';
        $matriculaBase = '24307001';
    }

    $ultimoAlumno = $coleccionAspirantes->findOne(
        ['matricula' => new MongoDB\BSON\Regex($patronRegex)],
        ['sort' => ['matricula' => -1]]
    );

    if ($ultimoAlumno !== null && !empty($ultimoAlumno['matricula'])) {
        $ultimaMat = (string)$ultimoAlumno['matricula'];
        if (function_exists('bcadd')) {
            return bcadd($ultimaMat, '1', 0);
        }
        $parteConsecutiva = (int)substr($ultimaMat, strlen($prefijo)) + 1;
        return $prefijo . str_pad((string)$parteConsecutiva, 3, '0', STR_PAD_LEFT);
    }

    return $matriculaBase;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $carrera = trim($_POST['carrera'] ?? '');
    $grupo = trim($_POST['grupo'] ?? '');
    $periodo = trim($_POST['periodo'] ?? '');

    if ($id && $carrera && $grupo) {
        try {
            $datosPlan = obtenerDatosGrupoMongo($carrera, $grupo, $PLANES_ESTUDIO);
            $cuatrimestre = trim($_POST['cuatrimestre'] ?? '') ?: $datosPlan['cuatrimestre'];
            $tutor_grupo = trim($_POST['tutor_grupo'] ?? '') ?: $datosPlan['tutor'];

            $numCuatri = (int)preg_replace('/\D/', '', (string)$cuatrimestre);
            $esIngenieria = in_array($numCuatri, [7, 10], true) || stripos($carrera, 'Ingenier') !== false;
            $matricula = obtenerSiguienteMatricula($coleccionAspirantes, $esIngenieria);

            $coleccionAspirantes->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => [
                    'estatus' => 'ALUMNO_ACTIVO',
                    'matricula' => $matricula,
                    'academico' => [
                        'carrera' => $carrera,
                        'grupo' => $grupo,
                        'cuatrimestre' => $cuatrimestre,
                        'periodo' => $periodo,
                        'tutor_grupo' => $tutor_grupo
                    ],
                    'cargas_academicas' => [
                        [
                            'periodo' => '2026-3',
                            'cuatrimestre' => $cuatrimestre,
                            'materias' => $datosPlan['materias']
                        ]
                    ],
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            $alumno = $coleccionAspirantes->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            $nombreCompleto = $alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'];

            $queryParams = http_build_query([
                'msg' => 'inscrito_exito',
                'nombre' => $nombreCompleto,
                'matricula' => $matricula,
                'carrera' => $carrera,
                'grupo' => $grupo,
                'cuatrimestre' => $cuatrimestre,
                'periodo' => $periodo,
                'tutor' => $tutor_grupo
            ]);

            header("Location: inscribir_aspirante.php?$queryParams");
            exit();
        } catch (Exception $e) {
            die("Error al inscribir: " . $e->getMessage());
        }
    }
}

$aspirantes = iterator_to_array($coleccionAspirantes->find(['estatus' => 'ASPIRANTE'], ['sort' => ['createdAt' => -1]]));
$inscritoExito = isset($_GET['msg']) && $_GET['msg'] === 'inscrito_exito';

// Precalcullacion de las siguientes matrículas disponibles para mostrarlas en vivo en el modal
$siguienteMatTSU = obtenerSiguienteMatricula($coleccionAspirantes, false);
$siguienteMatING = obtenerSiguienteMatricula($coleccionAspirantes, true);

require_once __DIR__ . '/../vistas/inscribir_aspirante.vista.html';