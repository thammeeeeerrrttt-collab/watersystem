<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

/* ===== تحقق ===== */
$check = $conn->query("
SELECT Status FROM billing_period WHERE PeriodID=$id
")->fetch_assoc();

if($check['Status'] == 'Open'){
    header("Location: periods.php");
    exit();
}

/* ===== فتح الدورة ===== */
$conn->query("
UPDATE billing_period
SET Status='Open'
WHERE PeriodID=$id
");

header("Location: periods.php?msg=open");
?>