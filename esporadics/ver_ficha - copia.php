<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Connexió a la base de dades

// Crear connexió
$conn = getDbConnection();

// Verificar la connexió

// Obtenir el DNI del soci seleccionat
if (isset($_GET['DNI'])) {
    $DNI = $_GET['DNI'];

    // SQL per obtenir les dades completes del soci seleccionat
    $sql_socio = "SELECT * FROM socis WHERE DNI = '$DNI'";
    $result_socio = $conn->query($sql_socio);
    
    if ($result_socio->num_rows > 0) {
        $soci = $result_socio->fetch_assoc();
    } else {
        echo "No s'ha trobat el soci.";
    }
} else {
    echo "No s'ha seleccionat cap soci.";
}
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Fitxa del Soci</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            position: relative;
        }
        .foto {
            position: absolute;
            top: 20px;
            right: 20px;
            max-width: 200px;
            max-height: 200px;
        }
    </style>
</head>
<body>
    <h1>Fitxa del Soci</h1>

    <?php if (isset($soci)): ?>
        <img src="data:image/jpeg;base64,<?php echo base64_encode($soci['Foto']); ?>" alt="Foto del Soci" class="foto">
        
        <p><strong>DNI:</strong> <?php echo $soci['DNI']; ?></p>
        <p><strong>Nom:</strong> <?php echo $soci['Nom']; ?></p>
        <p><strongCarrer:</strong> <?php echo $soci['Carrer']; ?></p>
        <p><strong>Codi Postal:</strong> <?php echo $soci['Codipostal']; ?></p>
        <p><strong>Població:</strong> <?php echo $soci['Poblacio']; ?></p>
        <p><strong>Província:</strong> <?php echo $soci['Provincia']; ?></p>
        <p><strong>Email:</strong> <?php echo $soci['email']; ?></p>
        <p><strong>Data de Naixement:</strong> <?php echo $soci['Data_naixement']; ?></p>
        <p><strong>Telèfon 1:</strong> <?php echo $soci['Telefon1']; ?></p>
        <p><strong>Telèfon 2:</strong> <?php echo $soci['Telefon2']; ?></p>
        <p><strong>Telèfon 3:</strong> <?php echo $soci['Telefon3']; ?></p>
        <p><strong>Número de Compte:</strong> <?php echo $soci['Numero_Conta']; ?></p>
        <p><strong>SEPA:</strong> <?php echo $soci['Sepa'] ? 'Sí' : 'No'; ?></p>
        <p><strong>Activitats:</strong> <?php echo $soci['Activitats']; ?></p>
        <p><strong>Quantitat:</strong> <?php echo $soci['Quantitat']; ?></p>
        <p><strong>Data d'Alta:</strong> <?php echo $soci['Alta']; ?></p>
        <p><strong>Data de Baixa:</strong> <?php echo $soci['Baixa']; ?></p>
        <p><strong>Facial:</strong> <?php echo $soci['Facial'] ? 'Sí' : 'No'; ?></p>
        <p><strong>Data Inici Activitat:</strong> <?php echo $soci['Data_Inici_activitat']; ?></p>
        <p><strong>Usuari:</strong> <?php echo $soci['Usuari']; ?></p>
        <p><strong>En Mà:</strong> <?php echo $soci['En_ma'] ? 'Sí' : 'No'; ?></p>
    <?php endif; ?>

</body>
</html>
