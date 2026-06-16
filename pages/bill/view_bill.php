<?php
include "../../db.php";

$id = $_GET['id'];

$bill = $conn->query("
SELECT b.*, c.Name, c.Phone, c.Address
FROM Bill b
JOIN Customer c ON b.CustomerID = c.CustomerID
WHERE b.BillID = $id
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>فاتورة</title>

<style>
body{
    font-family:Arial;
    background:#f1f5f9;
    padding:20px;
}

/* شكل الشاشة */
.box{
    width:60%;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
}

/* شكل الطباعة */
@media print {
    body{
        background:white;
        padding:0;
    }

    .box{
        width:100%;
        border:none;
        box-shadow:none;
    }

    .btn{
        display:none; /* نخفي زر الطباعة */
    }
}

.row{
    display:flex;
    justify-content:space-between;
    border-bottom:1px solid #ddd;
    padding:10px;
}

.btn{
    margin-top:20px;
    display:block;
    text-align:center;
    padding:10px;
    background:#2563eb;
    color:white;
    cursor:pointer;
}
</style>

</head>

<body>

<div class="box">

<h2 style="text-align:center;">💧 فاتورة مياه</h2>

<div class="row"><span>رقم</span><span><?= $bill['BillNumber'] ?></span></div>
<div class="row"><span>العميل</span><span><?= $bill['Name'] ?></span></div>
<div class="row"><span>الهاتف</span><span><?= $bill['Phone'] ?></span></div>
<div class="row"><span>العنوان</span><span><?= $bill['Address'] ?></span></div>

<div class="row"><span>الاستهلاك</span><span><?= number_format($bill['Consumption'],2) ?></span></div>
<div class="row"><span>سعر الوحدة</span><span><?= number_format($bill['Rate'],2) ?></span></div>
<div class="row"><span>المبلغ</span><span><?= number_format($bill['Amount'],2) ?></span></div>
<div class="row"><span>المتبقي</span><span><?= number_format($bill['RemainingAmount'] ?? ($bill['Amount'] - ($bill['PaidAmount'] ?? 0)),2) ?></span></div>
<div class="row"><span>المسدد</span><span><?= number_format($bill['PaidAmount'] ?? 0,2) ?></span></div>
<div class="row"><span>المتأخرات السابقة</span><span><?= number_format($bill['PreviousArrears'] ?? 0,2) ?></span></div>
<div class="row"><span>الحالة</span><span><?= htmlspecialchars($bill['Status']) ?></span></div>
<div class="row"><span>التاريخ</span><span><?= htmlspecialchars($bill['BillDate'] ?? '') ?></span></div>
<div class="row"><span>الدورة</span><span><?= htmlspecialchars($bill['PeriodID'] ?? '') ?></span></div>

<a class="btn" onclick="window.print()">🖨 طباعة الفاتورة</a>

</div>
<script>
window.onload = function(){
    window.print();
}
</script>
</body>
</html>