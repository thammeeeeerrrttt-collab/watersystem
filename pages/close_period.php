<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id'];

/* =====================================
   عدد العدادات النشطة فقط
===================================== */

$totalMeters = $conn->query("
SELECT COUNT(*) as c
FROM Meter
WHERE Status='Active'
")->fetch_assoc()['c'];

/* =====================================
   عدد القراءات داخل الدورة
===================================== */

$totalReadings = $conn->query("
SELECT COUNT(*) as c
FROM Reading
WHERE PeriodID=$id
")->fetch_assoc()['c'];

/* =====================================
   تحقق من اكتمال القراءات
===================================== */

if($totalReadings < $totalMeters){

    die("❌ لا يمكن إغلاق الدورة - توجد قراءات ناقصة");
}

/* =====================================
   إغلاق الدورة
===================================== */

$conn->query("
UPDATE billing_period
SET Status='Closed'
WHERE PeriodID=$id
");

/* =====================================
   رجوع
===================================== */

header("Location: periods.php?msg=closed");
exit();
?>