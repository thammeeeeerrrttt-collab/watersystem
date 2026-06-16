<?php
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$periodID = '';
if(isset($_POST['period_id'])) $periodID = intval($_POST['period_id']);
elseif(isset($_GET['period_id'])) $periodID = intval($_GET['period_id']);

// Build list of bills to update
// Get bills (optionally filter by period)
$sql = "SELECT BillID, CustomerID, PeriodID FROM Bill" . ($periodID !== '' ? " WHERE PeriodID = ?" : "");
$stmt = $conn->prepare($sql);
if($periodID !== ''){
    $stmt->bind_param("i", $periodID);
}
$stmt->execute();
$res = $stmt->get_result();


// Rebuild PreviousArrearsRecords table from current Bill RemainingAmount data
// (clear then repopulate)
$tblCheck = $conn->query("SHOW TABLES LIKE 'PreviousArrearsRecords'");
if(!$tblCheck || $tblCheck->num_rows == 0){
    $conn->query(
        "CREATE TABLE PreviousArrearsRecords (
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
$conn->query("TRUNCATE TABLE PreviousArrearsRecords");
$allBills = $conn->query("SELECT BillID, CustomerID, PeriodID, RemainingAmount FROM Bill");
if($allBills){
    $insAr = $conn->prepare("INSERT INTO PreviousArrearsRecords (BillID, CustomerID, PeriodID, RemainingAmount) VALUES (?, ?, ?, ?)");
    while($b = $allBills->fetch_assoc()){
        $bid2 = intval($b['BillID']);
        $cust2 = intval($b['CustomerID']);
        $p2 = intval($b['PeriodID']);
        $rem2 = floatval($b['RemainingAmount']);
        $insAr->bind_param("iiid", $bid2, $cust2, $p2, $rem2);
        $insAr->execute();
    }
    $insAr->close();
}

// Now compute aggregated previous arrears per bill using the records table
$sumStmt = $conn->prepare("SELECT COALESCE(SUM(RemainingAmount),0) AS s FROM PreviousArrearsRecords WHERE CustomerID = ? AND PeriodID < ?");
$updStmt = $conn->prepare("UPDATE Bill SET PreviousArrears = ? WHERE BillID = ?");

while($r = $res->fetch_assoc()){
    $bid = intval($r['BillID']);
    $cust = intval($r['CustomerID']);
    $p = intval($r['PeriodID']);

    $sumStmt->bind_param("ii", $cust, $p);
    $sumStmt->execute();
    $sres = $sumStmt->get_result();
    $srow = $sres->fetch_assoc();
    $s = isset($srow['s']) ? floatval($srow['s']) : 0.0;

    $updStmt->bind_param("di", $s, $bid);
    $updStmt->execute();
}

$sumStmt->close();
$updStmt->close();
$stmt->close();

header('Location: bills.php?period_id=' . $periodID . '&msg=recalc');
exit();

?>
