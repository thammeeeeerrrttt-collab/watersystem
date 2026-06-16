<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

/* ===== جلب الدورات ===== */
$cycles = $conn->query("SELECT * FROM billingcycle");

/* ===== جلب بيانات العميل ===== */
$id = $_GET['id'];

$result = $conn->query("
SELECT * 
FROM Customer 
WHERE CustomerID = $id
");

$customer = $result->fetch_assoc();

$message = "";

/* ===== تحديث البيانات ===== */
if(isset($_POST['update'])) {

    $name       = $_POST['name'];
    $phone      = $_POST['phone'];
    $email      = $_POST['email'];
    $address    = $_POST['address'];
    $cycleid    = $_POST['cycleid'];
    $unitprice  = $_POST['unitprice'];

    $stmt = $conn->prepare("
        UPDATE Customer 
        SET 
            Name=?,
            Phone=?,
            Email=?,
            Address=?,
            CycleID=?,
            UnitPrice=?
        WHERE CustomerID=?
    ");

    $stmt->bind_param(
        "ssssidi",
        $name,
        $phone,
        $email,
        $address,
        $cycleid,
        $unitprice,
        $id
    );

    if($stmt->execute()) {

        $message = "✅ تم تحديث بيانات العميل";

        /* تحديث البيانات المعروضة */
        $customer['Name'] = $name;
        $customer['Phone'] = $phone;
        $customer['Email'] = $email;
        $customer['Address'] = $address;
        $customer['CycleID'] = $cycleid;
        $customer['UnitPrice'] = $unitprice;

    } else {

        $message = "❌ خطأ في التحديث";

    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>تعديل عميل</title>

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
    background:orange;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:16px;
}

button:hover{
    background:#ea580c;
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

<h2>✏️ تعديل العميل</h2>

<div class="msg"><?php echo $message; ?></div>

<form method="POST">

<label>👤 اسم العميل</label>
<input type="text"
       name="name"
       value="<?php echo $customer['Name']; ?>">

<label>📞 رقم الهاتف</label>
<input type="text"
       name="phone"
       value="<?php echo $customer['Phone']; ?>">

<label>📧 البريد الإلكتروني</label>
<input type="email"
       name="email"
       value="<?php echo $customer['Email']; ?>">

<label>🏠 العنوان</label>
<input type="text"
       name="address"
       value="<?php echo $customer['Address']; ?>">

<label>🔄 دورة الاشتراك</label>
<select name="cycleid">

<?php while($row = $cycles->fetch_assoc()) { ?>

<option value="<?php echo $row['CycleID']; ?>"
<?php if($row['CycleID'] == $customer['CycleID']) echo "selected"; ?>>

<?php echo $row['CycleName']; ?>

</option>

<?php } ?>

</select>

<label>💰 سعر الوحدة</label>
<input type="number"
       step="0.01"
       name="unitprice"
       value="<?php echo $customer['UnitPrice']; ?>">

<button type="submit" name="update">
    💾 تحديث العميل
</button>

</form>

</div>

</body>
</html>