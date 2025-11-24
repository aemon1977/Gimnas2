<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Database configuration

$successMessage = '';
$errorMessage = '';
$empresa = null;

$conn = getDbConnection();

$conn->query("CREATE TABLE IF NOT EXISTS Empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) DEFAULT NULL,
    adresa VARCHAR(255) DEFAULT NULL,
    poblacio VARCHAR(100) DEFAULT NULL,
    provincia VARCHAR(100) DEFAULT NULL,
    codi_postal VARCHAR(10) DEFAULT NULL,
    telefon VARCHAR(15) DEFAULT NULL,
    mobil VARCHAR(15) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    pagina_web VARCHAR(100) DEFAULT NULL,
    cif_nif VARCHAR(20) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    actualitzat TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ensureSchema(mysqli $conn): void {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'Empresa' 
          AND COLUMN_NAME = 'logo_path'
    ");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ((int)$count === 0) {
            $conn->query("ALTER TABLE Empresa ADD COLUMN logo_path VARCHAR(255) DEFAULT NULL AFTER cif_nif");
        }
    }
}
ensureSchema($conn);

function truncateText(string $value, int $maxLength): string {
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function sanitizeText(?string $value, int $maxLength): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/\s+/u', ' ', $value);
    return truncateText($value, $maxLength);
}

function sanitizePhone(?string $value, int $maxLength): string {
    $value = preg_replace('/[^\d+]/', '', (string)$value);
    return truncateText($value, $maxLength);
}

