<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss']) && $_POST['dismiss'] === 'true') {
    $_SESSION['dismiss_2fa_alert'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Requisição inválida']);
}
?>