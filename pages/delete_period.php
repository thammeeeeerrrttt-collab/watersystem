<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

// Only admin may delete periods
if(!isset($_SESSION['Name']) || $_SESSION['Name'] !== 'Admin'){
    header('Location: periods.php?msg=forbidden');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$id){
    header('Location: periods.php?msg=invalid');
    exit();
}

// Delete safely inside transaction
try{
    $conn->begin_transaction();

    // Find bills for this period
    $stmt = $conn->prepare("SELECT BillID FROM Bill WHERE PeriodID = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $billIds = [];
    while($r = $res->fetch_assoc()){
        $billIds[] = intval($r['BillID']);
    }
    $stmt->close();

    if(count($billIds) > 0){
        // Delete arrears records for these bills
        $in = implode(',', array_fill(0, count($billIds), '?'));
        $types = str_repeat('i', count($billIds));

        $sql = "DELETE FROM PreviousArrearsRecords WHERE BillID IN ($in)";
        $delStmt = $conn->prepare($sql);
        $delStmt->bind_param($types, ...$billIds);
        $delStmt->execute();
        $delStmt->close();

        // Delete bills
        $sql2 = "DELETE FROM Bill WHERE BillID IN ($in)";
        $delBills = $conn->prepare($sql2);
        $delBills->bind_param($types, ...$billIds);
        $delBills->execute();
        $delBills->close();
    }

    // Delete readings for period
    $delRead = $conn->prepare("DELETE FROM Reading WHERE PeriodID = ?");
    $delRead->bind_param('i', $id);
    $delRead->execute();
    $delRead->close();

    // Finally delete the period
    $delP = $conn->prepare("DELETE FROM billing_period WHERE PeriodID = ?");
    $delP->bind_param('i', $id);
    $delP->execute();
    $delP->close();

    $conn->commit();
    header('Location: periods.php?msg=deleted');
    exit();

} catch(Exception $e){
    $conn->rollback();
    header('Location: periods.php?msg=error');
    exit();
}

?>
