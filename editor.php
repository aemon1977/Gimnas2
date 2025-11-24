<?php
session_start();

$password = '40531553x';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
    } else {
        $error = "Contraseña incorrecta.";
    }
}

if (!isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Autenticación Requerida</title>
    </head>
    <body>
        <h2>Por favor, ingresa la contraseña</h2>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" required>
            <button type="submit">Ingresar</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

$dir = isset($_GET['dir']) ? $_GET['dir'] : '.';

if (!is_dir($dir)) {
    echo "Directorio no válido.";
    exit;
}

$files = scandir($dir);

function create_folder($folder_name) {
    if (!mkdir($folder_name)) {
        echo "Error al crear la carpeta.";
    }
}

function create_file($file_name, $content) {
    file_put_contents($file_name, $content);
}

function upload_file($target_file) {
    move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file);
}

function upload_zip_and_extract($zip_file, $destination) {
    $zip = new ZipArchive;
    if ($zip->open($zip_file) === TRUE) {
        // Eliminar contenido existente en la carpeta de destino
        array_map('unlink', glob("$destination/*"));
        $zip->extractTo($destination);
        $zip->close();
        return true;
    } else {
        return false;
    }
}

if (isset($_POST['create_folder'])) {
    $new_folder = $dir . '/' . basename($_POST['folder_name']);
    create_folder($new_folder);
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['create_file'])) {
    $new_file = $dir . '/' . basename($_POST['file_name']);
    create_file($new_file, '');
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['upload'])) {
    $target_file = $dir . '/' . basename($_FILES['file_upload']['name']);
    upload_file($target_file);
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['upload_zip'])) {
    $zip_file = $dir . '/' . basename($_FILES['zip_upload']['name']);
    upload_file($zip_file);
    if (upload_zip_and_extract($zip_file, $dir)) {
        echo "ZIP subido y descomprimido correctamente.";
    } else {
        echo "Error al descomprimir el archivo ZIP.";
    }
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

if (isset($_POST['delete'])) {
    $file_to_delete = $dir . '/' . basename($_POST['file_name']);
    if (is_dir($file_to_delete)) {
        rmdir($file_to_delete);
    } else {
        unlink($file_to_delete);
    }
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

$content = '';
$file_to_edit = null;

if (isset($_GET['edit'])) {
    $file_to_edit = $dir . '/' . basename($_GET['edit']);
    if (file_exists($file_to_edit)) {
        $content = file_get_contents($file_to_edit);
    }
}

if (isset($_POST['save'])) {
    $file_to_save = $dir . '/' . basename($_POST['file_name']);
    file_put_contents($file_to_save, $_POST['content']);
    header("Location: editor.php?dir=" . urlencode($dir));
    exit;
}

// Mover archivos
if (isset($_POST['move'])) {
    $file_to_move = $dir . '/' . basename($_POST['file_name']);
    $new_location = $_POST['new_location'] . '/' . basename($_POST['file_name']);
    if (rename($file_to_move, $new_location)) {
        header("Location: editor.php?dir=" . urlencode($dir));
        exit;
    }
}

// Moverse a la carpeta superior
$parent_dir = dirname($dir);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Explorador de Archivos</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; }
        h1 { text-align: center; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .file { display: inline-block; width: 120px; text-align: center; margin: 10px; border: 1px solid #ccc; border-radius: 4px; padding: 10px; background-color: #fff; transition: background-color 0.3s; }
        .file:hover { background-color: #e6e6e6; }
        .folder { color: blue; }
        .icon { display: block; margin: 0 auto 5px; }
        input[type="text"], textarea { width: calc(100% - 22px); padding: 10px; margin-right: 5px; }
        button { padding: 10px 15px; }
        .form-container { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Explorador de Archivos</h1>

        <div class="form-container">
            <form method="post" enctype="multipart/form-data">
                <h3>Subir Archivo</h3>
                <input type="file" name="file_upload" required>
                <button type="submit" name="upload">Subir Archivo</button>
            </form>
            <form method="post" enctype="multipart/form-data">
                <h3>Subir ZIP</h3>
                <input type="file" name="zip_upload" required accept=".zip">
                <button type="submit" name="upload_zip">Subir y Descomprimir</button>
            </form>
            <form method="post">
                <h3>Crear Carpeta</h3>
                <input type="text" name="folder_name" placeholder="Nombre de la nueva carpeta" required>
                <button type="submit" name="create_folder">Crear Carpeta</button>
            </form>
            <form method="post">
                <h3>Crear Archivo</h3>
                <input type="text" name="file_name" placeholder="Nombre del nuevo archivo" required>
                <button type="submit" name="create_file">Crear Archivo</button>
            </form>
        </div>

        <h2>Directorio: <?php echo htmlspecialchars($dir); ?></h2>
        <div>
            <?php if ($parent_dir): ?>
                <a href="?dir=<?php echo urlencode($parent_dir); ?>">🔙 Volver a la carpeta anterior</a>
            <?php endif; ?>
            <?php foreach ($files as $file): ?>
                <?php if ($file != '.' && $file != '..'): ?>
                    <div class="file <?php echo is_dir($dir . '/' . $file) ? 'folder' : ''; ?>">
                        <span class="icon">
                            <?php if (is_dir($dir . '/' . $file)): ?>
                                <a href="?dir=<?php echo urlencode($dir . '/' . $file); ?>"><?php echo htmlspecialchars($file); ?></a>
                            <?php else: ?>
                                📄 <?php echo htmlspecialchars($file); ?>
                            <?php endif; ?>
                        </span>
                        <?php if (substr($dir, -1) !== '/'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" name="delete" style="background-color: red; color: white;">Eliminar</button>
                            </form>
                            <a href="?dir=<?php echo urlencode($dir); ?>&edit=<?php echo urlencode($file); ?>">Editar</a>
                            <form method="post" style="display:inline;">
                                <select name="new_location">
                                    <option value="">Seleccionar carpeta</option>
                                    <?php foreach (scandir(dirname($dir)) as $folder): ?>
                                        <?php if ($folder != '.' && $folder != '..' && is_dir(dirname($dir) . '/' . $folder)): ?>
                                            <option value="<?php echo htmlspecialchars(dirname($dir) . '/' . $folder); ?>"><?php echo htmlspecialchars($folder); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($file); ?>">
                                <button type="submit" name="move">Mover</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($file_to_edit): ?>
            <h3>Editando: <?php echo htmlspecialchars(basename($file_to_edit)); ?></h3>
            <form method="post">
                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars(basename($file_to_edit)); ?>">
                <textarea name="content" rows="10"><?php echo htmlspecialchars($content); ?></textarea>
                <br>
                <button type="submit" name="save">Guardar Cambios</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
