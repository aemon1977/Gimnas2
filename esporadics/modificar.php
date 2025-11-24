<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

// Crear connexió
$conn = getDbConnection();

// Verificar la connexió

$socis_encontrats = [];
$soci = null;

// Buscar soci si s'envia el formulari de cerca
if (isset($_POST['buscar'])) {
    $criteri_cerca = $_POST['criteri_cerca'];

    // SQL per buscar per DNI o nom
    $sql_buscar = "SELECT DNI, Nom FROM socis WHERE DNI LIKE '%$criteri_cerca%' OR Nom LIKE '%$criteri_cerca%'";
    $result_buscar = $conn->query($sql_buscar);
    
    if ($result_buscar->num_rows > 0) {
        while ($row = $result_buscar->fetch_assoc()) {
            $socis_encontrats[] = $row; // Desar resultats
        }
    } else {
        echo "No s'han trobat socis.";
    }
}

// Carregar dades del soci si se selecciona un
if (isset($_POST['seleccionar_soci'])) {
    $DNI_seleccionat = $_POST['DNI_seleccionat'];

    // SQL per obtenir les dades completes del soci seleccionat
    $sql_soci = "SELECT * FROM socis WHERE DNI = '$DNI_seleccionat'";
    $result_soci = $conn->query($sql_soci);
    
    if ($result_soci->num_rows > 0) {
        $soci = $result_soci->fetch_assoc();
        if (empty($soci['Foto'])) {
            $defaultPhoto = getDefaultPhotoBinary();
            if ($defaultPhoto !== null) {
                $soci['Foto'] = $defaultPhoto;
            }
        }
    }
}

