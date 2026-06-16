<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

if(!isset($_GET['id'])){
    header("Location: meters.php");
    exit();
}

$id = $_GET['id'];

/* Soft Delete بدل الحذف الحقيقي */
$stmt = $conn->prepare("
UPDATE Meter 
SET IsDeleted = 1, Status = 'inactive'
WHERE MeterID = ?
");

$stmt->bind_param("i", $id);

if($stmt->execute()){
    header("Location: meters.php?msg=deleted");
    exit();
} else {
    echo "❌ خطأ في الحذف";
}
?>