<?php
session_start();
if (!isset($_SESSION['rol'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../vistas/index.vista.html';