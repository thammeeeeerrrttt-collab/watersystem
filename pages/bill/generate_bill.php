<?php
session_start();
include "../../db.php";
if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* =========================
   1) التحقق من ID
========================= */
if(!isset($_GET['id'])){
    header("Location: ../pages/readings.php");
    exit();
}

$readingID = $_GET['id'];

/* =========================
   2) جلب بيانات القراءة
========================= */
$stmt = $conn->prepare("
SELECT r.*, c.CustomerID, c.Name
FROM Reading r
JOIN Customer c ON r.CustomerID = c.CustomerID
WHERE r.ReadingID = ?
");

$stmt->bind_param("i", $readingID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if(!$row){
    die("❌ Reading not found");
}

/* =========================
   3) منع التكرار
========================= */
$check = $conn->prepare("SELECT BillID FROM Bill WHERE ReadingID = ?");
$check->bind_param("i", $readingID);
$check->execute();
$exists = $check->get_result();

if($exists->num_rows > 0){
    header("Location:bills.php?msg=exists");
    exit();
}

/* =========================
   4) حساب الاستهلاك
========================= */
$consumption = $row['CurrentReading'] - $row['PreviousReading'];
if($consumption < 0) $consumption = 0;

/* =========================
   5) نظام الشرائح
========================= */
if($consumption <= 10){
    $rate = 0.3;
}
elseif($consumption <= 30){
    $rate = 0.5;
}
else{
    $rate = 0.8;
}

/* =========================
   6) حساب المبلغ
========================= */
$amount = $consumption * $rate;

/* =========================
   7) توليد رقم فاتورة
========================= */
$last = $conn->query("SELECT BillID FROM Bill ORDER BY BillID DESC LIMIT 1");
$lastRow = $last->fetch_assoc();

$nextID = $lastRow ? $lastRow['BillID'] + 1 : 1;
$billNumber = "BILL-" . str_pad($nextID, 4, "0", STR_PAD_LEFT);

/* =========================
   8) إدخال الفاتورة
========================= */
$periodID = isset($row['PeriodID']) ? intval($row['PeriodID']) : 0;

$insert = $conn->prepare(
"INSERT INTO Bill 
(BillNumber, CustomerID, ReadingID, Consumption, Rate, Amount, PaidAmount, RemainingAmount, Status, BillDate, PeriodID)
VALUES (?, ?, ?, ?, ?, ?, 0, ?, 'Unpaid', NOW(), ?)"
);

$remaining = $amount; // initially unpaid

$insert->bind_param(
    "siiidddi",
    $billNumber,
    $row['CustomerID'],
    $readingID,
    $consumption,
    $rate,
    $amount,
    $remaining,
    $periodID
);

if($insert->execute()){
    header("Location:bill/bills.php?msg=added");
    exit();
} else {
    echo "❌ خطأ في إنشاء الفاتورة";
}
?>