<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

$conn = getDbConnection();

// Assegurar Foto com a LONGBLOB
$result = $conn->query("SHOW COLUMNS FROM esporadics LIKE 'Foto'");
if ($result) {
    $column = $result->fetch_assoc();
    if ($column && $column['Type'] !== 'longblob') {
        $conn->query("ALTER TABLE esporadics MODIFY COLUMN Foto LONGBLOB");
    }
}

$soci = null;
if (isset($_GET['DNI_seleccionat'])) {
    $DNI_seleccionat = $_GET['DNI_seleccionat'];
    $sql_soci = "SELECT * FROM esporadics WHERE DNI = '" . $conn->real_escape_string($DNI_seleccionat) . "'";
    $result_soci = $conn->query($sql_soci);
    if ($result_soci->num_rows > 0) {
        $soci = $result_soci->fetch_assoc();
    }
}

if (isset($_POST['modificar']) && $soci) {
    $nuevo_DNI = $conn->real_escape_string($_POST['DNI']);
    $Nom = $conn->real_escape_string($_POST['Nom']);
    $Carrer = $conn->real_escape_string($_POST['Carrer']);
    $Codipostal = $conn->real_escape_string($_POST['Codipostal']);
    $Poblacio = $conn->real_escape_string($_POST['Poblacio']);
    $Provincia = $conn->real_escape_string($_POST['Provincia']);
    $email = $conn->real_escape_string($_POST['email']);
    $Data_naixement = $conn->real_escape_string($_POST['Data_naixement']);
    $Telefon1 = $conn->real_escape_string($_POST['Telefon1']);
    $Telefon2 = $conn->real_escape_string($_POST['Telefon2']);
    $Telefon3 = $conn->real_escape_string($_POST['Telefon3']);
    $Numero_Conta = $conn->real_escape_string($_POST['Numero_Conta']);
    $Sepa = isset($_POST['Sepa']) ? 1 : 0;

    $Activitats = [];
    if (isset($_POST['Activitats'])) {
        foreach ($_POST['Activitats'] as $id_activitat) {
            $sql_nom = "SELECT nom FROM activitats WHERE id = '" . $conn->real_escape_string($id_activitat) . "'";
            $result_nom = $conn->query($sql_nom);
            if ($result_nom->num_rows > 0) {
                $activitat = $result_nom->fetch_assoc();
                $Activitats[] = $activitat['nom'];
            }
        }
    }
    $Activitats_str = implode(',', $Activitats);

    $Quantitat = $conn->real_escape_string($_POST['Quantitat']);
    $Alta = $conn->real_escape_string($_POST['Alta']);
    $Baixa = $conn->real_escape_string($_POST['Baixa']);
    $Facial = isset($_POST['Facial']) ? 1 : 0;
    $Data_Inici_activitat = $conn->real_escape_string($_POST['Data_Inici_activitat']);
    $En_ma = isset($_POST['En_ma']) ? 1 : 0;

    $sql_check_dni = "SELECT * FROM esporadics WHERE DNI = '$nuevo_DNI' AND DNI != '{$soci['DNI']}'";
    $result_check_dni = $conn->query($sql_check_dni);
    if ($result_check_dni->num_rows > 0) {
        echo "El DNI ja existeix en un altre registre.";
    } else {
        if (isset($_FILES['Foto']) && $_FILES['Foto']['tmp_name']) {
            $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name']));
            $sql_update = "UPDATE esporadics SET 
                DNI='$nuevo_DNI', Nom='$Nom', Carrer='$Carrer', Codipostal='$Codipostal', Poblacio='$Poblacio',
                Provincia='$Provincia', email='$email', Data_naixement='$Data_naixement', Telefon1='$Telefon1',
                Telefon2='$Telefon2', Telefon3='$Telefon3', Numero_Conta='$Numero_Conta', Sepa='$Sepa',
                Activitats='$Activitats_str', Quantitat='$Quantitat', Alta='$Alta', Baixa='$Baixa',
                Facial='$Facial', Data_Inici_activitat='$Data_Inici_activitat', En_ma='$En_ma', Foto='$Foto'
                WHERE DNI='{$soci['DNI']}'";
        } else {
            $sql_update = "UPDATE esporadics SET 
                DNI='$nuevo_DNI', Nom='$Nom', Carrer='$Carrer', Codipostal='$Codipostal', Poblacio='$Poblacio',
                Provincia='$Provincia', email='$email', Data_naixement='$Data_naixement', Telefon1='$Telefon1',
                Telefon2='$Telefon2', Telefon3='$Telefon3', Numero_Conta='$Numero_Conta', Sepa='$Sepa',
                Activitats='$Activitats_str', Quantitat='$Quantitat', Alta='$Alta', Baixa='$Baixa',
                Facial='$Facial', Data_Inici_activitat='$Data_Inici_activitat', En_ma='$En_ma'
                WHERE DNI='{$soci['DNI']}'";
        }
        if ($conn->query($sql_update) === TRUE) {
            header("Location: modificar1.php?DNI_seleccionat=$nuevo_DNI");
            exit;
        } else {
            echo "Error: " . $sql_update . "<br>" . $conn->error;
        }
    }
}

