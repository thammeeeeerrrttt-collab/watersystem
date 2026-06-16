<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

/* جلب ID */
if(!isset($_GET['id'])){
    header("Location: meters.php");
    exit();
}

$id = $_GET['id'];

/* جلب بيانات العداد */
$stmt = $conn->prepare("
SELECT * FROM Meter WHERE MeterID = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$meter = $result->fetch_assoc();

if(!$meter){
    die("Meter not found");
}

/* جلب العملاء */
$customers = $conn->query("SELECT * FROM Customer");

$message = "";

/* تحديث البيانات */
if(isset($_POST['update'])){

    $customerid = $_POST['customerid'];
    $meternumber = $_POST['meternumber'];
    $location = $_POST['location'];
    $status = $_POST['status'];

    $update = $conn->prepare("
        UPDATE Meter 
        SET CustomerID=?, MeterNumber=?, Location=?, Status=?
        WHERE MeterID=?
    ");

    $update->bind_param("isssi", $customerid, $meternumber, $location, $status, $id);

    if($update->execute()){
        $message = "✅ تم التعديل بنجاح";
    } else {
        $message = "❌ خطأ في التعديل";
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>تعديل العداد</title>

<style>
body {
    font-family: Arial;
    background: linear-gradient(135deg,#2193b0,#6dd5ed);
    margin:0;
}

.container {
    width:400px;
    margin:50px auto;
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 0 10px rgba(0,0,0,0.2);
}

h2 {
    text-align:center;
    color:#333;
}

input, select {
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button {
    width:100%;
    padding:10px;
    background:#3b82f6;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:16px;
}

button:hover {
    background:#2563eb;
}

.msg {
    text-align:center;
    font-weight:bold;
    margin:10px 0;
}
</style>

</head>

<body>

<div class="container">

<h2>✏️ تعديل العداد</h2>

<p class="msg"><?php echo $message; ?></p>

<form method="POST">

<!-- العميل -->
<select name="customerid" required>
<option value="">اختر العميل</option>

<?php while($c = $customers->fetch_assoc()) { ?>
<option value="<?php echo $c['CustomerID']; ?>"
<?php if($c['CustomerID'] == $meter['CustomerID']) echo "selected"; ?>>
<?php echo $c['Name']; ?>
</option>
<?php } ?>

</select>

<!-- رقم العداد -->
<input type="text" name="meternumber" 
value="<?php echo $meter['MeterNumber']; ?>" required>

<!-- الموقع -->
<input type="text" name="location" 
value="<?php echo $meter['Location']; ?>" required>

<!-- الحالة -->
<select name="status">
<option value="active" <?php if($meter['Status']=="active") echo "selected"; ?>>
نشط
</option>

<option value="inactive" <?php if($meter['Status']=="inactive") echo "selected"; ?>>
متوقف
</option>
</select>

<button type="submit" name="update">💾 حفظ التعديل</button>

</form>

</div>

</body>
</html>