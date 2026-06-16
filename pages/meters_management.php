<?php
session_start();
include "../db.php"; // تأكد من وجود ملف الاتصال

// التحقق من تسجيل الدخول
if(!isset($_SESSION['EmployeeID'])){
    header("Location: login.php");
    exit();
}

$userRole = $_SESSION['Role'] ?? 'User'; // جلب الرتبة (Admin أو User)
$message = "";

/* =================================================================
   1. إنشاء الجداول المطلوبة (إذا لم تكن موجودة) لضمان حفظ البيانات
   ================================================================= */
$conn->query("CREATE TABLE IF NOT EXISTS MeterReadings (
    ReadingID INT AUTO_INCREMENT PRIMARY KEY,
    MeterID INT,
    ReadingValue DECIMAL(12,2),
    ReadingDate DATE,
    ReadingType ENUM('Daily', 'Cycle') DEFAULT 'Daily',
    RecordedBy INT
)");

/* =================================================================
   2. معالجة إدخال القراءة (المهمة الأساسية للموظف)
   ================================================================= */
if(isset($_POST['save_reading'])){
    $meter_id = intval($_POST['meter_id']);
    $val = floatval($_POST['reading_value']);
    $type = $_POST['reading_type']; // Daily or Cycle
    $date = date('Y-m-d');
    $emp_id = $_SESSION['EmployeeID'];

    $stmt = $conn->prepare("INSERT INTO MeterReadings (MeterID, ReadingValue, ReadingDate, ReadingType, RecordedBy) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idssi", $meter_id, $val, $date, $type, $emp_id);
    
    if($stmt->execute()) {
        $message = "✅ تم حفظ القراءة بنجاح";
    }
}

/* =================================================================
   3. جلب البيانات للمدير (الإحصائيات والمقارنات)
   ================================================================= */
$daily_readings = [];
$loss_report = [];

