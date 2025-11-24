<?php
// Importar las clases necesarias al principio del archivo
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuraciones
$site_dir = 'C:/xampp/htdocs/gimnas'; // Ruta del sitio web
$backup_dir = 'C:/xampp/htdocs/gimnas/backup'; // Directorio para las copias de seguridad
$db_name = 'gimnas'; // Nombre de la base de datos
$db_user = 'root'; // Usuario de MySQL
$db_password = ''; // Sin contraseña
$db_host = 'localhost'; // Host de MySQL
$recipient_email = 'lozanopereira77@gmail.com'; // Cambia por tu dirección de correo electrónico

// Ruta completa al comando mysqldump
$mysqldump_path = 'C:/xampp/mysql/bin/mysqldump.exe';

// Obtener fecha actual para los nombres de los archivos
$date = date('Y-m-d_H-i-s');
$db_backup_file = "$backup_dir/db_backup_$date.sql";
$site_backup_file = "$backup_dir/site_backup_$date.zip";

// 1. Crear copia de seguridad de la base de datos
exec("$mysqldump_path --user=$db_user --host=$db_host $db_name > $db_backup_file", $output, $return_var);
if ($return_var !== 0) {
    die("Error creando la copia de seguridad de la base de datos");
}

// 2. Crear un archivo comprimido con todos los archivos del sitio web
$zip = new ZipArchive();
if ($zip->open($site_backup_file, ZipArchive::CREATE) !== true) {
    die("Error creando el archivo ZIP del sitio web");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($site_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen($site_dir) + 1);
        $zip->addFile($file_path, $relative_path);
    }
}

$zip->close();

// 3. Dividir el archivo de respaldo de la base de datos y enviar todas las partes en un solo correo
function splitFile($filePath) {
    $maxSize = 24 * 1024 * 1024; // 24 MB
    $partFiles = [];
    $partNum = 0;

    $handle = fopen($filePath, "rb");
    if ($handle === false) {
        die("Error al abrir el archivo para dividir");
    }

    while (!feof($handle)) {
        $partNum++;
        $partFilePath = "$filePath.part$partNum";

        $partHandle = fopen($partFilePath, "wb");
        if ($partHandle === false) {
            fclose($handle);
            die("Error al crear el archivo de parte");
        }

        $bytesWritten = 0;

        // Escribir partes del archivo hasta alcanzar el tamaño máximo
        while ($bytesWritten < $maxSize && !feof($handle)) {
            $buffer = fread($handle, min($maxSize - $bytesWritten, 8192));
            $bytesWritten += fwrite($partHandle, $buffer);
        }

        fclose($partHandle);
        $partFiles[] = $partFilePath; // Almacenar el archivo de parte para enviar más tarde
    }

    fclose($handle);

    return $partFiles;
}

// Función para enviar correos electrónicos con múltiples archivos adjuntos
function sendEmailWithAttachments($filePaths, $recipient_email, $subject) {
    require 'vendor/autoload.php'; // Autoload de Composer

    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Cambia si usas otro servidor SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'lozanopereira77@gmail.com'; // Tu correo
        $mail->Password = 'zsua dymm ogxi degu'; // Tu contraseña o contraseña de aplicación
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Destinatarios
        $mail->setFrom('asuncionmartinez1973@gmail.com', 'enrique'); // Cambia según tu preferencia
        $mail->addAddress($recipient_email);

        // Asunto y cuerpo
        $mail->Subject = $subject;
        $mail->Body = "Adjunto la copia de seguridad del sitio web y la base de datos.";

        // Adjuntar todos los archivos
        foreach ($filePaths as $filePath) {
            $mail->addAttachment($filePath);
        }

        // Enviar el correo
        $mail->send();
        echo "El correo se envió correctamente con las copias de seguridad adjuntas.\n";
    } catch (Exception $e) {
        echo "El correo no se pudo enviar. Error: {$mail->ErrorInfo}\n";
    }

    // Limpiar los archivos de respaldo
    foreach ($filePaths as $filePath) {
        unlink($filePath);
    }
}

// 4. Preparar todos los archivos para enviar
$attachments = [];
$attachments[] = $db_backup_file; // Agregar el archivo de base de datos

// Dividir y agregar las partes del respaldo de la base de datos
$parts = splitFile($db_backup_file);
$attachments = array_merge($attachments, $parts); // Combinar con los adjuntos existentes

$attachments[] = $site_backup_file; // Agregar el archivo ZIP del sitio web

// 5. Enviar todos los archivos como adjuntos en un solo correo
sendEmailWithAttachments($attachments, $recipient_email, "Copia de seguridad combinada");

// Limpiar archivos de respaldo del servidor (ya se eliminan en la función de envío)
?>
