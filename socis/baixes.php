<?php
require_once __DIR__ . '/../config/bootstrap.php';

session_start();
function ensureBaixesLog(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS socis_baixes_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                soci_id INT NOT NULL,
                activitats TEXT,
                baixa_date DATE NOT NULL,
                reverted TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($sql);
}

function fetchSelectedSocis(mysqli $conn, array $ids): array {
    if (empty($ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("SELECT ID, DNI, Nom, IFNULL(Activitats, '') AS Activitats FROM socis WHERE ID IN ($placeholders) ORDER BY Nom ASC");
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function buildSortLink(string $column, string $currentSort, string $currentDir, string $searchTerm): string {
    $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'desc' : 'asc';
    $params = [];
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    $params['sort'] = $column;
    $params['dir'] = $nextDir;
    return 'baixes.php?' . http_build_query($params);
}

function sortIndicator(string $column, string $currentSort, string $currentDir): string {
    if ($column !== $currentSort) {
        return '';
    }
    return $currentDir === 'ASC' ? ' &uarr;' : ' &darr;';
}


$conn = getDbConnection();

ensureBaixesLog($conn);

$searchTerm = trim($_GET['q'] ?? '');
$statusMessage = "";
$statusType = "";

$allowedSort = [
    'DNI' => 'DNI',
    'Nom' => 'Nom',
    'Activitats' => 'Activitats'
];
$currentSort = $_GET['sort'] ?? 'Nom';
if (!array_key_exists($currentSort, $allowedSort)) {
    $currentSort = 'Nom';
}
$currentDir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$selectedIds = [];
if (!empty($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
    foreach ($_POST['selected_ids'] as $rawId) {
        if (is_numeric($rawId)) {
            $selectedIds[] = (int)$rawId;
        }
    }
    $selectedIds = array_values(array_unique($selectedIds));
}

$revertIds = [];
if (!empty($_POST['revert_ids']) && is_array($_POST['revert_ids'])) {
    foreach ($_POST['revert_ids'] as $rawId) {
        if (is_numeric($rawId)) {
            $revertIds[] = (int)$rawId;
        }
    }
    $revertIds = array_values(array_unique($revertIds));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'dar_baja') {
        if (empty($selectedIds)) {
            $statusMessage = "Selecciona almenys un soci per donar de baixa.";
            $statusType = "error";
        } else {
            $selectedRows = fetchSelectedSocis($conn, $selectedIds);
            if (empty($selectedRows)) {
                $statusMessage = "No s'han trobat els socis seleccionats.";
                $statusType = "error";
            } else {
                $activitiesById = [];
                foreach ($selectedRows as $row) {
                    $activitiesById[(int)$row['ID']] = $row['Activitats'] ?? '';
                }
                $today = date('Y-m-d');
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("UPDATE socis SET Activitats = NULL, Baixa = ? WHERE ID = ?");
                    if (!$stmt) {
                        throw new Exception("No s'ha pogut preparar l'actualització.");
                    }
                    $stmt->bind_param('si', $today, $currentId);

                    $logStmt = $conn->prepare("INSERT INTO socis_baixes_log (soci_id, activitats, baixa_date) VALUES (?, ?, ?)");
                    if (!$logStmt) {
                        throw new Exception("No s'ha pogut preparar el registre de baixes.");
                    }
                    $logStmt->bind_param('iss', $logSociId, $logAct, $logDate);
                    $logDate = $today;

                foreach ($selectedIds as $currentId) {
                    $logSociId = $currentId;
                    $logAct = $activitiesById[$currentId] ?? '';
                    if (!$logStmt->execute()) {
                        throw new Exception("Error registrant la baixa per al soci {$currentId}");
                        }
                        if (!$stmt->execute()) {
                            throw new Exception("Error actualitzant el soci amb ID {$currentId}");
                        }
                    }

                    $logStmt->close();
                    $stmt->close();
                    $conn->commit();
                    $_SESSION['last_baixes_data'] = $selectedRows;
                    $statusMessage = "S'han donat de baixa " . count($selectedIds) . " socis.";
                    $statusType = "success";
                    $selectedIds = [];
                } catch (Exception $e) {
                    $conn->rollback();
                    $statusMessage = "Error al donar de baixa: " . $e->getMessage();
                    $statusType = "error";
                }
            }
        }
    } elseif ($action === 'pdf') {
        $selectedRows = [];
        if (!empty($selectedIds)) {
            $selectedRows = fetchSelectedSocis($conn, $selectedIds);
        } elseif (!empty($_SESSION['last_baixes_data']) && is_array($_SESSION['last_baixes_data'])) {
            $selectedRows = $_SESSION['last_baixes_data'];
        }
        if (empty($selectedRows)) {
            $statusMessage = "Selecciona almenys un soci per generar el PDF o fes una baixa prèviament.";
            $statusType = "error";
        } else {
            require_once __DIR__ . '/../fpdf/fpdf.php';
            $pdf = new FPDF();
            $pdf->SetTitle("Llista de baixes");
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, "Llista de Baixes", 0, 1, 'C');
            $pdf->Ln(2);
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(0, 8, "Data: " . date('d/m/Y'), 0, 1, 'R');
            $pdf->Ln(5);

            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(40, 10, "DNI", 1, 0, 'C');
            $pdf->Cell(70, 10, "Nom", 1, 0, 'C');
            $pdf->Cell(80, 10, "Activitats", 1, 1, 'C');

            $pdf->SetFont('Arial', '', 10);
            foreach ($selectedRows as $row) {
                $dni = is_array($row) ? ($row['DNI'] ?? '') : '';
                $nom = is_array($row) ? ($row['Nom'] ?? '') : '';
                $act = is_array($row) ? ($row['Activitats'] ?? '') : '';
                $pdf->Cell(40, 8, $dni, 1);
                $pdf->Cell(70, 8, utf8_decode($nom), 1);
                $pdf->Cell(80, 8, utf8_decode($act), 1, 1);
            }

            $fileName = "baixes_" . date('Ymd_His') . ".pdf";
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            $pdf->Output('I', $fileName);
            exit;
        }
    } elseif ($action === 'desfer_baixa') {
        if (empty($revertIds)) {
            $statusMessage = "Selecciona almenys un registre a desfer.";
            $statusType = "error";
        } else {
            $placeholders = implode(',', array_fill(0, count($revertIds), '?'));
            $types = str_repeat('i', count($revertIds));
            $sql = "SELECT id, soci_id, activitats FROM socis_baixes_log
                    WHERE id IN ($placeholders) AND reverted = 0
                      AND baixa_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$revertIds);
                $stmt->execute();
                $result = $stmt->get_result();
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $stmt->close();

                if (empty($rows)) {
                    $statusMessage = "No hi ha registres vàlids per desfer.";
                    $statusType = "error";
                } else {
                    $conn->begin_transaction();
                    try {
                        $updateStmt = $conn->prepare("UPDATE socis SET Activitats = ?, Baixa = NULL WHERE ID = ?");
                        $flagStmt = $conn->prepare("UPDATE socis_baixes_log SET reverted = 1 WHERE id = ?");
                        if (!$updateStmt || !$flagStmt) {
                            throw new Exception("No s'han pogut preparar les consultes de reversió.");
                        }
                        $updateStmt->bind_param('si', $restoreAct, $restoreId);
                        $flagStmt->bind_param('i', $flagId);
                        foreach ($rows as $row) {
                            $restoreAct = $row['activitats'] ?? '';
                            $restoreId = (int)$row['soci_id'];
                            if (!$updateStmt->execute()) {
                                throw new Exception("Error reactivant el soci {$restoreId}");
                            }
                            $flagId = (int)$row['id'];
                            if (!$flagStmt->execute()) {
                                throw new Exception("No s'ha pogut marcar com revertit el registre {$flagId}");
                            }
                        }
                        $updateStmt->close();
                        $flagStmt->close();
                        $conn->commit();
                        $statusMessage = "S'han desfet " . count($rows) . " baixes.";
                        $statusType = "success";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $statusMessage = "Error en desfer: " . $e->getMessage();
                        $statusType = "error";
                    }
                }
            } else {
                $statusMessage = "No s'ha pogut preparar la consulta de reversió.";
                $statusType = "error";
            }
        }
    }
}

