<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;

try {
    $mongoUri = "mongodb+srv://24307023_db_user:05evds@utfv-yahir-cluster.qdxfjtt.mongodb.net/utfv_nosql?retryWrites=true&w=majority";
    $cliente = new Client($mongoUri);
    $db = $cliente->utfv_nosql;
    
    $coleccionAspirantes = $db->aspirantes;
    $coleccionUsuarios = $db->usuarios;
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}