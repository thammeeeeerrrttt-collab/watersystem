<?php
session_start();
include "../db.php";

if(!isset($_SESSION['EmployeeID'])){
    header("Location: ../login.php");
    exit();
}

/* =====================================
   التأكد من الأعمدة
===================================== */

$check1 = $conn->query("SHOW COLUMNS FROM Bill LIKE 'PreviousArrears'");
if($check1->num_rows == 0){
    $conn->query("
    ALTER TABLE Bill
    ADD PreviousArrears DECIMAL(12,2) DEFAULT 0
    ");
}

$check2 = $conn->query("SHOW COLUMNS FROM Bill LIKE 'PaidAmount'");
if($check2->num_rows == 0){
    $conn->query("
    ALTER TABLE Bill
    ADD PaidAmount DECIMAL(12,2) DEFAULT 0
    ");
}

$check3 = $conn->query("SHOW COLUMNS FROM Bill LIKE 'RemainingAmount'");
if($check3->num_rows == 0){
    $conn->query("
    ALTER TABLE Bill
    ADD RemainingAmount DECIMAL(12,2) DEFAULT 0
    ");
}

/* =====================================
   إنشاء جدول المتأخرات إذا غير موجود
===================================== */

$conn->query("
CREATE TABLE IF NOT EXISTS PreviousArrearsRecords (

    ArrearID INT AUTO_INCREMENT PRIMARY KEY,

    BillID INT,
    CustomerID INT,
    PeriodID INT,

    RemainingAmount DECIMAL(12,2) DEFAULT 0

)
");

/* =====================================
   البيانات
===================================== */

$periodID = intval($_POST['period_id']);
$readings = $_POST['reading'];

/* =====================================
   loop العدادات
===================================== */

foreach($readings as $meterID => $current){

    /* ===== تجاهل الفارغ ===== */

    if($current === ""){
        continue;
    }

    $meterID = intval($meterID);
    $current = floatval($current);

    /* =====================================
       جلب بيانات العداد
    ===================================== */

    $meter = $conn->query("
    SELECT CustomerID
    FROM Meter
    WHERE MeterID = $meterID
    AND Status='Active'
    ")->fetch_assoc();

    if(!$meter){
        continue;
    }

    $customerID = intval($meter['CustomerID']);

    /* =====================================
       آخر قراءة
    ===================================== */

    $last = $conn->query("
    SELECT CurrentReading
    FROM Reading
    WHERE MeterID = $meterID
    ORDER BY ReadingID DESC
    LIMIT 1
    ")->fetch_assoc();

    $previous = $last ? floatval($last['CurrentReading']) : 0;

    /* =====================================
       منع القراءة الأقل
    ===================================== */

    if($current < $previous){

        die("
        ❌ خطأ في العداد رقم:
        $meterID
        <br><br>
        القراءة الحالية أصغر من السابقة
        ");

    }

    /* =====================================
       تحقق القراءة الحالية
    ===================================== */

    $exists = $conn->query("
    SELECT ReadingID
    FROM Reading
    WHERE MeterID = $meterID
    AND PeriodID = $periodID
    ");

    if($exists->num_rows > 0){

        /* ===== تحديث ===== */

        $row = $exists->fetch_assoc();

        $readingID = intval($row['ReadingID']);

        $conn->query("
        UPDATE Reading
        SET
            PreviousReading = $previous,
            CurrentReading = $current,
            ReadingDate = NOW()
        WHERE ReadingID = $readingID
        ");

    } else {

        /* ===== إضافة ===== */

        $conn->query("
        INSERT INTO Reading
        (
            MeterID,
            CustomerID,
            PreviousReading,
            CurrentReading,
            ReadingDate,
            PeriodID
        )
        VALUES
        (
            $meterID,
            $customerID,
            $previous,
            $current,
            NOW(),
            $periodID
        )
        ");

        $readingID = $conn->insert_id;
    }

    /* =====================================
       الاستهلاك
    ===================================== */

    $consumption = $current - $previous;

    /* =====================================
       سعر الوحدة من المشترك
    ===================================== */

    $cust = $conn->query("
    SELECT UnitPrice
    FROM Customer
    WHERE CustomerID = $customerID
    ")->fetch_assoc();

    $rate = $cust ? floatval($cust['UnitPrice']) : 0;

    /* =====================================
       مبلغ الاستهلاك الحالي
    ===================================== */

    $amount = round($consumption * $rate, 2);

    /* =====================================
       المتأخرات السابقة
       يشمل السالب والموجب
    ===================================== */

    $arrears = $conn->query("
    SELECT COALESCE(SUM(RemainingAmount),0) as total
    FROM PreviousArrearsRecords
    WHERE CustomerID = $customerID
    AND PeriodID < $periodID
    ")->fetch_assoc();

    $previousArrears = floatval($arrears['total']);

    /* =====================================
       الإجمالي المستحق
    ===================================== */

    $totalDue = $amount + $previousArrears;

    /* =====================================
       الدفع الحالي
    ===================================== */

    $paidAmount = 0;

    /* =====================================
       المتبقي
       يسمح بالسالب
    ===================================== */

    $remainingAmount = $totalDue - $paidAmount;

    /* =====================================
       الحالة
    ===================================== */

    if($remainingAmount <= 0){

        $status = 'Paid';

    } elseif($paidAmount > 0){

        $status = 'Partial';

    } else {

        $status = 'Unpaid';
    }

    /* =====================================
       تحقق الفاتورة
    ===================================== */

    $billCheck = $conn->query("
    SELECT BillID, PaidAmount
    FROM Bill
    WHERE ReadingID = $readingID
    ");

    if($billCheck->num_rows > 0){

        /* =====================================
           تحديث الفاتورة
        ===================================== */

        $bill = $billCheck->fetch_assoc();

        $billID = intval($bill['BillID']);

        $paidAmount = floatval($bill['PaidAmount']);

        $remainingAmount = $totalDue - $paidAmount;

        if($remainingAmount <= 0){

            $status = 'Paid';

        } elseif($paidAmount > 0){

            $status = 'Partial';

        } else {

            $status = 'Unpaid';
        }

        $stmt = $conn->prepare("
        UPDATE Bill
        SET
            Consumption = ?,
            Rate = ?,
            Amount = ?,
            PreviousArrears = ?,
            PaidAmount = ?,
            RemainingAmount = ?,
            Status = ?
        WHERE ReadingID = ?
        ");

        $stmt->bind_param(
            "ddddddsi",
            $consumption,
            $rate,
            $amount,
            $previousArrears,
            $paidAmount,
            $remainingAmount,
            $status,
            $readingID
        );

        $stmt->execute();
        $stmt->close();

        /* =====================================
           تحديث سجل المتأخرات
        ===================================== */

        $checkAr = $conn->query("
        SELECT ArrearID
        FROM PreviousArrearsRecords
        WHERE BillID = $billID
        ");

        if($checkAr->num_rows > 0){

            $ar = $checkAr->fetch_assoc();

            $arID = intval($ar['ArrearID']);

            $conn->query("
            UPDATE PreviousArrearsRecords
            SET RemainingAmount = $remainingAmount
            WHERE ArrearID = $arID
            ");

        } else {

            $conn->query("
            INSERT INTO PreviousArrearsRecords
            (
                BillID,
                CustomerID,
                PeriodID,
                RemainingAmount
            )
            VALUES
            (
                $billID,
                $customerID,
                $periodID,
                $remainingAmount
            )
            ");
        }

    } else {

        /* =====================================
           إنشاء الفاتورة
        ===================================== */

        $stmt = $conn->prepare("
        INSERT INTO Bill
        (
            CustomerID,
            ReadingID,
            Consumption,
            Rate,
            Amount,
            PreviousArrears,
            PaidAmount,
            RemainingAmount,
            Status,
            BillDate,
            PeriodID
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?
        )
        ");

        $stmt->bind_param(
            "iidddddssi",
            $customerID,
            $readingID,
            $consumption,
            $rate,
            $amount,
            $previousArrears,
            $paidAmount,
            $remainingAmount,
            $status,
            $periodID
        );

        $stmt->execute();

        $billID = $stmt->insert_id;

        $stmt->close();

        /* =====================================
           رقم الفاتورة
        ===================================== */

        $billNumber = str_pad($billID, 6, '0', STR_PAD_LEFT);

        $upd = $conn->prepare("
        UPDATE Bill
        SET BillNumber = ?
        WHERE BillID = ?
        ");

        $upd->bind_param("si", $billNumber, $billID);
        $upd->execute();
        $upd->close();

        /* =====================================
           حفظ المتأخرات
        ===================================== */

        $ins = $conn->prepare("
        INSERT INTO PreviousArrearsRecords
        (
            BillID,
            CustomerID,
            PeriodID,
            RemainingAmount
        )
        VALUES
        (
            ?, ?, ?, ?
        )
        ");

        $ins->bind_param(
            "iiid",
            $billID,
            $customerID,
            $periodID,
            $remainingAmount
        );

        $ins->execute();
        $ins->close();
    }
}

/* =====================================
   رجوع
===================================== */

header("Location: periods.php?msg=saved");
exit();
?>