function getEmpresa(mysqli $conn): ?array {
    $result = $conn->query("SELECT * FROM Empresa ORDER BY id ASC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

$empresa = getEmpresa($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $nom = sanitizeText($_POST['nom'] ?? '', 100);
    $adresa = sanitizeText($_POST['adresa'] ?? '', 255);
    $poblacio = sanitizeText($_POST['poblacio'] ?? '', 100);
    $provincia = sanitizeText($_POST['provincia'] ?? '', 100);
    $codi_postal = sanitizeText($_POST['codi_postal'] ?? '', 10);
    $telefon = sanitizePhone($_POST['telefon'] ?? '', 15);
    $mobil = sanitizePhone($_POST['mobil'] ?? '', 15);

    $email = trim((string)($_POST['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email no valid.';
    }
    $email = truncateText($email, 100);

    $pagina_web = trim((string)($_POST['pagina_web'] ?? ''));
    if ($pagina_web !== '' && !preg_match('#^https?://#i', $pagina_web)) {
        $pagina_web = 'http://' . $pagina_web;
    }
    if ($pagina_web !== '' && !filter_var($pagina_web, FILTER_VALIDATE_URL)) {
        $errors[] = 'URL de la pagina web no valida.';
    }
    $pagina_web = truncateText($pagina_web, 100);

    $cif_nif = sanitizeText($_POST['cif_nif'] ?? '', 20);

    $logoPath = $empresa['logo_path'] ?? null;
    $uploadedLogo = false;

    if (!empty($_FILES['logo']['name'])) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error en pujar el logo.';
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'El logo no pot superar els 2MB.';
        } else {
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $_FILES['logo']['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($_FILES['logo']['tmp_name']);
            } else {
                $mime = $_FILES['logo']['type'] ?? '';
            }
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Format de logo no suportat. Usa JPG, PNG o WebP.';
            } else {
                $projectRoot = realpath(__DIR__ . '/..');
                if ($projectRoot === false) {
                    $projectRoot = dirname(__DIR__);
                }
                $uploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'logo';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                foreach (glob($uploadDir . DIRECTORY_SEPARATOR . 'logo.*') as $existing) {
                    @unlink($existing);
                }
                $destination = $uploadDir . DIRECTORY_SEPARATOR . 'logo.' . $allowed[$mime];
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
                    $logoPath = 'logo/' . basename($destination);
                    $uploadedLogo = true;
                } else {
                    $errors[] = 'No s\'ha pogut pujar el logo.';
                }
            }
        }
    }

    if (empty($errors)) {
        if ($empresa) {
            $stmt = $conn->prepare("UPDATE Empresa SET nom=?, adresa=?, poblacio=?, provincia=?, codi_postal=?, telefon=?, mobil=?, email=?, pagina_web=?, cif_nif=?, logo_path=? WHERE id=?");
            $stmt->bind_param(
                'sssssssssssi',
                $nom,
                $adresa,
                $poblacio,
                $provincia,
                $codi_postal,
                $telefon,
                $mobil,
                $email,
                $pagina_web,
                $cif_nif,
                $logoPath,
                $empresa['id']
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO Empresa (nom, adresa, poblacio, provincia, codi_postal, telefon, mobil, email, pagina_web, cif_nif, logo_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                'sssssssssss',
                $nom,
                $adresa,
                $poblacio,
                $provincia,
                $codi_postal,
                $telefon,
                $mobil,
                $email,
                $pagina_web,
                $cif_nif,
                $logoPath
            );
            $stmt->execute();
            $stmt->close();
        }

        $successMessage = $uploadedLogo ? "Dades i logo actualitzats." : "Dades guardades correctament.";
        $empresa = getEmpresa($conn);
    } else {
        $errorMessage = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Dades de l'Empresa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #0f172a;
        }
        .page {
            max-width: 960px;
            margin: 0 auto;
        }
        h1 {
            margin: 0 0 20px;
            font-size: 1.8rem;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12);
        }
        form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px 20px;
        }
        label {
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }
        input[type="text"],
        input[type="email"],
        input[type="file"] {
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 0.95rem;
            background: #f9fafb;
        }
        input[type="file"] {
            padding: 8px;
        }
        .full {
            grid-column: 1 / -1;
        }
        .alert {
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 500;
        }
        .alert.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #34d399;
        }
        .alert.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #f87171;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        button, .link-button {
            border: none;
            border-radius: 14px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        button {
            background: #ef4444;
            color: #fff;
        }
        .link-button {
            background: #111827;
            color: #fff;
        }
        .logo-preview {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 10px;
        }
        .logo-preview img {
            max-height: 120px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #fff;
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <h1>Dades de l'Empresa</h1>
            <?php if ($successMessage): ?>
                <div class="alert success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div>
                    <label for="nom">Nom</label>
                    <input type="text" name="nom" id="nom" value="<?php echo htmlspecialchars($empresa['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="adresa">Adresa</label>
                    <input type="text" name="adresa" id="adresa" value="<?php echo htmlspecialchars($empresa['adresa'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="poblacio">Poblacio</label>
                    <input type="text" name="poblacio" id="poblacio" value="<?php echo htmlspecialchars($empresa['poblacio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="provincia">Provincia</label>
                    <input type="text" name="provincia" id="provincia" value="<?php echo htmlspecialchars($empresa['provincia'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="codi_postal">Codi Postal</label>
                    <input type="text" name="codi_postal" id="codi_postal" value="<?php echo htmlspecialchars($empresa['codi_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="telefon">Telefon</label>
                    <input type="text" name="telefon" id="telefon" value="<?php echo htmlspecialchars($empresa['telefon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="mobil">Mobil</label>
                    <input type="text" name="mobil" id="mobil" value="<?php echo htmlspecialchars($empresa['mobil'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($empresa['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="pagina_web">Pagina Web</label>
                    <input type="text" name="pagina_web" id="pagina_web" value="<?php echo htmlspecialchars($empresa['pagina_web'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div>
                    <label for="cif_nif">CIF / NIF</label>
                    <input type="text" name="cif_nif" id="cif_nif" value="<?php echo htmlspecialchars($empresa['cif_nif'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="full">
                    <label for="logo">Logo (JPG/PNG/WebP, max. 2MB)</label>
                    <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp">
                    <?php
                        $hasLogo = !empty($empresa['logo_path']) && file_exists(__DIR__ . '/../' . $empresa['logo_path']);
                        if ($hasLogo):
                            $logoUrl = '../' . ltrim($empresa['logo_path'], '/');
                    ?>
                        <div class="logo-preview">
                            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
                            <span>Actual: <?php echo htmlspecialchars(basename($empresa['logo_path']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="actions full">
                    <button type="submit">Guardar</button>
                    <?php if ($hasLogo): ?>
                        <a class="link-button" href="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Veure logo</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
