<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/db.php';

// registrar un nuevo usuario en MongoDB
$nuevoUsuario = 'profesor1';
$nuevaPassword = 'ARTUR2026';
$nombreCompleto = 'Arturo Hernández Jiménez';

$existe = $coleccionUsuarios->findOne(['usuario' => $nuevoUsuario]);

if (!$existe) {
    $coleccionUsuarios->insertOne([
        'usuario' => $nuevoUsuario,
        'password' => password_hash($nuevaPassword, PASSWORD_DEFAULT),
        'nombre' => $nombreCompleto,
        'rol' => 'DOCENTE',
        'createdAt' => new MongoDB\BSON\UTCDateTime()
    ]);
    echo "Usuario '$nuevoUsuario' creado correctamente en MongoDB.";
} else {
    echo "El usuario '$nuevoUsuario' ya existe.";
}