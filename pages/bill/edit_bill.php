<?php
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* =====================================
   رقم الفاتورة
===================================== */

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$id){
    header('Location: bills.php');
    exit();
}

/* =====================================
   حفظ التعديلات
===================================== */

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $paid = floatval($_POST['PaidAmount']);
    $previous = floatval($_POST['PreviousArrears']);
    $rate = floatval($_POST['Rate']);
    $cons = floatval($_POST['Consumption']);

    /* =====================================
       حساب المبلغ تلقائي
    ===================================== */

    $amount = $cons * $rate;

    /* =====================================
       الإجمالي المستحق
    ===================================== */

    $totalDue = $amount + $previous;

    /* =====================================
       المتبقي بعد السداد
    ===================================== */

    $remaining = $totalDue - $paid;

    /* =====================================
       تحديد الحالة تلقائياً
    ===================================== */

    if($paid <= 0){

        $status = 'Unpaid';

    }
    elseif($remaining <= 0){

        $status = 'Paid';

    }
    else{

        $status = 'Partial';

    }

    /* =====================================
       تحديث الفاتورة الحالية
    ===================================== */

    $stmt = $conn->prepare("
    UPDATE Bill 
    SET 
        Amount = ?,
        PaidAmount = ?,
        PreviousArrears = ?,
        Rate = ?,
        Consumption = ?,
        RemainingAmount = ?,
        Status = ?
    WHERE BillID = ?
    ");

    $stmt->bind_param(
        "dddddssi",
        $amount,
        $paid,
        $previous,
        $rate,
        $cons,
        $remaining,
        $status,
        $id
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================
       جلب بيانات الفاتورة
    ===================================== */

    $bill = $conn->query("
    SELECT CustomerID, PeriodID
    FROM Bill
    WHERE BillID = $id
    ")->fetch_assoc();

    $customerID = $bill['CustomerID'];
    $currentPeriodID = $bill['PeriodID'];

    /* =====================================
       تحديث الفواتير الحالية وما بعدها
    ===================================== */

    $conn->query("
    UPDATE Bill
    SET
        Rate = $rate,
        Amount = Consumption * $rate,
        RemainingAmount = 
            ((Consumption * $rate) + PreviousArrears) - PaidAmount
    WHERE CustomerID = $customerID
    AND PeriodID >= $currentPeriodID
    ");

    /* =====================================
       رجوع
    ===================================== */

    header('Location: bills.php?period_id=' . intval($_POST['PeriodID']) . '&msg=edited');
    exit();
}

/* =====================================
   جلب بيانات الفاتورة
===================================== */

$stmt = $conn->prepare("
SELECT b.*, c.Name
FROM Bill b
JOIN Customer c ON b.CustomerID = c.CustomerID
WHERE b.BillID = ?
LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$res = $stmt->get_result();
$row = $res->fetch_assoc();

$stmt->close();

if(!$row){
    header('Location: bills.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="ar">
<head>

<meta charset="utf-8">

<title>تعديل فاتورة</title>

<style>

body{
    font-family:Arial;
    padding:20px;
    background:#0f172a;
    color:#fff;
}

label{
    display:block;
    margin-top:10px;
}

input,select{
    padding:10px;
    width:320px;
    border-radius:6px;
    border:1px solid #333;
    background:#0b1220;
    color:#fff;
}

button{
    padding:10px 20px;
    background:#22c55e;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

</style>

</head>

<body>

<h3>
تعديل فاتورة:
<?= htmlspecialchars($row['BillNumber'] ?? $row['BillID']) ?>
-
<?= htmlspecialchars($row['Name']) ?>
</h3>

<form method="POST">

    <input type="hidden"
           name="PeriodID"
           value="<?= htmlspecialchars($row['PeriodID']) ?>">

    <label>
        الاستهلاك
        <input name="Consumption"
               value="<?= htmlspecialchars($row['Consumption']) ?>">
    </label>

    <label>
        سعر الوحدة
        <input name="Rate"
               value="<?= htmlspecialchars($row['Rate']) ?>">
    </label>

    <label>
        المبلغ
        <input value="<?= htmlspecialchars($row['Amount']) ?>"
               readonly>
    </label>

    <label>
        المتأخرات السابقة
        <input name="PreviousArrears"
               value="<?= htmlspecialchars($row['PreviousArrears'] ?? 0) ?>">
    </label>

    <label>
        المسدد
        <input name="PaidAmount"
               value="<?= htmlspecialchars($row['PaidAmount']) ?>">
    </label>

    <label>
        المتبقي الحالي
        <input value="<?= htmlspecialchars($row['RemainingAmount']) ?>"
               readonly>
    </label>

    <div style="margin-top:15px">

        <button type="submit">
            حفظ التعديلات
        </button>

        <a style="margin-left:10px;color:white"
           href="bills.php?period_id=<?= htmlspecialchars($row['PeriodID']) ?>">
           رجوع
        </a>

    </div>

</form>

</body>
</html>