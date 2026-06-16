<?php
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$periodID = isset($_GET['period_id']) ? intval($_GET['period_id']) : '';

if($id){
    $stmt = $conn->prepare("DELETE FROM Bill WHERE BillID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: bills.php?period_id=' . $periodID . '&msg=deleted');
exit();

?>
