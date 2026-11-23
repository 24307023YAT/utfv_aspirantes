<?php
session_start();

if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true || ($_SESSION['rol'] ?? '') !== 'alumno') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';

$matricula = $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '';
$alumno = [];
$academico = [];
$cargas = [];
$carga_actual = [];
$materias = [];

if ($matricula !== '') {
    try {
        $doc = $db->aspirantes->findOne([
            '$or' => [
                ['matricula' => (string)$matricula],
                ['matricula' => (int)$matricula]
            ]
        ]);

        if ($doc) {
            $alumno = json_decode(json_encode($doc), true);
            $academico = $alumno['academico'] ?? [];
            $cargas = $alumno['cargas_academicas'] ?? [];
            $carga_actual = !empty($cargas) ? end($cargas) : [];
            $materias = $carga_actual['materias'] ?? [];
        }
    } catch (Exception $e) {
        $errorBoleta = 'Error al consultar la información de la boleta.';
    }
}

require_once __DIR__ . '/../vistas/alumno_boleta.vista.html';