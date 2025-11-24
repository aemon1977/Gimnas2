<?php
require_once __DIR__ . '/../config/bootstrap.php';

$conn = getDbConnection();
ensureLicenseTable($conn);

$message = '';
$error = '';
$prefill = null;

if (isset($_GET['prefill'])) {
    $prefillId = (int)$_GET['prefill'];
    $stmt = $conn->prepare("SELECT * FROM licencies WHERE id=?");
    $stmt->bind_param('i', $prefillId);
    $stmt->execute();
    $prefill = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $licenseKey = trim($_POST['license_key'] ?? '');
    $nomClient = trim($_POST['nom_client'] ?? '');
    $actiu = isset($_POST['actiu']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    $activar = isset($_POST['set_active_now']);

    if ($licenseKey === '') {
        $error = 'La clau de llicència és obligatòria.';
    } else {
        $metaKey = parseLicenseKeyMetadata($licenseKey);
        if ($metaKey === null) {
            $error = 'Format de clau no reconegut.';
        } else {
            $caducitat = $metaKey['expiry'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("UPDATE licencies SET license_key=?, nom_client=?, data_caducitat=?, actiu=?, notes=? WHERE id=?");
                $stmt->bind_param('sssisi', $licenseKey, $nomClient, $caducitat, $actiu, $notes, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO licencies (license_key, nom_client, data_caducitat, actiu, notes) VALUES (?,?,?,?,?)");
                $stmt->bind_param('sssis', $licenseKey, $nomClient, $caducitat, $actiu, $notes);
            }
            if ($stmt->execute()) {
                $message = $id ? 'Llicència actualitzada.' : 'Llicència creada.';
                if ($activar) {
                    if (writeLicenseKeyToConfig($licenseKey)) {
                        $message .= ' Clau establerta com a activa.';
                    } else {
                        $error = 'S\'ha guardat la llicència, però no s\'ha pogut actualitzar config.ini.';
                    }
                }
            } else {
                $error = 'No s\'ha pogut guardar la llicència: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM licencies WHERE id=?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $message = 'Llicència eliminada.';
    } else {
        $error = 'No s\'ha pogut eliminar.';
    }
    $stmt->close();
}

$configKey = loadLicenseConfig()['key'] ?? '';

if (isset($_GET['set_active'])) {
    $setId = (int)$_GET['set_active'];
    $stmt = $conn->prepare("SELECT license_key FROM licencies WHERE id=?");
    $stmt->bind_param('i', $setId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($result) {
        if (writeLicenseKeyToConfig($result['license_key'])) {
            $configKey = $result['license_key'];
            $message = 'Clau activa actualitzada.';
        } else {
            $error = 'No s\'ha pogut actualitzar config.ini.';
        }
    } else {
        $error = 'No s\'ha trobat la llicència.';
    }
}

$licencies = [];
$result = $conn->query("SELECT * FROM licencies ORDER BY actualitzat DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $licencies[] = $row;
    }
}

$configKey = $configKey ?: (loadLicenseConfig()['key'] ?? '');
$prefill = $prefill ?: ['id'=>'','license_key'=>'','nom_client'=>'','data_caducitat'=>'','actiu'=>1,'notes'=>''];
$prefillMeta = $prefill['license_key'] ? parseLicenseKeyMetadata($prefill['license_key']) : null;
$prefillExpiry = $prefillMeta['expiry'] ?? $prefill['data_caducitat'];

function getRemainingDays(?string $date): string {
    if (!$date) {
        return 'Sense caducitat';
    }
    try {
        $today = new DateTimeImmutable('today');
        $expiry = new DateTimeImmutable($date);
        $diff = $today->diff($expiry);
        $sign = $diff->invert ? '-' : '';
        return $sign . $diff->days . ' dies';
    } catch (Exception $e) {
        return $date;
    }
}

function maskLicenseKey(string $key): string {
    $len = strlen($key);
    if ($len <= 6) {
        return str_repeat('*', $len);
    }
    $prefix = substr($key, 0, 6);
    $suffix = substr($key, -4);
    $masked = str_repeat('*', max(0, $len - 10));
    return $prefix . $masked . $suffix;
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Gestió de llicències</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', Arial, sans-serif; background:#f5f6fb; margin:0; padding:24px; color:#0f172a; }
        .page { max-width: 960px; margin:0 auto; }
        .card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 15px 35px rgba(15,23,42,0.12); margin-bottom:20px; }
        h1 { margin:0 0 12px; }
        label { display:block; font-weight:600; margin:12px 0 6px; }
        input[type="text"], input[type="date"], textarea { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; }
        textarea { min-height:80px; }
        .btn { border:none; border-radius:12px; padding:11px 18px; font-weight:600; cursor:pointer; margin-top:14px; background:#ef4444; color:#fff; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { padding:10px 12px; border-bottom:1px solid #e5e7eb; text-align:left; }
        th { background:#f9fafb; color:#6b7280; }
        .msg { padding:12px 14px; border-radius:12px; margin-bottom:10px; }
        .msg.ok { background:#ecfdf5; color:#047857; border:1px solid #34d399; }
        .msg.error { background:#fef2f2; color:#b91c1c; border:1px solid #f87171; }
        .actions a { margin-right:10px; color:#2563eb; text-decoration:none; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Gestió de llicències</h1>
            <p style="margin:0 0 10px;">Clau configurada actualment: <strong><?php echo htmlspecialchars(maskLicenseKey($configKey), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php if ($message): ?><div class="msg ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="id" id="license_id" value="<?php echo htmlspecialchars($prefill['id']); ?>">
                <label for="license_key">Clau de llicència</label>
                <input type="text" name="license_key" id="license_key" required value="<?php echo htmlspecialchars($prefill['license_key']); ?>">

                <label for="nom_client">Nom del client</label>
                <input type="text" name="nom_client" id="nom_client" value="<?php echo htmlspecialchars($prefill['nom_client']); ?>">

                <p style="color:#6b7280;">
                    Caducitat detectada: <?php echo htmlspecialchars($prefillExpiry ?: 'Sense caducitat'); ?>
                    (<?php echo htmlspecialchars(getRemainingDays($prefillExpiry)); ?>)
                </p>

                <label><input type="checkbox" name="actiu" <?php echo ((int)$prefill['actiu'] === 1) ? 'checked' : ''; ?>> Activa</label>

                <label for="notes">Notes</label>
                <textarea name="notes" id="notes"><?php echo htmlspecialchars($prefill['notes']); ?></textarea>

                <label><input type="checkbox" name="set_active_now"> Establir com a clau activa</label>

                <button type="submit" class="btn">Guardar llicència</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin:0 0 12px;">Llicències registrades</h2>
            <?php if ($licencies): ?>
            <table>
                <thead>
                    <tr>
                        <th>Clau</th>
                        <th>Client</th>
                        <th>Caducitat</th>
                        <th>Activa</th>
                        <th>Actualització</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licencies as $licencia): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(maskLicenseKey($licencia['license_key'])); ?></td>
                        <td><?php echo htmlspecialchars($licencia['nom_client']); ?></td>
                        <td><?php echo htmlspecialchars($licencia['data_caducitat']); ?></td>
                        <td><?php echo $licencia['actiu'] ? 'Sí' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($licencia['actualitzat']); ?></td>
                        <td class="actions">
                            <a href="licencia.php?prefill=<?php echo $licencia['id']; ?>">Editar</a>
                            <a href="licencia.php?set_active=<?php echo $licencia['id']; ?>">Establir activa</a>
                            <a href="licencia.php?delete=<?php echo $licencia['id']; ?>" onclick="return confirm('Eliminar la llicència?');">Eliminar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No hi ha llicències registrades.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
