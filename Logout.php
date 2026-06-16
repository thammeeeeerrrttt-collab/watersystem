<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html>
<head>
<title>Logout</title>

<style>
body{
    margin:0;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    font-family:Arial;
    background: linear-gradient(-45deg,#0f172a,#1e3a8a,#0ea5e9,#38bdf8);
    background-size:400% 400%;
    animation:bg 10s ease infinite;
    color:white;
}

.box{
    text-align:center;
    background:rgba(0,0,0,0.5);
    padding:30px;
    border-radius:15px;
}
</style>
</head>

<body>

<div class="box">
    <h2>تم تسجيل الخروج 👋</h2>
    <p>جاري تحويلك لصفحة الدخول...</p>
</div>

<script>
setTimeout(() => {
    window.location.href = "login.php";
}, 2000);
</script>

</body>
</html>