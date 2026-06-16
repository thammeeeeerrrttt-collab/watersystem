<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* ===== جلب أنواع الدورات (شهري / نص شهر / ... ) ===== */
$cycles = $conn->query("SELECT * FROM billingcycle");

/* ===== حفظ البيانات ===== */
if(isset($_POST['save'])){

    $name   = $_POST['name'];
    $start  = $_POST['start'];
    $end    = $_POST['end'];
    $cycle  = $_POST['cycle_id'];

    // تحقق بسيط
    if($start > $end){
        $error = "❌ تاريخ البداية أكبر من النهاية";
    } else {

        $conn->query("
        INSERT INTO billing_period 
        (CycleID, PeriodName, StartDate, EndDate)
        VALUES ($cycle, '$name', '$start', '$end')
        ");

        header("Location: periods.php?msg=added");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>إنشاء دورة</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
}

/* FORM */
.container{
    width:400px;
    margin:60px auto;
    background:rgba(255,255,255,0.05);
    padding:25px;
    border-radius:12px;
    backdrop-filter:blur(10px);
}

h2{
    text-align:center;
    margin-bottom:20px;
}

input, select{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:none;
    border-radius:8px;
}

/* BUTTONS */
.btn{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
    margin-top:10px;
}

.save{background:#22c55e;color:white;}
.back{background:#64748b;color:white;display:block;text-align:center;text-decoration:none;padding:10px;border-radius:8px;margin-top:10px;}

.error{
    text-align:center;
    color:#ef4444;
}

</style>

</head>

<body>

<div class="container">

<h2>➕ إنشاء دورة جديدة</h2>

<?php if(isset($error)): ?>
<div class="error"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

    <label>اسم الدورة</label>
    <input type="text" name="name" placeholder="مثال: مارس - 1" required>

    <label>نوع الدورة</label>
    <select name="cycle_id" required>
        <option value="">اختر</option>
        <?php while($c = $cycles->fetch_assoc()): ?>
            <option value="<?= $c['CycleID'] ?>">
                <?= $c['CycleName'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>من تاريخ</label>
    <input type="date" name="start" required>

    <label>إلى تاريخ</label>
    <input type="date" name="end" required>

    <button type="submit" name="save" class="btn save">💾 حفظ</button>

</form>

<a href="periods.php" class="back">⬅ رجوع</a>

</div>

</body>
</html>