$selectedPreview = fetchSelectedSocis($conn, $selectedIds);

$socis = [];
$sql = "SELECT ID, DNI, Nom, IFNULL(Activitats, '') AS Activitats FROM socis WHERE Activitats IS NOT NULL AND Activitats != ''";
if ($searchTerm !== '') {
    $sql .= " AND (DNI LIKE ? OR Nom LIKE ?)";
}
$sql .= " ORDER BY {$allowedSort[$currentSort]} {$currentDir}";

if ($stmt = $conn->prepare($sql)) {
    if ($searchTerm !== '') {
        $like = '%' . $searchTerm . '%';
        $stmt->bind_param('ss', $like, $like);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $socis[] = $row;
    }
    $stmt->close();
}

$undoRows = [];
$undoSql = "SELECT l.id, l.soci_id, s.DNI, s.Nom, IFNULL(l.activitats, '') AS activitats, l.baixa_date
            FROM socis_baixes_log l
            INNER JOIN socis s ON s.ID = l.soci_id
            WHERE l.reverted = 0 AND l.baixa_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY l.baixa_date DESC";
if ($resultUndo = $conn->query($undoSql)) {
    while ($row = $resultUndo->fetch_assoc()) {
        $undoRows[] = $row;
    }
    $resultUndo->free();
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Gestió de Baixes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6fb;
            --card: #ffffff;
            --accent: #e63946;
            --accent-dark: #b02a35;
            --text: #1f2933;
            --muted: #6b7280;
            --border: #e5e7eb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .page {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }
        h1 { margin-bottom: 8px; }
        .muted { color: var(--muted); margin-bottom: 18px; }
        .card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 12px 26px rgba(15,23,42,0.07);
            padding: 18px;
            margin-bottom: 18px;
        }
        .status {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-weight: 500;
        }
        .status.success {
            background: #ecfdf5;
            border: 1px solid #34d399;
            color: #065f46;
        }
        .status.error {
            background: #fef2f2;
            border: 1px solid #f87171;
            color: #991b1b;
        }
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-form input[type="text"] {
            flex: 1;
            min-width: 260px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #f9fafb;
        }
        .btn {
            padding: 12px 18px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 18px;
        }
        .table-scroll {
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: auto;
            max-height: 420px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        th {
            text-align: left;
            background: #f9fafb;
            color: var(--muted);
            font-weight: 600;
        }
        tr:hover td { background: #fdf2f3; }
        .sortable {
            color: inherit;
            text-decoration: none;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }
        .selected-list {
            border: 1px solid var(--border);
            border-radius: 12px;
            max-height: 420px;
            overflow-y: auto;
        }
        .selected-list li {
            list-style: none;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .selected-list li:last-child { border-bottom: none; }
        .empty {
            padding: 40px;
            text-align: center;
            color: var(--muted);
        }
        @media (max-width: 992px) {
            .layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="page">
        <h1>Gestió de Baixes</h1>
        <p class="muted">Cerca socis actius, selecciona'ls per donar-los de baixa o genera un PDF. També pots desfer baixes recents.</p>

        <?php if ($statusMessage): ?>
            <div class="status <?php echo $statusType === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($statusMessage); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form class="search-form" method="get">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Cerca per nom o DNI">
                <button type="submit" class="btn btn-secondary">Cercar</button>
            </form>
        </div>

        <form class="card" method="post">
            <div class="layout">
                <div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th><a class="sortable" href="<?php echo htmlspecialchars(buildSortLink('DNI', $currentSort, $currentDir, $searchTerm)); ?>">DNI<?php echo sortIndicator('DNI', $currentSort, $currentDir); ?></a></th>
                                    <th><a class="sortable" href="<?php echo htmlspecialchars(buildSortLink('Nom', $currentSort, $currentDir, $searchTerm)); ?>">Nom<?php echo sortIndicator('Nom', $currentSort, $currentDir); ?></a></th>
                                    <th><a class="sortable" href="<?php echo htmlspecialchars(buildSortLink('Activitats', $currentSort, $currentDir, $searchTerm)); ?>">Activitats<?php echo sortIndicator('Activitats', $currentSort, $currentDir); ?></a></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($socis)): ?>
                                    <?php foreach ($socis as $soci): ?>
                                        <tr class="soci-row">
                                            <td>
                                                <input class="soci-checkbox" type="checkbox" name="selected_ids[]" value="<?php echo (int)$soci['ID']; ?>"
                                                    data-nom="<?php echo htmlspecialchars($soci['Nom'], ENT_QUOTES); ?>"
                                                    data-dni="<?php echo htmlspecialchars($soci['DNI'], ENT_QUOTES); ?>"
                                                    <?php echo in_array((int)$soci['ID'], $selectedIds, true) ? 'checked' : ''; ?>>
                                            </td>
                                            <td><?php echo htmlspecialchars($soci['DNI']); ?></td>
                                            <td><?php echo htmlspecialchars($soci['Nom']); ?></td>
                                            <td><?php echo htmlspecialchars($soci['Activitats']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty">No hi ha socis per mostrar.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="actions">
                        <button type="submit" name="action" value="dar_baja" class="btn btn-primary">Donar de baixa</button>
                        <button type="submit" name="action" value="pdf" class="btn btn-secondary">Generar PDF</button>
                    </div>
                </div>

                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <strong>Selecció (<span data-selected-count><?php echo count($selectedPreview); ?></span>)</strong>
                        <small class="muted">Clica una fila per afegir-la</small>
                    </div>
                    <ul class="selected-list">
                        <?php if (!empty($selectedPreview)): ?>
                            <?php foreach ($selectedPreview as $sel): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($sel['Nom']); ?></strong><br>
                                    <span style="color:var(--muted); font-size:12px;"><?php echo htmlspecialchars($sel['DNI']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="empty">Encara no hi ha socis seleccionats.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </form>

        <div class="card">
            <h2 style="margin:0 0 10px;">Desfer baixes (última setmana)</h2>
            <?php if (!empty($undoRows)): ?>
                <form method="post">
                    <div class="table-scroll" style="max-height:260px;">
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>DNI</th>
                                    <th>Nom</th>
                                    <th>Activitats</th>
                                    <th>Data Baixa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($undoRows as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" name="revert_ids[]" value="<?php echo (int)$row['id']; ?>"></td>
                                        <td><?php echo htmlspecialchars($row['DNI']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Nom']); ?></td>
                                        <td><?php echo htmlspecialchars($row['activitats']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['baixa_date']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="actions">
                        <button type="submit" name="action" value="desfer_baixa" class="btn btn-secondary">Desfer selecció</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty">No hi ha baixes recents per desfer.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.soci-checkbox');
        const selectedList = document.querySelector('.selected-list');
        const countTarget = document.querySelector('[data-selected-count]');

        function renderSelection() {
            const fragment = document.createDocumentFragment();
            let total = 0;
            checkboxes.forEach(function (cb) {
                if (cb.checked) {
                    total += 1;
                    const li = document.createElement('li');
                    const name = document.createElement('strong');
                    name.textContent = cb.dataset.nom || '';
                    const br = document.createElement('br');
                    const extra = document.createElement('span');
                    extra.style.color = 'var(--muted)';
                    extra.style.fontSize = '12px';
                    extra.textContent = cb.dataset.dni || '';
                    li.appendChild(name);
                    li.appendChild(br);
                    li.appendChild(extra);
                    fragment.appendChild(li);
                }
            });
            selectedList.innerHTML = '';
            if (!total) {
                const li = document.createElement('li');
                li.className = 'empty';
                li.textContent = 'Encara no hi ha socis seleccionats.';
                selectedList.appendChild(li);
            } else {
                selectedList.appendChild(fragment);
            }
            if (countTarget) {
                countTarget.textContent = total;
            }
        }

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', renderSelection);
        });

        document.querySelectorAll('.soci-row').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.tagName === 'INPUT' || event.target.closest('label')) {
                    return;
                }
                const box = row.querySelector('.soci-checkbox');
                if (box) {
                    box.checked = !box.checked;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        renderSelection();
    });
    </script>
</body>
</html>
