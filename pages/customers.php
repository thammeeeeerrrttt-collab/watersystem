<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])) {
    header("Location: ../login.php");
    exit();
}

$search = "";

if(isset($_GET['search'])) {
    $search = $_GET['search'];
}

if($search != "") {

    $sql = "
        SELECT 
            Customer.CustomerID,
            Customer.Name,
            Customer.Phone,
            Customer.Email,
            Customer.Address,
            Customer.DateCreated,
            billingcycle.CycleName
        FROM Customer
        LEFT JOIN billingcycle
        ON Customer.CycleID = billingcycle.CycleID
        WHERE Customer.Name LIKE '%$search%'
        OR Customer.Phone LIKE '%$search%'
    ";

} else {

    $sql = "
        SELECT 
            Customer.CustomerID,
            Customer.Name,
            Customer.Phone,
            Customer.Email,
            Customer.Address,
            Customer.DateCreated,
            billingcycle.CycleName
        FROM Customer
        LEFT JOIN billingcycle
        ON Customer.CycleID = billingcycle.CycleID
    ";
}

$result = $conn->query($sql);

/* 🔴 مهم للتصحيح */
if(!$result){
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>المشتركين</title>

<style>
body{
    font-family:Arial;
    margin:0;
    background:#f1f5f9;
}
.container{ padding:20px; }

.search-box input{
    padding:8px;
    width:300px;
}

.add-btn{
    padding:8px 12px;
    background:green;
    color:white;
    border:none;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    margin-top:10px;
}

th, td{
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#0ea5e9;
    color:white;
}

.edit-btn{
    background:orange;
    color:white;
    padding:5px 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
</style>

</head>

<body>

<div class="container">

<a href="../index.php">⬅ الرئيسية</a>

<h2>👥 إدارة المشتركين</h2>

<form method="GET" class="search-box">
    <input type="text" name="search" value="<?php echo $search; ?>">
    <button type="submit">بحث</button>
</form>

<a href="add_customer.php">
    <button class="add-btn">+ إضافة عميل</button>
</a>

<table>

<tr>
    <th>ID</th>
    <th>الاسم</th>
    <th>الهاتف</th>
    <th>الإيميل</th>
    <th>العنوان</th>
    <th>الدورة</th>
    <th>تاريخ التسجيل</th>
    <th>الإجراء</th>
</tr>

<?php
if($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
?>

<tr>
    <td><?php echo $row['CustomerID']; ?></td>
    <td><?php echo $row['Name']; ?></td>
    <td><?php echo $row['Phone']; ?></td>
    <td><?php echo $row['Email']; ?></td>
    <td><?php echo $row['Address']; ?></td>
    <td><?php echo $row['CycleName']; ?></td>
    <td><?php echo $row['DateCreated']; ?></td>

    <td>
        <a href="edit_customer.php?id=<?php echo $row['CustomerID']; ?>">
            <button class="edit-btn">تعديل</button>
            
            <a href="delete_customer.php?id=<?php echo $row['CustomerID']; ?>"
   onclick="return confirm('هل أنت متأكد من الحذف؟');">
    <button style="
        padding:5px 10px;
        background:red;
        color:white;
        border:none;
        border-radius:5px;
        cursor:pointer;
    ">حذف</button>
</a>
        </a>
    </td>
</tr>

<?php
    }
} else {
    echo "<tr><td colspan='8'>لا يوجد بيانات</td></tr>";
}
?>

</table>

</div>

</body>
</html>