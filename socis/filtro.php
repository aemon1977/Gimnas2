<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Conexión a la base de datos

$conn = getDbConnection();

// Inicializa las variables
$nom_cerca = '';
$order_by = 'data_modificacio'; // Orden por defecto
$order = 'DESC'; // Descendente por defecto

// Captura los valores de búsqueda y orden
if (isset($_GET['cerca'])) {
    $nom_cerca = $_GET['cerca'];
}
if (isset($_GET['order_by'])) {
    $order_by = $_GET['order_by'];
}
if (isset($_GET['order'])) {
    $order = $_GET['order'];
}

// Normaliza columnas y orden para evitar columnas no indexadas o inválidas
$allowedOrderBy = [
    'Nom','Activitats','data_modificacio'
];
if (!in_array($order_by, $allowedOrderBy, true)) {
    $order_by = 'data_modificacio';
}
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Construcción de la consulta (se limita a columnas necesarias y a 300 resultados)
$sql = "SELECT DNI, Nom, Activitats, data_modificacio
        FROM socis
        WHERE Nom LIKE ? OR Activitats LIKE ?
        ORDER BY data_modificacio DESC, $order_by $order
        LIMIT 300";
$stmt = $conn->prepare($sql);
$search_param = "%" . $conn->real_escape_string($nom_cerca) . "%";
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Cerca de socis</title>
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
    .card h1 {
        margin: 0 0 8px;
        font-size: 22px;
    }
    .muted { color: var(--muted); margin: 0; }
    .form-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .form-row input[type="text"] {
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #f9fafb;
        font-size: 14px;
        flex: 1;
        min-width: 240px;
    }
    .btn {
        padding: 11px 16px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
    }
    .btn-primary {
        background: var(--accent);
        color: #fff;
        box-shadow: 0 10px 20px rgba(230,57,70,0.18);
    }
    .btn-primary:hover { background: var(--accent-dark); }

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
        font-size: 14px;
    }
    th, td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    th {
        background: #f9fafb;
        font-weight: 600;
        color: var(--muted);
    }
    tr:hover td { background: #fdf2f3; }
    a { color: inherit; text-decoration: none; }
    a:hover { color: var(--accent-dark); }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Cerca de socis</h1>
        <p class="muted">Filtra per nom o activitat i ordena els resultats.</p>
        <form method="GET" action="">
            <div class="form-row">
                <input type="text" name="cerca" placeholder="Cerca per nom o activitat" value="<?php echo htmlspecialchars($nom_cerca); ?>">
                <button type="submit" class="btn btn-primary">Cercar</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th><a href="?order_by=Nom&order=<?php echo ($order_by == 'Nom' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>&cerca=<?php echo urlencode($nom_cerca); ?>">Nom</a></th>
                    <th><a href="?order_by=Activitats&order=<?php echo ($order_by == 'Activitats' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>&cerca=<?php echo urlencode($nom_cerca); ?>">Activitats</a></th>
                    <th><a href="?order_by=data_modificacio&order=<?php echo ($order_by == 'data_modificacio' && $order == 'ASC') ? 'DESC' : 'ASC'; ?>&cerca=<?php echo urlencode($nom_cerca); ?>">Data Modificació</a></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0) : ?>
                    <?php while ($soci = $result->fetch_assoc()) : ?>
                        <tr>
                            <td><a href="modificar1.php?DNI_seleccionat=<?php echo urlencode($soci['DNI']); ?>"><?php echo htmlspecialchars($soci['Nom']); ?></a></td>
                            <td><?php echo htmlspecialchars($soci['Activitats']); ?></td>
                            <td><?php echo htmlspecialchars($soci['data_modificacio']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No s'han trobat socis.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Cierra la conexión
$stmt->close();
$conn->close();
?>
</body>
</html>
