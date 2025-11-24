<?php
declare(strict_types=1);

/**
 * Backup semanal de la base de datos sin usar BAT.
 * Se ejecuta por CLI (p.ej. desde scheduler.php) y guarda el .sql en backup/backups.
 */

$config = [
    'backupDir'      => realpath(__DIR__ . '/../arxiu/data/backup') ?: (__DIR__ . '/../arxiu/data/backup'),
    'mysqldumpPath'  => 'C:/xampp/mysql/bin/mysqldump.exe',
    'dbHost'         => 'localhost',
    'dbName'         => 'gimnas',
    'dbUser'         => 'root',
    'dbPassword'     => '',
    'lastRunFile'    => (__DIR__ . '/../arxiu/data/backup/.last_backup'),
    'lockFile'       => (__DIR__ . '/../arxiu/data/backup/.backup.lock'),
];

$maxBackupsToKeep = 7;

/**
 * Devuelve la lista de backups existentes.
 */
function listBackups(string $backupDir): array
{
    $files = glob($backupDir . DIRECTORY_SEPARATOR . 'db_backup_*.sql') ?: [];
    return array_values(array_filter($files, 'is_file'));
}

/**
 * Comprueba si un backup parece válido (tamaño y cabecera mínimas).
 */
function isBackupValid(string $file): bool
{
    if (!is_file($file) || filesize($file) < 1024) {
        return false;
    }

    $fh = fopen($file, 'rb');
    if (!$fh) {
        return false;
    }

    $chunk = fread($fh, 2048);
    fclose($fh);

    if ($chunk === false) {
        return false;
    }

    $chunkLower = strtolower($chunk);
    return str_contains($chunkLower, 'mysql') || str_contains($chunkLower, 'create table') || str_contains($chunkLower, 'insert into');
}

/**
 * Elimina backups corruptos o antiguos para mantener el máximo configurado.
 */
function pruneBackups(string $backupDir, int $maxBackups): void
{
    $files = listBackups($backupDir);

    // Quitar los que parezcan corruptos
    foreach ($files as $file) {
        if (!isBackupValid($file)) {
            @unlink($file);
        }
    }

    // Recalcular tras eliminar corruptos
    $files = listBackups($backupDir);

    if (count($files) <= $maxBackups) {
        return;
    }

    // Orden por fecha (más antiguo primero)
    usort($files, static function ($a, $b) {
        return filemtime($a) <=> filemtime($b);
    });

    while (count($files) > $maxBackups) {
        $oldest = array_shift($files);
        @unlink($oldest);
    }
}

/**
 * Ejecuta el volcado de MySQL y almacena un marcador de última ejecución.
 */
function runBackup(array $config): void
{
    global $maxBackupsToKeep;

    $backupDir = $config['backupDir'];
    $mysqldumpPath = $config['mysqldumpPath'];
    $dbHost = $config['dbHost'];
    $dbName = $config['dbName'];
    $dbUser = $config['dbUser'];
    $dbPassword = $config['dbPassword'];
    $lastRunFile = $config['lastRunFile'];
    $lockFile = $config['lockFile'];

    if (!file_exists($mysqldumpPath)) {
        throw new RuntimeException("No se encuentra mysqldump en {$mysqldumpPath}");
    }

    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
        throw new RuntimeException("No se pudo crear el directorio de backups {$backupDir}");
    }

    $timestamp = date('Y-m-d_H-i-s');
    $targetFile = $backupDir . DIRECTORY_SEPARATOR . "db_backup_{$timestamp}.sql";

    // Evita ejecuciones simultáneas
    file_put_contents($lockFile, (string)time());

    $command = sprintf(
        '"%s" --user=%s --password=%s --host=%s %s --single-transaction --routines --events --result-file=%s',
        $mysqldumpPath,
        escapeshellarg($dbUser),
        escapeshellarg($dbPassword),
        escapeshellarg($dbHost),
        escapeshellarg($dbName),
        escapeshellarg($targetFile)
    );

    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file($targetFile)) {
        @unlink($targetFile);
        @unlink($lockFile);
        throw new RuntimeException('Fallo al crear el backup de la base de datos');
    }

    file_put_contents($lastRunFile, (string)time());
    pruneBackups($backupDir, (int)$maxBackupsToKeep);
    @unlink($lockFile);
}

// Permite invocar directamente por CLI: php auto_backup.php
if (php_sapi_name() === 'cli') {
    runBackup($config);
}
