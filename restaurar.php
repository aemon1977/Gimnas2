<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    // Configuración de la base de datos
    $servername = "localhost";
    $username = "root";
    $password = ""; // Cambia esto si es diferente
    $dbname = "gimnas";

    // Verifica que el archivo se haya subido sin errores
    if ($_FILES['file']['error'] == 0) {
        $filePath = $_FILES['file']['tmp_name'];

        // Comando para restaurar la base de datos
        $command = "mysql -h $servername -u $username -p$password $dbname < $filePath";

        system($command, $output);

        // Comprobar si la restauración se ha realizado
        if ($output === 0) {
            echo "Restauració completada amb èxit.";
        } else {
            echo "Error en la restauració de la base de dades.";
        }
    } else {
        echo "Error al pujar el fitxer.";
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Restaurar Base de Dades</title>
</head>
<body>
    <h1>Restaurar Base de Dades</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <input type="submit" value="Restaurar">
    </form>
</body>
</html>
