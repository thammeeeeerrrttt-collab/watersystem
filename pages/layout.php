<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title><?= $title ?? "Dashboard" ?></title>

<script src="https://cdn.tailwindcss.com"></script>

<style>
body{
    direction: rtl;
}
</style>

</head>

<body class="bg-gray-100">

<div class="flex">

    <!-- Sidebar -->
    <div class="w-64 bg-gray-900 text-white min-h-screen p-4">

        <h2 class="text-xl font-bold mb-6">💧 نظام المياه</h2>

        <ul class="space-y-3">

            <li><a href="../index.php" class="block hover:bg-gray-700 p-2 rounded">🏠 الرئيسية</a></li>

            <li><a href="customers.php" class="block hover:bg-gray-700 p-2 rounded">👥 المشتركين</a></li>

            <li><a href="meters.php" class="block hover:bg-gray-700 p-2 rounded">📟 العدادات</a></li>

            <li><a href="periods.php" class="block hover:bg-gray-700 p-2 rounded">📅 الدورات</a></li>

            <li><a href="bill/bills.php" class="block hover:bg-gray-700 p-2 rounded">💰 الفواتير</a></li>

            <li><a href="../logout.php" class="block bg-red-500 p-2 rounded text-center mt-4">🚪 خروج</a></li>

        </ul>
    </div>

    <!-- Content -->
    <div class="flex-1 p-6">

        <!-- Navbar -->
        <div class="bg-white shadow p-4 rounded mb-6 flex justify-between">

            <h1 class="text-xl font-bold"><?= $title ?? "" ?></h1>

            <span>👤 <?= $_SESSION['RoleName'] ?? '' ?></span>

        </div>

        <!-- Page Content -->
        <?php echo $content; ?>

    </div>

</div>

</body>
</html>