if (isset($_POST['eliminar_foto']) && $soci) {
    $conn->query("UPDATE esporadics SET Foto=NULL WHERE DNI='" . $conn->real_escape_string($soci['DNI']) . "'");
    header("Location: modificar1.php?DNI_seleccionat=" . $soci['DNI']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar esporàdic</title>
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
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 24px 0;
    }
    .form-container {
        width: 95%;
        max-width: 1100px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15,23,42,0.08);
        padding: 20px;
        position: relative;
    }
    .row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .row label {
        width: 160px;
        font-weight: 600;
        color: var(--muted);
    }
    .row input[type="text"],
    .row input[type="email"],
    .row input[type="date"],
    .row input[type="file"],
    .row input[type="number"] {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #f9fafb;
        font-size: 14px;
    }
    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .activity-box {
        border: 1px solid var(--border);
        border-radius: 10px;
        max-height: 180px;
        overflow-y: auto;
        padding: 10px;
        background: #f9fafb;
        margin-top: 6px;
    }
    .activity-box label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
    }
    .photo-box {
        width: 120px;
        height: 150px;
        border: 1px dashed var(--border);
        border-radius: 10px;
        background: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .photo-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }
    .photo-floating {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        justify-self: end;
        grid-column: 2;
        grid-row: 1 / span 3;
    }
    .photo-floating input[type="file"] {
        width: 180px;
        padding: 6px;
    }
    .actions {
        margin-top: 14px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
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
    .btn-secondary {
        background: #e5e7eb;
        color: #111827;
    }
    .btn-danger {
        background: #ef4444;
        color: #fff;
        box-shadow: 0 10px 18px rgba(239,68,68,0.18);
    }
    .first-row { margin-top: 0; }

    .form-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
        gap: 12px 18px;
        align-items: start;
    }
    .row {
        margin-bottom: 6px;
    }
    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .row.full, .checkbox-row.full {
        grid-column: 1 / -1;
    }

    @media (max-width: 768px) {
        body {
            padding: 12px 0;
        }
        .form-container {
            width: 100%;
            padding: 16px;
        }
        .photo-floating {
            position: static;
            flex-direction: row;
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 10px;
        }
        .photo-floating input[type="file"] {
            width: auto;
        }
        .first-row { margin-top: 0; }
        .form-section {
            grid-template-columns: 1fr;
        }
    }
    </style>
	<script>
        function confirmarEliminacion() {
            return confirm("Estàs segur que vols eliminar aquest esporàdic?");
        }
    </script>
