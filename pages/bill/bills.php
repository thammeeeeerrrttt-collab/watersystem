<?php
session_start();
include "../../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* ===== جلب آخر دورة ===== */
$latestPeriod = $conn->query("
SELECT PeriodID 
FROM billing_period
ORDER BY PeriodID DESC
LIMIT 1
")->fetch_assoc();

/* ===== تحديد الدورة ===== */
if(isset($_GET['period_id'])){
    $periodID = intval($_GET['period_id']);
} elseif(isset($_GET['id'])){
    $periodID = intval($_GET['id']);
} else {
    $periodID = $latestPeriod['PeriodID'] ?? '';
}

/* ===== استقبال الفلاتر الجديدة ===== */
$status = isset($_GET['status']) ? $_GET['status'] : '';
$allowedStatuses = ['Paid','Unpaid','Partial',''];
if(!in_array($status, $allowedStatuses)) $status = '';

// تغيير الفلتر من region إلى location
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$paymentDate = isset($_GET['payment_date']) ? $conn->real_escape_string($_GET['payment_date']) : '';

/* ===== جلب الدورات والمناطق للفلاتر ===== */
$periods = $conn->query("SELECT * FROM billing_period ORDER BY PeriodID DESC");
// جلب المواقع من جدول العدادات بدلاً من جدول العملاء
$locations_result = $conn->query("SELECT DISTINCT Location FROM Meter WHERE Location IS NOT NULL AND Location != ''");

/* ===== جلب الفواتير مع اسم المشترك والموقع ===== */
// نستخدم LEFT JOIN مع استعلام فرعي لجدول العدادات لضمان جلب موقع واحد لكل عميل وعدم تكرار الفواتير
$sql = "SELECT b.*, c.Name, m.Location 
        FROM Bill b 
        JOIN Customer c ON b.CustomerID = c.CustomerID 
        LEFT JOIN (SELECT CustomerID, MAX(Location) as Location FROM Meter GROUP BY CustomerID) m ON c.CustomerID = m.CustomerID
        WHERE 1=1";

/* ===== تطبيق الفلاتر على الاستعلام ===== */
if($periodID != ''){
    $sql .= " AND b.PeriodID = $periodID ";
}
if($status != ''){
    $sql .= " AND b.Status='$status'";
}
if($location != ''){
    $sql .= " AND m.Location='$location'";
}
if($paymentDate != ''){
    // الفلترة بتاريخ السداد فقط (بدون الوقت)
    $sql .= " AND DATE(b.PaymentDate) = '$paymentDate'";
}

$sql .= " ORDER BY b.BillID DESC";
$result = $conn->query($sql);

/* ===== الإحصائيات (تتأثر بالفلاتر المحددة) ===== */
$whereStats = "WHERE 1=1";
$joinStats = " FROM Bill b 
               JOIN Customer c ON b.CustomerID = c.CustomerID 
               LEFT JOIN (SELECT CustomerID, MAX(Location) as Location FROM Meter GROUP BY CustomerID) m ON c.CustomerID = m.CustomerID ";

if($periodID != '') $whereStats .= " AND b.PeriodID=$periodID";
if($status != '') $whereStats .= " AND b.Status='$status'";
if($location != '') $whereStats .= " AND m.Location='$location'";
if($paymentDate != '') $whereStats .= " AND DATE(b.PaymentDate)='$paymentDate'";

// غير المدفوع
$unpaid = $conn->query("
SELECT COUNT(*) as c
$joinStats
$whereStats AND b.Status='Unpaid'
")->fetch_assoc()['c'];

// إجمالي الفواتير
$totalMoney = $conn->query("
SELECT SUM(b.Amount) as s
$joinStats
$whereStats
")->fetch_assoc()['s'];

// إجمالي المسدد (المتحصل) بناءً على الفلترة الحالية
$totalPaid = $conn->query("
SELECT SUM(b.PaidAmount) as tp
$joinStats
$whereStats
")->fetch_assoc()['tp'];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>الفواتير</title>

<style>
body{
    margin:0;
    font-family:Arial;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
}
.header{
    display:flex;
    justify-content:space-between;
    padding:15px;
    background:#111827;
}
.btn{
    padding:10px;
    border-radius:8px;
    text-decoration:none;
    color:white;
}
.back{
    background:#64748b;
}
.stats{
    text-align:center;
    margin:15px;
}
.badge{
    padding:10px;
    border-radius:8px;
    margin:5px;
    display:inline-block;
    font-weight: bold;
}
.red{ background:#ef4444; }
.green{ background:#22c55e; }
.blue{ background:#3b82f6; }

table{
    width:98%;
    margin:20px auto;
    border-collapse:collapse;
    background:rgba(255,255,255,0.05);
    font-size: 14px;
}
th,td{
    padding:10px 8px;
    text-align:center;
    border-bottom:1px solid #334155;
}
th{ background:#1f2937; }

.action{
    padding:6px 10px;
    border-radius:8px;
    text-decoration:none;
    color:white;
}
.actions{ display:flex; gap:6px; justify-content:center; flex-wrap: wrap;}
.action.small{ padding:6px; width:45px; display:inline-flex; align-items:center; justify-content:center; font-size:12px;}

.filter-form{
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top:15px;
    flex-wrap: wrap;
    background: rgba(0,0,0,0.2);
    padding: 15px;
    border-radius: 8px;
    width: 95%;
    margin-left: auto;
    margin-right: auto;
}
select, input[type="date"], button{
    padding:10px;
    border-radius:8px;
    border:none;
    font-family: inherit;
}
.filter-btn { background: #3b82f6; color: white; cursor: pointer; font-weight: bold; }
.filter-btn:hover { background: #2563eb; }
</style>
</head>
<body>

<div class="header">
    <h2>💰 نظام الفواتير</h2>
    <a href="../periods.php" class="btn back">⬅ رجوع</a>
</div>

<div class="stats">
    <span class="badge red">
        🔔 غير المدفوع: <?= $unpaid ?>
    </span>
    <span class="badge green">
        💰 إجمالي مبلغ الفواتير: <?= number_format($totalMoney ?? 0, 2) ?>
    </span>
    <span class="badge blue">
        💵 إجمالي المسدد (المتحصل): <?= number_format($totalPaid ?? 0, 2) ?>
    </span>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] === 'recalc'): ?>
    <div style="text-align:center;margin-top:8px;">
        <span class="badge green">✅ تم إعادة حساب المتأخرات السابقة</span>
    </div>
<?php endif; ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div style="text-align:center;margin-top:8px;">
        <span class="badge green">✅ تم حذف الفاتورة</span>
    </div>
<?php endif; ?>

<!-- ===== الفلاتر ===== -->
<form method="GET" class="filter-form">
    
    <select name="period_id">
        <option value="">كل الدورات</option>
        <?php while($p = $periods->fetch_assoc()): ?>
            <option value="<?= $p['PeriodID'] ?>" <?= ($periodID == $p['PeriodID']) ? 'selected' : '' ?>>
                <?= $p['PeriodName'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select name="status">
        <option value="">كل الحالات</option>
        <option value="Paid" <?= ($status=='Paid')?'selected':'' ?>>مدفوع</option>
        <option value="Unpaid" <?= ($status=='Unpaid')?'selected':'' ?>>غير مدفوع</option>
        <option value="Partial" <?= ($status=='Partial')?'selected':'' ?>>دفع جزء</option>
    </select>

    <!-- تم تغيير name إلى location واستخدام المواقع بدلاً من المناطق -->
    <select name="location">
        <option value="">كل المواقع</option>
        <?php while($r = $locations_result->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($r['Location']) ?>" <?= ($location == $r['Location']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['Location']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <input type="date" name="payment_date" value="<?= htmlspecialchars($paymentDate) ?>" title="تاريخ السداد">

    <button type="submit" class="filter-btn">🔍 بحث وفلترة</button>
</form>

<form method="POST" action="recalc_previous_arrears.php" style="text-align: center; margin-top: 15px;">
    <input type="hidden" name="period_id" value="<?= htmlspecialchars($periodID) ?>">
    <button type="submit" style="background:#f59e0b; color:white; font-weight:bold; cursor:pointer; padding:10px; border:none; border-radius:8px;">إعادة حساب المتأخرات السابقة لهذه الدورة</button>
</form>

<table>
<tr>
    <th>رقم الفاتورة</th>
    <th>العميل</th>
    <th>الموقع</th> <!-- تم التغيير إلى الموقع -->
    <th>الاستهلاك</th>
    <th>سعر الوحدة</th>
    <th>المبلغ</th>
    <th>المتأخرات</th>
    <th>الإجمالي المستحق</th>
    <th>المسدد</th>
    <th>المتبقي</th>
    <th>تاريخ السداد</th>
    <th>الحالة</th>
    <th>العمليات</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['BillNumber'] ?? $row['BillID']) ?></td>
    <td><?= htmlspecialchars($row['Name']) ?></td>
    <td><?= htmlspecialchars($row['Location'] ?? 'غير محدد') ?></td> <!-- تم التغيير لعرض Location -->
    <td><?= number_format($row['Consumption'], 2) ?></td>
    <td><?= number_format($row['Rate'], 2) ?></td>

    <?php
        $amount = isset($row['Amount']) ? floatval($row['Amount']) : 0.0;
        $previous = isset($row['PreviousArrears']) ? floatval($row['PreviousArrears']) : 0.0;
        $paid = isset($row['PaidAmount']) ? floatval($row['PaidAmount']) : 0.0;
        
        $totalDue = $amount + $previous;
        $remaining = $totalDue - $paid;
    ?>

    <td><?= number_format($amount, 2) ?></td>
    <td><?= number_format($previous, 2) ?></td>
    <td><?= number_format($totalDue, 2) ?></td>
    <td style="color:#4ade80; font-weight:bold;"><?= number_format($paid, 2) ?></td>
    <td><?= number_format($remaining, 2) ?></td>
    
    <td style="font-size: 12px; color: #cbd5e1;">
        <?php 
            if(!empty($row['PaymentDate'])){
                echo date('Y-m-d', strtotime($row['PaymentDate'])); 
            } else {
                echo "-";
            }
        ?>
    </td>
    
    <td><?= htmlspecialchars($row['Status']) ?></td>

    <td class="actions">
        <a class="action small" style="background:#22c55e;" href="pay_bill.php?id=<?php echo $row['BillID']; ?>">دفع</a>
        <a class="action small" style="background:#3b82f6;" href="view_bill.php?id=<?php echo $row['BillID']; ?>">عرض</a>
        <a class="action small" style="background:#f59e0b;" href="edit_bill.php?id=<?php echo $row['BillID']; ?>">تعديل</a>
        <a class="action small" style="background:#64748b;" href="print_bill.php?id=<?php echo $row['BillID']; ?>" target="_blank">طباعة</a>
        <a class="action small" style="background:#ef4444;" href="delete_bill.php?id=<?php echo $row['BillID']; ?>&period_id=<?php echo $periodID; ?>" onclick="return confirm('هل أنت متأكد من حذف الفاتورة؟');">حذف</a>
    </td>
</tr>
<?php endwhile; ?>

</table>

</body>
</html>