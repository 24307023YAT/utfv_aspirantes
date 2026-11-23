<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'alumno') {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';

$matricula = $_SESSION['matricula'];
$doc = $db->aspirantes->findOne([
    '$or' => [
        ['matricula' => $matricula],
        ['matricula' => (int)$matricula]
    ]
]);

$alumno = $doc ? json_decode(json_encode($doc), true) : [];
$academico = $alumno['academico'] ?? [];
$cargas = $alumno['cargas_academicas'] ?? [];
$carga_actual = !empty($cargas) ? end($cargas) : [];
$materias = $carga_actual['materias'] ?? [];

require_once __DIR__ . '/../vistas/alumno_tira.vista.html';