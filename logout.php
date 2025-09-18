<?php
session_start();

// limpiar todas las variables de sesión
$_SESSION = [];

// destruir la sesión
session_destroy();

// redirigir a la página principal
header("Location: index.php");
exit();
