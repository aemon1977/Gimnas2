<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

// Crear connexió
$conn = getDbConnection();
applyDefaultPhotoToTable($conn, 'socis');

// Comprovar la connexió

// Verificar si el campo 'data_modificacio' existe en la tabla 'socis'
$tableName = 'socis';
$columnName = 'data_modificacio';

// Consulta para verificar si el campo existe
$query = "SHOW COLUMNS FROM $tableName LIKE '$columnName'";
$result = $conn->query($query);

if ($result->num_rows == 0) {
    // Si el campo no existe, añadirlo
    $alterQuery = "ALTER TABLE $tableName ADD $columnName DATE DEFAULT NULL";
    if ($conn->query($alterQuery) === TRUE) {
        echo "El campo '$columnName' se ha creado en la tabla '$tableName'.";
    } else {
        echo "Error al crear el campo: " . $conn->error;
    }
}

// Consultar les activitats per a la llista de verificació
$sql = "SELECT id, nom FROM activitats";
$result_activitats = $conn->query($sql);

// Inicialitzar variables per població i província
$poblacio = '';
$provincia = '';

// Comprovar si s'ha enviat una sol·licitud AJAX per carregar població i província
if (isset($_GET['cp'])) {
    $cp = $_GET['cp'];
    $sql_cp = "SELECT Poblacio, Provincia FROM codipostal WHERE CP = '$cp'";
    $result_cp = $conn->query($sql_cp);

    if ($result_cp->num_rows > 0) {
        $row_cp = $result_cp->fetch_assoc();
        echo json_encode($row_cp); // Retornar com a JSON
        exit; // Terminar l'execució
    } else {
        echo json_encode(['Poblacio' => '', 'Provincia' => '']); // Retornar buit si no es troba
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Rebre dades del formulari
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
    
    // Obtenir noms d'activitats seleccionades
    $activitats_seleccionades = isset($_POST['Activitats']) ? $_POST['Activitats'] : [];
    $nombres_activitats = [];
    
    foreach ($activitats_seleccionades as $id_actividad) {
        // Consultar el nom de l'activitat per ID
        $sql_nombre = "SELECT nom FROM activitats WHERE id = '$id_actividad'";
        $result_nombre = $conn->query($sql_nombre);
        if ($result_nombre->num_rows > 0) {
            $row_nombre = $result_nombre->fetch_assoc();
            $nombres_activitats[] = $row_nombre['nom'];
        }
    }
    
    // Concatenar noms d'activitats
    $Activitats = implode(',', $nombres_activitats);
    
    $Quantitat = $_POST['Quantitat'];
    $Alta = $_POST['Alta'];
    $Baixa = $_POST['Baixa'];
    $Facial = isset($_POST['Facial']) ? 1 : 0;
    $Data_Inici_activitat = $_POST['Data_Inici_activitat'];
    $Usuari = $_POST['Usuari'];

    // Si es carrega una foto
    if (isset($_FILES['Foto']) && $_FILES['Foto']['error'] == 0) {
        // Verifica que el tipus de fitxer sigui una imatge v??lida
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['Foto']['tmp_name']);
        if (in_array($fileType, $allowedTypes)) {
            // Convertir la imatge a binari
            $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name']));
        } else {
            // Si no ??s una imatge v??lida, estableix a null
            $Foto = null;
            echo "Tipus de fitxer no v??lid.";
        }
    } else {
        $Foto = null; // No es carrega foto
    }

    if ($Foto === null) {
        $defaultPhoto = getDefaultPhotoBinary();
        if ($defaultPhoto !== null) {
            $Foto = addslashes($defaultPhoto);
        }
    }

    // Generar un número temporal si no se proporciona DNI
    if (empty($DNI)) {
        // Obtener el siguiente ID de la tabla 'socis' para usar como número temporal
        $sql_temp_id = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'socis'";
        $result_temp_id = $conn->query($sql_temp_id);
        if ($result_temp_id->num_rows > 0) {
            $row_temp_id = $result_temp_id->fetch_assoc();
            $DNI = $row_temp_id['AUTO_INCREMENT']; // Usar el próximo ID como DNI
        }
    }
	
    // SQL per inserir les dades
    $sql_insert = "INSERT INTO socis (DNI, Nom, Carrer, Codipostal, Poblacio, Provincia, email, Data_naixement, Telefon1, Telefon2, Telefon3, Numero_Conta, Sepa, Activitats, Quantitat, Alta, Baixa, Facial, Data_Inici_activitat, Usuari, Foto, data_modificacio) 
                   VALUES ('$DNI', '$Nom', '$Carrer', '$Codipostal', '$Poblacio', '$Provincia', '$email', '$Data_naixement', '$Telefon1', '$Telefon2', '$Telefon3', '$Numero_Conta', '$Sepa', '$Activitats', '$Quantitat', '$Alta', '$Baixa', '$Facial', '$Data_Inici_activitat', '$Usuari', '$Foto', CURDATE())";

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
    <title>Afegir socis</title>
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
.card h1 {
    margin: 0 0 6px;
    font-size: 22px;
}
.card p {
    margin: 0;
    color: var(--muted);
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px 18px;
}
.field label {
    display: block;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 6px;
}
.field input[type="text"],
.field input[type="email"],
.field input[type="date"],
.field input[type="file"],
.field input[type="number"] {
    width: 100%;
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
    padding: 6px 0;
}
.activity-box {
    border: 1px solid var(--border);
    border-radius: 10px;
    max-height: 180px;
    overflow-y: auto;
    padding: 10px;
    background: #f9fafb;
}
.activity-box label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
}
.photo-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
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
.actions {
    margin-top: 14px;
    display: flex;
    gap: 10px;
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

@media (max-width: 720px) {
    .page { margin: 12px auto; }
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
<div class="page">
    <div class="card">
        <h1>Afegir soci</h1>
        <p>Completa les dades per donar d'alta un nou soci.</p>
    </div>
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="field">
                    <label for="DNI">DNI</label>
                    <input type="text" name="DNI" id="DNI">
                </div>
                <div class="field">
                    <label for="Nom">Nom</label>
                    <input type="text" name="Nom" id="Nom" required>
                </div>
                <div class="field">
                    <label for="Carrer">Carrer</label>
                    <input type="text" name="Carrer" id="Carrer">
                </div>
                <div class="field">
                    <label for="Codipostal">Codi Postal</label>
                    <input type="text" name="Codipostal" id="Codipostal" onblur="carregarPoblacioProvincia()">
                </div>
                <div class="field">
                    <label for="Poblacio">Població</label>
                    <input type="text" name="Poblacio" id="Poblacio">
                </div>
                <div class="field">
                    <label for="Provincia">Província</label>
                    <input type="text" name="Provincia" id="Provincia">
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email">
                </div>
                <div class="field">
                    <label for="Data_naixement">Data de naixement</label>
                    <input type="date" name="Data_naixement" id="Data_naixement">
                </div>
                <div class="field">
                    <label for="Telefon1">Telèfon 1</label>
                    <input type="text" name="Telefon1" id="Telefon1">
                </div>
                <div class="field">
                    <label for="Telefon2">Telèfon 2</label>
                    <input type="text" name="Telefon2" id="Telefon2">
                </div>
                <div class="field">
                    <label for="Telefon3">Telèfon 3</label>
                    <input type="text" name="Telefon3" id="Telefon3">
                </div>
                <div class="field">
                    <label for="Numero_Conta">Número de Compte</label>
                    <input type="text" name="Numero_Conta" id="Numero_Conta">
                </div>
                <div class="field">
                    <div class="checkbox-row">
                        <input type="checkbox" name="Sepa" id="Sepa">
                        <label for="Sepa">SEPA</label>
                    </div>
                </div>
                <div class="field">
                    <label for="Quantitat">Quantitat</label>
                    <input type="text" name="Quantitat" id="Quantitat">
                </div>
                <div class="field">
                    <label for="Alta">Alta</label>
                    <input type="date" name="Alta" id="Alta">
                </div>
                <div class="field">
                    <label for="Baixa">Baixa</label>
                    <input type="date" name="Baixa" id="Baixa">
                </div>
                <div class="field">
                    <div class="checkbox-row">
                        <input type="checkbox" name="Facial" id="Facial">
                        <label for="Facial">Facial</label>
                    </div>
                </div>
                <div class="field">
                    <label for="Data_Inici_activitat">Data d'inici activitat</label>
                    <input type="date" name="Data_Inici_activitat" id="Data_Inici_activitat">
                </div>
                <div class="field">
                    <label for="Usuari">Usuari</label>
                    <input type="text" name="Usuari" id="Usuari">
                </div>
                <div class="field">
                    <label>Activitats</label>
                    <div class="activity-box">
                        <?php if ($result_activitats->num_rows > 0): ?>
                            <?php while($row_activitat = $result_activitats->fetch_assoc()): ?>
                                <label>
                                    <input type="checkbox" name="Activitats[]" value="<?php echo $row_activitat['id']; ?>"> 
                                    <?php echo $row_activitat['nom']; ?>
                                </label>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="Foto">Foto (opcional)</label>
                    <div class="photo-wrap">
                        <input type="file" name="Foto" id="Foto" accept="image/*" onchange="mostrarPreviewFoto(this)">
                        <div class="photo-box">
                            <img id="foto-preview" src="" alt="Foto de perfil" style="display: none;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Afegir</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
