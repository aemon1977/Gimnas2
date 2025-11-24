<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Conexión a la base de datos

// Crear conexión
$conn = getDbConnection();

// Verificar la conexión

// Verificar si la columna 'Foto' es de tipo LONGBLOB
$result = $conn->query("SHOW COLUMNS FROM socis LIKE 'Foto'");
if ($result) {
    $column = $result->fetch_assoc();
    if ($column && $column['Type'] !== 'longblob') {
        // Modificar la columna a LONGBLOB
        if ($conn->query("ALTER TABLE socis MODIFY COLUMN Foto LONGBLOB") === TRUE) {
            echo "Columna 'Foto' modificada a LONGBLOB.";
        } else {
            echo "Error al modificar la columna: " . $conn->error;
        }
    }
}

// Inicializar la variable $soci
$soci = null;

// Cargar datos del socio si se selecciona uno
if (isset($_GET['DNI_seleccionat'])) {
    $DNI_seleccionat = $_GET['DNI_seleccionat'];

    // SQL para obtener los datos completos del socio seleccionado
    $sql_soci = "SELECT * FROM socis WHERE DNI = '" . $conn->real_escape_string($DNI_seleccionat) . "'";
    $result_soci = $conn->query($sql_soci);
    
    if ($result_soci->num_rows > 0) {
        $soci = $result_soci->fetch_assoc();
    }
}

// Actualizar datos del socio si se envía el formulario de modificación
if (isset($_POST['modificar'])) {
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

    // Obtener los nombres de las actividades seleccionadas
    $Activitats = [];
    if (isset($_POST['Activitats'])) {
        foreach ($_POST['Activitats'] as $id_activitat) {
            $sql_nombre_activitat = "SELECT nom FROM activitats WHERE id = '" . $conn->real_escape_string($id_activitat) . "'";
            $result_nombre_activitat = $conn->query($sql_nombre_activitat);
            if ($result_nombre_activitat->num_rows > 0) {
                $activitat = $result_nombre_activitat->fetch_assoc();
                $Activitats[] = $activitat['nom'];
            }
        }
    }
    $Activitats_str = implode(',', $Activitats); // Convertir array a string
    
    $Quantitat = $conn->real_escape_string($_POST['Quantitat']);
    $Alta = $conn->real_escape_string($_POST['Alta']);
    $Baixa = $conn->real_escape_string($_POST['Baixa']);
    $Facial = isset($_POST['Facial']) ? 1 : 0;
    $Data_Inici_activitat = $conn->real_escape_string($_POST['Data_Inici_activitat']);
    $En_ma = isset($_POST['En_ma']) ? 1 : 0;

    // Verificar si el nuevo DNI ya existe
    $sql_check_dni = "SELECT * FROM socis WHERE DNI = '$nuevo_DNI' AND DNI != '{$soci['DNI']}'";
    $result_check_dni = $conn->query($sql_check_dni);
    
    if ($result_check_dni->num_rows > 0) {
        echo "El DNI ya existe. Por favor, introduce uno diferente.";
    } else {
        // Actualizar imagen si se ha subido una nueva
        if ($_FILES['Foto']['tmp_name']) {
            $Foto = addslashes(file_get_contents($_FILES['Foto']['tmp_name'])); // Convertir la imagen a blob
            $sql_update = "UPDATE socis SET 
                DNI='$nuevo_DNI', 
                Nom='$Nom', 
                Carrer='$Carrer', 
                Codipostal='$Codipostal', 
                Poblacio='$Poblacio', 
                Provincia='$Provincia', 
                email='$email', 
                Data_naixement='$Data_naixement', 
                Telefon1='$Telefon1', 
                Telefon2='$Telefon2', 
                Telefon3='$Telefon3', 
                Numero_Conta='$Numero_Conta', 
                Sepa='$Sepa', 
                Activitats='$Activitats_str', 
                Quantitat='$Quantitat', 
                Alta='$Alta', 
                Baixa='$Baixa', 
                Facial='$Facial', 
                Data_Inici_activitat='$Data_Inici_activitat', 
                En_ma='$En_ma', 
                Foto='$Foto' 
                WHERE DNI='{$soci['DNI']}'";
        } else {
            // SQL sin actualización de la foto
            $sql_update = "UPDATE socis SET 
                DNI='$nuevo_DNI', 
                Nom='$Nom', 
                Carrer='$Carrer', 
                Codipostal='$Codipostal', 
                Poblacio='$Poblacio', 
                Provincia='$Provincia', 
                email='$email', 
                Data_naixement='$Data_naixement', 
                Telefon1='$Telefon1', 
                Telefon2='$Telefon2', 
                Telefon3='$Telefon3', 
                Numero_Conta='$Numero_Conta', 
                Sepa='$Sepa', 
                Activitats='$Activitats_str', 
                Quantitat='$Quantitat', 
                Alta='$Alta', 
                Baixa='$Baixa', 
                Facial='$Facial', 
                Data_Inici_activitat='$Data_Inici_activitat', 
                En_ma='$En_ma' 
                WHERE DNI='{$soci['DNI']}'";
        }

        if ($conn->query($sql_update) === TRUE) {
            echo "Socio actualizado correctamente!";
            // Recargar la página después de la actualización
            header("Location: modificar1.php?DNI_seleccionat=$nuevo_DNI");
            exit;
        } else {
            echo "Error: " . $sql_update . "<br>" . $conn->error;
        }
    }
}

