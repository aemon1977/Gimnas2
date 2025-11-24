<?php
// Verificar si se ha enviado el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener la carpeta existente o la nueva carpeta del formulario
    $directory = isset($_POST['directory']) ? trim($_POST['directory']) : '';
    $new_folder = isset($_POST['new_folder']) ? trim($_POST['new_folder']) : '';

    // Si se proporciona un nombre de nueva carpeta, usarlo
    if (!empty($new_folder)) {
        // Limpiar el nombre de la carpeta para evitar problemas de seguridad
        $new_folder = preg_replace('/[^A-Za-z0-9_]/', '', $new_folder); // Permitir solo letras, números y guiones bajos
        $target_dir = $new_folder . "/";
        
        // Crear la carpeta si no existe
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true); // Crear carpeta con permisos 0777
        }
    } elseif (!empty($directory)) {
        // Si se selecciona una carpeta existente, usarla
        $target_dir = $directory . "/";
    } else {
        // Si no se proporciona un nombre de carpeta ni se selecciona, usar la carpeta raíz
        $target_dir = "";
    }

    // Verificar si el archivo fue subido
    if (isset($_FILES['file'])) {
        $target_file = $target_dir . basename($_FILES["file"]["name"]);
        $uploadOk = 1;

        // Verificar el tamaño del archivo
        if ($_FILES["file"]["size"] > 5000000) { // 5MB
            echo "Lo siento, el archivo es demasiado grande.<br>";
            $uploadOk = 0;
        }

        // Verificar si $uploadOk es 0 por un error
        if ($uploadOk == 0) {
            echo "Lo siento, tu archivo no ha sido subido.<br>";
        } else {
            // Intentar subir el archivo y sobrescribir el existente
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                echo "El archivo " . htmlspecialchars(basename($_FILES["file"]["name"])) . " ha sido subido correctamente a '$target_dir'.<br>";
            } else {
                echo "Lo siento, hubo un error al subir tu archivo.<br>";
            }
        }
    } else {
        echo "No se ha seleccionado ningún archivo.<br>";
    }
} else {
    echo "El formulario no se ha enviado correctamente.<br>";
}
?>
