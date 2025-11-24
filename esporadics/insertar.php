<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

$conn = getDbConnection();
applyDefaultPhotoToTable($conn, 'esporadics');

// Consultar les activitats
$sql = "SELECT id, nom FROM activitats";
$result_activitats = $conn->query($sql);

// AJAX per carregar població/província
if (isset($_GET['cp'])) {
    $cp = $_GET['cp'];
    $sql_cp = "SELECT Poblacio, Provincia FROM codipostal WHERE CP = '$cp'";
    $result_cp = $conn->query($sql_cp);
    if ($result_cp->num_rows > 0) {
        $row_cp = $result_cp->fetch_assoc();
        echo json_encode($row_cp);
    } else {
        echo json_encode(['Poblacio' => '', 'Provincia' => '']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $DNI = $_POST['DNI'];
    $Nom = $_POST['Nom'];
    $Carrer = $_POST['Carrer'];
    $Codipostal = $_POST['Codipostal'];
    $Poblacio = $_POST['Poblacio'];
    $Provincia = $_POST['Provincia'];
    $email = $_POST['email'];
    $Data_naixement = $_POST['Data_naixement'];
    $Telefon1 = $_POST['Telefon1'];
    $Telefon2 = $_POST['Telefon2'];
    $Telefon3 = $_POST['Telefon3'];
    $Numero_Conta = $_POST['Numero_Conta'];
    $Sepa = isset($_POST['Sepa']) ? 1 : 0;

    $activitats_seleccionades = isset($_POST['Activitats']) ? $_POST['Activitats'] : [];
    $nombres_activitats = [];
    foreach ($activitats_seleccionades as $id_activitat) {
        $sql_nombre = "SELECT nom FROM activitats WHERE id = '$id_activitat'";
        $result_nombre = $conn->query($sql_nombre);
        if ($result_nombre->num_rows > 0) {
            $row_nombre = $result_nombre->fetch_assoc();
            $nombres_activitats[] = $row_nombre['nom'];
        }
    }
    $Activitats = implode(',', $nombres_activitats);

    $Quantitat = $_POST['Quantitat'];
    $Alta = $_POST['Alta'];
    $Baixa = $_POST['Baixa'];
    $Facial = isset($_POST['Facial']) ? 1 : 0;
    $Data_Inici_activitat = $_POST['Data_Inici_activitat'];
    $Usuari = $_POST['Usuari'];
    $Foto = null;

    if (isset($_FILES['Foto']) && $_FILES['Foto']['error'] == 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['Foto']['tmp_name']);
        if (in_array($fileType, $allowedTypes)) {
            $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name']));
        } else {
            echo "Tipus de fitxer no v??lid.";
            $Foto = null;
        }
    } else {
        $Foto = null;
    }

    if ($Foto === null) {
        $defaultPhoto = getDefaultPhotoBinary();
        if ($defaultPhoto !== null) {
            $Foto = addslashes($defaultPhoto);
        }
    }

    $sql_insert = "INSERT INTO esporadics (DNI, Nom, Carrer, Codipostal, Poblacio, Provincia, email, Data_naixement, Telefon1, Telefon2, Telefon3, Numero_Conta, Sepa, Activitats, Quantitat, Alta, Baixa, Facial, Data_Inici_activitat, Usuari, Foto) 
                   VALUES ('$DNI', '$Nom', '$Carrer', '$Codipostal', '$Poblacio', '$Provincia', '$email', '$Data_naixement', '$Telefon1', '$Telefon2', '$Telefon3', '$Numero_Conta', '$Sepa', '$Activitats', '$Quantitat', '$Alta', '$Baixa', '$Facial', '$Data_Inici_activitat', '$Usuari', '$Foto')";

    if ($conn->query($sql_insert) === TRUE) {
        echo "Registre afegit correctament.";
    } else {
        echo "Error: " . $sql_insert . "<br>" . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Afegir esporàdic</title>
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
    max-width: 1200px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15,23,42,0.08);
    padding: 20px;
    position: relative;
}
h1 { margin: 0 0 8px; font-size: 22px; }
.muted { color: var(--muted); margin: 0 0 12px; }
.form-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 12px 18px;
    align-items: start;
    padding-top: 10px;
}
    .row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
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
    }
    .activity-box {
        border: 1px solid var(--border);
        border-radius: 10px;
        max-height: 180px;
        overflow-y: auto;
        padding: 10px;
        background: #f9fafb;
    }
    .activity-box label { display: block; margin-bottom: 6px; font-weight: 500; }
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
.photo-field {
    display: flex;
    align-items: center;
    gap: 12px;
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
@media (max-width: 768px) {
    body { padding: 12px 0; }
    .form-container { padding: 16px; }
    .form-section {
        grid-template-columns: 1fr;
    }
}
    </style>
    <script>
        function carregarPoblacioProvincia() {
            var cp = document.getElementById('Codipostal').value;
            if (cp) {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', '?cp=' + cp, true);
                xhr.onload = function() {
                    if (this.status == 200) {
                        var response = JSON.parse(this.responseText);
                        document.getElementById('Poblacio').value = response.Poblacio;
                        document.getElementById('Provincia').value = response.Provincia;
                    }
                };
                xhr.send();
            } else {
                document.getElementById('Poblacio').value = '';
                document.getElementById('Provincia').value = '';
            }
        }
        function mostrarPreviewFoto(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var fotoPreview = document.getElementById('foto-preview');
                    fotoPreview.src = e.target.result;
                    fotoPreview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</head>
<body>
<div class="form-container">
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-section">
            <div class="row">
                <label for="DNI">DNI</label>
                <input type="text" name="DNI" id="DNI" required>
            </div>
            <div class="row">
                <label for="Nom">Nom</label>
                <input type="text" name="Nom" id="Nom" required>
            </div>
            <div class="row">
                <label for="Carrer">Carrer</label>
                <input type="text" name="Carrer" id="Carrer">
            </div>
            <div class="row">
                <label for="Codipostal">Codi Postal</label>
                <input type="text" name="Codipostal" id="Codipostal" onblur="carregarPoblacioProvincia()">
            </div>
            <div class="row">
                <label for="Poblacio">Població</label>
                <input type="text" name="Poblacio" id="Poblacio">
            </div>
            <div class="row">
                <label for="Provincia">Província</label>
                <input type="text" name="Provincia" id="Provincia">
            </div>
            <div class="row">
                <label for="email">Correu electrònic</label>
                <input type="email" name="email" id="email">
            </div>
            <div class="row">
                <label for="Data_naixement">Data de Naixement</label>
                <input type="date" name="Data_naixement" id="Data_naixement">
            </div>
            <div class="row">
                <label for="Telefon1">Telèfon 1</label>
                <input type="text" name="Telefon1" id="Telefon1">
            </div>
            <div class="row">
                <label for="Telefon2">Telèfon 2</label>
                <input type="text" name="Telefon2" id="Telefon2">
            </div>
            <div class="row">
                <label for="Telefon3">Telèfon 3</label>
                <input type="text" name="Telefon3" id="Telefon3">
            </div>
            <div class="row">
                <label for="Numero_Conta">Número de Compte</label>
                <input type="text" name="Numero_Conta" id="Numero_Conta">
            </div>
            <div class="checkbox-row">
                <input type="checkbox" name="Sepa" id="Sepa">
                <label for="Sepa">SEPA</label>
            </div>
            <div class="row" style="align-items:flex-start;">
                <label for="Activitats">Activitats</label>
                <div class="activity-box">
                    <?php
                    if ($result_activitats->num_rows > 0) {
                        while ($row = $result_activitats->fetch_assoc()) {
                            echo '<label><input type="checkbox" name="Activitats[]" value="' . $row['id'] . '"> ' . $row['nom'] . '</label>';
                        }
                    } else {
                        echo "<span class=\"muted\">No hi ha activitats disponibles.</span>";
                    }
                    ?>
                </div>
            </div>
            <div class="row">
                <label for="Quantitat">Quantitat</label>
                <input type="text" name="Quantitat" id="Quantitat">
            </div>
            <div class="row">
                <label for="Alta">Data d'Alta</label>
                <input type="date" name="Alta" id="Alta">
            </div>
            <div class="row">
                <label for="Baixa">Data de Baixa</label>
                <input type="date" name="Baixa" id="Baixa">
            </div>
            <div class="checkbox-row">
                <input type="checkbox" name="Facial" id="Facial">
                <label for="Facial">Facial</label>
            </div>
            <div class="row">
                <label for="Data_Inici_activitat">Data d'Inici Activitat</label>
                <input type="date" name="Data_Inici_activitat" id="Data_Inici_activitat">
            </div>
            <div class="row">
                <label for="Usuari">Usuari</label>
                <input type="text" name="Usuari" id="Usuari">
            </div>
            <div class="row" style="align-items:flex-start;">
                <label for="Foto">Foto (opcional)</label>
                <div class="photo-field">
                    <input type="file" name="Foto" id="Foto" accept="image/*" onchange="mostrarPreviewFoto(this)">
                    <div class="photo-box">
                        <img id="foto-preview" src="" alt="Foto de l'esporàdic" style="display: none;">
                    </div>
                </div>
            </div>
        </div>
        <div class="actions">
            <button type="submit" class="btn btn-primary">Afegir esporàdic</button>
        </div>
    </form>
</div>

</body>
</html>
