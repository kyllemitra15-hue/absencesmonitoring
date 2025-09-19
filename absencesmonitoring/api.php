<?php
require "config.php";
header("Content-Type: application/json");

// Decode JSON body
$input = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $input['date'] ?? null;
    $shift = $input['shift'] ?? null;
    $totalEmployees = intval($input['totalEmployees'] ?? 0);
    $totalAbsent = intval($input['totalAbsent'] ?? 0);

    // Flatten categories
    $matex  = intval($input['categories']['matex'] ?? 0);
    $avance = intval($input['categories']['avance'] ?? 0);
    $hrpro  = intval($input['categories']['hrpro'] ?? 0);
    $leave  = intval($input['categories']['leave'] ?? 0);

    if (!$date || !$shift) {
        echo json_encode(["success" => false, "message" => "Missing date or shift"]);
        exit;
    }

    // Check if a record exists for this date+shift
    $chk = $conn->prepare("SELECT id FROM absences WHERE date = ? AND shift = ? LIMIT 1");
    $chk->bind_param("ss", $date, $shift);
    $chk->execute();
    $res = $chk->get_result();
    $row = $res->fetch_assoc();
    $chk->close();

    if ($row && isset($row['id'])) {
        // Update existing
        $stmt = $conn->prepare("UPDATE absences SET totalEmployees = ?, totalAbsent = ?, matex = ?, avance = ?, hrpro = ?, leaveAbsent = ? WHERE id = ?");
        $stmt->bind_param("iiiiiii", $totalEmployees, $totalAbsent, $matex, $avance, $hrpro, $leave, $row['id']);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO absences (date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiiii", $date, $shift, $totalEmployees, $totalAbsent, $matex, $avance, $hrpro, $leave);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Record saved successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // DAILY
    if (isset($_GET['date'])) {
        $date = $_GET['date'];
        $stmt = $conn->prepare("SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent FROM absences WHERE date = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $row['leave'] = $row['leaveAbsent'];
            unset($row['leaveAbsent']);
            $records[] = $row;
        }
        echo json_encode($records);
        exit;
    }

    // BY MONTH (all records in one month)
    if (isset($_GET['month'])) {
        $month = $_GET['month']; // e.g. "2025-08"
        $stmt = $conn->prepare("SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent 
                                FROM absence_db.absences
                                WHERE DATE_FORMAT(date, '%Y-%m') = ?");
        $stmt->bind_param("s", $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $row['leave'] = $row['leaveAbsent'];
            unset($row['leaveAbsent']);
            $records[] = $row;
        }
        echo json_encode($records);
        exit;
    }

    // BY YEAR
    if (isset($_GET['year'])) {
        $year = intval($_GET['year']);
        $stmt = $conn->prepare("SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent FROM absences WHERE YEAR(date) = ?");
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $row['leave'] = $row['leaveAbsent'];
            unset($row['leaveAbsent']);
            $records[] = $row;
        }
        echo json_encode($records);
        exit;
    }

    // MONTHLY SUMMARY (group by month)
    if (isset($_GET['report']) && $_GET['report'] === 'monthly') {
        $sql = "SELECT DATE_FORMAT(date, '%Y-%m') as month,
                       SUM(totalEmployees) as totalEmployees,
                       SUM(totalAbsent) as totalAbsent,
                       SUM(matex) as matex,
                       SUM(avance) as avance,
                       SUM(hrpro) as hrpro,
                       SUM(leaveAbsent) as `leave`
                FROM absences
                GROUP BY month
                ORDER BY month ASC";
        $result = $conn->query($sql);
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        echo json_encode($records);
        exit;
    }

    // Return all raw records for export
    if (isset($_GET['report']) && $_GET['report'] === 'all') {
        $result = $conn->query("SELECT id, date, shift, totalEmployees, totalAbsent, matex, avance, hrpro, leaveAbsent FROM absences ORDER BY date ASC");
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $row['leave'] = $row['leaveAbsent'];
            unset($row['leaveAbsent']);
            $records[] = $row;
        }
        echo json_encode($records);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}
?>
