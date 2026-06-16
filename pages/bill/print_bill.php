<?php
session_start();
include "../../db.php"; // تأكد من مسار الاتصال بقاعدة البيانات

// التحقق من تسجيل الدخول
if(!isset($_SESSION['EmployeeID'])){
    die("الرجاء تسجيل الدخول أولاً.");
}

// جلب رقم الفاتورة من الرابط
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id == 0){
    die("رقم الفاتورة غير صحيح.");
}

// جلب بيانات الفاتورة والعميل
$sql = "SELECT b.*, c.Name as CustomerName 
        FROM Bill b 
        JOIN Customer c ON b.CustomerID = c.CustomerID 
        WHERE b.BillID = $id";
$result = $conn->query($sql);

if($result->num_rows == 0){
    die("الفاتورة غير موجودة.");
}

$bill = $result->fetch_assoc();

// إعداد المتغيرات حسب طلبك
$projectName = "مشروع ثامر للمياة النقية"; // <-- قم بتغيير هذا الاسم
$receiptNumber = $bill['BillNumber'] ?? $bill['BillID'];
$customerName = $bill['CustomerName'];
$paidAmount = floatval($bill['PaidAmount']); // المبلغ المسدد
$cashierName = $_SESSION['EmployeeName'] ?? 'مدير النظام'; // اسم الموظف من الجلسة

// إعداد التاريخ والوقت
date_default_timezone_set('Asia/Aden'); // ضبط التوقيت على توقيت اليمن
$printDate = date('Y-m-d');
$printTime = date('h:i A');

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة السند - <?= htmlspecialchars($receiptNumber) ?></title>
    
    <style>
        /* إعدادات الصفحة الأساسية لتناسب طابعات الكاشير */
        @page {
            margin: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background-color: #f0f0f0; /* خلفية رمادية للعرض على الشاشة */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        /* حاوية الفاتورة - مقاس طابعة حرارية 80mm أو 58mm */
        .receipt-container {
            width: 75mm; /* عرض مناسب للطابعات الحرارية */
            background-color: white;
            padding: 10px;
            color: #000;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        /* التنسيقات الداخلية */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        
        h2 { margin: 5px 0; font-size: 18px; }
        h3 { margin: 5px 0; font-size: 14px; font-weight: normal; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        table td { padding: 5px 0; }
        
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        
        .amount-row { font-size: 15px; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0; margin-top: 10px;}
        
        .footer-text { font-size: 12px; margin-top: 10px; }

        /* الأزرار (لن تظهر في الطباعة) */
        .no-print-area {
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
            position: absolute;
            top: 10px;
        }
        .btn {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        /* إعدادات عند بدء الطباعة الفعلي */
        @media print {
            body { 
                background-color: white; 
                padding: 0; 
                display: block;
            }
            .receipt-container { 
                box-shadow: none; 
                width: 100%; 
                padding: 0;
            }
            .no-print-area { 
                display: none; 
            }
        }
    </style>
</head>
<body>

    <!-- أزرار التحكم تظهر فقط على الشاشة وتختفي عند الطباعة -->
    <div class="no-print-area">
        <button class="btn" onclick="window.print()">🖨️ طباعة السند</button>
        <button class="btn" onclick="window.close()" style="background:#c0392b;">❌ إغلاق</button>
    </div>

    <!-- محتوى السند الذي سيتم طباعته -->
    <div class="receipt-container">
        
        <div class="text-center">
            <h2><?= htmlspecialchars($projectName) ?></h2>
            <h3>سند تحصيل آلي</h3>
        </div>

        <table>
            <tr>
                <td class="text-right">رقم السند:</td>
                <td class="text-left font-bold"><?= htmlspecialchars($receiptNumber) ?></td>
            </tr>
            <tr>
                <td class="text-right">التاريخ:</td>
                <td class="text-left"><?= $printDate ?></td>
            </tr>
            <tr>
                <td class="text-right">الوقت:</td>
                <td class="text-left"><?= $printTime ?></td>
            </tr>
        </table>

        <div class="divider"></div>

        <table>
            <tr>
                <td class="text-right">العميل:</td>
                <td class="text-left font-bold"><?= htmlspecialchars($customerName) ?></td>
            </tr>
        </table>

        <div class="amount-row text-center">
            <span class="font-bold">المبلغ المسدد:</span> 
            <span class="font-bold" style="font-size:18px; margin-right:10px;">
                <?= number_format($paidAmount, 2) ?>
            </span>
        </div>

        <table>
            <tr>
                <td class="text-right">المتحصل:</td>
                <td class="text-left"><?= htmlspecialchars($cashierName) ?></td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="text-center footer-text">
            <p>تمت الطباعة بواسطة النظام</p>
            <p>شكراً لتعاملكم معنا</p>
        </div>

    </div>

    <!-- كود جافاسكريبت لفتح نافذة الطباعة تلقائياً عند تحميل الصفحة -->
    <script>
        window.onload = function() {
            // نضع تأخير بسيط (نصف ثانية) لضمان تحميل الخطوط والتنسيقات قبل فتح نافذة الطباعة
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>

</body>
</html>