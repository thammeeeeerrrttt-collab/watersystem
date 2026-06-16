<?php
// Cleanup script: recompute Amount, RemainingAmount, Status; sync PreviousArrearsRecords; recompute PreviousArrears
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

set_time_limit(0);
$conn->begin_transaction();
try{
    // Prepare statements
    $getBills = $conn->query("SELECT BillID, CustomerID, ReadingID, Consumption, Rate, PaidAmount, PeriodID FROM Bill ORDER BY PeriodID, BillID");

    $updBill = $conn->prepare("UPDATE Bill SET Amount = ?, PaidAmount = ?, RemainingAmount = ?, Status = ? WHERE BillID = ?");

    $checkAr = $conn->prepare("SELECT ArrearID FROM PreviousArrearsRecords WHERE BillID = ? LIMIT 1");
    $insAr = $conn->prepare("INSERT INTO PreviousArrearsRecords (BillID, CustomerID, PeriodID, RemainingAmount) VALUES (?, ?, ?, ?)");
    $updAr = $conn->prepare("UPDATE PreviousArrearsRecords SET RemainingAmount = ?, CustomerID = ?, PeriodID = ? WHERE ArrearID = ?");

    while($b = $getBills->fetch_assoc()){
        $billId = intval($b['BillID']);
        $cust = intval($b['CustomerID']);
        $cons = floatval($b['Consumption']);
        $rate = floatval($b['Rate']);
        $paid = isset($b['PaidAmount']) ? floatval($b['PaidAmount']) : 0.0;
        $period = intval($b['PeriodID']);

        $amount = $cons * $rate;
        $remaining = $amount - $paid;

        if($remaining <= 0){
            $status = 'Paid';
        } elseif($paid == 0){
            $status = 'Unpaid';
        } else {
            $status = 'Partial';
        }

        $updBill->bind_param('dddis', $amount, $paid, $remaining, $status, $billId);
        $updBill->execute();

        // sync PreviousArrearsRecords
        $checkAr->bind_param('i', $billId);
        $checkAr->execute();
        $resAr = $checkAr->get_result();
        if($resAr && $resAr->num_rows > 0){
            $ar = $resAr->fetch_assoc();
            $arId = intval($ar['ArrearID']);
            $updAr->bind_param('diii', $remaining, $cust, $period, $arId);
            $updAr->execute();
        } else {
            $insAr->bind_param('iiid', $billId, $cust, $period, $remaining);
            $insAr->execute();
        }
    }

    // Recompute aggregated PreviousArrears per bill
    $allBills = $conn->query("SELECT BillID, CustomerID, PeriodID FROM Bill");
    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(RemainingAmount),0) as s FROM PreviousArrearsRecords WHERE CustomerID = ? AND PeriodID < ?");
    $updPrev = $conn->prepare("UPDATE Bill SET PreviousArrears = ? WHERE BillID = ?");

    while($b2 = $allBills->fetch_assoc()){
        $bid = intval($b2['BillID']);
        $cust2 = intval($b2['CustomerID']);
        $p2 = intval($b2['PeriodID']);

        $sumStmt->bind_param('ii', $cust2, $p2);
        $sumStmt->execute();
        $sres = $sumStmt->get_result();
        $srow = $sres->fetch_assoc();
        $sumVal = isset($srow['s']) ? floatval($srow['s']) : 0.0;

        $updPrev->bind_param('di', $sumVal, $bid);
        $updPrev->execute();
    }

    $conn->commit();
    $msg = 'cleanup_done';
} catch(Exception $e){
    $conn->rollback();
    $msg = 'cleanup_failed: ' . $e->getMessage();
}

header('Location: bills.php?msg=' . urlencode($msg));
exit();

?>
