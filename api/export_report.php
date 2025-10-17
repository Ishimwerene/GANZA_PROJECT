<?php
// export_report.php
require_once '../config.php';

// Get parameters
$report_type = $_GET['type'] ?? 'daily';
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Validate dates
if ($start_date > $end_date) {
    die('Error: Start date cannot be after end date');
}

// Generate report data
$report_data = generateReportData($report_type, $start_date, $end_date);

// Export as HTML that can be printed as PDF
exportAsPrintableHTML($report_data, $report_type, $start_date, $end_date);

function generateReportData($report_type, $start_date, $end_date) {
    global $conn;
    
    $data = [
        'title' => '',
        'headers' => [],
        'rows' => [],
        'summary' => [],
        'period' => '',
        'has_data' => false
    ];
    
    switch ($report_type) {
        case 'daily':
            $data['title'] = 'Daily Traffic Report';
            $data['period'] = "Date: $start_date";
            $data['headers'] = ['Time', 'Direction 1 (Northbound)', 'Direction 2 (Southbound)', 'Total'];
            
            // Get hourly data for the day
            $sql = "SELECT 
                        HOUR(td.timestamp) as hour,
                        SUM(CASE WHEN s.direction = 'direction_1' THEN 1 ELSE 0 END) as direction_1,
                        SUM(CASE WHEN s.direction = 'direction_2' THEN 1 ELSE 0 END) as direction_2,
                        COUNT(td.id) as total
                    FROM traffic_data td 
                    JOIN sensors s ON td.sensor_id = s.id 
                    WHERE DATE(td.timestamp) = ?
                    GROUP BY HOUR(td.timestamp)
                    ORDER BY hour";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $start_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $total_dir1 = 0;
            $total_dir2 = 0;
            $grand_total = 0;
            $peak_hour = '';
            $peak_count = 0;
            
            // Initialize all hours with zeros
            $hourly_data = [];
            for ($i = 0; $i < 24; $i++) {
                $hourly_data[$i] = [
                    'hour' => $i,
                    'direction_1' => 0,
                    'direction_2' => 0,
                    'total' => 0
                ];
            }
            
            // Update with actual data
            while ($row = $result->fetch_assoc()) {
                $hour = (int)$row['hour'];
                $hourly_data[$hour] = [
                    'hour' => $hour,
                    'direction_1' => $row['direction_1'],
                    'direction_2' => $row['direction_2'],
                    'total' => $row['total']
                ];
                
                $total_dir1 += $row['direction_1'];
                $total_dir2 += $row['direction_2'];
                $grand_total += $row['total'];
                
                // Track peak hour
                if ($row['total'] > $peak_count) {
                    $peak_count = $row['total'];
                    $peak_hour = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
                }
            }
            $stmt->close();
            
            // Format rows for display
            foreach ($hourly_data as $hour_data) {
                $time_range = str_pad($hour_data['hour'], 2, '0', STR_PAD_LEFT) . ':00 - ' . str_pad($hour_data['hour'], 2, '0', STR_PAD_LEFT) . ':59';
                $data['rows'][] = [
                    $time_range,
                    $hour_data['direction_1'],
                    $hour_data['direction_2'],
                    $hour_data['total']
                ];
            }
            
            $data['summary'] = [
                'Total Direction 1 (Northbound)' => $total_dir1,
                'Total Direction 2 (Southbound)' => $total_dir2,
                'Grand Total Vehicles' => $grand_total,
                'Peak Hour' => $peak_count > 0 ? $peak_hour . ' (' . $peak_count . ' vehicles)' : 'No data',
                'Average Vehicles per Hour' => $grand_total > 0 ? round($grand_total / 24, 2) : 0
            ];
            $data['has_data'] = ($grand_total > 0);
            break;
            
        case 'weekly':
            $data['title'] = 'Weekly Traffic Report';
            $data['period'] = "Week: $start_date to $end_date";
            $data['headers'] = ['Day', 'Direction 1 (Northbound)', 'Direction 2 (Southbound)', 'Total'];
            
            $sql = "SELECT 
                        DATE(td.timestamp) as date,
                        DAYNAME(td.timestamp) as day_name,
                        SUM(CASE WHEN s.direction = 'direction_1' THEN 1 ELSE 0 END) as direction_1,
                        SUM(CASE WHEN s.direction = 'direction_2' THEN 1 ELSE 0 END) as direction_2,
                        COUNT(td.id) as total
                    FROM traffic_data td 
                    JOIN sensors s ON td.sensor_id = s.id 
                    WHERE DATE(td.timestamp) BETWEEN ? AND ?
                    GROUP BY DATE(td.timestamp)
                    ORDER BY date";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $total_dir1 = 0;
            $total_dir2 = 0;
            $grand_total = 0;
            $days_with_data = 0;
            
            while ($row = $result->fetch_assoc()) {
                $data['rows'][] = [
                    $row['day_name'] . ' (' . $row['date'] . ')',
                    $row['direction_1'],
                    $row['direction_2'],
                    $row['total']
                ];
                
                $total_dir1 += $row['direction_1'];
                $total_dir2 += $row['direction_2'];
                $grand_total += $row['total'];
                $days_with_data++;
            }
            $stmt->close();
            
            $avg_dir1 = $days_with_data > 0 ? round($total_dir1 / $days_with_data, 2) : 0;
            $avg_dir2 = $days_with_data > 0 ? round($total_dir2 / $days_with_data, 2) : 0;
            $avg_total = $days_with_data > 0 ? round($grand_total / $days_with_data, 2) : 0;
            
            $data['summary'] = [
                'Total Direction 1 (Northbound)' => $total_dir1,
                'Total Direction 2 (Southbound)' => $total_dir2,
                'Grand Total Vehicles' => $grand_total,
                'Average Daily (Direction 1)' => $avg_dir1,
                'Average Daily (Direction 2)' => $avg_dir2,
                'Average Daily Total' => $avg_total,
                'Days with Data' => $days_with_data
            ];
            $data['has_data'] = ($days_with_data > 0);
            break;
            
        case 'monthly':
            $data['title'] = 'Monthly Traffic Report';
            $data['period'] = "Month: " . date('F Y', strtotime($start_date));
            $data['headers'] = ['Date', 'Direction 1 (Northbound)', 'Direction 2 (Southbound)', 'Total'];
            
            $sql = "SELECT 
                        DATE(td.timestamp) as date,
                        SUM(CASE WHEN s.direction = 'direction_1' THEN 1 ELSE 0 END) as direction_1,
                        SUM(CASE WHEN s.direction = 'direction_2' THEN 1 ELSE 0 END) as direction_2,
                        COUNT(td.id) as total
                    FROM traffic_data td 
                    JOIN sensors s ON td.sensor_id = s.id 
                    WHERE DATE(td.timestamp) BETWEEN ? AND ?
                    GROUP BY DATE(td.timestamp)
                    ORDER BY date";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $total_dir1 = 0;
            $total_dir2 = 0;
            $grand_total = 0;
            $days_with_data = 0;
            
            while ($row = $result->fetch_assoc()) {
                $data['rows'][] = [
                    $row['date'],
                    $row['direction_1'],
                    $row['direction_2'],
                    $row['total']
                ];
                
                $total_dir1 += $row['direction_1'];
                $total_dir2 += $row['direction_2'];
                $grand_total += $row['total'];
                $days_with_data++;
            }
            $stmt->close();
            
            $avg_dir1 = $days_with_data > 0 ? round($total_dir1 / $days_with_data, 2) : 0;
            $avg_dir2 = $days_with_data > 0 ? round($total_dir2 / $days_with_data, 2) : 0;
            $avg_total = $days_with_data > 0 ? round($grand_total / $days_with_data, 2) : 0;
            
            $data['summary'] = [
                'Total Direction 1 (Northbound)' => $total_dir1,
                'Total Direction 2 (Southbound)' => $total_dir2,
                'Grand Total Vehicles' => $grand_total,
                'Average Daily (Direction 1)' => $avg_dir1,
                'Average Daily (Direction 2)' => $avg_dir2,
                'Average Daily Total' => $avg_total,
                'Days with Data' => $days_with_data
            ];
            $data['has_data'] = ($days_with_data > 0);
            break;
            
        case 'custom':
            $data['title'] = 'Custom Traffic Report';
            $data['period'] = "Period: $start_date to $end_date";
            $data['headers'] = ['Date', 'Direction 1 (Northbound)', 'Direction 2 (Southbound)', 'Total'];
            
            $sql = "SELECT 
                        DATE(td.timestamp) as date,
                        SUM(CASE WHEN s.direction = 'direction_1' THEN 1 ELSE 0 END) as direction_1,
                        SUM(CASE WHEN s.direction = 'direction_2' THEN 1 ELSE 0 END) as direction_2,
                        COUNT(td.id) as total
                    FROM traffic_data td 
                    JOIN sensors s ON td.sensor_id = s.id 
                    WHERE DATE(td.timestamp) BETWEEN ? AND ?
                    GROUP BY DATE(td.timestamp)
                    ORDER BY date";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $total_dir1 = 0;
            $total_dir2 = 0;
            $grand_total = 0;
            $days_with_data = 0;
            
            while ($row = $result->fetch_assoc()) {
                $data['rows'][] = [
                    $row['date'],
                    $row['direction_1'],
                    $row['direction_2'],
                    $row['total']
                ];
                
                $total_dir1 += $row['direction_1'];
                $total_dir2 += $row['direction_2'];
                $grand_total += $row['total'];
                $days_with_data++;
            }
            $stmt->close();
            
            $avg_dir1 = $days_with_data > 0 ? round($total_dir1 / $days_with_data, 2) : 0;
            $avg_dir2 = $days_with_data > 0 ? round($total_dir2 / $days_with_data, 2) : 0;
            $avg_total = $days_with_data > 0 ? round($grand_total / $days_with_data, 2) : 0;
            
            $data['summary'] = [
                'Total Direction 1 (Northbound)' => $total_dir1,
                'Total Direction 2 (Southbound)' => $total_dir2,
                'Grand Total Vehicles' => $grand_total,
                'Average Daily (Direction 1)' => $avg_dir1,
                'Average Daily (Direction 2)' => $avg_dir2,
                'Average Daily Total' => $avg_total,
                'Days in Period' => $days_with_data
            ];
            $data['has_data'] = ($days_with_data > 0);
            break;
    }
    
    return $data;
}

