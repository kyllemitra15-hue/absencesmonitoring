<?php
require "config.php";
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $date  = $data['date'];
    $shift = $data['shift'];

    $matexEmployees  = intval($data['matexEmployees']);
    $matexAbsent     = intval($data['matexAbsent']);
    $avanceEmployees = intval($data['avanceEmployees']);
    $avanceAbsent    = intval($data['avanceAbsent']);
    $hrproEmployees  = intval($data['hrproEmployees']);
    $hrproAbsent     = intval($data['hrproAbsent']);

    $totalEmployees  = intval($data['totalEmployees']);
    $totalAbsent     = intval($data['totalAbsent']);
    $totalPresent    = intval($data['totalPresent']);

    // check if record exists
    $stmt = $conn->prepare("SELECT id FROM man_power WHERE date=? AND shift=?");
    $stmt->bind_param("ss", $date, $shift);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row) {
        $stmt = $conn->prepare("UPDATE man_power 
            SET matexEmployees=?, matexAbsent=?, 
                avanceEmployees=?, avanceAbsent=?, 
                hrproEmployees=?, hrproAbsent=?, 
                totalEmployees=?, totalAbsent=?, totalPresent=? 
            WHERE id=?");
        $stmt->bind_param("iiiiiiiiii", 
            $matexEmployees, $matexAbsent, 
            $avanceEmployees, $avanceAbsent, 
            $hrproEmployees, $hrproAbsent, 
            $totalEmployees, $totalAbsent, $totalPresent, 
            $row['id']
        );
        $stmt->execute();
        $stmt->close();
        echo json_encode(["success"=>true,"message"=>"Record updated"]);
    } else {
        $stmt = $conn->prepare("INSERT INTO man_power 
            (date, shift, matexEmployees, matexAbsent, avanceEmployees, avanceAbsent, hrproEmployees, hrproAbsent, totalEmployees, totalAbsent, totalPresent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiiiiiii", 
            $date, $shift, 
            $matexEmployees, $matexAbsent, 
            $avanceEmployees, $avanceAbsent, 
            $hrproEmployees, $hrproAbsent, 
            $totalEmployees, $totalAbsent, $totalPresent
        );
        $stmt->execute();
        $stmt->close();
        echo json_encode(["success"=>true,"message"=>"Record have been added"]);
    }
    exit;
}

//GET TREND DATA
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['trend'])) {
    $sql = "SELECT date, shift,
                   matexEmployees, matexAbsent,
                   avanceEmployees, avanceAbsent,
                   hrproEmployees, hrproAbsent
            FROM man_power
            ORDER BY date DESC, id DESC
            LIMIT 10";
    $res = $conn->query($sql);

    $trend = [];
    while ($r = $res->fetch_assoc()) {
        $trend[] = [
            "date" => $r["date"],
            "shift" => $r["shift"],
            "matexPct" => $r["matexEmployees"] > 0 ? round(($r["matexAbsent"] / $r["matexEmployees"]) * 100, 1) : 0,
            "avancePct" => $r["avanceEmployees"] > 0 ? round(($r["avanceAbsent"] / $r["avanceEmployees"]) * 100, 1) : 0,
            "hrproPct" => $r["hrproEmployees"] > 0 ? round(($r["hrproAbsent"] / $r["hrproEmployees"]) * 100, 1) : 0
        ];
    }

    echo json_encode($trend);
    exit;
}

// GET MONTHLY RECORDS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['month'])) {
    $month = $_GET['month']; // format YYYY-MM
    $sql = "SELECT * FROM man_power WHERE DATE_FORMAT(date, '%Y-%m') = ? ORDER BY date ASC, shift ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode($rows);
    exit;
}

echo json_encode(["success"=>false,"message"=>"❌ Invalid request"]);
$conn->close();
