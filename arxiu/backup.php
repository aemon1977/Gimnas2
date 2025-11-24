<?php
declare(strict_types=1);

ini_set('memory_limit', '512M');

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $needleLength = strlen($needle);
        return substr($haystack, -$needleLength) === $needle;
    }
}

$appDir    = __DIR__;
$dataDir   = $appDir . DIRECTORY_SEPARATOR . 'data';
$backupDir = $dataDir . DIRECTORY_SEPARATOR . 'backup';
$retentionLimit = 7;

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

$messages = [
    'success' => [],
    'error'   => [],
    'status'  => [],
];

function loadAppConfiguration(string $appDir): array
{
    $defaults = [
        'host'     => 'localhost',
        'port'     => '3306',
        'user'     => 'root',
        'password' => '',
        'database' => 'gimnas',
    ];

    $paths = [
        $appDir . DIRECTORY_SEPARATOR . 'config.ini',
        dirname($appDir) . DIRECTORY_SEPARATOR . 'config.ini',
        dirname($appDir, 2) . DIRECTORY_SEPARATOR . 'config.ini',
    ];

    $result = ['mysql' => $defaults, 'xampp' => null];

    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $parsedRaw = parse_ini_file($path, true, INI_SCANNER_RAW);
        if (!$parsedRaw) {
            continue;
        }

        if (isset($parsedRaw['mysql'])) {
            $section = array_change_key_case($parsedRaw['mysql'], CASE_LOWER);
            foreach ($defaults as $key => $value) {
                if (isset($section[$key]) && $section[$key] !== '') {
                    $result['mysql'][$key] = (string)$section[$key];
                }
            }
        }

        if (isset($parsedRaw['xampp']['bin_dir'])) {
            $result['xampp'] = trim((string)$parsedRaw['xampp']['bin_dir']);
        }

        break;
    }

    $result['mysql']['host'] = trim($result['mysql']['host'] ?? '');
    if ($result['mysql']['host'] === '' || strcasecmp($result['mysql']['host'], 'localhost') === 0) {
        $result['mysql']['host'] = '127.0.0.1';
    }
    $result['mysql']['port'] = (string)($result['mysql']['port'] ?? '3306');
    return $result;
}

$appConfig = loadAppConfiguration($appDir);
$cfg = $appConfig['mysql'];
$xamppOverride = $appConfig['xampp'];

function detectXamppBin(?string $override): string
{
    $candidates = [];
    if ($override) {
        $candidates[] = $override;
    }
    $candidates[] = 'C:\xampp\mysql\bin';
    $candidates[] = 'D:\xampp\mysql\bin';

    foreach ($candidates as $candidate) {
        $normalized = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
        if (is_dir($normalized) && is_file($normalized . DIRECTORY_SEPARATOR . 'mysqldump.exe')) {
            return $normalized;
        }
    }

    throw new RuntimeException('No s\'ha trobat mysql.exe/mysqldump.exe. Configura [xampp] bin_dir a config.ini.');
}

$xamppBin = detectXamppBin($xamppOverride);
$mysqlBin = $xamppBin . DIRECTORY_SEPARATOR . 'mysql.exe';
$mysqldumpBin = $xamppBin . DIRECTORY_SEPARATOR . 'mysqldump.exe';
$pluginDirReal = realpath($xamppBin . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'plugin');
$pluginDir = $pluginDirReal !== false ? $pluginDirReal : null;

function buildDumpCommand(string $mysqldump, array $cfg, ?string $pluginDir): array
{
    $cmd = [
        $mysqldump,
        '-h', $cfg['host'],
        '-P', (string)$cfg['port'],
        '-u', $cfg['user'],
        '--password=' . $cfg['password'],
        '--single-transaction',
        '--skip-lock-tables',
        '--routines',
        '--events',
        '--triggers',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        $cfg['database'],
    ];
    if ($pluginDir && is_dir($pluginDir)) {
        $cmd[] = '--plugin-dir=' . $pluginDir;
        $cmd[] = '--default-auth=mysql_native_password';
    }
    return $cmd;
}

function buildMysqlCommand(
    string $mysql,
    array $cfg,
    ?string $pluginDir,
    ?string $database = null,
    ?string $extraSql = null
): array {
    $cmd = [
        $mysql,
        '-h', $cfg['host'],
        '-P', (string)$cfg['port'],
        '-u', $cfg['user'],
        '--password=' . $cfg['password'],
        '--protocol=tcp',
    ];
    if ($pluginDir && is_dir($pluginDir)) {
        $cmd[] = '--plugin-dir=' . $pluginDir;
        $cmd[] = '--default-auth=mysql_native_password';
    }
    if ($database) {
        $cmd[] = $database;
    }
    if ($extraSql) {
        $cmd[] = '-e';
        $cmd[] = $extraSql;
    }
    return $cmd;
}

