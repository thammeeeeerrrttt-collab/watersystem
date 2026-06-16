<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

$result = $conn->query("
SELECT 
    Meter.MeterID,
    Meter.MeterNumber,
    Meter.Location,
    Meter.Status,
    Customer.Name
FROM Meter
LEFT JOIN Customer
ON Meter.CustomerID = Customer.CustomerID
WHERE Meter.IsDeleted = 0");

if(!$result){
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>العدادات</title>

<style>
body {
    margin:0;
    font-family:Arial;
    background: linear-gradient(135deg,#2193b0,#6dd5ed);
    overflow:hidden;
}

/* الحاوية */
.container {
    padding:20px;
    animation: fadeIn 1.2s ease;
}

/* العنوان */
h2 {
    color:white;
    text-align:center;
}

/* أزرار عامة */
.btn {
    padding:10px 15px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:bold;
    margin:5px;
}

/* زر إضافة */
.add-btn {
    background:green;
    color:white;
}

/* زر رجوع */
.back-btn {
    background:#374151;
    color:white;
}

/* الجدول */
.table-box {
    margin-top:20px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius:15px;
    padding:15px;
}

table {
    width:100%;
    border-collapse:collapse;
    color:white;
}

th, td {
    padding:10px;
    text-align:center;
}

th {
    border-bottom:2px solid rgba(255,255,255,0.5);
}

tr:hover {
    background: rgba(255,255,255,0.2);
}

/* الحالة */
.active {
    color:#22c55e;
    font-weight:bold;
}

.inactive {
    color:#ef4444;
    font-weight:bold;
}

/* أزرار التعديل والحذف */
.edit-btn {
    background:#f59e0b;
    color:white;
    padding:6px 10px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    margin:2px;
    transition:0.3s;
}

.edit-btn:hover {
    background:#d97706;
    transform: scale(1.05);
}

.delete-btn {
    background:#ef4444;
    color:white;
    padding:6px 10px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    margin:2px;
    transition:0.3s;
}

.delete-btn:hover {
    background:#dc2626;
    transform: scale(1.05);
}

/* أنيميشن */
@keyframes fadeIn {
    from {opacity:0; transform: translateY(30px);}
    to {opacity:1; transform: translateY(0);}
}

/* فقاعات */
.bubble {
    position:absolute;
    bottom:-100px;
    background:rgba(255,255,255,0.3);
    border-radius:50%;
    animation:rise 10s infinite ease-in;
}

@keyframes rise {
    0% {transform: translateY(0);}
    100% {transform: translateY(-800px);}
}
</style>

</head>

<body>

<div class="container">

<h2>💧 إدارة العدادات</h2>

<!-- أزرار -->
<div style="text-align:center;">
    <a href="../index.php">
        <button class="btn back-btn">⬅ الرئيسية</button>
    </a>

    <a href="add_meter.php">
        <button class="btn add-btn">+ إضافة عداد</button>
    </a>
</div>

<!-- جدول -->
<div class="table-box">

<table>

<tr>
<th>ID</th>
<th>العميل</th>
<th>رقم العداد</th>
<th>الموقع</th>
<th>الحالة</th>
<th>الإجراء</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>

<tr>
<td><?php echo $row['MeterID']; ?></td>
<td><?php echo $row['Name']; ?></td>
<td><?php echo $row['MeterNumber']; ?></td>
<td><?php echo $row['Location']; ?></td>

<td class="<?php echo $row['Status']; ?>">
    <?php echo $row['Status']; ?>
</td>

<td>
    <a href="edit_meter.php?id=<?php echo $row['MeterID']; ?>">
        <button class="edit-btn">تعديل</button>
    </a>

    <a href="delete_meter.php?id=<?php echo $row['MeterID']; ?>"
       onclick="return confirm('هل تريد حذف العداد؟');">
        <button class="delete-btn">حذف</button>
    </a>
</td>

</tr>

<?php } ?>

</table>

</div>

</div>

<script>
/* فقاعات */
for(let i=0;i<20;i++){
    let b=document.createElement("div");
    b.className="bubble";
    b.style.left=Math.random()*100+"vw";
    b.style.width=b.style.height=(Math.random()*40+10)+"px";
    b.style.animationDuration=(Math.random()*10+5)+"s";
    document.body.appendChild(b);
}
</script>

</body>
</html>