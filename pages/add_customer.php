<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

$message = "";

/* جلب الدورات */
$cycles = $conn->query("SELECT * FROM billingcycle");

if(isset($_POST['submit'])) {

    $name       = $_POST['name'];
    $phone      = $_POST['phone'];
    $email      = $_POST['email'];
    $address    = $_POST['address'];
    $cycleid    = $_POST['cycleid'];
    $unitprice  = $_POST['unitprice'];

    if($name != "" && $phone != "") {

        $stmt = $conn->prepare("
            INSERT INTO Customer 
            (Name, Phone, Email, Address, CycleID, UnitPrice)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssid",
            $name,
            $phone,
            $email,
            $address,
            $cycleid,
            $unitprice
        );

        if($stmt->execute()) {
            $message = "✅ تم إضافة العميل بنجاح";
        } else {
            $message = "❌ خطأ أثناء الحفظ";
        }

        $stmt->close();

    } else {
        $message = "⚠ الاسم ورقم الهاتف مطلوبين";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>إضافة عميل</title>

<style>

body{
    font-family:Arial;
    background:#f1f5f9;
}

.container{
    width:420px;
    margin:40px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
}

input, select{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;
}

button{
    width:100%;
    padding:12px;
    background:green;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:16px;
}

button:hover{
    background:#15803d;
}

.back{
    display:inline-block;
    margin-bottom:10px;
    background:#6b7280;
    color:white;
    padding:8px 12px;
    border-radius:5px;
    text-decoration:none;
}

.msg{
    text-align:center;
    font-weight:bold;
    margin-bottom:10px;
}

label{
    font-weight:bold;
    display:block;
    margin-top:10px;
}

</style>

</head>

<body>

<div class="container">

<a href="customers.php" class="back">⬅ رجوع</a>

<h2>➕ إضافة عميل</h2>

<div class="msg"><?php echo $message; ?></div>

<form method="POST">

<label>👤 اسم العميل</label>
<input type="text" name="name" placeholder="اسم العميل">

<label>📞 رقم الهاتف</label>
<input type="text" name="phone" placeholder="رقم الهاتف">

<label>📧 البريد الإلكتروني</label>
<input type="email" name="email" placeholder="البريد الإلكتروني">

<label>🏠 العنوان</label>
<input type="text" name="address" placeholder="العنوان">

<label>🔄 دورة الاشتراك</label>
<select name="cycleid">

    <option value="">-- اختر دورة الاشتراك --</option>

    <?php while($row = $cycles->fetch_assoc()) { ?>

        <option value="<?php echo $row['CycleID']; ?>">
            <?php echo $row['CycleName']; ?>
        </option>

    <?php } ?>

</select>

<label>💰 سعر الوحدة</label>
<input type="number"
       step="0.01"
       name="unitprice"
       placeholder="مثال: 0.5">

<button type="submit" name="submit">
    💾 حفظ العميل
</button>

</form>

</div>

</body>
</html>