function runCommand(array $command, ?string $input = null, ?string $pluginDir = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = getenv();
    if (!is_array($env) || empty($env)) {
        $env = $_ENV ?: null;
    }
    if ($pluginDir && is_dir($pluginDir)) {
        if (!is_array($env)) {
            $env = [];
        }
        $env['MYSQL_PLUGIN_DIR'] = $pluginDir;
    }

    $process = proc_open($command, $descriptors, $pipes, null, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('No s\'ha pogut executar la comanda MySQL.');
    }

    if ($input !== null) {
        fwrite($pipes[0], $input);
    }
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [$exitCode, $stdout, $stderr];
}

function compressSqlFile(string $source, string $destination): void
{
    $in = fopen($source, 'rb');
    if ($in === false) {
        throw new RuntimeException('No s\'ha pogut llegir la còpia temporal.');
    }
    $out = gzopen($destination, 'wb6');
    if ($out === false) {
        fclose($in);
        throw new RuntimeException('No s\'ha pogut crear l\'arxiu comprimit.');
    }
    while (!feof($in)) {
        $data = fread($in, 1024 * 512);
        if ($data === false) {
            break;
        }
        gzwrite($out, $data);
    }
    fclose($in);
    gzclose($out);
}

function createBackupFromDatabase(
    string $mysqldump,
    array $cfg,
    ?string $pluginDir,
    string $backupDir,
    bool $compress,
    string $label = 'backup'
): string {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $base = sprintf('%s_%s_%s', $cfg['database'], strtoupper($label), $timestamp);
    $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $base . '.sql';
    if (is_file($sqlPath)) {
        @unlink($sqlPath);
    }

    $command = buildDumpCommand($mysqldump, $cfg, $pluginDir);
    $command[] = '--result-file=' . $sqlPath;

    [$code, , $err] = runCommand($command, null, $pluginDir);
    if ($code !== 0 || !is_file($sqlPath)) {
        throw new RuntimeException('mysqldump ha fallat: ' . ($err ?: 'Sense detalls.'));
    }

    if ($compress) {
        $gzPath = $sqlPath . '.gz';
        if (is_file($gzPath)) {
            @unlink($gzPath);
        }
        compressSqlFile($sqlPath, $gzPath);
        @unlink($sqlPath);
        $finalPath = $gzPath;
    } else {
        $finalPath = $sqlPath;
    }

    if (!is_file($finalPath) || filesize($finalPath) === 0) {
        throw new RuntimeException('No s\'ha pogut crear la còpia.');
    }

    return $finalPath;
}

