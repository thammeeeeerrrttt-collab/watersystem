<?php
// Simple migration: add PreviousArrears column if missing
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

// Ensure Bill.PreviousArrears exists
$check = $conn->query("SHOW COLUMNS FROM bill LIKE 'PreviousArrears'");
if($check && $check->num_rows == 0){
    $conn->query("ALTER TABLE bill ADD PreviousArrears DECIMAL(12,2) NOT NULL DEFAULT 0");
}

// Create a dedicated table to store per-bill arrears records (previous periods)
$tblCheck = $conn->query("SHOW TABLES LIKE 'previousarrearsrecords'");
if(!$tblCheck || $tblCheck->num_rows == 0){
    $conn->query(
        "CREATE TABLE previousarrearsrecords (
            ArrearID INT AUTO_INCREMENT PRIMARY KEY,
            BillID INT NOT NULL,
            CustomerID INT NOT NULL,
            PeriodID INT NOT NULL,
            RemainingAmount DECIMAL(12,2) NOT NULL DEFAULT 0,
            CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (CustomerID),
            INDEX (PeriodID),
            INDEX (BillID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

header('Location: bills.php?msg=migrated');
exit();

?>
