<?php
declare(strict_types=1);

/**
 * Lanza el backup de MySQL como proceso en segundo plano una vez por semana.
 * Se llama desde index.php para que se autoprograme sin usar BAT.
 */

function maybeRunWeeklyBackup(): void
{
    require_once __DIR__ . '/auto_backup.php';
    if (!function_exists('runBackup') || !isset($config)) {
        return;
    }

    $backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
    $lastRunFile = $backupDir . DIRECTORY_SEPARATOR . '.last_backup';
    $lockFile = $backupDir . DIRECTORY_SEPARATOR . '.backup.lock';

    $now = time();
    $lastRun = is_file($lastRunFile) ? (int)trim((string)file_get_contents($lastRunFile)) : 0;

    // Si ha pasado menos de 6 días desde el último backup, no lanzar.
    if ($lastRun > 0 && ($now - $lastRun) < (6 * 24 * 60 * 60)) {
        return;
    }

    // Evita solapar ejecuciones
    if (is_file($lockFile)) {
        $lockAge = $now - filemtime($lockFile);
        if ($lockAge < (2 * 60 * 60)) {
            return; // otro proceso está ejecutando backup
        }
        // lock obsoleto: limpiar para que no bloquee futuras ejecuciones
        @unlink($lockFile);
    }

    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0700, true);
    }

    try {
        runBackup($config); // ejecuta y limpia el lock internamente
    } catch (Throwable $e) {
        error_log('Backup semanal fallido: ' . $e->getMessage());
        @unlink($lockFile);
    }
}