function enforceRetention(string $backupDir, int $limit): void
{
    $files = [];
    foreach (glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') as $file) {
        $files[] = ['path' => $file, 'mtime' => filemtime($file)];
    }
    foreach (glob($backupDir . DIRECTORY_SEPARATOR . '*.sql.gz') as $file) {
        $files[] = ['path' => $file, 'mtime' => filemtime($file)];
    }
    usort($files, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    if (count($files) <= $limit) {
        return;
    }
    foreach (array_slice($files, $limit) as $old) {
        @unlink($old['path']);
    }
}

function listBackups(string $backupDir): array
{
    $items = [];
    foreach (array_merge(
        glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') ?: [],
        glob($backupDir . DIRECTORY_SEPARATOR . '*.sql.gz') ?: []
    ) as $file) {
        $items[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'mtime' => filemtime($file),
            'type' => str_ends_with($file, '.gz') ? 'SQL GZip' : 'SQL',
        ];
    }
    usort($items, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $items;
}

function readBackupContent(string $file): string
{
    if (!is_file($file)) {
        throw new RuntimeException('L\'arxiu de còpia no existeix.');
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('No s\'ha pogut llegir la còpia.');
    }
    if (str_ends_with($file, '.gz')) {
        $decoded = gzdecode($raw);
        if ($decoded === false) {
            throw new RuntimeException('El fitxer comprimit és invàlid.');
        }
        return $decoded;
    }
    return $raw;
}

function ensureDatabaseExists(string $mysql, array $cfg, ?string $pluginDir): void
{
    $sql = sprintf(
        "CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
        $cfg['database']
    );
    [$code, , $err] = runCommand(buildMysqlCommand($mysql, $cfg, $pluginDir, null, $sql), null, $pluginDir);
    if ($code !== 0) {
        throw new RuntimeException('No s\'ha pogut crear la base de dades: ' . $err);
    }
}

function dropAndCreateDatabase(string $mysql, array $cfg, ?string $pluginDir): void
{
    $sql = sprintf(
        "DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;",
        $cfg['database'],
        $cfg['database']
    );
    [$code, , $err] = runCommand(buildMysqlCommand($mysql, $cfg, $pluginDir, null, $sql), null, $pluginDir);
    if ($code !== 0) {
        throw new RuntimeException('No s\'ha pogut recrear la base de dades: ' . $err);
    }
}

function databaseExists(string $mysql, array $cfg, ?string $pluginDir): bool
{
    $sql = sprintf("SHOW DATABASES LIKE '%s';", $cfg['database']);
    [$code, $out, $err] = runCommand(buildMysqlCommand($mysql, $cfg, $pluginDir, null, $sql), null, $pluginDir);
    if ($code !== 0) {
        throw new RuntimeException('No s\'ha pogut comprovar la base de dades: ' . $err);
    }
    return stripos($out, $cfg['database']) !== false;
}

function importSqlDump(string $mysql, array $cfg, ?string $pluginDir, string $sql): void
{
    [$code, , $err] = runCommand(buildMysqlCommand($mysql, $cfg, $pluginDir, $cfg['database']), $sql, $pluginDir);
    if ($code !== 0) {
        throw new RuntimeException('La restauració ha fallat: ' . $err);
    }
}


$action = $_POST['action'] ?? null;

try {
    if ($action === 'test_connection') {
        [$code, , $err] = runCommand(buildMysqlCommand($mysqlBin, $cfg, $pluginDir, null, 'SELECT 1;'), null, $pluginDir);
        if ($code === 0) {
            $messages['success'][] = 'Connexió correcta amb MySQL.';
        } else {
            throw new RuntimeException('Connexió fallida: ' . $err);
        }
    } elseif ($action === 'create_backup') {
        $compress = isset($_POST['compress']);
        $final = createBackupFromDatabase($mysqldumpBin, $cfg, $pluginDir, $backupDir, $compress, 'backup');
        enforceRetention($backupDir, $retentionLimit);
        $messages['success'][] = 'Còpia creada: ' . basename($final);
    } elseif ($action === 'delete_backup') {
        $file = basename($_POST['file'] ?? '');
        $path = realpath($backupDir . DIRECTORY_SEPARATOR . $file);
        $baseBackup = realpath($backupDir);
        if (!$file || !$path || !$baseBackup || !str_starts_with($path, $baseBackup)) {
            throw new RuntimeException('Ruta no vàlida.');
        }
        if (is_file($path)) {
            unlink($path);
            $messages['success'][] = 'Còpia eliminada.';
        }
    } elseif ($action === 'restore_backup') {
        $file = basename($_POST['file'] ?? '');
        $dropBefore = !empty($_POST['drop_before']);
        $path = realpath($backupDir . DIRECTORY_SEPARATOR . $file);
        $baseBackup = realpath($backupDir);
        if (!$file || !$path || !$baseBackup || !str_starts_with($path, $baseBackup)) {
            throw new RuntimeException('Còpia no vàlida.');
        }
        $sql = readBackupContent($path);
        $exists = databaseExists($mysqlBin, $cfg, $pluginDir);
        if ($exists && $dropBefore) {
            $safety = createBackupFromDatabase($mysqldumpBin, $cfg, $pluginDir, $backupDir, true, 'SAFETY');
            enforceRetention($backupDir, $retentionLimit);
            dropAndCreateDatabase($mysqlBin, $cfg, $pluginDir);
            $messages['status'][] = 'S\'ha guardat una còpia prèvia: ' . basename($safety);
        } else {
            ensureDatabaseExists($mysqlBin, $cfg, $pluginDir);
        }
        importSqlDump($mysqlBin, $cfg, $pluginDir, $sql);
        $messages['success'][] = 'Base de dades restaurada correctament.';
    } elseif ($action === 'restore_latest') {
        $dropBefore = !empty($_POST['drop_before']);
        $latest = listBackups($backupDir)[0] ?? null;
        if (!$latest) {
            throw new RuntimeException('No hi ha còpies disponibles per restaurar.');
        }
        $file = $latest['name'];
        $path = realpath($backupDir . DIRECTORY_SEPARATOR . $file);
        $baseBackup = realpath($backupDir);
        if (!$path || !$baseBackup || !str_starts_with($path, $baseBackup)) {
            throw new RuntimeException('No s\'ha pogut localitzar la còpia.');
        }
        $sql = readBackupContent($path);
        $exists = databaseExists($mysqlBin, $cfg, $pluginDir);
        if ($exists && $dropBefore) {
            $safety = createBackupFromDatabase($mysqldumpBin, $cfg, $pluginDir, $backupDir, true, 'SAFETY');
            enforceRetention($backupDir, $retentionLimit);
            dropAndCreateDatabase($mysqlBin, $cfg, $pluginDir);
            $messages['status'][] = 'S\'ha guardat una còpia prèvia: ' . basename($safety);
        } else {
            ensureDatabaseExists($mysqlBin, $cfg, $pluginDir);
        }
        importSqlDump($mysqlBin, $cfg, $pluginDir, $sql);
        $messages['success'][] = 'Restauració ràpida feta des de ' . htmlspecialchars($file);
    } elseif ($action === 'restore_upload' && isset($_FILES['external_backup'])) {
        if ($_FILES['external_backup']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error en pujar l\'arxiu extern.');
        }
        $name = basename($_FILES['external_backup']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'gz'], true)) {
            throw new RuntimeException('Només es permeten arxius .sql o .gz.');
        }
        $sanitized = preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
        $target = $backupDir . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . $sanitized;
        if (!move_uploaded_file($_FILES['external_backup']['tmp_name'], $target)) {
            throw new RuntimeException('No s\'ha pogut desar l\'arxiu pujat.');
        }
        $messages['success'][] = 'Arxiu pujat. Pots restaurar-lo des de la llista.';
    }
} catch (Throwable $e) {
    $messages['error'][] = $e->getMessage();
}

