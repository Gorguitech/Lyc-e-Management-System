<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

if (isset($_SESSION['user_id'])) {
    $auth = new Auth();
    $auth->logout();
}

// Rediriger vers la page de connexion
header('Location: ' . BASE_URL . 'login.php');
exit();
?>