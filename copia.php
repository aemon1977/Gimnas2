<?php
// Comprobación si se ha enviado el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Configuración de la base de datos
    $servername = "localhost";
    $username = "root";
    $password = ""; // Cambia esto si es diferente
    $dbname = "gimnas";

    // Nombre del archivo de copia de seguridad
    $backupFile = 'backup_' . date("Y-m-d_H-i-s") . '.sql';

    // Ruta al ejecutable mysqldump en XAMPP
    $mysqldumpPath = 'C:\xampp\mysql\bin\mysqldump.exe';

    // Comando para crear la copia de seguridad
    $command = "$mysqldumpPath --opt -h $servername -u $username -p$password $dbname > \"$backupFile\"";

    // Ejecutar el comando
    system($command, $output);

    // Verificar si la copia se creó correctamente
    if ($output === 0) {
        // Si se creó la copia, forzar la descarga del archivo
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
        header('Content-Length: ' . filesize($backupFile));
        readfile($backupFile);

        // Opcional: eliminar el archivo del servidor después de la descarga
        unlink($backupFile);
        exit();
    } else {
        echo "Error en la creació de la còpia de seguretat.";
    }
} else {
    // Mostrar el formulario si no se ha enviado
    ?>

    <!DOCTYPE html>
    <html lang="ca">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Còpia de Seguretat</title>
    </head>
    <body>
        <h1>Crear Còpia de Seguretat</h1>
        <form method="post" action="">
            <input type="submit" value="Crear Còpia i Descarregar">
        </form>
    </body>
    </html>

    <?php
}
?>
