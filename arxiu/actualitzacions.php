<?php
require_once __DIR__ . '/../config/bootstrap.php';

$projectRoot = dirname(__DIR__);
$dataRoot = __DIR__ . '/data';
$updateStore = $dataRoot . '/update_manager';
$packagesDir = $updateStore . '/packages';
$snapshotsDir = $updateStore . '/snapshots';

foreach ([$updateStore, $packagesDir, $snapshotsDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

$messages = ['success' => [], 'error' => [], 'status' => []];
$updateConfig = loadUpdateConfig();
$currentVersion = $updateConfig['current_version'] ?: '0.0.0';
$feedUrl = $updateConfig['feed_url'] ?? '';
$authToken = $updateConfig['auth_token'] ?? '';

$availablePackages = [];
$feedError = '';
if ($feedUrl !== '') {
    try {
        $feedData = fetchUpdateFeed($feedUrl, $authToken);
        $availablePackages = normalizePackageList($feedData);
    } catch (Throwable $e) {
        $feedError = $e->getMessage();
    }
} else {
    $feedError = 'Configura "feed_url" dins la secció [updates] de config.ini.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetVersion = trim($_POST['version'] ?? '');

    if ($feedError) {
        $messages['error'][] = 'No es pot executar l\'acció perquè el feed no és accessible.';
    } elseif ($targetVersion === '') {
        $messages['error'][] = 'Falta indicar la versió objectiu.';
    } else {
        $packageMeta = findPackageByVersion($availablePackages, $targetVersion);
        if (!$packageMeta) {
            $messages['error'][] = 'No s\'ha trobat cap paquet amb la versió indicada.';
        } else {
            $packagePath = buildLocalPackagePath($packagesDir, $targetVersion);
            try {
                if ($action === 'download') {
                    downloadUpdatePackage($packageMeta['package_url'], $packagePath, $authToken);
                    if ($packageMeta['checksum']) {
                        verifyPackageChecksum($packagePath, $packageMeta['checksum']);
                    }
                    $messages['success'][] = 'Paquet descarregat correctament.';
                } elseif ($action === 'apply') {
                    if (!is_file($packagePath)) {
                        throw new RuntimeException('Cal descarregar el paquet abans d\'aplicar-lo.');
                    }
                    if ($packageMeta['checksum']) {
                        verifyPackageChecksum($packagePath, $packageMeta['checksum']);
                    }
                    $snapshotName = 'snapshot_' . date('Ymd_His') . '.zip';
                    $snapshotPath = $snapshotsDir . '/' . $snapshotName;
                    createProjectSnapshot($projectRoot, $snapshotPath, [$updateStore]);
                    applyUpdateArchive($packagePath, $projectRoot);
                    setCurrentAppVersion($targetVersion);
                    $currentVersion = $targetVersion;
                    $messages['success'][] = 'Actualització aplicada correctament. S\'ha creat una còpia de seguretat a ' . basename($snapshotName) . '.';
                }
            } catch (Throwable $e) {
                $messages['error'][] = $e->getMessage();
            }
        }
    }
}

function fetchUpdateFeed(string $url, string $token = ''): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No s\'ha pogut inicialitzar la connexió cURL.');
    }
    $headers = ['Accept: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Error en descarregar el manifest d\'actualitzacions: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('El servidor d\'actualitzacions ha retornat l\'estat HTTP ' . $status . '.');
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('El manifest d\'actualitzacions és invàlid o està buit.');
    }
    return $data;
}

function normalizePackageList(array $feed): array
{
    $packages = [];
    $entries = $feed['packages'] ?? $feed['updates'] ?? [];
    if (!is_array($entries)) {
        return [];
    }
    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['version']) || empty($entry['package_url'])) {
            continue;
        }
        $packages[] = [
            'version' => (string)$entry['version'],
            'package_url' => (string)$entry['package_url'],
            'released_at' => $entry['released_at'] ?? ($entry['date'] ?? ''),
            'notes' => $entry['notes'] ?? ($entry['description'] ?? ''),
            'checksum' => $entry['checksum'] ?? ($entry['hash'] ?? ''),
        ];
    }
    usort($packages, static function ($a, $b) {
        return version_compare($b['version'], $a['version']);
    });
    return $packages;
}

function findPackageByVersion(array $packages, string $version): ?array
{
    foreach ($packages as $package) {
        if (strcasecmp($package['version'], $version) === 0) {
            return $package;
        }
    }
    return null;
}

function buildLocalPackagePath(string $packagesDir, string $version): string
{
    $safe = preg_replace('/[^0-9A-Za-z_.-]/', '_', $version);
    return $packagesDir . '/update_' . $safe . '.zip';
}

function downloadUpdatePackage(string $url, string $destination, string $token = ''): void
{
    $temp = $destination . '.part';
    $fp = fopen($temp, 'w');
    if (!$fp) {
        throw new RuntimeException('No es pot crear l\'arxiu temporal per descarregar el paquet.');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        fclose($fp);
        @unlink($temp);
        throw new RuntimeException('No s\'ha pogut inicialitzar cURL per descarregar el paquet.');
    }
    $headers = [];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $ok = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fp);
    if ($ok === false || $status < 200 || $status >= 300) {
        @unlink($temp);
        throw new RuntimeException('Error en descarregar el paquet d\'actualització: ' . ($error ?: 'HTTP ' . $status));
    }
    rename($temp, $destination);
}

