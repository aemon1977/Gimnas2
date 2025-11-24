<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Conexión a la base de datos

// Crear conexión
$conn = getDbConnection();

// Verificar la conexión

// Obtener el código postal desde la solicitud GET
$cp = $_GET['CP'];

// Consultar la base de datos para obtener la población y provincia
$sql = "SELECT poblacio, provincia FROM codigos_postales WHERE CP = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $CP);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Obtener los datos y devolverlos como JSON
    $data = $result->fetch_assoc();
    echo json_encode($data);
} else {
    echo json_encode(null); // Devuelve null si no se encuentra el código postal
}

$stmt->close();
$conn->close();
?>
