<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

// Crear connexió
$conn = getDbConnection();

// Comprovar la connexió

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
        $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name'])); // Convertir la imatge a blob
    } else {
        $Foto = null; // No es carrega foto
    }
    
    // SQL per inserir les dades
    $sql_insert = "INSERT INTO socis (DNI, Nom, Carrer, Codipostal, Poblacio, Provincia, email, Data_naixement, Telefon1, Telefon2, Telefon3, Numero_Conta, Sepa, Activitats, Quantitat, Alta, Baixa, Facial, Data_Inici_activitat, Usuari, Foto) 
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
    <title>Afegir Socis</title>
    <style>
        /* Estils per posicionar la foto a la part superior dreta */
        .photo-box {
            width: 105px; /* 3.5 cm */
            height: 135px; /* 4.5 cm */
            border: 1px solid #000;
            margin-bottom: 10px;
            position: absolute;
            top: 20px;
            right: 1000px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover; /* Assegura que la imatge s'ajusti al recuadre */
        }

        /* Estils per a la llista d'activitats */
        .activities-box {
            border: 1px solid #ccc;
            padding: 10px;
            max-height: 150px;
            overflow-y: scroll;
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
    </script>
</head>
<body>
    <h1>Formulari d'Inserció de Socis</h1>
    
    <!-- Quadre per mostrar la foto -->
    <div class="photo-box">
        <img id="foto-preview" src="" alt="Foto del soci" style="display: none;">
    </div>
    
    <!-- Formulari -->
    <form action="" method="POST" enctype="multipart/form-data">
        DNI: <input type="text" name="DNI" required><br>
        Nom: <input type="text" name="Nom" required><br>
        Carrer: <input type="text" name="Carrer"><br>
        Codi Postal: <input type="text" name="Codipostal" id="Codipostal" onblur="carregarPoblacioProvincia()"><br>
        Població: <input type="text" name="Poblacio" id="Poblacio"><br>
        Província: <input type="text" name="Provincia" id="Provincia"><br>
        Correu electrònic: <input type="email" name="email"><br>
        Data de Naixement: <input type="date" name="Data_naixement"><br>
        Telèfon 1: <input type="text" name="Telefon1"><br>
        Telèfon 2: <input type="text" name="Telefon2"><br>
        Telèfon 3: <input type="text" name="Telefon3"><br>
        Número de Comptes: <input type="text" name="Numero_Conta" size="40"><br>
        Sepa: <input type="checkbox" name="Sepa"><br>
        
        <!-- Llista d'Activitats amb caselles de verificació (checkboxes) -->
        <label for="Activitats">Activitats:</label><br>
        <div class="activities-box">
            <?php
            if ($result_activitats->num_rows > 0) {
                while($row = $result_activitats->fetch_assoc()) {
                    echo '<input type="checkbox" name="Activitats[]" value="' . $row['id'] . '">' . $row['nom'] . '<br>';
                }
            } else {
                echo "No hi ha activitats disponibles.";
            }
            ?>
        </div>
        
        Quantitat: <input type="text" name="Quantitat"><br>
        Data d'Alta: <input type="date" name="Alta"><br>
        Data de Baixa: <input type="date" name="Baixa"><br>
        Facial: <input type="checkbox" name="Facial"><br>
        Data d'Inici Activitat: <input type="date" name="Data_Inici_activitat"><br>
        Usuari: <input type="text" name="Usuari"><br>
        
        Foto: <input type="file" name="Foto" id="Foto" accept="image/*"><br>
        
        <input type="submit" value="Afegir Soci">
    </form>
</body>
</html>
