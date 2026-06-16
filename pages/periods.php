<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* ===== جلب الدورات ===== */
$result = $conn->query("SELECT * FROM billing_period ORDER BY PeriodID DESC");
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>الدورات</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    padding:15px;
    background:#111827;
}

/* BUTTONS */
.btn{
    padding:10px 14px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-weight:bold;
    margin:3px;
    display:inline-block;
}

.add{background:#22c55e;}
.enter{background:#3b82f6;}
.close{background:#ef4444;}
.delete{background:#dc2626;}
.back{background:#64748b;}

.btn.small{padding:6px 8px;font-size:13px}

/* TABLE */
table{
    width:95%;
    margin:20px auto;
    border-collapse:collapse;
    background:rgba(255,255,255,0.05);
}

th,td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #334155;
}

th{
    background:#1f2937;
}

/* STATUS */
.open{
    background:#22c55e;
    padding:5px 10px;
    border-radius:6px;
}

.closed{
    background:#ef4444;
    padding:5px 10px;
    border-radius:6px;
}

/* MESSAGE */
.msg{
    text-align:center;
    margin-top:10px;
    color:#22c55e;
}

</style>

</head>

<body>

<!-- HEADER -->
<div class="header">
    <h2>📅 إدارة الدورات</h2>

    <div>
        <a href="add_period.php" class="btn add">➕ إنشاء دورة</a>
        <a href="../index.php" class="btn back">⬅ الرئيسية</a>
    </div>
</div>

<!-- MESSAGE -->
<?php if(isset($_GET['msg'])): ?>
<div class="msg">
    <?php
    if($_GET['msg'] == 'closed'){
        echo "✅ تم إغلاق الدورة وإنشاء الفواتير";
    }
    ?>
</div>
<?php endif; ?>

<!-- TABLE -->
<table>

<tr>
    <th>ID</th>
    <th>اسم الدورة</th>
    <th>من</th>
    <th>إلى</th>
    <th>الحالة</th>
    <th>التحكم</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>

<tr>
    <td><?= $row['PeriodID'] ?></td>
    <td><?= $row['PeriodName'] ?></td>
    <td><?= $row['StartDate'] ?></td>
    <td><?= $row['EndDate'] ?></td>

    <td>
        <?php if($row['Status'] == 'Open'): ?>
            <span class="open">🟢 مفتوحة</span>
        <?php else: ?>
            <span class="closed">🔴 مغلقة</span>
        <?php endif; ?>
    </td>

    <td>

        <!-- دخول القراءات -->
        <a class="btn enter"
           href="cycle_readings.php?id=<?= $row['PeriodID'] ?>">
           📊 إدخال
        </a>

<?php if($row['Status'] == 'Open'): ?>

    <a class="btn close"
       href="close_period.php?id=<?= $row['PeriodID'] ?>"
       onclick="return confirm('هل تريد إغلاق الدورة؟')">
       🔒 إغلاق
    </a>

<?php else: ?>

    <a class="btn open"
       href="open_period.php?id=<?= $row['PeriodID'] ?>"
       onclick="return confirm('هل تريد إعادة فتح الدورة؟')">
       🔓 فتح
    </a>

<?php endif; ?>

          <!-- عرض الفواتير -->
          <a class="btn small" href="bill/bills.php?id=<?= $row['PeriodID'] ?>">💰 الفواتير</a>

        <!-- حذف (Admin فقط) -->
        <?php if((isset($_SESSION['Role']) && strtolower($_SESSION['Role']) == 'admin') || (isset($_SESSION['IsAdmin']) && $_SESSION['IsAdmin'])): ?>
          <a class="btn delete small"
              href="clean_period.php?id=<?= $row['PeriodID'] ?>"
              onclick="return confirm('⚠ هل تريد تنظيف (حذف البيانات داخل) هذه الدورة؟')"
              title="تنظيف محتوى الدورة">
              🧹
          </a>
          <?php endif; ?>

    </td>
</tr>

<?php endwhile; ?>

</table>

</body>
</html>