</head>
<body>
<div class="form-container">
    <?php if ($soci): ?>
        <form action="modificar1.php?DNI_seleccionat=<?php echo $soci['DNI']; ?>" method="POST" enctype="multipart/form-data">
            <div class="form-section">
                <div class="photo-floating">
                    <div class="photo-box">
                        <?php if ($soci['Foto']): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($soci['Foto']); ?>" alt="Foto del soci">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="Foto" id="Foto" accept="image/*">
                </div>
                <div class="row first-row">
                    <label for="DNI">DNI</label>
                    <input type="text" name="DNI" id="DNI" value="<?php echo $soci['DNI']; ?>" required>
                </div>
                <div class="row">
                    <label for="Nom">Nom</label>
                    <input type="text" name="Nom" id="Nom" value="<?php echo $soci['Nom']; ?>" required>
                </div>
                <div class="row">
                    <label for="Carrer">Carrer</label>
                    <input type="text" name="Carrer" id="Carrer" value="<?php echo $soci['Carrer']; ?>">
                </div>
                <div class="row">
                    <label for="Codipostal">Codi Postal</label>
                    <input type="text" name="Codipostal" id="Codipostal" value="<?php echo $soci['Codipostal']; ?>">
                </div>
                <div class="row">
                    <label for="Poblacio">Població</label>
                    <input type="text" name="Poblacio" id="Poblacio" value="<?php echo $soci['Poblacio']; ?>">
                </div>
                <div class="row">
                    <label for="Provincia">Província</label>
                    <input type="text" name="Provincia" id="Provincia" value="<?php echo $soci['Provincia']; ?>">
                </div>
                <div class="row">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo $soci['email']; ?>">
                </div>
                <div class="row">
                    <label for="Data_naixement">Data de Naixement</label>
                    <input type="date" name="Data_naixement" id="Data_naixement" value="<?php echo $soci['Data_naixement']; ?>">
                </div>
                <div class="row">
                    <label for="Telefon1">Telèfon 1</label>
                    <input type="text" name="Telefon1" id="Telefon1" value="<?php echo $soci['Telefon1']; ?>">
                </div>
                <div class="row">
                    <label for="Telefon2">Telèfon 2</label>
                    <input type="text" name="Telefon2" id="Telefon2" value="<?php echo $soci['Telefon2']; ?>">
                </div>
                <div class="row">
                    <label for="Telefon3">Telèfon 3</label>
                    <input type="text" name="Telefon3" id="Telefon3" value="<?php echo $soci['Telefon3']; ?>">
                </div>
                <div class="row">
                    <label for="Numero_Conta">Número de Conta</label>
                    <input type="text" name="Numero_Conta" id="Numero_Conta" value="<?php echo $soci['Numero_Conta']; ?>">
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" name="Sepa" id="Sepa" <?php echo $soci['Sepa'] ? 'checked' : ''; ?>>
                    <label for="Sepa">SEPA</label>
                </div>

                <div class="row full" style="align-items:flex-start;">
                    <label for="Activitats">Activitats</label>
                    <div class="activity-box">
                        <?php
                        $sql_activitats = "SELECT * FROM activitats";
                        $result_activitats = $conn->query($sql_activitats);
                        $soci_activitats = explode(',', $soci['Activitats']);
                        if ($result_activitats->num_rows > 0) {
                            while ($activitat = $result_activitats->fetch_assoc()) {
                                $checked = in_array($activitat['nom'], $soci_activitats) ? 'checked' : '';
                                echo "<label><input type='checkbox' name='Activitats[]' value='" . $activitat['id'] . "' $checked> " . $activitat['nom'] . "</label>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <div class="row">
                    <label for="Quantitat">Quantitat</label>
                    <input type="number" name="Quantitat" id="Quantitat" value="<?php echo $soci['Quantitat']; ?>" step="any">
                </div>
                <div class="row">
                    <label for="Alta">Data d'Alta</label>
                    <input type="date" name="Alta" id="Alta" value="<?php echo $soci['Alta']; ?>">
                </div>
                <div class="row">
                    <label for="Baixa">Data de Baixa</label>
                    <input type="date" name="Baixa" id="Baixa" value="<?php echo $soci['Baixa']; ?>">
                </div>
                <div class="checkbox-row full">
                    <input type="checkbox" name="Facial" id="Facial" <?php echo $soci['Facial'] ? 'checked' : ''; ?>>
                    <label for="Facial">Facial</label>
                </div>
                <div class="row">
                    <label for="Data_Inici_activitat">Data d'Inici Activitat</label>
                    <input type="date" name="Data_Inici_activitat" id="Data_Inici_activitat" value="<?php echo $soci['Data_Inici_activitat']; ?>">
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" name="En_ma" id="En_ma" <?php echo $soci['En_ma'] ? 'checked' : ''; ?>>
                    <label for="En_ma">En mà</label>
                </div>
            </div><!-- /.form-section -->
            <div class="actions">
                <button type="submit" name="modificar" class="btn btn-primary">Modificar esporàdic</button>
                <button type="submit" name="eliminar_foto" class="btn btn-secondary">Eliminar Foto</button>
            </div>
        </form>
        <form action="eliminar_soci.php" method="POST" onsubmit="return confirmarEliminacion();">
            <input type="hidden" name="DNI" value="<?php echo $soci['DNI']; ?>">
            <div class="actions">
                <input type="submit" name="eliminar" value="Eliminar Esporàdic" class="btn btn-danger">
                <a href="filtro.php" class="btn btn-secondary" style="text-decoration:none; padding:11px 16px; display:inline-block;">Tornar a buscar</a>
            </div>
        </form>
    <?php else: ?>
        <p class="muted">No s'ha seleccionat cap esporàdic.</p>
        <a href="filtro.php" class="btn btn-secondary" style="text-decoration:none; padding:11px 16px; display:inline-block;">Tornar a buscar</a>
    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>
