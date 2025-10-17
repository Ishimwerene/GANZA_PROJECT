<?php
require_once '../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input)) {
        $input = $_POST;
    }

    $direction   = $input['direction'] ?? null;   // North / South
    $vehicleType = $input['type'] ?? null;       // From sensor: Car, Bus, Truck, etc.
    $height      = $input['height'] ?? null;     // Vehicle height in meters
    $distance    = $input['distance'] ?? null;   // Distance from sensor

    // Map direction to sensor_id
    $sensorMap = ['North' => 1, 'South' => 2];
    $sensor_id = $sensorMap[$direction] ?? null;

    // Map vehicle type to ID
    $typeMap = [
        'Motorcycle' => 1,
        'Car'        => 2,
        'SUV/Van'    => 3,
        'Bus'        => 4,
        'Truck'      => 5
    ];
    $vehicle_type_id = $typeMap[$vehicleType] ?? null;

    if ($sensor_id && $vehicleType && $vehicle_type_id && $height !== null) {
        // Prepare SQL
        $stmt = $conn->prepare(
            "INSERT INTO traffic_data 
            (sensor_id, vehicle_count, vehicle_type_id, vehicle_height, vehicle_type, timestamp, distance) 
            VALUES (?, 1, ?, ?, ?, NOW(), ?)"
        );

        // Bind parameters: iidsi → integer, integer, double, string, integer
        $stmt->bind_param("iidsi", $sensor_id, $vehicle_type_id, $height, $vehicleType, $distance);

        if ($stmt->execute()) {
            echo json_encode([
                'status'          => 'success',
                'message'         => 'Data received successfully',
                'sensor_id'       => $sensor_id,
                'direction'       => $direction,
                'vehicle_type'    => $vehicleType,
                'vehicle_type_id' => $vehicle_type_id,
                'height'          => $height,
                'distance'        => $distance
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Database error: ' . $stmt->error
            ]);
        }

        $stmt->close();
    } else {
        http_response_code(400);
        echo json_encode([
            'status'        => 'error',
            'message'       => 'Invalid data: direction, type, height are required',
            'received_data' => $input
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Method not allowed'
    ]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>