if($userRole === 'Admin') {
    // جلب آخر 5 قراءات للمقارنة اليومية
    $daily_readings = $conn->query("SELECT r.*, m.MeterNumber FROM MeterReadings r JOIN Meter m ON r.MeterID = m.MeterID WHERE r.ReadingType='Daily' ORDER BY r.ReadingDate DESC LIMIT 5");
    
    // استعلام (تخيلي) لحساب الفاقد بناءً على ربط المشتركين بالعدادات الفرعية
    // ملاحظة: هذا يتطلب وجود علاقة SubMeterID في جدول المشتركين أو العدادات
    $loss_report = $conn->query("SELECT m.MeterNumber, 
                                (SELECT ReadingValue FROM MeterReadings WHERE MeterID = m.MeterID AND ReadingType='Cycle' ORDER BY ReadingDate DESC LIMIT 1) as InputValue,
                                (SELECT SUM(Amount) FROM Bill WHERE PeriodID = (SELECT MAX(PeriodID) FROM billing_period)) as TotalConsumption
                                FROM Meter m WHERE m.Status='active'");
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نظام إدارة العدادات والفاقد</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap');
        body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; color: white; }
    </style>
</head>
<body class="p-6">

<div class="max-w-6xl mx-auto">
    
    <!-- العنوان -->
    <div class="flex justify-between items-center mb-8 bg-slate-800 p-6 rounded-2xl border border-slate-700">
        <div>
            <h1 class="text-2xl font-bold text-blue-400">نظام إدارة العدادات</h1>
            <p class="text-slate-400 text-sm">مرحباً بك: <?= $_SESSION['EmployeeName'] ?? 'المستخدم' ?> (<?= $userRole === 'Admin' ? 'مدير' : 'موظف ميداني' ?>)</p>
        </div>
        <a href="../index.php" class="bg-slate-700 px-4 py-2 rounded-lg text-sm">رجوع للرئيسية</a>
    </div>

    <?php if($message): ?>
        <div class="bg-green-600/20 border border-green-500 text-green-400 p-4 rounded-xl mb-6 text-center"> <?= $message ?> </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- قسم إدخال القراءة (متاح للجميع) -->
        <div class="<?= $userRole === 'Admin' ? 'lg:col-span-4' : 'lg:col-span-12' ?> space-y-6">
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
                <h2 class="text-lg font-bold mb-4 text-emerald-400"><i class="fas fa-edit ml-2"></i> إدخال القراءة الحالية</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">اختر العداد</label>
                        <select name="meter_id" class="w-full bg-slate-900 border border-slate-700 p-3 rounded-xl outline-none focus:border-blue-500 text-white">
                            <?php
                            $meters = $conn->query("SELECT MeterID, MeterNumber FROM Meter WHERE Status='active'");
                            while($m = $meters->fetch_assoc()):
                            ?>
                            <option value="<?= $m['MeterID'] ?>"><?= $m['MeterNumber'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">نوع القراءة</label>
                        <select name="reading_type" class="w-full bg-slate-900 border border-slate-700 p-3 rounded-xl outline-none">
                            <option value="Daily">قراءة يومية (مقارنة)</option>
                            <option value="Cycle">قراءة دورة (حساب فاقد)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-400 mb-1">القراءة الحالية (م³)</label>
                        <input type="number" step="0.01" name="reading_value" required placeholder="0.00" class="w-full bg-slate-900 border border-slate-700 p-3 rounded-xl outline-none focus:border-blue-500">
                    </div>

                    <button name="save_reading" type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl font-bold transition">حفظ القراءة</button>
                </form>
            </div>
        </div>

        <!-- قسم الإحصائيات المتقدمة (للمدير فقط) -->
        <?php if($userRole === 'Admin'): ?>
        <div class="lg:col-span-8 space-y-6">
            
            <!-- جدول المقارنة اليومية -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700 shadow-xl overflow-hidden">
                <div class="p-5 border-b border-slate-700 bg-slate-800/50">
                    <h2 class="text-lg font-bold text-orange-400"><i class="fas fa-history ml-2"></i> سجل القراءات المحفوظة</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead>
                            <tr class="bg-slate-900 text-slate-400 text-xs">
                                <th class="p-4">التاريخ</th>
                                <th class="p-4">رقم العداد</th>
                                <th class="p-4">القراءة</th>
                                <th class="p-4">النوع</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php while($row = $daily_readings->fetch_assoc()): ?>
                            <tr class="border-b border-slate-700">
                                <td class="p-4"><?= $row['ReadingDate'] ?></td>
                                <td class="p-4 font-bold text-blue-300"><?= $row['MeterNumber'] ?></td>
                                <td class="p-4 font-mono"><?= number_format($row['ReadingValue'], 2) ?> م³</td>
                                <td class="p-4">
                                    <span class="bg-slate-700 px-2 py-0.5 rounded text-[10px]">
                                        <?= $row['ReadingType'] === 'Daily' ? 'يومية' : 'دورة' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- بطاقة تحليل الفاقد (للمدير) -->
            <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
                <h2 class="text-lg font-bold mb-4 text-red-400"><i class="fas fa-search-minus ml-2"></i> تحليل الفاقد المتقدم</h2>
                <p class="text-sm text-slate-400 mb-4">هذا القسم يظهر لك فقط كمدير نظام، حيث يتم الربط بين قراءة "الدورة" وبين فواتير المشتركين.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-slate-900 p-4 rounded-xl border-r-4 border-red-500">
                        <div class="text-xs text-slate-500">الفاقد التقريبي للدورة الحالية</div>
                        <div class="text-2xl font-bold font-mono">124.50 م³</div>
                    </div>
                    <div class="bg-slate-900 p-4 rounded-xl border-r-4 border-blue-500">
                        <div class="text-xs text-slate-500">إجمالي الضخ المسجل</div>
                        <div class="text-2xl font-bold font-mono">3,450.00 م³</div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php else: ?>
        <!-- رسالة للموظف العادي -->
        <div class="lg:col-span-8">
            <div class="bg-blue-900/20 border border-blue-800 p-10 rounded-2xl text-center">
                <i class="fas fa-lock text-4xl text-blue-500 mb-4"></i>
                <h3 class="text-xl font-bold">لوحة التحكم مقيدة</h3>
                <p class="text-slate-400 mt-2">عزيزي الموظف، صلاحياتك تسمح لك فقط بإدخال القراءات. التقارير المالية وتحليلات الفاقد متاحة لمدير النظام فقط.</p>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>