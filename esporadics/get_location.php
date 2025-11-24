<?php
require_once __DIR__ . '/../config/bootstrap.php';


// Crear conexión
$conn = getDbConnection();

// Verificar la conexión

if (isset($_GET['cp'])) {
    $cp = $_GET['cp'];

    // Consulta para obtener la población y provincia
    $sql = "SELECT Poblacio, Provincia FROM codipostal WHERE CP = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode($row); // Retorna la población y provincia en formato JSON
    } else {
        echo json_encode(null); // No se encontró el código postal
    }

    $stmt->close();
}

$conn->close();
?>
