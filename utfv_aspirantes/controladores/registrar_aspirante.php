<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';

$errorRegistro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefonoLimpio = preg_replace('/\D/', '', trim($_POST['telefono'] ?? ''));

    if ($telefonoLimpio !== '' && strlen($telefonoLimpio) !== 10) {
        $errorRegistro = 'El número de teléfono debe contener exactamente 10 dígitos.';
    } else {
        $nuevoAspirante = [
            'estatus' => 'ASPIRANTE',
            'nombre' => strtoupper(trim($_POST['nombre'] ?? '')),
            'apellido_paterno' => strtoupper(trim($_POST['apellido_paterno'] ?? '')),
            'apellido_materno' => strtoupper(trim($_POST['apellido_materno'] ?? '')),
            'edad' => (int)($_POST['edad'] ?? 0),
            'genero' => $_POST['genero'] ?? 'Masculino',
            'telefono' => $telefonoLimpio,
            'correo' => trim($_POST['correo'] ?? ''),
            'direccion' => [
                'calle' => trim($_POST['calle'] ?? ''),
                'colonia' => trim($_POST['colonia'] ?? ''),
                'municipio' => trim($_POST['municipio'] ?? ''),
                'codigo_postal' => trim($_POST['codigo_postal'] ?? '')
            ],
            'cargas_academicas' => [],
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ];

        try {
            $coleccionAspirantes->insertOne($nuevoAspirante);
            header('Location: registrar_aspirante.php?msg=ok');
            exit();
        } catch (Exception $e) {
            die("Error al registrar: " . $e->getMessage());
        }
    }
}

$registroExitoso = isset($_GET['msg']) && $_GET['msg'] === 'ok';

require_once __DIR__ . '/../vistas/registrar_aspirante.vista.html';