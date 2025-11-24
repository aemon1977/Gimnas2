<?php
require_once __DIR__ . '/../config/bootstrap.php';

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


$conn = getDbConnection();

$activitats = [];
$resAct = $conn->query("SELECT id, nom FROM activitats ORDER BY nom ASC");
if ($resAct && $resAct->num_rows > 0) {
    while ($row = $resAct->fetch_assoc()) {
        $activitats[] = $row;
    }
}

// AJAX preview
if (isset($_POST['activitat_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['generate_excel'])) {
    $activitat_id = (int) $_POST['activitat_id'];
    $stmtAct = $conn->prepare("SELECT nom FROM activitats WHERE id = ?");
    $stmtAct->bind_param("i", $activitat_id);
    $stmtAct->execute();
    $resNom = $stmtAct->get_result();
    $activitat = $resNom->fetch_assoc()['nom'] ?? '';
    $stmtAct->close();

    $socis = [];
    if ($activitat !== '') {
        $stmt = $conn->prepare("SELECT Nom FROM socis WHERE FIND_IN_SET(?, Activitats) ORDER BY Nom ASC");
        $stmt->bind_param("s", $activitat);
        $stmt->execute();
        $resSocis = $stmt->get_result();
        while ($row = $resSocis->fetch_assoc()) {
            $socis[] = $row['Nom'];
        }
        $stmt->close();
    }

    echo "<h2>Socis que participen en: " . htmlspecialchars($activitat) . "</h2>";
    echo "<table border='1'>
            <tr><th>Nom</th></tr>";
    if (!empty($socis)) {
        foreach ($socis as $nom) {
            echo "<tr><td>" . htmlspecialchars($nom) . "</td></tr>";
        }
    } else {
        echo "<tr><td>No s'han trobat socis per a l'activitat seleccionada.</td></tr>";
    }
    echo "</table>";
    exit;
}

// Export Excel
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['generate_excel']) && !empty($_POST['activitat'])) {
    $activitat_id = (int) $_POST['activitat'];
    $stmtAct = $conn->prepare("SELECT nom FROM activitats WHERE id = ?");
    $stmtAct->bind_param("i", $activitat_id);
    $stmtAct->execute();
    $resNom = $stmtAct->get_result();
    $activitat = $resNom->fetch_assoc()['nom'] ?? '';
    $stmtAct->close();

    $socis = [];
    if ($activitat !== '') {
        $stmt = $conn->prepare("SELECT Nom FROM socis WHERE FIND_IN_SET(?, Activitats) ORDER BY Nom ASC");
        $stmt->bind_param("s", $activitat);
        $stmt->execute();
        $resSocis = $stmt->get_result();
        while ($row = $resSocis->fetch_assoc()) {
            $socis[] = $row['Nom'];
        }
        $stmt->close();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setTitle(substr($activitat, 0, 31) ?: 'Activitat');
    $sheet->setCellValue('A1', "Socis que participen en: $activitat");
    $sheet->mergeCells('A1:B1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 3;
    if (!empty($socis)) {
        foreach ($socis as $nom) {
            $sheet->setCellValue("A$row", $nom);
            $row++;
        }
    } else {
        $sheet->setCellValue("A$row", "No s'han trobat socis per a l'activitat seleccionada.");
        $row++;
    }

    $sheet->getColumnDimension('A')->setWidth(40);
    $sheet->getStyle("A1:B$row")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ]);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="socis_activitat_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $activitat) . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Excel per activitat</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg: #f7f8fc;
        --card: #ffffff;
        --accent: #e63946;
        --text: #1f2937;
        --muted: #6b7280;
        --border: #e5e7eb;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Poppins', Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 24px 16px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        padding: 18px;
        width: 100%;
        max-width: 620px;
    }
    h1 { margin: 0 0 10px; font-size: 22px; }
    .muted { margin: 0 0 12px; color: var(--muted); }
    label { display: block; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #f9fafb;
        font-size: 14px;
        margin-bottom: 12px;
    }
    .btn {
        padding: 11px 16px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        background: var(--accent);
        color: #fff;
        width: 100%;
        box-shadow: 0 10px 20px rgba(230,57,70,0.18);
        margin-bottom: 10px;
    }
    #result { margin-top: 10px; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid var(--border); padding: 8px; text-align: left; }
    th { background: #f9fafb; color: var(--muted); }
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#activitat').change(function() {
                var activitat_id = $(this).val();
                if (activitat_id) {
                    $.post("", { activitat_id: activitat_id }, function(response) {
                        $('#result').html(response);
                    });
                } else {
                    $('#result').html("");
                }
            });
        });
    </script>
</head>
<body>
    <div class="card">
        <h1>Generar Excel per activitat</h1>
        <p class="muted">Selecciona una activitat per previsualitzar els socis i exportar-los a Excel.</p>
        <form method="post">
            <label for="activitat">Activitat</label>
            <select name="activitat" id="activitat" required>
                <option value="">Seleccioneu una activitat</option>
                <?php foreach ($activitats as $act): ?>
                    <option value="<?php echo (int) $act['id']; ?>"><?php echo htmlspecialchars($act['nom']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="generate_excel" value="1" class="btn">Generar Excel</button>
        </form>
        <div id="result"></div>
    </div>
</body>
</html>
