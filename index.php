<?php
session_start();
include "db.php"; 

// التحقق من تسجيل الدخول
if(!isset($_SESSION['EmployeeID'])) {
    header("Location: login.php");
    exit();
}

$name = $_SESSION['EmployeeName'] ?? $_SESSION['Name']; 
$role = $_SESSION['Role']; 
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - نظام المياه الذكي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --dark: #0f172a;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            height: 100vh;
            background: linear-gradient(-45deg, #0f172a, #1e3a8a, #0ea5e9, #38bdf8);
            background-size: 400% 400%;
            animation: bg 15s ease infinite;
            color: white;
            overflow: hidden;
        }

        @keyframes bg {
            0% { background-position: 0% 50% }
            50% { background-position: 100% 50% }
            100% { background-position: 0% 50% }
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(15px);
            color: white;
            padding: 20px;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar h2 {
            text-align: center;
            font-size: 1.3rem;
            margin-bottom: 25px;
            color: #38bdf8;
            border-bottom: 1px solid rgba(56, 189, 248, 0.2);
            padding-bottom: 15px;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            text-align: center;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: #cbd5e1;
            text-decoration: none;
            padding: 10px 15px;
            margin: 2px 0;
            border-radius: 8px;
            transition: 0.3s;
            font-size: 0.95rem;
        }

        .sidebar a i { margin-left: 12px; width: 20px; text-align: center; }

        .sidebar a:hover {
            background: rgba(14, 165, 233, 0.2);
            color: #38bdf8;
            transform: translateX(-5px);
        }

        .sidebar a.active { background: #0ea5e9; color: white; }

        .menu-label {
            font-size: 0.75rem;
            color: #64748b;
            margin: 15px 10px 5px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* CONTENT */
        .content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .top h1 { font-size: 2.2rem; margin: 0; font-weight: 800; }
        .top p { color: #e2e8f0; margin-top: 5px; opacity: 0.8; }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 35px;
        }

        .card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            color: var(--text-main);
            text-decoration: none;
            position: relative;
            border-bottom: 5px solid transparent;
        }

        .card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .card i { font-size: 45px; color: var(--primary); margin-bottom: 20px; }
        .card h3 { margin: 10px 0; font-size: 1.25rem; font-weight: 700; }
        .card p { color: #64748b; font-size: 0.9rem; line-height: 1.4; }

        /* الألوان المميزة للبطاقات */
        .card.analysis { border-color: var(--primary); }
        .card.market { border-color: var(--danger); }
        .card.settle { border-color: var(--success); }
        .card.finance { border-color: var(--warning); }

        .admin-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: bold;
            color: #64748b;
        }
    </style>
</head>

<body>

<div class="sidebar">
    <h2>💧 نظام المياه الذكي</h2>

    <div class="user-info">
        <div><i class="fa fa-user-circle"></i> <?php echo htmlspecialchars($name); ?></div>
        <div style="color: #38bdf8; font-weight: bold; margin-top: 5px;">
            <i class="fa fa-shield-alt"></i> <?php echo ($role == "Admin") ? "مدير النظام" : "موظف ميداني"; ?>
        </div>
    </div>

    <a href="index.php" class="active"><i class="fa fa-home"></i> الرئيسة</a>

    <div class="menu-label">إدارة البيانات</div>
    <a href="pages/customers.php"><i class="fa fa-users"></i> المشتركين</a>
    <a href="pages/meters.php"><i class="fa fa-tachometer-alt"></i> عدادات المشتركين</a>
    <a href="pages/main_meters.php"><i class="fa fa-tachometer-alt"></i> العدادات الرئيسية</a>
    
    <div class="menu-label">العمليات والضخ</div>
    <a href="pages/main_meter_readings.php"><i class="fa fa-faucet"></i> إدخال قراءات الضخ</a>
    <a href="pages/periods.php"><i class="fa fa-user-edit"></i> قراءات المشتركين</a>
    <?php if($role == "Admin") : ?>
        <div class="menu-label">التحليل والرقابة</div>
        <a href="pages/daily_analysis.php"><i class="fa fa-chart-area"></i> فواقد السلسلة</a>
        <a href="pages/period_analyzer.php"><i class="fa fa-magnifying-glass-chart"></i> الموجود في السوق</a>
        <a href="pages/period_settlement.php"><i class="fa fa-file-shield"></i> اعتماد الدورات</a>
    <?php endif; ?>
    <div class="menu-label">المالية والصيانة</div>
    <a href="pages/bill/bills.php"><i class="fa fa-file-invoice-dollar"></i> الفواتير والسداد</a>
    <?php if($role == "Admin") : ?>
        <a href="pages/finance_manager.php"><i class="fa fa-hand-holding-dollar"></i> الإدارة المالية</a>
    <?php endif; ?>
    <a href="pages/maintenance.php"><i class="fa fa-screwdriver-wrench"></i> الصيانة</a>

    <?php if($role == "Admin") : ?>
        <div class="menu-label">الإدارة العليا</div>
        <a href="pages/employees.php"><i class="fa fa-user-tie"></i> الموظفين</a>
        <a href="pages/reports.php"><i class="fa fa-chart-pie"></i> التقارير والتحليلات</a>
        <a href="pages/settings.php"><i class="fa fa-gears"></i> الإعدادات</a>
    <?php endif; ?>

    <div style="margin-top: auto;">
        <hr style="opacity: 0.1; margin: 10px 0;">
        <a href="logout.php" style="color: #f87171;"><i class="fa fa-power-off"></i> تسجيل الخروج</a>
    </div>
</div>

<div class="content">
    <div class="top">
        <h1>Dashboard</h1>
        <p>مرحباً بك مجدداً.. نظام الرقابة يعمل بكفاءة 💧</p>
    </div>
    <div class="cards"> 
       <?php if($role == "Admin") : ?>
            <a href="pages/daily_analysis.php" class="card analysis">
                <i class="fa fa-chart-line"></i>
                <h3>التحليل اليومي</h3>
                <p>مراقبة كميات الضخ واكتشاف فواقد المواسير الرئيسية.</p>
            </a>
    
        <a href="pages/period_analyzer.php" class="card market">
            <i class="fa fa-hand-holding-droplet"></i>
            <h3>الموجود في السوق</h3>
            <p>مقارنة استهلاك المنطقة مع فواتير الناس (كشف السرقة).</p>
        </a>
        <a href="pages/message.php" class="card market">
            <i class="fa fa-messages"></i>
            <h3>الرسائل</h3>
            <p>ارسال رسائل للمشتركين.</p>
        </a>

        <!-- بطاقة الإدارة المالية الجديدة -->
        <a href="pages/finance_manager.php" class="card finance">
            <i class="fa fa-calculator"></i>
            <h3>الإدارة المالية</h3>
            <p>تسجيل المصروفات النثرية وتحويلات المبالغ للمالك.</p>
        </a>
    <?php endif; ?>
        <a href="pages/bill/bills.php" class="card">
            <i class="fa fa-money-bill-transfer"></i>
            <h3>التحصيل المالي</h3>
            <p>إدارة الفواتير، مراجعة السداد، والمبالغ المتأخرة.</p>
        </a>

        <?php if($role == "Admin") : ?>
            <a href="pages/period_settlement.php" class="card settle">
                <span class="admin-tag">ADMIN</span>
                <i class="fa fa-box-archive"></i>
                <h3>اعتماد السجلات</h3>
                <p>ترحيل بيانات الفواقد إلى السجل التاريخي الدائم.</p>
            </a>

            <a href="pages/reports.php" class="card">
                <span class="admin-tag">ADMIN</span>
                <i class="fa fa-magnifying-glass-chart"></i>
                <h3>تقارير الأداء</h3>
                <p>عرض إحصائيات الأرباح والخسائر والنمو السنوي.</p>
            </a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>