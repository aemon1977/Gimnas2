<?php
require_once __DIR__ . '/../config/bootstrap.php';

require('fpdf/fpdf.php');

// Connexió a la base de dades

$conn = getDbConnection();

$activitat_seleccionada = null;
$socis = [];
$missatge = '';
$error = '';

// Llistat d'activitats
$activitats = [];
$result_activitats = $conn->query("SELECT id, nom FROM activitats ORDER BY nom ASC");
if ($result_activitats && $result_activitats->num_rows > 0) {
    while ($row = $result_activitats->fetch_assoc()) {
        $activitats[] = $row;
    }
}

// Si s'envia el formulari
if (isset($_POST['actividad']) && $_POST['actividad'] !== '') {
    $activitat_seleccionada = $_POST['actividad'];
    $stmt = $conn->prepare("SELECT Nom FROM socis WHERE FIND_IN_SET(?, Activitats) ORDER BY Nom ASC");
    $stmt->bind_param("s", $activitat_seleccionada);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $socis[] = $row['Nom'];
    }
    $stmt->close();

    if (!empty($socis)) {
        // Generar PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode($activitat_seleccionada), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);

        $cell_height = 6;
        $first_cell_width = 50;
        $subsequent_cell_width = 5;
        $margin = 10;
        $total_rows = 40;
        $total_columns = 26;

        $pdf->SetFillColor(255, 255, 255);

        for ($row_index = 0; $row_index < $total_rows; $row_index++) {
            $pdf->Rect($margin, 40 + $row_index * $cell_height, $first_cell_width, $cell_height, 'DF');
            for ($col = 1; $col < $total_columns; $col++) {
                $pdf->Rect($margin + $first_cell_width + ($col - 1) * $subsequent_cell_width, 40 + $row_index * $cell_height, $subsequent_cell_width, $cell_height, 'DF');
            }
            if ($row_index < count($socis)) {
                $pdf->Text($margin + 1, 40 + $row_index * $cell_height + 4, utf8_decode($socis[$row_index]));
            }
        }

        $pdf->Output('D', 'socis_activitat_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $activitat_seleccionada) . '.pdf');
        exit;
    } else {
        $missatge = "No hi ha socis associats a aquesta activitat.";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = "Has de seleccionar una activitat.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Llistat per activitat</title>
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
    }
    .msg { margin: 0 0 12px; font-weight: 600; }
    .msg.ok { color: #10b981; }
    .msg.error { color: #ef4444; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Llistat per activitat</h1>
        <p class="muted">Selecciona una activitat i descarrega el PDF amb els socis.</p>
        <?php if ($missatge): ?><p class="msg ok"><?php echo htmlspecialchars($missatge); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="msg error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form action="llistat.php" method="POST">
            <label for="actividad">Activitat</label>
            <select name="actividad" id="actividad" required>
                <option value="">Selecciona una activitat</option>
                <?php foreach ($activitats as $activitat): ?>
                    <option value="<?php echo htmlspecialchars($activitat['nom']); ?>" <?php echo ($activitat_seleccionada === $activitat['nom']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($activitat['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Generar PDF</button>
        </form>
    </div>
</body>
</html>
