<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

$periodID = $_GET['id'];

/* ===== حالة الدورة ===== */
$status = $conn->query("
SELECT Status FROM billing_period WHERE PeriodID=$periodID
")->fetch_assoc();

/* ===== العدادات ===== */
$meters = $conn->query("
SELECT 
    m.MeterID,
    m.MeterNumber,
    c.Name,

    (
        SELECT CurrentReading
        FROM Reading r
        WHERE r.MeterID = m.MeterID
        ORDER BY r.ReadingID DESC
        LIMIT 1
    ) AS LastReading

FROM Meter m
JOIN Customer c ON c.CustomerID = m.CustomerID
");

// نهيئ المتغيرات لمجاميع الدورة الحالية (ستُملأ أثناء دورة العرض)
$total_prev = 0.0;
$total_curr = 0.0;
$total_cons = 0.0;
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إدخال القراءات</title>

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

/* cards */
.cards{
    display:flex;
    justify-content:center;
    gap:15px;
    margin:20px;
}

.card{
    background:rgba(255,255,255,0.08);
    padding:20px;
    border-radius:12px;
    width:200px;
    text-align:center;
}

/* table */
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

th{background:#1f2937;}

input{
    padding:8px;
    border-radius:6px;
    border:none;
    width:80px;
    text-align:center;
}

.btn{
    padding:10px;
    background:#22c55e;
    border:none;
    border-radius:8px;
    color:white;
}

.back{
    background:#64748b;
    padding:8px;
    text-decoration:none;
    color:white;
    border-radius:8px;
}
</style>
</head>

<body>

<div class="header">
    <h2>📊 إدخال القراءات</h2>
    <a href="periods.php" class="back">⬅ رجوع</a>
</div>

<!-- LIVE TOTALS -->
<div class="cards">
    <div class="card">
        <h3 id="total_prev">0</h3>
        <p>📊 السابق</p>
    </div>

    <div class="card">
        <h3 id="total_curr">0</h3>
        <p>📊 الحالي</p>
    </div>

    <div class="card">
        <h3 id="total_cons">0</h3>
        <p>💧 الاستهلاك</p>
    </div>
</div>

<form method="POST" action="save_cycle_readings.php">

<input type="hidden" name="period_id" value="<?= $periodID ?>">

<table>

<tr>
    <th>العميل</th>
    <th>العداد</th>
    <th>السابق</th>
    <th>الحالي</th>
    <th>الاستهلاك</th>
</tr>

<?php while($m = $meters->fetch_assoc()): ?>

<?php
    // جلب القراءة المحفوظة لهذه الدورة إن وجدت
    $existing = $conn->query("SELECT PreviousReading, CurrentReading FROM Reading WHERE MeterID = {$m['MeterID']} AND PeriodID = $periodID LIMIT 1")->fetch_assoc();

    if($existing){
        $prev = $existing['PreviousReading'];
        $curr = $existing['CurrentReading'];
    } else {
        $prev = $m['LastReading'] ?? 0;
        $curr = $m['LastReading'] ?? 0;
    }

    $cons = $curr - $prev;

    // تراكم المجاميع لعرضها في البطاقات (JS سيعرض أيضاً عند التعديل)
    $total_prev += floatval($prev);
    $total_curr += floatval($curr);
    $total_cons += floatval($cons);
?>

<tr>
    <td><?= $m['Name'] ?></td>
    <td><?= $m['MeterNumber'] ?></td>

    <td class="prev"><?= $prev ?></td>

    <td>
        <input type="number"
               name="reading[<?= $m['MeterID'] ?>]"
               class="current"
               value="<?= $curr ?>"
               min="<?= $prev ?>"
               oninput="updateRow(this)">
    </td>

    <td class="cons"><?= $cons ?></td>
</tr>

<?php endwhile; ?>

</table>

<div style="text-align:center;">
    <button class="btn">💾 حفظ</button>
</div>

</form>

<script>
function updateRow(input){

    let row = input.closest("tr");

    let prev = parseFloat(row.querySelector(".prev").innerText);
    let current = parseFloat(input.value) || 0;

    let consCell = row.querySelector(".cons");

    if(current < prev){
        input.style.border="2px solid red";
        consCell.innerText="❌";
        return;
    }

    input.style.border="none";

    let cons = current - prev;
    consCell.innerText = cons;

    updateTotals();
}

function updateTotals(){

    let prevs = document.querySelectorAll(".prev");
    let currents = document.querySelectorAll(".current");
    let conss = document.querySelectorAll(".cons");

    let total_prev=0, total_curr=0, total_cons=0;

    prevs.forEach(e=> total_prev += parseFloat(e.innerText));
    currents.forEach(e=> total_curr += parseFloat(e.value||0));
    conss.forEach(e=> total_cons += parseFloat(e.innerText||0));

    document.getElementById("total_prev").innerText = total_prev;
    document.getElementById("total_curr").innerText = total_curr;
    document.getElementById("total_cons").innerText = total_cons;
}
// حساب المجموعات عند تحميل الصفحة
window.addEventListener('load', function(){
    updateTotals();
});
</script>

</body>
</html>