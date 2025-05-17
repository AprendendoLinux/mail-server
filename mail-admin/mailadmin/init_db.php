<?php
// Incluir configurações do banco
require_once 'config.db.php';

// Caminho para o script SQL
$sql_file = '/var/www/html/mailserver.sql';

try {
    // Conectar ao banco MySQL
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Verificar se o banco está vazio (sem tabelas)
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();

    if (empty($tables)) {
        // Banco está vazio, executar o script SQL
        if (!file_exists($sql_file)) {
            throw new Exception("Arquivo mailserver.sql não encontrado em /var/www/html");
        }

        // Ler o conteúdo do arquivo SQL
        $sql = file_get_contents($sql_file);
        if ($sql === false) {
            throw new Exception("Erro ao ler o arquivo mailserver.sql");
        }

        // Executar o script SQL
        $pdo->exec($sql);
        echo "Banco de dados inicializado com sucesso usando mailserver.sql";
    } else {
        echo "Banco de dados já contém tabelas, inicialização ignorada";
    }
} catch (PDOException $e) {
    echo "Erro ao conectar ou inicializar o banco: " . $e->getMessage();
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