function verifyPackageChecksum(string $file, string $expected): void
{
    $expected = trim($expected);
    if ($expected === '') {
        return;
    }
    $algo = 'sha256';
    $hash = $expected;
    if (strpos($expected, ':') !== false) {
        [$algo, $hash] = explode(':', $expected, 2);
    }
    $algo = strtolower($algo);
    if (!in_array($algo, hash_algos(), true)) {
        throw new RuntimeException('Algorisme de checksum no suportat: ' . $algo);
    }
    $calculated = hash_file($algo, $file);
    if ($calculated === false || strtolower($calculated) !== strtolower($hash)) {
        throw new RuntimeException('El checksum del paquet no coincideix.');
    }
}

function createProjectSnapshot(string $root, string $destination, array $excludeDirs = []): void
{
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No es pot crear la còpia de seguretat prèvia.');
    }
    $rootReal = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
    $exclusions = [];
    foreach ($excludeDirs as $dir) {
        $exclusions[] = realpath($dir) ?: $dir;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $skip = false;
        foreach ($exclusions as $exclude) {
            if ($exclude !== false && strpos($path, $exclude) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $relative = ltrim(substr($path, strlen($rootReal)), DIRECTORY_SEPARATOR);
        $localName = ltrim(str_replace('\\', '/', $relative), '/');
        if ($localName === '') {
            continue;
        }
        if ($item->isDir()) {
            $zip->addEmptyDir($localName);
        } else {
            $zip->addFile($path, $localName);
        }
    }
    $zip->close();
}

function applyUpdateArchive(string $packagePath, string $targetDir): void
{
    $zip = new ZipArchive();
    if ($zip->open($packagePath) !== true) {
        throw new RuntimeException('No s\'ha pogut obrir el paquet d\'actualització.');
    }
    if (!$zip->extractTo($targetDir)) {
        $zip->close();
        throw new RuntimeException('Error en extreure el contingut del paquet.');
    }
    $zip->close();
}

function formatNotes($notes): string
{
    if (is_array($notes)) {
        return implode("<br>", array_map('htmlspecialchars', $notes));
    }
    return htmlspecialchars((string)$notes);
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Actualitzacions online</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', Arial, sans-serif; background:#f4f6fb; margin:0; padding:24px; color:#0f172a; }
        .page { max-width: 1100px; margin:0 auto; }
        .card { background:#fff; border-radius:20px; padding:24px; box-shadow:0 18px 40px rgba(15,23,42,0.12); margin-bottom:20px; }
        h1 { margin:0 0 12px; }
        table { width:100%; border-collapse:collapse; margin-top:16px; }
        th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; }
        th { background:#f9fafb; text-transform:uppercase; font-size:13px; color:#6b7280; }
        .alert { padding:12px 16px; border-radius:12px; margin-bottom:10px; }
        .alert.error { background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5; }
        .alert.success { background:#ecfdf5; color:#047857; border:1px solid #6ee7b7; }
        .alert.status { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        button { border:none; border-radius:14px; padding:10px 18px; font-weight:600; cursor:pointer; }
        button.primary { background:#2563eb; color:#fff; }
        button.secondary { background:#0f172a; color:#fff; }
        button:disabled { opacity:0.5; cursor:not-allowed; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; }
        .notes { font-size:0.9rem; color:#374151; }
        .meta { color:#6b7280; font-size:0.9rem; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Actualitzacions en línia</h1>
        <p class="meta">Versió instal·lada: <strong><?php echo htmlspecialchars($currentVersion); ?></strong></p>
        <p class="meta">Feed configurat: <?php echo $feedUrl ? '<a href="' . htmlspecialchars($feedUrl) . '" target="_blank">' . htmlspecialchars($feedUrl) . '</a>' : 'no definit'; ?></p>
        <?php foreach ($messages as $type => $entries): ?>
            <?php foreach ($entries as $entry): ?>
                <div class="alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($entry); ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($feedError): ?>
            <div class="alert error"><?php echo htmlspecialchars($feedError); ?></div>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Versió</th>
                    <th>Data</th>
                    <th>Notes</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$availablePackages): ?>
                <tr><td colspan="5">Cap paquet disponible ara mateix.</td></tr>
            <?php else: ?>
                <?php foreach ($availablePackages as $pkg): ?>
                    <?php $localPath = buildLocalPackagePath($packagesDir, $pkg['version']); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pkg['version']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pkg['released_at'] ?: '—'); ?></td>
                        <td class="notes"><?php echo $pkg['notes'] ? formatNotes($pkg['notes']) : 'Sense notes'; ?></td>
                        <td>
                            <?php if (is_file($localPath)): ?>
                                Descarregat
                            <?php else: ?>
                                Pendent de descarregar
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="post">
                                <input type="hidden" name="version" value="<?php echo htmlspecialchars($pkg['version']); ?>">
                                <button type="submit" name="action" value="download" class="secondary">Descarregar</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="version" value="<?php echo htmlspecialchars($pkg['version']); ?>">
                                <button type="submit" name="action" value="apply" class="primary" <?php echo is_file($localPath) ? '' : 'disabled'; ?>>Aplicar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
