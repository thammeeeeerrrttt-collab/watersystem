<?php
session_start();
include "db.php";

$error = "";

if(isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. البحث عن اسم المستخدم مع تصحيح أسماء الجداول لحروف صغيرة (employee و role)
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.Name, e.Username, e.Password, e.DeviceToken, e.Location, r.RoleName
        FROM employee e
        JOIN role r ON e.RoleID = r.RoleID
        WHERE e.Username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result && $result->num_rows == 1) {

        $row = $result->fetch_assoc();
        
        $db_password = $row['Password']; 
        
        // 2. التحقق من كلمة المرور
        if (password_verify($password, $db_password) || $password === $db_password) {
            
            $employee_id = $row['EmployeeID'];
            $db_device_token = $row['DeviceToken'];
            
            // جلب الرمز المحفوظ في متصفح المستخدم
            $browser_cookie_token = $_COOKIE['emp_device_id'] ?? '';

            // الحالة الأولى: الموظف يدخل لأول مرة
            if (empty($db_device_token)) {
                
                $new_token = bin2hex(random_bytes(32)); 
                
                setcookie('emp_device_id', $new_token, time() + (86400 * 365), "/"); 
                
                // تصحيح اسم الجدول هنا أيضاً إلى حروف صغيرة (employee)
                $conn->query("UPDATE employee SET DeviceToken = '$new_token' WHERE EmployeeID = $employee_id");
                
                $_SESSION['EmployeeID'] = $row['EmployeeID'];
                $_SESSION['Name'] = $row['Name'];
                $_SESSION['Role'] = $row['RoleName'];
                $_SESSION['Location'] = $row['Location']; 

                header("Location: index.php");
                exit();

            } 
            // الحالة الثانية: الموظف لديه جهاز مربوط مسبقاً
            else {
                if ($browser_cookie_token === $db_device_token) {
                    $_SESSION['EmployeeID'] = $row['EmployeeID'];
                    $_SESSION['Name'] = $row['Name'];
                    $_SESSION['Role'] = $row['RoleName'];
                    $_SESSION['Location'] = $row['Location']; 

                    header("Location: index.php");
                    exit();
                } else {
                    $error = "❌ غير مصرح لك بالدخول من هذا الجهاز. يرجى التواصل مع الإدارة لفك الارتباط.";
                }
            }
            
        } else {
            $error = "❌ كلمة المرور غير صحيحة";
        }

    } else {
        $error = "❌ اسم المستخدم غير صحيح أو فشل الاستعلام";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول</title>

<style>
body{
    margin:0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(-45deg,#0f172a,#1e3a8a,#0ea5e9,#38bdf8);
    background-size:400% 400%;
    animation:bg 10s ease infinite;
}

@keyframes bg{
    0%{background-position:0% 50%}
    50%{background-position:100% 50%}
    100%{background-position:0% 50%}
}

.box{
    background:white;
    padding:40px 30px;
    border-radius:15px;
    width:320px;
    text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,0.5);
}

.box h2 {
    margin-top: 0;
    color: #1e293b;
    margin-bottom: 25px;
}

input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
    box-sizing: border-box;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 5px rgba(14, 165, 233, 0.3);
}

button{
    width:100%;
    padding:12px;
    margin-top: 15px;
    border:none;
    border-radius:8px;
    background:#0ea5e9;
    color:white;
    font-size: 16px;
    font-weight: bold;
    cursor:pointer;
    transition: 0.3s;
}

button:hover {
    background: #0284c7;
}

.error{
    color:#ef4444;
    background: #fee2e2;
    padding: 10px;
    border-radius: 8px;
    margin-bottom:15px;
    font-size: 14px;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="box">

    <h2>💧 تسجيل الدخول</h2>

    <?php if($error != "") echo "<div class='error'>$error</div>"; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="اسم المستخدم" required autocomplete="off">
        <input type="password" name="password" placeholder="كلمة المرور" required>
        <button type="submit" name="login">دخول</button>
    </form>

</div>

</body>
</html>
