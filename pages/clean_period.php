<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* ===== Admin فقط ===== */

$isAdmin = false;

if(isset($_SESSION['Role']) && strtolower($_SESSION['Role']) == 'admin'){
    $isAdmin = true;
}

if(isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin']){
    $isAdmin = true;
}

if(!$isAdmin){
    die("❌ ليس لديك صلاحية");
}

/* ===== التحقق ===== */

if(!isset($_GET['id'])){
    header("Location: periods.php");
    exit();
}

$periodID = intval($_GET['id']);

$conn->begin_transaction();

try{

    /* ===== حذف الفواتير ===== */

    $conn->query("
    DELETE FROM Bill
    WHERE PeriodID = $periodID
    ");

    /* ===== حذف القراءات ===== */

    $conn->query("
    DELETE FROM Reading
    WHERE PeriodID = $periodID
    ");

    /* ===== إعادة فتح الدورة ===== */

    $conn->query("
    UPDATE billing_period
    SET Status='Open'
    WHERE PeriodID = $periodID
    ");

    $conn->commit();

    header("Location: periods.php?msg=cleaned");
    exit();

}catch(Exception $e){

    $conn->rollback();

    echo "❌ حصل خطأ أثناء التنظيف";
}
?>