<?php
session_start();
require_once __DIR__ . '/../config/bootstrap.php';

$conn = getDbConnection();

$missatge = '';
$error = '';
$undoStack = $_SESSION['activitats_undo'] ?? [];
$undoDisabled = empty($undoStack);

function pushUndo(array $entry) {
    $stack = $_SESSION['activitats_undo'] ?? [];
    $stack[] = $entry;
    if (count($stack) > 20) {
        array_shift($stack);
    }
    $_SESSION['activitats_undo'] = $stack;
}

function popUndo() {
    if (empty($_SESSION['activitats_undo'])) {
        return null;
    }
    $entry = array_pop($_SESSION['activitats_undo']);
    return $entry;
}

// Desfer última acció
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo'])) {
    $action = popUndo();
    if ($action) {
        if ($action['type'] === 'insert') {
            $stmtUndo = $conn->prepare("DELETE FROM activitats WHERE id = ?");
            $stmtUndo->bind_param("i", $action['id']);
            if ($stmtUndo->execute()) {
                $missatge = "S'ha desfet l'addició de l'activitat.";
            } else {
                $error = "No s'ha pogut desfer l'addició.";
            }
            $stmtUndo->close();
        } elseif ($action['type'] === 'delete') {
            $stmtUndo = $conn->prepare("INSERT INTO activitats (id, nom) VALUES (?, ?)");
            $stmtUndo->bind_param("is", $action['id'], $action['nom']);
            if ($stmtUndo->execute()) {
                $missatge = "S'ha restaurat l'activitat eliminada.";
            } else {
                $error = "No s'ha pogut restaurar l'activitat.";
            }
            $stmtUndo->close();
        }
    } else {
        $error = "No hi ha accions per desfer.";
    }
    $undoStack = $_SESSION['activitats_undo'] ?? [];
    $undoDisabled = empty($undoStack);
}

// Eliminar activitat
if (isset($_GET['eliminar_id'])) {
    $eliminar_id = (int) $_GET['eliminar_id'];
    $nomAnterior = null;
    $stmtSel = $conn->prepare("SELECT nom FROM activitats WHERE id = ?");
    $stmtSel->bind_param("i", $eliminar_id);
    if ($stmtSel->execute()) {
        $stmtSel->bind_result($nomAnterior);
        $stmtSel->fetch();
    }
    $stmtSel->close();

    $stmtDel = $conn->prepare("DELETE FROM activitats WHERE id = ?");
    $stmtDel->bind_param("i", $eliminar_id);
    if ($stmtDel->execute()) {
        $missatge = "Activitat eliminada correctament.";
        if ($nomAnterior !== null) {
            pushUndo(['type' => 'delete', 'id' => $eliminar_id, 'nom' => $nomAnterior]);
            $undoStack = $_SESSION['activitats_undo'] ?? [];
            $undoDisabled = empty($undoStack);
        }
    } else {
        $error = "Error en eliminar l'activitat.";
    }
    $stmtDel->close();
}

// Afegir activitat
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['nom'])) {
    $nom = trim($_POST['nom']);
    if ($nom !== '') {
        $stmtIns = $conn->prepare("INSERT INTO activitats (nom) VALUES (?)");
        $stmtIns->bind_param("s", $nom);
        if ($stmtIns->execute()) {
            $missatge = "Nova activitat afegida correctament.";
            $newId = $stmtIns->insert_id;
            pushUndo(['type' => 'insert', 'id' => $newId, 'nom' => $nom]);
            $undoStack = $_SESSION['activitats_undo'] ?? [];
            $undoDisabled = empty($undoStack);
        } else {
            $error = "Error en afegir l'activitat.";
        }
        $stmtIns->close();
    } else {
        $error = "El nom és obligatori.";
    }
}

// Llista d'activitats
$activitats = [];
$result = $conn->query("SELECT id, nom FROM activitats ORDER BY id ASC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $activitats[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Activitats</title>
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
    .page { width: 100%; max-width: 900px; }
    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 22px rgba(15,23,42,0.06);
        padding: 18px;
        margin-bottom: 16px;
    }
    h1 { margin: 0 0 10px; font-size: 22px; }
    h2 { margin: 0 0 8px; font-size: 18px; }
    .muted { color: var(--muted); margin: 0 0 12px; }
    label { display: block; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    input[type="text"] {
        width: 100%;
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
    .msg { margin: 0 0 10px; font-weight: 600; }
    .msg.ok { color: #10b981; }
    .msg.error { color: #ef4444; }
    a.action {
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
    }
    a.action:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Gestionar activitats</h1>
        <p class="muted">Afegeix o elimina activitats disponibles.</p>
        <?php if ($missatge): ?><p class="msg ok"><?php echo htmlspecialchars($missatge); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="msg error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form action="activitats.php" method="POST" style="margin-bottom:16px;">
            <button type="submit" name="undo" value="1" class="btn" <?php echo $undoDisabled ? 'disabled style="opacity:0.6;cursor:not-allowed;"' : ''; ?>>Desfer últim canvi</button>
        </form>
        <h2>Afegir activitat</h2>
        <form action="activitats.php" method="POST">
            <label for="nom">Nom de l'activitat</label>
            <input type="text" name="nom" id="nom" required>
            <div style="margin-top:12px;">
                <button type="submit" class="btn">Desa</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Llista d'activitats</h2>
        <?php if (!empty($activitats)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activitats as $act): ?>
                        <tr>
                            <td><?php echo (int) $act['id']; ?></td>
                            <td><?php echo htmlspecialchars($act['nom']); ?></td>
                            <td>
                                <a class="action" href="activitats.php?eliminar_id=<?php echo (int) $act['id']; ?>" onclick="return confirm('Estàs segur que vols eliminar aquesta activitat?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No hi ha activitats disponibles.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

<?php
$conn->close();
?>
