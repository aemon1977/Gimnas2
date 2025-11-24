<?php
require_once __DIR__ . '/../config/bootstrap.php';

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Conectar a la base de dades

// Crear la connexió
$conn = getDbConnection();

// Verificar la connexió

// Funció per generar Excel
function generarExcel($tipus, $socis) {
    // Crear un nou document d'Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Títol de la fulla d'Excel segons el tipus
    $titol = $tipus == 'actius' ? 'Socis Actius' : 'Socis Inactius';
    $sheet->setTitle($titol);

    // Col·locar el títol de la fulla
    $sheet->setCellValue('A1', $titol);

    // Títols de les columnes (sense incloure 'Foto')
    $encapçalaments = array('ID', 'DNI', 'Nom', 'Carrer', 'Codipostal', 'Poblacio', 'Provincia', 'email', 'Data_naixement', 'Telefon1', 'Telefon2', 'Telefon3', 'Numero_Conta', 'Sepa', 'Activitats', 'Quantitat', 'Alta', 'Baixa', 'Facial', 'Data_Inici_activitat', 'Usuari', 'Descompte', 'Total', 'Temps_descompte', 'Extres', 'En_ma');

    // Establir els encapçalaments a la fila 2
    $col = 'A';
    foreach ($encapçalaments as $encapçament) {
        $sheet->setCellValue($col . '2', $encapçament);
        $col++;
    }

    // Omplir les dades dels socis
    $fila = 3;
    foreach ($socis as $soci) {
        $col = 'A';
        foreach ($encapçalaments as $camp) {
            // Excloure el camp 'Foto'
            if ($camp !== 'Foto') {
                $sheet->setCellValue($col . $fila, $soci[$camp]);
                $col++;
            }
        }
        $fila++;
    }

    // Ajustar l'ample de les columnes automàticament
    foreach (range('A', 'V') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Aplicar estils a la fulla d'Excel (bordes i alineació)
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

    // Establir els encapçalaments per a la descàrrega de l'arxiu
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $titol . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Crear l'arxiu d'Excel i enviar-ho al navegador
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Obtenir la llista de socis "actius" (amb activitats)
if (isset($_POST['tipus'])) {
    $tipus = $_POST['tipus'];
    if ($tipus == 'actius') {
        $sql = "SELECT * FROM socis WHERE Activitats IS NOT NULL AND Activitats != ''";
    } elseif ($tipus == 'inactius') {
        $sql = "SELECT * FROM socis WHERE Activitats IS NULL OR Activitats = ''";
    }

    $result = $conn->query($sql);
    $socis = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $socis[] = $row;
        }
    }

    generarExcel($tipus, $socis);
}

// Tancar la connexió
$conn->close();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Generar Llistes de Socis</title>
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
        padding: 32px 12px;
    }
    .card {
        width: 100%;
        max-width: 480px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        padding: 18px 18px 24px;
    }
    h1 {
        margin: 0 0 8px;
        font-size: 22px;
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
    }
    </style>
</head>
<body>
    <div class="card">
        <h1>Seleccioneu el tipus de llista</h1>
        <p style="margin:0 0 14px; color: var(--muted);">Exporta els socis actius o inactius a Excel.</p>
        <form method="post" action="">
            <label for="tipus">Tipus de llista</label>
            <select name="tipus" id="tipus" required>
                <option value="">Seleccioneu un tipus</option>
                <option value="actius">Socis Actius</option>
                <option value="inactius">Socis Inactius</option>
            </select>
            <button type="submit" class="btn">Generar Excel</button>
        </form>
    </div>
</body>
</html>
