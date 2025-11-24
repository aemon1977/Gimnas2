<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Conexión a la base de datos

// Crear conexión
$conn = getDbConnection();

// Verificar la conexión

// Verificar si se ha enviado el DNI
if (isset($_POST['DNI'])) {
    $DNI = $conn->real_escape_string($_POST['DNI']);

    // Eliminar el socio
    $sql_delete = "DELETE FROM socis WHERE DNI='$DNI'";
    if ($conn->query($sql_delete) === TRUE) {
        echo "Socio eliminado correctamente!";
        // Redirigir a filtro.php después de la eliminación
        header("Location: filtro.php");
        exit;
    } else {
        echo "Error al eliminar el socio: " . $conn->error;
    }
}
$conn->close();
?>
