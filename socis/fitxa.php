<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Conexión a la base de datos

// Crear conexión
$conn = getDbConnection();

// Verificar la conexión

$socios_encontrados = [];

// Buscar socio si se envía el formulario de búsqueda
if (isset($_POST['buscar'])) {
    $criterio_busqueda = $_POST['criterio_busqueda'];

    // SQL para buscar por DNI o nombre
    $sql_buscar = "SELECT DNI, Nom FROM socis WHERE DNI LIKE '%$criterio_busqueda%' OR Nom LIKE '%$criterio_busqueda%'";
    $result_buscar = $conn->query($sql_buscar);
    
    if ($result_buscar->num_rows > 0) {
        while ($row = $result_buscar->fetch_assoc()) {
            $socios_encontrados[] = $row; // Guardar resultados
        }
    } else {
        echo "No s'han trobat socis.";
    }
}

// Obtener todos los socios para mostrar en la tabla
$sql_todos_socios = "SELECT DNI, Nom FROM socis";
$result_todos_socios = $conn->query($sql_todos_socios);

?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Fitxa de Socis</title>
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
    }
    .page {
        max-width: 1100px;
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
    h1 { margin: 0 0 6px; font-size: 22px; }
    .muted { margin: 0; color: var(--muted); }
    .form-row {
        display: flex;
        gap: 12px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    .form-row input[type="text"] {
        flex: 1;
        min-width: 240px;
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
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-secondary { background: #e5e7eb; color: #111827; }
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
        <h1>Fitxa de Socis</h1>
        <p class="muted">Cerca per DNI o nom i obre la fitxa.</p>
        <form action="fitxa.php" method="POST">
            <div class="form-row">
                <input type="text" name="criterio_busqueda" placeholder="Cerca per DNI o nom" required>
                <button type="submit" name="buscar" class="btn btn-primary">Cercar</button>
            </div>
        </form>
    </div>

    <?php if (!empty($socios_encontrados)): ?>
    <div class="card">
        <h2 style="margin:0 0 8px;font-size:18px;">Resultats de la cerca</h2>
        <form action="ver_ficha.php" method="POST">
            <?php foreach ($socios_encontrados as $socio): ?>
                <div style="margin-bottom:8px;">
                    <input type="radio" name="DNI_seleccionado" value="<?php echo $socio['DNI']; ?>" required>
                    <label><?php echo $socio['DNI'] . " - " . $socio['Nom']; ?></label>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="ver_ficha" class="btn btn-secondary">Veure fitxa</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Nom</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_todos_socios->num_rows > 0): ?>
                    <?php while ($socio = $result_todos_socios->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $socio['DNI']; ?></td>
                            <td>
                                <a href="ver_ficha.php?DNI=<?php echo $socio['DNI']; ?>" class="nombre-socio">
                                    <?php echo $socio['Nom']; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No hi ha socis disponibles.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
