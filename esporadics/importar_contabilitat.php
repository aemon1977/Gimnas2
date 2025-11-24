<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Configuració de la base de dades

$conn = getDbConnection();

// Anys disponibles per filtrar
$years = [];
$result_years = $conn->query("SELECT DISTINCT YEAR(data) AS any FROM contabilitat_esporadics ORDER BY any DESC");
if ($result_years && $result_years->num_rows > 0) {
    while ($row = $result_years->fetch_assoc()) {
        $years[] = $row['any'];
    }
}

$selected_month = isset($_POST['month']) ? $_POST['month'] : '';
$selected_year  = isset($_POST['year']) ? $_POST['year'] : '';

// Consulta i filtres
$sql_display = "
    SELECT nom_soci, MONTH(data) AS mes, YEAR(data) AS any, SUM(quantitat) AS total
    FROM contabilitat_esporadics
";

$conditions = [];
$params = [];
$types = '';

if ($selected_month !== '') {
    $conditions[] = "MONTH(data) = ?";
    $params[] = $selected_month;
    $types .= 'i';
}
if ($selected_year !== '') {
    $conditions[] = "YEAR(data) = ?";
    $params[] = $selected_year;
    $types .= 'i';
}
if ($conditions) {
    $sql_display .= " WHERE " . implode(' AND ', $conditions);
}
$sql_display .= " GROUP BY nom_soci, mes, any";

$stmt_display = $conn->prepare($sql_display);
if ($params) {
    $stmt_display->bind_param($types, ...$params);
}
$stmt_display->execute();
$result_display = $stmt_display->get_result();

// Helpers
$months_ca = [1 => 'Gener','Febrer','Març','Abril','Maig','Juny','Juliol','Agost','Setembre','Octubre','Novembre','Desembre'];
$rows = [];
$total_global = 0;
if ($result_display && $result_display->num_rows > 0) {
    while ($row = $result_display->fetch_assoc()) {
        $rows[] = $row;
        $total_global += $row['total'];
    }
}

?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contabilitat esporàdics</title>
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
    .page {
        width: 100%;
        max-width: 1100px;
    }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        padding: 18px;
        margin-bottom: 16px;
    }
    h1 { margin: 0 0 8px; font-size: 22px; }
    .muted { margin: 0 0 14px; color: var(--muted); }
    .filters {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    label { display: block; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    select {
        min-width: 160px;
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
        background: var(--accent);
        color: #fff;
        box-shadow: 0 10px 20px rgba(230,57,70,0.18);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    th { background: #f9fafb; color: var(--muted); font-weight: 600; }
    tr:hover td { background: #fdf2f3; }
    .total {
        text-align: right;
        margin-top: 10px;
        font-weight: 600;
    }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Contabilitat esporàdics</h1>
        <p class="muted">Filtra per mes i any per veure els totals per soci.</p>
        <form method="post">
            <div class="filters">
                <div>
                    <label for="month">Mes</label>
                    <select name="month" id="month">
                        <option value="">Tots</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($selected_month == $i) ? 'selected' : ''; ?>>
                                <?php echo $months_ca[$i]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label for="year">Any</label>
                    <select name="year" id="year">
                        <option value="">Tots</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($selected_year == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn">Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <?php if (!empty($rows)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Mes</th>
                        <th>Any</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['nom_soci']); ?></td>
                            <td><?php echo htmlspecialchars($months_ca[$row['mes']] ?? $row['mes']); ?></td>
                            <td><?php echo htmlspecialchars($row['any']); ?></td>
                            <td><?php echo number_format($row['total'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Total: <?php echo number_format($total_global, 2, ',', '.'); ?></div>
        <?php else: ?>
            <p class="muted" style="margin:0;">No hi ha dades per mostrar.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