if (isset($_GET['download'])) {
    $file = basename((string)$_GET['download']);
    $path = realpath($backupDir . DIRECTORY_SEPARATOR . $file);
    $baseBackup = realpath($backupDir);
    if ($file && $path && $baseBackup && str_starts_with($path, $baseBackup) && is_file($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

$backups = listBackups($backupDir);
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Còpies de seguretat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: #f4f6fb;
            margin: 0;
            padding: 24px;
            color: #0f172a;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            margin-bottom: 20px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 1.9rem;
        }
        form.inline {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        button, label.upload-label {
            border: none;
            border-radius: 14px;
            background: #111a2c;
            color: #fff;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
        }
        button.primary {
            background: #dc2626;
        }
        input[type="file"] { display: none; }
        .alert {
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 10px;
        }
        .alert.success { background: #ecfdf5; color: #047857; border: 1px solid #34d399; }
        .alert.error { background: #fef2f2; color: #b91c1c; border: 1px solid #f87171; }
        .alert.status { background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 0.95rem;
        }
        th { background: #f9fafb; font-weight: 600; }
        td.actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        a.download-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Gestor de còpies MySQL</h1>
            <?php foreach ($messages as $type => $entries): ?>
                <?php foreach ($entries as $entry): ?>
                    <div class="alert <?php echo htmlspecialchars($type); ?>">
                        <?php echo htmlspecialchars($entry); ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <form class="inline" method="post">
                <input type="hidden" name="action" value="create_backup">
                <label class="checkbox">
                    <input type="checkbox" name="compress" checked>
                    Arxiu comprimit (.sql.gz)
                </label>
                <button type="submit" class="primary">Crear còpia ara</button>
            </form>
            <form class="inline" method="post" style="margin-top: 16px;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit">Provar connexió</button>
            </form>
            <form class="inline" method="post" enctype="multipart/form-data" style="margin-top: 16px;">
                <input type="hidden" name="action" value="restore_upload">
                <input type="file" id="external_backup" name="external_backup" accept=".sql,.gz">
                <label for="external_backup" class="upload-label">Pujar còpia externa…</label>
                <span style="font-size:0.9rem;color:#6b7280;">Accepta .sql o .sql.gz</span>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Còpies disponibles</h2>
            <table>
                <thead>
                    <tr>
                        <th>Arxiu</th>
                        <th>Data</th>
                        <th>Mida</th>
                        <th>Tipus</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$backups): ?>
                        <tr>
                            <td colspan="5">Encara no hi ha còpies.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($backups as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $item['mtime']); ?></td>
                            <td><?php echo number_format($item['size'] / 1024, 1); ?> KB</td>
                            <td><?php echo htmlspecialchars($item['type']); ?></td>
                            <td class="actions">
                                <a class="download-link" href="?download=<?php echo urlencode($item['name']); ?>">Descarregar</a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="restore_backup">
                                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <label class="checkbox" style="font-size:0.85rem;">
                                        <input type="checkbox" name="drop_before">
                                        Drop + create
                                    </label>
                                    <button type="submit">Restaurar</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <button type="submit" onclick="return confirm('Eliminar aquesta còpia?');">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        const fileInput = document.getElementById('external_backup');
        if (fileInput) {
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) {
                    fileInput.closest('form').submit();
                }
            });
        }
    </script>
</body>
</html>
