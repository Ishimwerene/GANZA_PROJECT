<?php
require_once '../config.php';

header('Content-Type: application/json');

$response = [
    'direction_1' => ['total' => 0, 'types' => []],
    'direction_2' => ['total' => 0, 'types' => []],
    'recent_activity' => []
];

try {
    $today = date('Y-m-d');
    
    // Get counts by direction and vehicle type
    $sql = "SELECT s.direction, vt.type_name, COUNT(td.id) as count 
            FROM traffic_data td 
            JOIN sensors s ON td.sensor_id = s.id 
            JOIN vehicle_types vt ON td.vehicle_type_id = vt.id
            WHERE DATE(td.timestamp) = ? 
            GROUP BY s.direction, vt.type_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $direction = $row['direction'];
        $response[$direction]['types'][$row['type_name']] = $row['count'];
        $response[$direction]['total'] += $row['count'];
    }
    $stmt->close();

    // Get recent activity with classification
    $sql = "SELECT s.direction, vt.type_name, td.vehicle_height, td.timestamp 
            FROM traffic_data td 
            JOIN sensors s ON td.sensor_id = s.id 
            JOIN vehicle_types vt ON td.vehicle_type_id = vt.id
            ORDER BY td.timestamp DESC 
            LIMIT 10";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $response['recent_activity'][] = [
            'direction' => $row['direction'] == 'direction_1' ? 'Direction 1' : 'Direction 2',
            'vehicle_type' => $row['type_name'],
            'height' => $row['vehicle_height'],
            'time' => date('H:i:s', strtotime($row['timestamp']))
        ];
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>