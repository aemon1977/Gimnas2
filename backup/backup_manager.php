<?php
// Directorio donde se almacenarán las copias de seguridad
$backup_dir = "backups/";

// Verificar si el directorio de copias de seguridad existe, si no, crearlo
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Función para crear una nueva copia de seguridad
function createBackup($backup_dir) {
    $database = "gimnas";
    $user = "root";
    $password = "";
    $backup_file = $backup_dir . "backup_gimnas_" . date("Y-m-d_H-i-s") . ".sql";
    $command = "\"C:/xampp/mysql/bin/mysqldump.exe\" --user={$user} --password={$password} {$database} > {$backup_file}";

    system($command, $output);
    if (file_exists($backup_file)) {
        echo "Copia de seguridad creada: " . basename($backup_file);
    } else {
        echo "Error al crear la copia de seguridad.";
    }
}

// Función para restaurar la base de datos desde un archivo SQL
function restoreBackup($file) {
    $database = "gimnas";
    $user = "root";
    $password = "";
    $command = "\"C:/xampp/mysql/bin/mysql.exe\" --user={$user} --password={$password} {$database} < {$file}";

    system($command, $output);
    if ($output === 0) {
        echo "La base de datos se ha restaurado correctamente.";
    } else {
        echo "Error al restaurar la base de datos.";
    }
}

// Si se solicita crear una nueva copia
if (isset($_GET['action']) && $_GET['action'] === 'create') {
    createBackup($backup_dir);
}

// Si se solicita eliminar una copia
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['file'])) {
    $file_to_delete = $backup_dir . basename($_GET['file']);
    if (file_exists($file_to_delete)) {
        unlink($file_to_delete);
        echo "Copia de seguridad eliminada: " . basename($file_to_delete);
    } else {
        echo "Archivo no encontrado.";
    }
}

// Si se ha subido un archivo para restaurar la base de datos
if (isset($_FILES['restore_file'])) {
    $uploaded_file = $_FILES['restore_file']['tmp_name'];
    if (is_uploaded_file($uploaded_file)) {
        $restore_file = $backup_dir . basename($_FILES['restore_file']['name']);
        move_uploaded_file($uploaded_file, $restore_file);
        restoreBackup($restore_file);
    } else {
        echo "Error al subir el archivo.";
    }
}

// Obtener la lista de archivos de copia de seguridad
$backups = glob($backup_dir . "*.sql");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Copias de Seguridad</title>
</head>
<body>
    <h1>Gestión de Copias de Seguridad</h1>
    
    <!-- Botón para crear una nueva copia de seguridad -->
    <a href="?action=create">Crear nueva copia de seguridad</a>

    <h2>Copias de seguridad existentes</h2>
    <?php if (!empty($backups)): ?>
        <ul>
            <?php foreach ($backups as $backup): ?>
                <li>
                    <?php echo basename($backup); ?>
                    <!-- Enlace para descargar el archivo -->
                    <a href="<?php echo $backup; ?>" download>Descargar</a>
                    <!-- Enlace para eliminar el archivo -->
                    <a href="?action=delete&file=<?php echo basename($backup); ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta copia de seguridad?');">Eliminar</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay copias de seguridad disponibles.</p>
    <?php endif; ?>

    <h2>Restaurar base de datos desde un archivo SQL</h2>
    <form action="backup_manager.php" method="post" enctype="multipart/form-data">
        <label for="restore_file">Selecciona un archivo SQL para restaurar:</label>
        <input type="file" name="restore_file" id="restore_file" accept=".sql">
        <button type="submit">Restaurar</button>
    </form>
</body>
</html>
