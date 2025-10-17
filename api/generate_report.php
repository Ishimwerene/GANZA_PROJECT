<?php
require_once '../config.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'daily';
$startDate = $_GET['start'] ?? date('Y-m-d');
$endDate = $_GET['end'] ?? date('Y-m-d');

try {
    $response = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'rows' => [],
        'averages' => [
            'direction1' => 0,
            'direction2' => 0,
            'total' => 0
        ]
    ];

    // Generate report based on type
    $sql = "SELECT DATE(td.timestamp) as date, 
                   s.direction,
                   COUNT(td.id) as count
            FROM traffic_data td 
            JOIN sensors s ON td.sensor_id = s.id 
            WHERE DATE(td.timestamp) BETWEEN ? AND ?
            GROUP BY DATE(td.timestamp), s.direction
            ORDER BY DATE(td.timestamp)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($data[$date])) {
            $data[$date] = ['direction1' => 0, 'direction2' => 0];
        }
        
        if ($row['direction'] == 'direction_1') {
            $data[$date]['direction1'] = $row['count'];
        } else {
            $data[$date]['direction2'] = $row['count'];
        }
    }

    // Format response
    foreach ($data as $date => $counts) {
        $total = $counts['direction1'] + $counts['direction2'];
        $response['rows'][] = [
            'date' => $date,
            'direction1' => $counts['direction1'],
            'direction2' => $counts['direction2'],
            'total' => $total
        ];
        
        $response['averages']['direction1'] += $counts['direction1'];
        $response['averages']['direction2'] += $counts['direction2'];
        $response['averages']['total'] += $total;
    }

    // Calculate averages
    $rowCount = count($response['rows']);
    if ($rowCount > 0) {
        $response['averages']['direction1'] = round($response['averages']['direction1'] / $rowCount);
        $response['averages']['direction2'] = round($response['averages']['direction2'] / $rowCount);
        $response['averages']['total'] = round($response['averages']['total'] / $rowCount);
    }

    // Generate summary
    $total1 = array_sum(array_column($response['rows'], 'direction1'));
    $total2 = array_sum(array_column($response['rows'], 'direction2'));
    $diff = abs($total1 - $total2);
    $percentage = round(($diff / max($total1, $total2)) * 100, 1);
    
    if ($total1 > $total2) {
        $response['summary'] = "Direction 1 had $percentage% more traffic than Direction 2 during this period.";
    } else if ($total2 > $total1) {
        $response['summary'] = "Direction 2 had $percentage% more traffic than Direction 1 during this period.";
    } else {
        $response['summary'] = "Both directions had equal traffic during this period.";
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>