// Actualitzar dades del soci si s'envia el formulari de modificació
if (isset($_POST['modificar'])) {
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
    
    // Obtenir els noms de les activitats seleccionades
    $Activitats = [];
    if (isset($_POST['Activitats'])) {
        foreach ($_POST['Activitats'] as $id_activitat) {
            $sql_nombre_activitat = "SELECT nom FROM activitats WHERE id = '$id_activitat'";
            $result_nombre_activitat = $conn->query($sql_nombre_activitat);
            if ($result_nombre_activitat->num_rows > 0) {
                $activitat = $result_nombre_activitat->fetch_assoc();
                $Activitats[] = $activitat['nom'];
            }
        }
    }
    $Activitats_str = implode(',', $Activitats); // Convertir array a string
    
    $Quantitat = $_POST['Quantitat'];
    $Alta = $_POST['Alta'];
    $Baixa = $_POST['Baixa'];
    $Facial = isset($_POST['Facial']) ? 1 : 0;
    $Data_Inici_activitat = $_POST['Data_Inici_activitat'];
    $Usuari = $_POST['Usuari'];
    $Descompte = $_POST['Descompte'];
    $Total = $_POST['Total'];
    $Temps_descompte = $_POST['Temps_descompte'];
    $Extres = $_POST['Extres'];
    $En_ma = isset($_POST['En_ma']) ? 1 : 0;

    // Actualitzar imatge si s'ha pujat una nova
    if ($_FILES['Foto']['tmp_name']) {
        $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name'])); // Convertir la imatge a blob
        $sql_update = "UPDATE socis SET Nom='$Nom', Carrer='$Carrer', Codipostal='$Codipostal', Poblacio='$Poblacio', Provincia='$Provincia', email='$email', Data_naixement='$Data_naixement', Telefon1='$Telefon1', Telefon2='$Telefon2', Telefon3='$Telefon3', Numero_Conta='$Numero_Conta', Sepa='$Sepa', Activitats='$Activitats_str', Quantitat='$Quantitat', Alta='$Alta', Baixa='$Baixa', Facial='$Facial', Data_Inici_activitat='$Data_Inici_activitat', Usuari='$Usuari', Descompte='$Descompte', Total='$Total', Temps_descompte='$Temps_descompte', Extres='$Extres', En_ma='$En_ma', Foto='$Foto' WHERE DNI='$DNI'";
    } else {
        // SQL sense actualització de la foto
        $sql_update = "UPDATE socis SET Nom='$Nom', Carrer='$Carrer', Codipostal='$Codipostal', Poblacio='$Poblacio', Provincia='$Provincia', email='$email', Data_naixement='$Data_naixement', Telefon1='$Telefon1', Telefon2='$Telefon2', Telefon3='$Telefon3', Numero_Conta='$Numero_Conta', Sepa='$Sepa', Activitats='$Activitats_str', Quantitat='$Quantitat', Alta='$Alta', Baixa='$Baixa', Facial='$Facial', Data_Inici_activitat='$Data_Inici_activitat', Usuari='$Usuari', Descompte='$Descompte', Total='$Total', Temps_descompte='$Temps_descompte', Extres='$Extres', En_ma='$En_ma' WHERE DNI='$DNI'";
    }

    if ($conn->query($sql_update) === TRUE) {
        echo "Registre actualitzat correctament.";
    } else {
        echo "Error: " . $sql_update . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Cercar i modificar espor?dics</title>
    <style>
        /* Estils per a la previsualització de la foto */
        .photo-box {
            width: 105px; /* 3.5 cm */
            height: 135px; /* 4.5 cm */
            border: 1px solid #000;
            margin-bottom: 10px;
            position: absolute;
            top: 20px;
            right: 1800px;
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

        /* Estil per al contenidor d'activitats */
        .activities-container {
            border: 1px solid #ccc;
            max-height: 200px; /* Alçada màxima del recuadre */
            overflow-y: auto; /* Permetre desplaçament vertical */
            margin-top: 10px;
            padding: 10px;
        }
    </style>
</head>
<body>
    <h1>Cercar espordic</h1>
    
    <!-- Formulari de cerca -->
    <form action="modificar.php" method="POST">
        Cerca per DNI o nom: <input type="text" name="criteri_cerca" required>
        <input type="submit" name="buscar" value="Cercar">
    </form>

    <!-- Mostrar resultats si hi ha més d'un soci trobat -->
    <?php if (!empty($socis_encontrats)): ?>
        <h2>Seleccioneu un soci:</h2>
        <form action="modificar.php" method="POST">
            <select name="DNI_seleccionat" required>
                <?php foreach ($socis_encontrats as $soci): ?>
                    <option value="<?php echo $soci['DNI']; ?>"><?php echo $soci['DNI'] . " - " . $soci['Nom']; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" name="seleccionar_soci" value="Selecciona">
        </form>
    <?php endif; ?>

    <!-- Mostrar el formulari de modificació només si un soci ha estat seleccionat -->
    <?php if ($soci): ?>
        <h2>Modificar Soci</h2>
        <form action="modificar.php" method="POST" enctype="multipart/form-data">
            DNI: <input type="text" name="DNI" value="<?php echo $soci['DNI']; ?>" required readonly><br>
            Nom: <input type="text" name="Nom" value="<?php echo $soci['Nom']; ?>" required><br>
            Direcció: <input type="text" name="Carrer" value="<?php echo $soci['Carrer']; ?>"><br>
            Codi Postal: <input type="text" name="Codipostal" value="<?php echo $soci['Codipostal']; ?>"><br>
            Població: <input type="text" name="Poblacio" value="<?php echo $soci['Poblacio']; ?>"><br>
            Provincia: <input type="text" name="Provincia" value="<?php echo $soci['Provincia']; ?>"><br>
            Correu Electrònic: <input type="email" name="email" value="<?php echo $soci['email']; ?>"><br>
            Data de Naixement: <input type="date" name="Data_naixement" value="<?php echo $soci['Data_naixement']; ?>"><br>
            Telèfon 1: <input type="text" name="Telefon1" value="<?php echo $soci['Telefon1']; ?>"><br>
            Telèfon 2: <input type="text" name="Telefon2" value="<?php echo $soci['Telefon2']; ?>"><br>
            Telèfon 3: <input type="text" name="Telefon3" value="<?php echo $soci['Telefon3']; ?>"><br>
            Número de Comptes: <input type="text" name="Numero_Conta" value="<?php echo $soci['Numero_Conta']; ?>"><br>
            SEPA: <input type="checkbox" name="Sepa" <?php echo $soci['Sepa'] ? 'checked' : ''; ?>><br>
            <div class="activities-container">
                <strong>Activitats:</strong>
                <div>
                    <?php
                    // Carregar activitats disponibles
                    $sql_activitats = "SELECT * FROM activitats";
                    $result_activitats = $conn->query($sql_activitats);
                    
                    while ($row_activitat = $result_activitats->fetch_assoc()) {
                        $checked = in_array($row_activitat['nom'], explode(',', $soci['Activitats'])) ? 'checked' : '';
                        echo '<input type="checkbox" name="Activitats[]" value="' . $row_activitat['id'] . '" ' . $checked . '> ' . $row_activitat['nom'] . '<br>';
                    }
                    ?>
                </div>
            </div>
            Quantitat: <input type="number" name="Quantitat" value="<?php echo $soci['Quantitat']; ?>"><br>
            Alta: <input type="date" name="Alta" value="<?php echo $soci['Alta']; ?>"><br>
            Baixa: <input type="date" name="Baixa" value="<?php echo $soci['Baixa']; ?>"><br>
            Facial: <input type="checkbox" name="Facial" <?php echo $soci['Facial'] ? 'checked' : ''; ?>><br>
            Data d'Inici d'Activitat: <input type="date" name="Data_Inici_activitat" value="<?php echo $soci['Data_Inici_activitat']; ?>"><br>
			Foto: <input type="file" name="Foto"><br>
            <div class="photo-box">
                <?php if ($soci['Foto']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($soci['Foto']); ?>" alt="Foto Soci">
                <?php endif; ?>
            </div>
            <input type="submit" name="modificar" value="Modificar Soci">
        </form>
    <?php endif; ?>
    
    <?php $conn->close(); ?>
</body>
</html>
