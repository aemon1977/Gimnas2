<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

$conn = getDbConnection();

$nom_cerca = isset($_POST['nom']) ? $_POST['nom'] : '';
$soci_data = [];

if ($nom_cerca != '') {
    $sql = "SELECT DNI, Nom, Carrer, Codipostal, Poblacio, Provincia, email, Data_naixement, Telefon1, Telefon2, Telefon3, Numero_Conta, Sepa, Activitats, Quantitat, Alta, Baixa, Facial, Data_Inici_activitat, Usuari, Descompte, Total, Temps_descompte, Extres, En_ma FROM esporadics WHERE Nom LIKE '%$nom_cerca%' OR DNI LIKE '%$nom_cerca%'";
} else {
    $sql = "SELECT DNI, Nom, Carrer, Codipostal, Poblacio, Provincia, email, Data_naixement, Telefon1, Telefon2, Telefon3, Numero_Conta, Sepa, Activitats, Quantitat, Alta, Baixa, Facial, Data_Inici_activitat, Usuari, Descompte, Total, Temps_descompte, Extres, En_ma FROM esporadics";
}
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $soci_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Filtrar Esporàdics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg: #f7f8fc;
        --card: #ffffff;
        --accent: #e63946;
        --accent-dark: #c92c3c;
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
    }
    .page {
        max-width: 1200px;
        margin: 24px auto;
        padding: 0 18px 36px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        padding: 18px 18px 22px;
        margin-bottom: 16px;
    }
    h1 { margin: 0 0 8px; font-size: 22px; }
    .muted { color: var(--muted); margin: 0 0 14px; }
    .form-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 10px;
        align-items: center;
    }
    .form-row input[type="text"] {
        flex: 1;
        min-width: 260px;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #f9fafb;
        font-size: 14px;
    }
    .btn {
        padding: 11px 16px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
    }
    .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 10px 20px rgba(230,57,70,0.18); }

    .table-card {
        overflow: hidden;
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        background: var(--card);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    th {
        background: #f9fafb;
        font-weight: 600;
        color: var(--muted);
    }
    tr:hover td { background: #fdf2f3; }
    a { color: inherit; text-decoration: none; }
    a:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Filtrar Esporàdics</h1>
        <p class="muted">Cerca per nom o DNI i obre la fitxa.</p>
        <form method="POST" action="filtro.php">
            <div class="form-row">
                <input type="text" name="nom" placeholder="Nom o DNI" value="<?php echo htmlspecialchars($nom_cerca); ?>">
                <button type="submit" class="btn btn-primary">Cercar</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>DNI</th>
                    <th>Telefon1</th>
                    <th>Activitats</th>
                    <th>Data Baixa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($soci_data) > 0): ?>
                    <?php foreach ($soci_data as $soci): ?>
                        <tr>
                            <td><a href="modificar1.php?DNI_seleccionat=<?php echo htmlspecialchars($soci['DNI']); ?>"><?php echo htmlspecialchars($soci['Nom']); ?></a></td>
                            <td><?php echo htmlspecialchars($soci['DNI']); ?></td>
                            <td><?php echo htmlspecialchars($soci['Telefon1']); ?></td>
                            <td><?php echo htmlspecialchars($soci['Activitats']); ?></td>
                            <td><?php echo htmlspecialchars($soci['Baixa']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No s'han trobat esporàdics.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $conn->close(); ?>
</body>
</html>
