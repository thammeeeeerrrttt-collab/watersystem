<?php
// قراءة المتغيرات السحابية من سيرفر Railway، وفي حال عدم وجودها يتم الاعتماد على السيرفر المحلي (XAMPP) لكي لا يتعطل مشروعك محلياً
$host     = getenv('MYSQLHOST')     ?: 'localhost';
$user     = getenv('MYSQLUSER')     ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$dbname   = getenv('MYSQLDATABASE') ?: 'water_system';
$port     = getenv('MYSQLPORT')     ?: '3306';

// الاتصال بقاعدة البيانات مع تحديد المنفذ (Port) وهو مهم جداً للسيرفرات السحابية
$conn = new mysqli($host, $user, $password, $dbname, $port);

// فحص الاتصال
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

// ضبط الترميز للغة العربية
$conn->set_charset("utf8mb4");
?>