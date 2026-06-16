<?php
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$id = intval($_GET['id']);

/* =====================================
   جلب بيانات الفاتورة
===================================== */

$bill = $conn->query("
SELECT b.*, c.Name
FROM Bill b
JOIN Customer c ON b.CustomerID = c.CustomerID
WHERE b.BillID = $id
")->fetch_assoc();

if(!$bill){
    die("❌ الفاتورة غير موجودة");
}

/* =====================================
   التأكد من الأعمدة
===================================== */

$columns = [
    "PreviousArrears" => "ALTER TABLE Bill ADD PreviousArrears DECIMAL(12,2) DEFAULT 0",
    "PaidAmount" => "ALTER TABLE Bill ADD PaidAmount DECIMAL(12,2) DEFAULT 0",
    "RemainingAmount" => "ALTER TABLE Bill ADD RemainingAmount DECIMAL(12,2) DEFAULT 0",
    "PaymentDate" => "ALTER TABLE Bill ADD PaymentDate DATETIME NULL" 
];

foreach($columns as $col => $sql){
    $check = $conn->query("SHOW COLUMNS FROM Bill LIKE '$col'");
    if($check->num_rows == 0){
        $conn->query($sql);
    }
}

/* =====================================
   رسالة
===================================== */
$message = "";

/* =====================================
   الدفع
===================================== */
if(isset($_POST['pay'])){

    $payment = floatval($_POST['payment']);

    /* =====================================
       بيانات الفاتورة الحالية
    ===================================== */
    $amount = floatval($bill['Amount']);
    $previousArrears = floatval($bill['PreviousArrears']);
    $currentPaid = floatval($bill['PaidAmount']);

    /* =====================================
       الإجمالي المستحق
    ===================================== */
    $totalDue = $amount + $previousArrears;

    /* =====================================
       إجمالي المسدد
    ===================================== */
    $newPaid = $currentPaid + $payment;

    /* =====================================
       المتبقي
       يسمح بالسالب
    ===================================== */
    $remaining = $totalDue - $newPaid;

    /* =====================================
       الحالة
    ===================================== */
    if($remaining <= 0){
        $status = "Paid";
    } elseif($newPaid > 0){
        $status = "Partial";
    } else {
        $status = "Unpaid";
    }

    /* =====================================
       تحديث الفاتورة (تم إضافة تاريخ السداد)
    ===================================== */
    // إذا كان هناك مبلغ مدفوع جديد، قم بتحديث التاريخ إلى الوقت الحالي
    $paymentDateQuery = "";
    if ($payment > 0) {
       $paymentDateQuery = "PaymentDate = NOW(),";
    }
    
    $stmt = $conn->prepare("
    UPDATE Bill
    SET 
        PaidAmount = ?, 
        RemainingAmount = ?, 
        $paymentDateQuery
        Status = ?
    WHERE BillID = ?
    ");

    $stmt->bind_param(
        "ddsi",
        $newPaid,
        $remaining,
        $status,
        $id
    );

    $stmt->execute();
    $stmt->close();

    /* =====================================
       تحديث جدول المتأخرات
    ===================================== */
    $checkAr = $conn->query("
    SELECT ArrearID
    FROM PreviousArrearsRecords
    WHERE BillID = $id
    ");

    if($checkAr->num_rows > 0){
        $ar = $checkAr->fetch_assoc();
        $arID = intval($ar['ArrearID']);

        $conn->query("
        UPDATE PreviousArrearsRecords
        SET RemainingAmount = $remaining
        WHERE ArrearID = $arID
        ");
    } else {
        $customerID = intval($bill['CustomerID']);
        $periodID = intval($bill['PeriodID']);

        $conn->query("
        INSERT INTO PreviousArrearsRecords
        (
            BillID,
            CustomerID,
            PeriodID,
            RemainingAmount
        )
        VALUES
        (
            $id,
            $customerID,
            $periodID,
            $remaining
        )
        ");
    }

    /* =====================================
       تحديث القيم المعروضة
    ===================================== */
    $bill['PaidAmount'] = $newPaid;
    $bill['RemainingAmount'] = $remaining;
    $bill['Status'] = $status;

    /* =====================================
       الرسالة
    ===================================== */
    $message = "✅ تم السداد بنجاح";

    if($remaining < 0){
        $message .= "<br>🔵 رصيد لدى العميل: " . number_format(abs($remaining),2);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سداد فاتورة</title>
<style>
body{
    margin:0;
    font-family:Arial;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
}

.container{
    width:430px;
    margin:40px auto;
    background:rgba(255,255,255,0.05);
    padding:25px;
    border-radius:15px;
}

h2{
    text-align:center;
}

.info{
    background:rgba(255,255,255,0.08);
    padding:12px;
    border-radius:10px;
    margin-bottom:10px;
}

.credit{
    background:rgba(34,197,94,0.08);
    color:#bbf7d0;
}

input{
    width:100%;
    padding:12px;
    margin-top:10px;
    border:none;
    border-radius:8px;
    box-sizing:border-box;
}

button{
    width:100%;
    padding:12px;
    margin-top:15px;
    border:none;
    border-radius:8px;
    background:#22c55e;
    color:white;
    cursor:pointer;
    font-size:16px;
}

.back{
    display:inline-block;
    margin-bottom:15px;
    background:#64748b;
    color:white;
    padding:8px 12px;
    border-radius:8px;
    text-decoration:none;
}

.msg{
    text-align:center;
    margin-top:15px;
    font-weight:bold;
}
</style>
</head>
<body>

<div class="container">

<a href="bills.php?period_id=<?= $bill['PeriodID'] ?>" class="back">
⬅ رجوع
</a>

<h2>💳 سداد فاتورة</h2>

<div class="info">
👤 العميل:
<b><?= htmlspecialchars($bill['Name']) ?></b>
</div>

<div class="info">
📊 الاستهلاك:
<b><?= number_format($bill['Consumption'],2) ?></b>
</div>

<div class="info">
🏷 سعر الوحدة:
<b><?= number_format($bill['Rate'],2) ?></b>
</div>

<div class="info">
💰 مبلغ الاستهلاك:
<b><?= number_format($bill['Amount'],2) ?></b>
</div>

<div class="info">
🟠 المتأخرات السابقة:
<b><?= number_format($bill['PreviousArrears'],2) ?></b>
</div>

<?php
$totalDue = floatval($bill['Amount']) + floatval($bill['PreviousArrears']);
?>

<div class="info">
💵 الإجمالي المستحق:
<b><?= number_format($totalDue,2) ?></b>
</div>

<div class="info">
✅ المسدد (الإجمالي):
<b><?= number_format($bill['PaidAmount'],2) ?></b>
</div>

<?php if($bill['RemainingAmount'] < 0): ?>
<div class="info credit">
🔵 رصيد لدى العميل:
<b><?= number_format(abs($bill['RemainingAmount']),2) ?></b>
</div>
<?php else: ?>
<div class="info">
🟠 المتبقي:
<b><?= number_format($bill['RemainingAmount'],2) ?></b>
</div>
<?php endif; ?>

<form method="POST">
<input
type="number"
step="0.01"
name="payment"
placeholder="أدخل مبلغ السداد الحالي"
required>

<button type="submit" name="pay">
💵 تأكيد السداد
</button>
</form>

<div class="msg">
<?= $message ?>
</div>

</div>

</body>
</html>