<?php
$senha = 'Hen#!Fag131281'; // Substitua 'admin' pela senha que você deseja usar
$hash = password_hash($senha, PASSWORD_DEFAULT);
echo "Hash gerado: " . $hash . "\n";
?>
