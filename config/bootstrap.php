<?php
declare(strict_types=1);

function loadFullConfig(): array
{
    static $fullConfig = null;
    if ($fullConfig !== null) {
        return $fullConfig;
    }

    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.ini';
    if (!is_file($configPath)) {
        throw new RuntimeException("No s'ha trobat config.ini a la arrel del projecte.");
    }

    $parsed = parse_ini_file($configPath, true, INI_SCANNER_TYPED);
    if (!$parsed) {
        throw new RuntimeException("config.ini no es pot llegir.");
    }
    $fullConfig = $parsed;
    return $fullConfig;
}

function loadDatabaseConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'user'     => 'root',
        'password' => '',
        'database' => 'gimnas',
    ];

    $parsed = loadFullConfig();
    if (!isset($parsed['mysql'])) {
        throw new RuntimeException("config.ini no conté la secció [mysql].");
    }

    $mysql = array_change_key_case($parsed['mysql'], CASE_LOWER);

    $config = [
        'host'     => trim((string)($mysql['host'] ?? $defaults['host'])),
        'port'     => (int)($mysql['port'] ?? $defaults['port']),
        'user'     => (string)($mysql['user'] ?? $defaults['user']),
        'password' => (string)($mysql['password'] ?? $defaults['password']),
        'database' => (string)($mysql['database'] ?? $defaults['database']),
    ];

    if ($config['host'] === '' || strcasecmp($config['host'], 'localhost') === 0) {
        $config['host'] = '127.0.0.1';
    }

    if ($config['port'] <= 0) {
        $config['port'] = 3306;
    }

    return $config;
}

function loadLicenseConfig(): array
{
    $parsed = loadFullConfig();
    $defaults = [
        'key' => 'DEMO-000000',
    ];
    if (!isset($parsed['license'])) {
        return $defaults;
    }
    $section = array_change_key_case($parsed['license'], CASE_LOWER);
    return [
        'key' => trim((string)($section['key'] ?? $defaults['key'])),
    ];
}

function loadUpdateConfig(): array
{
    $defaults = [
        'current_version' => '0.0.0',
        'feed_url' => '',
        'auth_token' => '',
    ];

    $parsed = loadFullConfig();
    if (!isset($parsed['updates'])) {
        return $defaults;
    }
    $section = array_change_key_case($parsed['updates'], CASE_LOWER);
    return [
        'current_version' => trim((string)($section['current_version'] ?? $defaults['current_version'])),
        'feed_url' => trim((string)($section['feed_url'] ?? $defaults['feed_url'])),
        'auth_token' => trim((string)($section['auth_token'] ?? '')),
    ];
}

function getCurrentAppVersion(): string
{
    $updates = loadUpdateConfig();
    return $updates['current_version'] !== '' ? $updates['current_version'] : '0.0.0';
}

function setCurrentAppVersion(string $version): bool
{
    return writeConfigValue('updates', 'current_version', $version);
}

