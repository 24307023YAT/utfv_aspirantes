<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['usuario_autenticado']) && $_SESSION['usuario_autenticado'] === true) {
    header('Location: index.php');
    exit();
}

$errorLogin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioInput = trim($_POST['usuario'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if ($usuarioInput !== '' && $passwordInput !== '') {
        try {
            $usuarioDB = $coleccionUsuarios->findOne(['usuario' => $usuarioInput]);

            if ($usuarioDB && isset($usuarioDB['password']) && $passwordInput === (string)$usuarioDB['password']) {
                $_SESSION['usuario_autenticado'] = true;
                $_SESSION['usuario'] = $usuarioDB['usuario'];
                $_SESSION['nombre_usuario'] = $usuarioDB['nombre'] ?? $usuarioDB['usuario'];
                $_SESSION['rol'] = $usuarioDB['rol'] ?? 'USUARIO';

                header('Location: index.php');
                exit();
            }

            $alumnoDB = $db->aspirantes->findOne([
                '$or' => [
                    ['matricula' => $usuarioInput],
                    ['matricula' => (int)$usuarioInput]
                ]
            ]);

            if ($alumnoDB) {
                $primer_nombre = explode(' ', trim($alumnoDB['nombre']))[0];
                $password_esperada = $alumnoDB['matricula'] . $primer_nombre;

                if (strcasecmp($passwordInput, $password_esperada) === 0) {
                    $nombreCompleto = trim(
                        $alumnoDB['nombre'] . ' ' .
                        ($alumnoDB['apellido_paterno'] ?? '') . ' ' .
                        ($alumnoDB['apellido_materno'] ?? '')
                    );

                    $_SESSION['usuario_autenticado'] = true;
                    $_SESSION['usuario'] = (string)$alumnoDB['matricula'];
                    $_SESSION['matricula'] = (string)$alumnoDB['matricula'];
                    $_SESSION['nombre'] = $nombreCompleto;
                    $_SESSION['nombre_usuario'] = $nombreCompleto;
                    $_SESSION['rol'] = 'alumno';

                    header('Location: index.php');
                    exit();
                }
            }

            $errorLogin = 'Usuario o contraseña incorrectos.';
        } catch (Exception $e) {
            $errorLogin = 'Error al consultar la base de datos.';
        }
    } else {
        $errorLogin = 'Ingresa tu usuario y contraseña.';
    }
}

require_once __DIR__ . '/../vistas/login.vista.html';