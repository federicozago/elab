<?php
/**
 * File di esempio per l'ambiente PHP Docker
 * 
 * Questo file dimostra il funzionamento di base dell'ambiente PHP
 * e fornisce informazioni sulla configurazione attuale.
 */

// Titolo della pagina
$titolo = "Demo PHP Docker con Caddy";

// Informazioni sul server
$server_info = [
    "PHP Version" => phpversion(),
    "Server Software" => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Non disponibile',
    "Server Name" => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'Non disponibile',
    "Document Root" => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Non disponibile',
    "Remote Address" => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Non disponibile'
];

// Funzione di esempio
function saluta($nome) {
    return "Ciao, $nome! Benvenuto nell'ambiente PHP Docker.";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titolo; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #3498db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .greeting {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $titolo; ?></h1>
        
        <div class="greeting">
            <?php echo saluta("Zago"); ?>
        </div>
        
        <h2>Informazioni sul Server</h2>
        <table>
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
            </tr>
            <?php foreach ($server_info as $key => $value): ?>
            <tr>
                <td><?php echo htmlspecialchars($key); ?></td>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2>Test delle Estensioni PHP</h2>
        <p>
            Estensione ZIP: <?php echo extension_loaded('zip') ? 'Installata' : 'Non installata'; ?><br>
            Estensione PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? 'Installata' : 'Non installata'; ?>
        </p>
        
        <h2>Data e Ora Corrente</h2>
        <p><?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</body>
</html>