function exportAsPrintableHTML($data, $report_type, $start_date, $end_date) {
    // Create comprehensive HTML with print styling
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <link rel="shortcut icon" href="../icon_image/image4.ico">
        <meta charset="UTF-8">
        <title>'.$data['title'].'</title>
        <style>
            @media print {
                @page {
                    margin: 20px;
                    size: A4;
                }
                
                .watermark {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 80px;
                    color: rgba(0, 0, 0, 0.1);
                    font-weight: bold;
                    z-index: -1;
                    white-space: nowrap;
                    pointer-events: none;
                }
                
                body {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .print-section {
                    display: none;
                }
            }
            
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background: white;
                color: #333;
                position: relative;
            }
            
            .watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 80px;
                color: rgba(0, 0, 0, 0.08);
                font-weight: bold;
                z-index: -1;
                white-space: nowrap;
                pointer-events: none;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 3px double #333;
            }
            
            .title {
                font-size: 28px;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 10px;
            }
            
            .period {
                font-size: 18px;
                color: #7f8c8d;
                margin-bottom: 5px;
            }
            
            .generated {
                font-size: 14px;
                color: #95a5a6;
            }
            
            .report-content {
                margin: 20px 0;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            th {
                background-color: #3498db;
                color: white;
                font-weight: bold;
                padding: 12px;
                text-align: left;
                border: 1px solid #2980b9;
            }
            
            td {
                padding: 10px;
                border: 1px solid #ddd;
                text-align: left;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .summary {
                background-color: #ecf0f1;
                padding: 20px;
                border-radius: 8px;
                margin-top: 30px;
                border-left: 5px solid #3498db;
            }
            
            .summary h3 {
                color: #2c3e50;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 20px;
            }
            
            .summary-item {
                margin: 8px 0;
                font-size: 16px;
            }
            
            .summary-key {
                font-weight: bold;
                color: #2c3e50;
                display: inline-block;
                width: 250px;
            }
            
            .footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #bdc3c7;
                color: #7f8c8d;
                font-size: 12px;
            }
            
            .no-data {
                text-align: center;
                padding: 40px;
                color: #7f8c8d;
                font-style: italic;
                background-color: #f8f9fa;
                border-radius: 8px;
                margin: 20px 0;
            }
            
            .print-btn {
                background: #27ae60;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin: 20px 0;
            }
            
            .print-btn:hover {
                background: #219653;
            }
            
            @media screen {
                .print-section {
                    text-align: center;
                    margin: 20px 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="watermark">TRAFFIC MONITORING SYSTEM</div>
        
        <div class="header">
            <div class="title">'.$data['title'].'</div>
            <div class="period">'.$data['period'].'</div>
            <div class="generated">Generated on: '.date('F j, Y \a\t g:i A').'</div>
        </div>
        
        <div class="print-section">
            <button class="print-btn" onclick="window.print()">📄 Print as PDF</button>
            <p>Click the button above to print this report as PDF, or use Ctrl+P</p>
        </div>
        
        <div class="report-content">';
    
    if ($data['has_data'] && !empty($data['rows'])) {
        $html .= '<table>
                    <thead>
                        <tr>';
        foreach ($data['headers'] as $header) {
            $html .= '<th>'.$header.'</th>';
        }
        $html .= '</tr>
                    </thead>
                    <tbody>';
        
        foreach ($data['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.$cell.'</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
                </table>';
    } else {
        $html .= '<div class="no-data">
                    <h3>📊 No Traffic Data Available</h3>
                    <p>No traffic data was recorded for the selected period:<br>
                    <strong>'.$start_date.' to '.$end_date.'</strong></p>
                    <p>Please check if:</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>Sensors are properly connected</li>
                        <li>Data is being recorded in the database</li>
                        <li>The date range is correct</li>
                    </ul>
                 </div>';
    }
    
    if ($data['has_data'] && !empty($data['summary'])) {
        $html .= '<div class="summary">
                    <h3>📈 Report Summary</h3>';
        foreach ($data['summary'] as $key => $value) {
            $html .= '<div class="summary-item">
                        <span class="summary-key">'.$key.':</span>
                        <span class="summary-value">'.$value.'</span>
                      </div>';
        }
        $html .= '</div>';
    }
    
    $html .= '
        </div>
        
        <div class="footer">
            <p>© '.date('Y').' Traffic Monitoring System | Generated Automatically</p>
            <p>This report contains confidential traffic data. Unauthorized distribution is prohibited.</p>
        </div>
        
        <script>
            // Auto-trigger print dialog when page loads
            window.onload = function() {
                setTimeout(function() {
                    // Only auto-print if there\'s data
                    if (' . ($data['has_data'] ? 'true' : 'false') . ') {
                        window.print();
                    }
                }, 500);
            };
        </script>
    </body>
    </html>';
    
    // Output the HTML
    echo $html;
    exit;
}
?>