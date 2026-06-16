<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

if(isset($_GET['id'])) {

    $id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM Customer WHERE CustomerID = ?");
    $stmt->bind_param("i", $id);

    if($stmt->execute()) {
        header("Location: customers.php");
        exit();
    } else {
        echo "❌ خطأ في الحذف";
    }
}
?>