// Eliminar foto del socio si se presiona el botón
if (isset($_POST['eliminar_foto']) && $soci) {
    $sql_delete_foto = "UPDATE socis SET Foto=NULL WHERE DNI='" . $conn->real_escape_string($soci['DNI']) . "'";
    if ($conn->query($sql_delete_foto) === TRUE) {
        echo "Foto eliminada correctamente!";
        // Recargar la página después de la eliminación
        header("Location: modificar1.php?DNI_seleccionat=" . $soci['DNI']);
        exit;
    } else {
        echo "Error al eliminar la foto: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Modificar Soci</title>
    <style>
        /* Estilos para la previsualización de la foto */
        .photo-box {
            width: 105px; /* 3.5 cm */
            height: 135px; /* 4.5 cm */
            border: 1px solid #000;
            margin-bottom: 10px;
            position: absolute;
            top: 20px;
            right: 1000px; /* Ajustar para mostrar a la derecha */
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover; /* Asegura que la imagen se ajuste al recuadro */
        }

        /* Estilos para la caja de actividades */
        .activity-box {
            width: 300px; /* Ajusta el ancho según sea necesario */
            height: 150px; /* Ajusta la altura para ver 6 actividades a la vez */
            overflow-y: scroll; /* Permite el desplazamiento vertical */
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>Modificar Soci</h1>

    <!-- Mostrar el formulario de modificación solo si un socio ha sido seleccionado -->
    <?php if ($soci): ?>
        <form action="modificar1.php?DNI_seleccionat=<?php echo $soci['DNI']; ?>" method="POST" enctype="multipart/form-data">
            DNI: <input type="text" name="DNI" value="<?php echo $soci['DNI']; ?>" required><br>
            Nom: <input type="text" name="Nom" value="<?php echo $soci['Nom']; ?>" required><br>
            Carrer: <input type="text" name="Carrer" value="<?php echo $soci['Carrer']; ?>"><br>
            Codi Postal: <input type="text" name="Codipostal" value="<?php echo $soci['Codipostal']; ?>"><br>
            Població: <input type="text" name="Poblacio" value="<?php echo $soci['Poblacio']; ?>"><br>
            Provincia: <input type="text" name="Provincia" value="<?php echo $soci['Provincia']; ?>"><br>
            Email: <input type="email" name="email" value="<?php echo $soci['email']; ?>"><br>
            Data de Naixement: <input type="date" name="Data_naixement" value="<?php echo $soci['Data_naixement']; ?>"><br>
            Telèfon 1: <input type="text" name="Telefon1" value="<?php echo $soci['Telefon1']; ?>"><br>
            Telèfon 2: <input type="text" name="Telefon2" value="<?php echo $soci['Telefon2']; ?>"><br>
            Telèfon 3: <input type="text" name="Telefon3" value="<?php echo $soci['Telefon3']; ?>"><br>
            Número de Conta: <input type="text" name="Numero_Conta" value="<?php echo $soci['Numero_Conta']; ?>" size="30"><br>
            SEPA: <input type="checkbox" name="Sepa" <?php echo $soci['Sepa'] ? 'checked' : ''; ?>><br>

            Activitats: <br>
            <div class="activity-box">
                <?php
                // Obtener y mostrar todas las actividades
                $sql_activitats = "SELECT * FROM activitats";
                $result_activitats = $conn->query($sql_activitats);
                if ($result_activitats->num_rows > 0) {
                    while ($activitat = $result_activitats->fetch_assoc()) {
                        $checked = in_array($activitat['nom'], explode(',', $soci['Activitats'])) ? 'checked' : '';
                        echo "<input type='checkbox' name='Activitats[]' value='" . $activitat['id'] . "' $checked> " . $activitat['nom'] . "<br>";
                    }
                }
                ?>
            </div>
            
            Quantitat: <input type="number" name="Quantitat" value="<?php echo $soci['Quantitat']; ?>" step="any"><br>
            Data d'Alta: <input type="date" name="Alta" value="<?php echo $soci['Alta']; ?>"><br>
            Data de Baixa: <input type="date" name="Baixa" value="<?php echo $soci['Baixa']; ?>"><br>
            Facial: <input type="checkbox" name="Facial" <?php echo $soci['Facial'] ? 'checked' : ''; ?>><br>
            Data d'Inici Activitat: <input type="date" name="Data_Inici_activitat" value="<?php echo $soci['Data_Inici_activitat']; ?>"><br>
            En mà: <input type="checkbox" name="En_ma" <?php echo $soci['En_ma'] ? 'checked' : ''; ?>><br>

            Foto: <input type="file" name="Foto" accept="image/*"><br>
            <div class="photo-box">
                <?php if ($soci['Foto']): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($soci['Foto']); ?>" alt="Foto del soci">
                <?php endif; ?>
            </div>
             <!-- Botón para eliminar la foto -->
            <button type="submit" name="eliminar_foto">Eliminar Foto</button>
            <button type="submit" name="modificar">Modificar Soci</button>
        </form>
		<a href="filtro.php"><button>Tornar a buscar un soci</button></a>
    <?php else: ?>
    <?php endif; ?>
</body>
</html>


<?php
$conn->close();
?>