function getDbConnection(): mysqli
{
    $cfg = loadDatabaseConfig();
    $conn = @new mysqli(
        $cfg['host'],
        $cfg['user'],
        $cfg['password'],
        $cfg['database'],
        $cfg['port']
    );

    if ($conn->connect_error) {
        throw new RuntimeException('Connexió fallida: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    enforceLicense($conn);
    return $conn;
}

$__dbGlobals = loadDatabaseConfig();
$GLOBALS['servername'] = $__dbGlobals['host'];
$GLOBALS['username']   = $__dbGlobals['user'];
$GLOBALS['password']   = $__dbGlobals['password'];
$GLOBALS['dbname']     = $__dbGlobals['database'];
$GLOBALS['database']   = $__dbGlobals['database'];
$GLOBALS['dbhost']     = $__dbGlobals['host'];
$GLOBALS['dbuser']     = $__dbGlobals['user'];
$GLOBALS['dbpass']     = $__dbGlobals['password'];
$GLOBALS['db_name']    = $__dbGlobals['database'];

// load empresa data
function getEmpresaInfo(): array
{
    static $empresa = null;
    if ($empresa !== null) {
        return $empresa;
    }
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare('SELECT nom, pagina_web FROM Empresa ORDER BY id ASC LIMIT 1');
        if ($stmt && $stmt->execute()) {
            $stmt->bind_result($nom, $paginaWeb);
            if ($stmt->fetch()) {
                $empresa = [
                    'nom' => $nom,
                    'pagina_web' => $paginaWeb,
                ];
            } else {
                $empresa = ['nom' => null, 'pagina_web' => null];
            }
            $stmt->close();
        } else {
            $empresa = ['nom' => null, 'pagina_web' => null];
        }
    } catch (Throwable $e) {
        $empresa = ['nom' => null, 'pagina_web' => null];
    }
    return $empresa;
}

function ensureLicenseTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS licencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_key VARCHAR(100) NOT NULL UNIQUE,
        nom_client VARCHAR(150) DEFAULT NULL,
        data_caducitat DATE DEFAULT NULL,
        actiu TINYINT(1) DEFAULT 1,
        notes TEXT DEFAULT NULL,
        actualitzat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function enforceLicense(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ensureLicenseTable($conn);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    if ($method === 'POST' && isset($_POST['activate_license'])) {
        processLicenseSubmission($conn);
    }

    $license = loadLicenseConfig();
    $key = trim($license['key'] ?? '');
    if ($key === '' || $key === 'DEMO-000000') {
        renderLicensePrompt('No hi ha cap clau configurada.', $_POST ?? []);
    }

    $stmt = $conn->prepare("SELECT id, data_caducitat, actiu FROM licencies WHERE license_key = ?");
    if (!$stmt) {
        renderLicensePrompt('Error en verificar la llicència.', $_POST ?? []);
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($licId, $caducitat, $actiu);
    if ($stmt->fetch()) {
        $stmt->close();
        if ((int)$actiu !== 1) {
            renderLicensePrompt('Llicència desactivada. Contacta amb el proveïdor.', $_POST ?? []);
        }
        $meta = parseLicenseKeyMetadata($key);
        if ($meta && $meta['expiry']) {
            if (empty($caducitat) || $caducitat !== $meta['expiry']) {
                updateLicenseExpiry($conn, $licId, $meta['expiry']);
                $caducitat = $meta['expiry'];
            }
        }
        if (!empty($caducitat)) {
            try {
                $today = new DateTimeImmutable('today');
                $expiry = new DateTimeImmutable($caducitat);
                $graceLimit = $expiry->modify('+7 days');
                if ($today > $graceLimit) {
                    deleteExpiredLicense($conn, $licId);
                    renderLicensePrompt('Llicència caducada i eliminada automàticament.', $_POST ?? []);
                }
                if ($today > $expiry && $today <= $graceLimit) {
                    $GLOBALS['LICENSE_WARNING'] = 'Llicència en període de gràcia fins ' . $graceLimit->format('Y-m-d');
                }
            } catch (Exception $e) {
                renderLicensePrompt('Data de caducitat invàlida. Contacta amb suport.', $_POST ?? []);
            }
        }
    } else {
        $stmt->close();
        renderLicensePrompt('Llicència no vàlida. Introdueix una clau correcta.', $_POST ?? []);
    }
}

function processLicenseSubmission(mysqli $conn): void
{
    $licenseKey = trim($_POST['license_key'] ?? '');
    $nomClient = trim($_POST['nom_client'] ?? '');
    $actiu = isset($_POST['actiu']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    $old = [
        'license_key' => $licenseKey,
        'nom_client' => $nomClient,
        'actiu' => $actiu,
        'notes' => $notes,
    ];

    if ($licenseKey === '') {
        renderLicensePrompt('La clau de llicència és obligatòria.', $old);
    }
    $meta = parseLicenseKeyMetadata($licenseKey);
    if ($meta === null) {
        renderLicensePrompt('Format de clau no reconegut.', $old);
    }

    $stmt = $conn->prepare("
        INSERT INTO licencies (license_key, nom_client, data_caducitat, actiu, notes)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            nom_client = VALUES(nom_client),
            data_caducitat = VALUES(data_caducitat),
            actiu = VALUES(actiu),
            notes = VALUES(notes),
            actualitzat = NOW()
    ");
    if (!$stmt) {
        renderLicensePrompt('No s\'ha pogut preparar l\'operació.', $old);
    }
    $cadValue = $meta['expiry'] ?? null;
    $stmt->bind_param('sssis', $licenseKey, $nomClient, $cadValue, $actiu, $notes);
    if (!$stmt->execute()) {
        renderLicensePrompt('No s\'ha pogut guardar la llicència: ' . $stmt->error, $old);
    }
    $stmt->close();

    if (!writeLicenseKeyToConfig($licenseKey)) {
        renderLicensePrompt('No s\'ha pogut actualitzar config.ini.', $old);
    }

    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

function renderLicensePrompt(string $message, array $old = []): void
{
    http_response_code(403);
    $defaults = [
        'license_key' => '',
        'nom_client' => '',
        'data_caducitat' => '',
        'actiu' => 1,
        'notes' => '',
    ];
    $data = array_merge($defaults, $old);
    ?>
    <!DOCTYPE html>
    <html lang="ca">
    <head>
        <meta charset="UTF-8">
        <title>Activació de llicència</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            body { font-family:'Poppins',Arial,sans-serif; background:#0f172a; color:#fff; margin:0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
            .card { background:#fff; color:#0f172a; border-radius:18px; padding:24px; width:90%; max-width:480px; box-shadow:0 25px 50px rgba(15,23,42,0.35); }
            h1 { margin:0 0 10px; font-size:1.5rem; }
            p { margin:0 0 18px; }
            label { display:block; font-weight:600; margin-bottom:6px; }
            input[type="text"], input[type="date"], textarea { width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; margin-bottom:12px; font-family:'Poppins',Arial,sans-serif; }
            textarea { min-height:70px; }
            button { width:100%; border:none; border-radius:12px; padding:12px; font-weight:600; background:#ef4444; color:#fff; cursor:pointer; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Activació requerida</h1>
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <form method="post">
                <input type="hidden" name="activate_license" value="1">
                <label for="license_key">Clau de llicència</label>
                <input type="text" name="license_key" id="license_key" required value="<?php echo htmlspecialchars($data['license_key'], ENT_QUOTES, 'UTF-8'); ?>">

                <label for="nom_client">Nom del client</label>
                <input type="text" name="nom_client" id="nom_client" value="<?php echo htmlspecialchars($data['nom_client'], ENT_QUOTES, 'UTF-8'); ?>">

                <label><input type="checkbox" name="actiu" <?php echo ((int)$data['actiu'] === 1) ? 'checked' : ''; ?>> Activa</label>

                <label for="notes">Notes</label>
                <textarea name="notes" id="notes"><?php echo htmlspecialchars($data['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                <button type="submit">Guardar llicència</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function writeLicenseKeyToConfig(string $newKey): bool
{
    return writeConfigValue('license', 'key', $newKey);
}

function writeConfigValue(string $section, string $key, string $value): bool
{
    $configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.ini';
    $lines = file($configPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }
    $result = [];
    $sectionPattern = '/^\s*\[' . preg_quote($section, '/') . '\]\s*$/i';
    $keyPattern = '/^\s*' . preg_quote($key, '/') . '\s*=/i';
    $inSection = false;
    $foundSection = false;
    $foundKey = false;

    foreach ($lines as $line) {
        $trim = trim($line);
        if (preg_match($sectionPattern, $trim)) {
            $inSection = true;
            $foundSection = true;
            $result[] = $line;
            continue;
        }
        if ($inSection && preg_match('/^\s*\[.*\]\s*$/', $trim)) {
            if (!$foundKey) {
                $result[] = $key . '=' . $value;
                $foundKey = true;
            }
            $inSection = false;
        }
        if ($inSection && preg_match($keyPattern, $trim)) {
            $line = $key . '=' . $value;
            $foundKey = true;
        }
        $result[] = $line;
    }

    if ($inSection && !$foundKey) {
        $result[] = $key . '=' . $value;
        $foundKey = true;
    }

    if (!$foundSection) {
        if (!empty($result) && end($result) !== '') {
            $result[] = '';
        }
        $result[] = '[' . $section . ']';
        $result[] = $key . '=' . $value;
    }

    return file_put_contents($configPath, implode(PHP_EOL, $result) . PHP_EOL) !== false;
}

function parseLicenseKeyMetadata(string $key): ?array
{
    if (preg_match('/^LIC(\\d{8})(\\d{3})-([A-Z0-9]+)$/', $key, $matches)) {
        $start = DateTimeImmutable::createFromFormat('Ymd', $matches[1]);
        if (!$start) {
            return null;
        }
        $duration = (int)$matches[2];
        $expiry = null;
        if ($duration > 0) {
            $expiry = $start->modify('+' . $duration . ' days')->format('Y-m-d');
        }
        return [
            'start' => $start->format('Y-m-d'),
            'duration' => $duration,
            'expiry' => $expiry,
        ];
    }
    return null;
}

function updateLicenseExpiry(mysqli $conn, int $id, string $expiry): void
{
    $stmt = $conn->prepare("UPDATE licencies SET data_caducitat=? WHERE id=?");
    if ($stmt) {
        $stmt->bind_param('si', $expiry, $id);
        $stmt->execute();
        $stmt->close();
    }
}

function deleteExpiredLicense(mysqli $conn, int $id): void
{
    $stmt = $conn->prepare("DELETE FROM licencies WHERE id=?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

function getDefaultPhotoBinary(): ?string
{
    static $loaded = false;
    static $data = null;
    if ($loaded) {
        return $data;
    }
    $loaded = true;
    $logoPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo.jpg';
    if (is_file($logoPath)) {
        $contents = @file_get_contents($logoPath);
        if ($contents !== false) {
            $data = $contents;
        }
    }
    return $data;
}

function applyDefaultPhotoToTable(mysqli $conn, string $table, string $column = 'Foto'): void
{
    $default = getDefaultPhotoBinary();
    if ($default === null) {
        return;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return;
    }
    $sql = sprintf(
        "UPDATE `%s` SET `%s`=? WHERE `%s` IS NULL OR `%s`=''",
        $table,
        $column,
        $column,
        $column
    );
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $default);
    $stmt->execute();
    $stmt->close();
}

