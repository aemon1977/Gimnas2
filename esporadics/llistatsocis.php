<?php
require_once __DIR__ . '/../config/bootstrap.php';

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Conexi&oacute; a la base de dades

$conn = getDbConnection();

// Genera l'Excel segons el tipus seleccionat
function generarExcel($tipus, $esporadics) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $titol = $tipus === 'actius' ? 'Espor&agrave;dics Actius' : 'Espor&agrave;dics Inactius';
    $sheet->setTitle($titol);
    $sheet->setCellValue('A1', $titol);

    $encap = array('ID', 'DNI', 'Nom', 'Carrer', 'Codipostal', 'Poblacio', 'Provincia', 'email', 'Data_naixement', 'Telefon1', 'Telefon2', 'Telefon3', 'Numero_Conta', 'Sepa', 'Activitats', 'Quantitat', 'Alta', 'Baixa', 'Facial', 'Data_Inici_activitat', 'Usuari', 'Descompte', 'Total', 'Temps_descompte', 'Extres', 'En_ma');

    $col = 'A';
    foreach ($encap as $t) {
        $sheet->setCellValue($col . '2', $t);
        $col++;
    }

    $fila = 3;
    foreach ($esporadics as $esporadic) {
        $col = 'A';
        foreach ($encap as $camp) {
            $sheet->setCellValue($col . $fila, $esporadic[$camp] ?? '');
            $col++;
        }
        $fila++;
    }

    foreach (range('A', 'V') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $sheet->getStyle("A2:V$fila")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ]);
    $sheet->getStyle("A2:V$fila")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A2:V$fila")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $titol . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Si s'envia el formulari, obtenim el tipus i exportem
if (isset($_POST['tipus']) && in_array($_POST['tipus'], ['actius', 'inactius'], true)) {
    $tipus = $_POST['tipus'];
    if ($tipus === 'actius') {
        $sql = "SELECT * FROM esporadics WHERE Activitats IS NOT NULL AND Activitats != ''";
    } else {
        $sql = "SELECT * FROM esporadics WHERE Activitats IS NULL OR Activitats = ''";
    }

    $result = $conn->query($sql);
    $esporadics = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $esporadics[] = $row;
        }
    }
    generarExcel($tipus, $esporadics);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Llistes d'espor&agrave;dics</title>
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
        padding: 20px;
        width: 100%;
        max-width: 540px;
    }
    h1 {
        margin: 0 0 10px;
        font-size: 22px;
    }
    .muted {
        margin: 0 0 16px;
        color: var(--muted);
    }
    label {
        font-weight: 600;
        color: var(--muted);
        display: block;
        margin-bottom: 6px;
    }
    select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #f9fafb;
        font-size: 14px;
        margin-bottom: 14px;
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
    }
    </style>
</head>
<body>
    <div class="card">
        <h1>Llistes d'espor&agrave;dics</h1>
        <p class="muted">Exporta a Excel els espor&agrave;dics actius o inactius.</p>
        <form method="post">
            <label for="tipus">Seleccioneu el tipus de llista:</label>
            <select name="tipus" id="tipus" required>
                <option value="">Seleccioneu un tipus</option>
                <option value="actius">Espor&agrave;dics Actius</option>
                <option value="inactius">Espor&agrave;dics Inactius</option>
            </select>
            <button type="submit" class="btn">Generar Excel</button>
        </form>
    </div>
</body>
</html>
