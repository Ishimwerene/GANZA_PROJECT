<?php
// index.php
require_once 'config.php';

// Get current traffic counts
$today = date('Y-m-d');
$count_direction_1 = 0;
$count_direction_2 = 0;

// Get today's counts - FIXED: Properly handle cases where only one direction has data
$sql = "SELECT s.direction, COUNT(td.id) as count 
        FROM traffic_data td 
        JOIN sensors s ON td.sensor_id = s.id 
        WHERE DATE(td.timestamp) = ? 
        GROUP BY s.direction";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

// Initialize both directions to 0 first
$direction_counts = [
    'direction_1' => 0,
    'direction_2' => 0
];

while ($row = $result->fetch_assoc()) {
    $direction_counts[$row['direction']] = $row['count'];
}

// Assign the counts (this ensures both directions always have a value)
$count_direction_1 = $direction_counts['direction_1'];
$count_direction_2 = $direction_counts['direction_2'];

$stmt->close();

// Get vehicle type counts for today
$vehicle_type_counts = [];
$vehicle_sql = "SELECT vehicle_type, COUNT(*) as count 
                FROM traffic_data 
                WHERE DATE(timestamp) = ? 
                GROUP BY vehicle_type";

$stmt = $conn->prepare($vehicle_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$vehicle_result = $stmt->get_result();

while ($row = $vehicle_result->fetch_assoc()) {
    $vehicle_type_counts[$row['vehicle_type']] = $row['count'];
}
$stmt->close();

// Get hourly data for today's chart - FIXED: Ensure both directions are properly handled
$hourly_data = ['direction_1' => array_fill(0, 24, 0), 'direction_2' => array_fill(0, 24, 0)];
$hourly_sql = "SELECT s.direction, HOUR(td.timestamp) as hour, COUNT(td.id) as count 
               FROM traffic_data td 
               JOIN sensors s ON td.sensor_id = s.id 
               WHERE DATE(td.timestamp) = ? 
               GROUP BY s.direction, HOUR(td.timestamp)";

$stmt = $conn->prepare($hourly_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$hourly_result = $stmt->get_result();

while ($row = $hourly_result->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $direction = $row['direction'];
    $hourly_data[$direction][$hour] = $row['count'];
}
$stmt->close();

// Get weekly data - FIXED: Ensure both directions are properly handled
$weekly_data = ['direction_1' => array_fill(0, 7, 0), 'direction_2' => array_fill(0, 7, 0)];
$week_start = date('Y-m-d', strtotime('-6 days'));
$weekly_sql = "SELECT s.direction, DAYNAME(td.timestamp) as day, COUNT(td.id) as count 
               FROM traffic_data td 
               JOIN sensors s ON td.sensor_id = s.id 
               WHERE DATE(td.timestamp) BETWEEN ? AND ? 
               GROUP BY s.direction, DAYOFWEEK(td.timestamp) 
               ORDER BY DAYOFWEEK(td.timestamp)";

$stmt = $conn->prepare($weekly_sql);
$stmt->bind_param("ss", $week_start, $today);
$stmt->execute();
$weekly_result = $stmt->get_result();

$day_map = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
while ($row = $weekly_result->fetch_assoc()) {
    $day_index = $day_map[$row['day']];
    $direction = $row['direction'];
    $weekly_data[$direction][$day_index] = $row['count'];
}
$stmt->close();

// Get monthly data for trends - FIXED: Ensure both directions are properly handled
$monthly_data = ['direction_1' => array_fill(0, 12, 0), 'direction_2' => array_fill(0, 12, 0)];
$current_year = date('Y');
$monthly_sql = "SELECT s.direction, MONTH(td.timestamp) as month, COUNT(td.id) as count 
                FROM traffic_data td 
                JOIN sensors s ON td.sensor_id = s.id 
                WHERE YEAR(td.timestamp) = ? 
                GROUP BY s.direction, MONTH(td.timestamp)";

$stmt = $conn->prepare($monthly_sql);
$stmt->bind_param("s", $current_year);
$stmt->execute();
$monthly_result = $stmt->get_result();

while ($row = $monthly_result->fetch_assoc()) {
    $month = (int)$row['month'] - 1;
    $direction = $row['direction'];
    $monthly_data[$direction][$month] = $row['count'];
}
$stmt->close();

// Get peak hours data
$peak_hours_data = array_fill(0, 24, 0);
$peak_sql = "SELECT HOUR(td.timestamp) as hour, COUNT(td.id) as count 
             FROM traffic_data td 
             WHERE DATE(td.timestamp) = ? 
             GROUP BY HOUR(td.timestamp)";

$stmt = $conn->prepare($peak_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$peak_result = $stmt->get_result();

while ($row = $peak_result->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $peak_hours_data[$hour] = $row['count'];
}
$stmt->close();

// Get direction comparison data - FIXED: Ensure both directions are properly handled
$direction_totals = [0, 0];
$total_sql = "SELECT s.direction, COUNT(td.id) as count 
              FROM traffic_data td 
              JOIN sensors s ON td.sensor_id = s.id 
              WHERE DATE(td.timestamp) = ? 
              GROUP BY s.direction";
$stmt = $conn->prepare($total_sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$total_result = $stmt->get_result();

// Initialize direction totals properly
$dir_totals = ['direction_1' => 0, 'direction_2' => 0];
while ($row = $total_result->fetch_assoc()) {
    $dir_totals[$row['direction']] = $row['count'];
}
$direction_totals = [$dir_totals['direction_1'], $dir_totals['direction_2']];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Monitoring System</title>
    <link rel="shortcut icon" href="icon_image/image4.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .navbar {
            background-color: var(--primary-color);
        }
        
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
            border: none;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .traffic-count {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .direction-indicator {
            height: 10px;
            width: 100%;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .direction-1 {
            background-color: var(--secondary-color);
        }
        
        .direction-2 {
            background-color: var(--accent-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: #2ecc71;
        }
        
        .status-inactive {
            background-color: #e74c3c;
        }
        
        .sensor-data {
            background-color: var(--light-color);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 20px 0;
            margin-top: 30px;
        }
        
        .report-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .vehicle-type-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.6em;
        }
        
        .vehicle-type-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .vehicle-count {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s ease;
            margin-left: 15px;
            opacity: 0.9;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                🚦 Traffic Monitoring System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#reports">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#analysis">Analysis</a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="logout.php" class="logout-btn">Logout</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Dashboard Section -->
        <section id="dashboard">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">Traffic Dashboard</h2>
                </div>
            </div>
            
            <!-- Vehicle Type Counts -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card dashboard-card vehicle-type-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">📊 Today's Vehicle Type Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="vehicle-type-counts">
                                <?php
                                $total_vehicles = array_sum($vehicle_type_counts);
                                $default_vehicle_types = ['Car',  'Truck', 'Bus'];
                                
                                foreach ($default_vehicle_types as $vehicle_type) {
                                    $count = $vehicle_type_counts[$vehicle_type] ?? 0;
                                    $percentage = $total_vehicles > 0 ? round(($count / $total_vehicles) * 100, 1) : 0;
                                    $icon = getVehicleIcon($vehicle_type);
                                    
                                    echo "
                                    <div class='col-md-2 col-sm-4 col-6 text-center mb-3'>
                                        <div class='vehicle-count'>{$count}</div>
                                        <div class='mb-1'>{$icon}</div>
                                        <div><strong>{$vehicle_type}</strong></div>
                                        <small class='text-light'>{$percentage}%</small>
                                    </div>";
                                }
                                
                                // Add total vehicles
                                echo "
                                <div class='col-md-2 col-sm-4 col-6 text-center mb-3'>
                                    <div class='vehicle-count'>{$total_vehicles}</div>
                                    <div class='mb-1'>🚗</div>
                                    <div><strong>Total</strong></div>
                                    <small class='text-light'>100%</small>
                                </div>";
                                
                                function getVehicleIcon($type) {
                                    $icons = [
                                        'Car' => '🚗',
                                        'Motorcycle' => '🏍️',
                                        'Truck' => '🚚',
                                        'Bus' => '🚌',
                                        'Bicycle' => '🚲',
                                        'Van' => '🚐',
                                        'SUV' => '🚙'
                                    ];
                                    return $icons[$type] ?? '🚗';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Traffic Count -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Direction 1 - Northbound</h5>
                        </div>
                        <div class="card-body">
                            <div class="direction-indicator direction-1"></div>
                            <div class="traffic-count" id="count-direction-1"><?php echo $count_direction_1; ?></div>
                            <p class="text-muted">Vehicles counted today</p>
                            <div class="sensor-data">
                                <h6>Sensor Status: 
                                    <span class="status-indicator status-active"></span>
                                    <span id="sensor-status-1">Active</span>
                                </h6>
                                <p>Last updated: <span id="last-update-1"><?php echo date('H:i:s'); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Direction 2 - Southbound</h5>
                        </div>
                        <div class="card-body">
                            <div class="direction-indicator direction-2"></div>
                            <div class="traffic-count" id="count-direction-2"><?php echo $count_direction_2; ?></div>
                            <p class="text-muted">Vehicles counted today</p>
                            <div class="sensor-data">
                                <h6>Sensor Status: 
                                    <span class="status-indicator status-active"></span>
                                    <span id="sensor-status-2">Active</span>
                                </h6>
                                <p>Last updated: <span id="last-update-2"><?php echo date('H:i:s'); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Today's Traffic Flow</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="hourlyTrafficChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Weekly Comparison</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="weeklyComparisonChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Direction</th>
                                            <th>Vehicle Type</th>
                                            <th>Height</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-activity">
                                        <!-- Activity data will be populated via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Reports Section -->
        <section id="reports" class="mt-5" style="display: none;">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">Traffic Reports</h2>
                </div>
            </div>
            
            <div class="report-section">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="report-type" class="form-label">Report Type</label>
                        <select class="form-select" id="report-type">
                            <option value="daily">Daily Report</option>
                            <option value="weekly">Weekly Report</option>
                            <option value="monthly">Monthly Report</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="start-date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start-date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end-date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end-date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button class="btn btn-primary" id="generate-report">Generate Report</button>
                        <button class="btn btn-success" id="export-report">Export Report as PDF</button>
                    </div>
                </div>
                
                <!-- Export Note -->
                <div class="mt-2">
                    <small class="text-muted">PDF report includes watermark and professional formatting</small>
                </div>
            </div>
            
            <div class="report-section mt-4">
                <h4>Report Results</h4>
                <div id="report-results">
                    <p class="text-muted">Select parameters and generate a report to view data.</p>
                </div>
            </div>
        </section>
        
        <!-- Analysis Section -->
        <section id="analysis" class="mt-5" style="display: none;">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">Traffic Analysis</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Monthly Traffic Trends</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Peak Hours Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Direction Comparison</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="directionComparisonChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vehicle Type Analysis -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Vehicle Type Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="vehicleTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Settings Section -->
        <section id="settings" class="mt-5" style="display: none;">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">System Settings</h2>
                </div>
            </div>
            
            <div class="report-section">
                <h4>Sensor Configuration</h4>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="sensor-1-name" class="form-label">Direction 1 Name</label>
                        <input type="text" class="form-control" id="sensor-1-name" value="Northbound">
                    </div>
                    <div class="col-md-6">
                        <label for="sensor-2-name" class="form-label">Direction 2 Name</label>
                        <input type="text" class="form-control" id="sensor-2-name" value="Southbound">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="data-interval" class="form-label">Data Update Interval (seconds)</label>
                        <input type="number" class="form-control" id="data-interval" value="10" min="5">
                    </div>
                    <div class="col-md-6">
                        <label for="alert-threshold" class="form-label">Traffic Alert Threshold (vehicles/hour)</label>
                        <input type="number" class="form-control" id="alert-threshold" value="100">
                    </div>
                </div>
                
                <button class="btn btn-primary" id="save-settings">Save Settings</button>
            </div>
            
            <div class="report-section mt-4">
                <h4>API Configuration</h4>
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="api-endpoint" class="form-label">Sensor Data Endpoint</label>
                        <input type="text" class="form-control" id="api-endpoint" value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/api/receive_data.php">
                    </div>
                </div>
                <button class="btn btn-primary" id="test-api">Test Connection</button>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Traffic Monitoring System</h5>
                    <p>Real-time traffic data collection and analysis using Arduino and ultrasonic sensors.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#dashboard" class="text-light">Go Above</a></li>
                        <!--
                        <li><a href="#reports" class="text-light">Reports</a></li>
                        <li><a href="#analysis" class="text-light">Analysis</a></li>
                        -->
                    </ul>
                </div>

            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 Traffic Monitoring System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Hide all sections
                document.querySelectorAll('section').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show selected section
                const targetId = this.getAttribute('href').substring(1);
                document.getElementById(targetId).style.display = 'block';
                
                // Update active nav link
                document.querySelectorAll('.nav-link').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Vehicle type counts from PHP
        const vehicleTypeCounts = <?php echo json_encode($vehicle_type_counts); ?>;
        const totalVehicles = <?php echo array_sum($vehicle_type_counts); ?>;

        // Initialize charts with real data from PHP
        const hourlyTrafficCtx = document.getElementById('hourlyTrafficChart').getContext('2d');
        const hourlyTrafficChart = new Chart(hourlyTrafficCtx, {
            type: 'line',
            data: {
                labels: ['12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'],
                datasets: [
                    {
                        label: 'Direction 1',
                        data: <?php echo json_encode($hourly_data['direction_1']); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Direction 2',
                        data: <?php echo json_encode($hourly_data['direction_2']); ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Vehicle Count'
                        }
                    }
                }
            }
        });

        const weeklyComparisonCtx = document.getElementById('weeklyComparisonChart').getContext('2d');
        const weeklyComparisonChart = new Chart(weeklyComparisonCtx, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [
                    {
                        label: 'Direction 1',
                        data: <?php echo json_encode($weekly_data['direction_1']); ?>,
                        backgroundColor: '#3498db'
                    },
                    {
                        label: 'Direction 2',
                        data: <?php echo json_encode($weekly_data['direction_2']); ?>,
                        backgroundColor: '#e74c3c'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Vehicle Count'
                        }
                    }
                }
            }
        });

        const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyTrendChart = new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Direction 1',
                        data: <?php echo json_encode($monthly_data['direction_1']); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Direction 2',
                        data: <?php echo json_encode($monthly_data['direction_2']); ?>,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Monthly Vehicle Count'
                        }
                    }
                }
            }
        });

        const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
        const peakHoursChart = new Chart(peakHoursCtx, {
            type: 'bar',
            data: {
                labels: ['12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM'],
                datasets: [
                    {
                        label: 'Total Vehicles',
                        data: <?php echo json_encode($peak_hours_data); ?>,
                        backgroundColor: '#9b59b6'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Vehicle Count'
                        }
                    }
                }
            }
        });

        const directionComparisonCtx = document.getElementById('directionComparisonChart').getContext('2d');
        const directionComparisonChart = new Chart(directionComparisonCtx, {
            type: 'doughnut',
            data: {
                labels: ['Direction 1', 'Direction 2'],
                datasets: [{
                    data: <?php echo json_encode($direction_totals); ?>,
                    backgroundColor: ['#3498db', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Vehicle Type Chart
        const vehicleTypeCtx = document.getElementById('vehicleTypeChart').getContext('2d');
        const vehicleTypeChart = new Chart(vehicleTypeCtx, {
            type: 'pie',
            data: {
                labels: Object.keys(vehicleTypeCounts),
                datasets: [{
                    data: Object.values(vehicleTypeCounts),
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6',
                        '#1abc9c', '#d35400', '#c0392b', '#16a085', '#8e44ad'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Vehicle Type Distribution'
                    }
                }
            }
        });

        // Function to fetch real-time data
        async function fetchTrafficData() {
            try {
                const response = await fetch('api/get_data.php');
                const data = await response.json();
                
                // Update counts - FIXED: Ensure both directions are properly updated
                document.getElementById('count-direction-1').textContent = data.direction_1?.total || 0;
                document.getElementById('count-direction-2').textContent = data.direction_2?.total || 0;
                
                // Update vehicle type counts if available
                if (data.vehicle_types) {
                    updateVehicleTypeCounts(data.vehicle_types);
                }
                
                // Update last updated time
                const now = new Date();
                document.getElementById('last-update-1').textContent = now.toLocaleTimeString();
                document.getElementById('last-update-2').textContent = now.toLocaleTimeString();
                
                // Update recent activity
                if (data.recent_activity) {
                    updateRecentActivity(data.recent_activity);
                }
                
            } catch (error) {
                console.error('Error fetching traffic data:', error);
            }
        }

        // Function to update vehicle type counts
        function updateVehicleTypeCounts(vehicleTypes) {
            const container = document.getElementById('vehicle-type-counts');
            let total = 0;
            
            // Calculate total
            Object.values(vehicleTypes).forEach(count => {
                total += parseInt(count);
            });
            
            // Update display
            const defaultTypes = ['Car', 'Motorcycle', 'Truck', 'Bus', 'Bicycle'];
            let html = '';
            
            defaultTypes.forEach(type => {
                const count = vehicleTypes[type] || 0;
                const percentage = total > 0 ? Math.round((count / total) * 100) : 0;
                const icon = getVehicleIcon(type);
                
                html += `
                <div class='col-md-2 col-sm-4 col-6 text-center mb-3'>
                    <div class='vehicle-count'>${count}</div>
                    <div class='mb-1'>${icon}</div>
                    <div><strong>${type}</strong></div>
                    <small class='text-light'>${percentage}%</small>
                </div>`;
            });
            
            // Add total
            html += `
            <div class='col-md-2 col-sm-4 col-6 text-center mb-3'>
                <div class='vehicle-count'>${total}</div>
                <div class='mb-1'>🚗</div>
                <div><strong>Total</strong></div>
                <small class='text-light'>100%</small>
            </div>`;
            
            container.innerHTML = html;
        }

        // Helper function to get vehicle icons
        function getVehicleIcon(type) {
            const icons = {
                'Car': '🚗',
                'Motorcycle': '🏍️',
                'Truck': '🚚',
                'Bus': '🚌',
                'Bicycle': '🚲',
                'Van': '🚐',
                'SUV': '🚙'
            };
            return icons[type] || '🚗';
        }

        // Function to update recent activity
        function updateRecentActivity(activities) {
            const activityTable = document.getElementById('recent-activity');
            
            // Clear existing content
            while (activityTable.firstChild) {
                activityTable.removeChild(activityTable.firstChild);
            }
            
            activities.forEach(activity => {
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td>${activity.time}</td>
                    <td>${activity.direction}</td>
                    <td>${activity.vehicle_type || 'Car'}</td>
                    <td>${activity.height ? activity.height + 'm' : 'N/A'}</td>
                    <td><span class="badge bg-success">Detected</span></td>
                `;
                activityTable.appendChild(newRow);
            });
        }

        // Report generation
        document.getElementById('generate-report').addEventListener('click', async function() {
            const reportType = document.getElementById('report-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            const button = this;
            button.classList.add('loading');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Generating...';
            
            try {
                const response = await fetch(`api/generate_report.php?type=${reportType}&start=${startDate}&end=${endDate}`);
                const data = await response.json();
                displayReportResults(data);
            } catch (error) {
                console.error('Error generating report:', error);
                document.getElementById('report-results').innerHTML = '<div class="alert alert-danger">Error generating report. Please try again.</div>';
            } finally {
                button.classList.remove('loading');
                button.innerHTML = 'Generate Report';
            }
        });

        function displayReportResults(data) {
            const reportResults = document.getElementById('report-results');
            
            if (data.error) {
                reportResults.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            reportResults.innerHTML = `
                <h5>Traffic Report: ${data.startDate} to ${data.endDate}</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Direction 1 Count</th>
                                <th>Direction 2 Count</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.rows.map(row => `
                                <tr>
                                    <td>${row.date}</td>
                                    <td>${row.direction1}</td>
                                    <td>${row.direction2}</td>
                                    <td>${row.total}</td>
                                </tr>
                            `).join('')}
                            ${data.rows.length > 0 ? `
                            <tr>
                                <td><strong>Average</strong></td>
                                <td><strong>${data.averages.direction1}</strong></td>
                                <td><strong>${data.averages.direction2}</strong></td>
                                <td><strong>${data.averages.total}</strong></td>
                            </tr>
                            ` : ''}
                        </tbody>
                    </table>
                </div>
                ${data.summary ? `<p><strong>Summary:</strong> ${data.summary}</p>` : ''}
                ${data.rows.length === 0 ? '<p class="text-muted">No data found for the selected period.</p>' : ''}
            `;
        }

        // Export Report functionality - Updated for PDF only
        document.getElementById('export-report').addEventListener('click', function() {
            exportReportPDF();
        });

        // Function to handle PDF export
        async function exportReportPDF() {
            const reportType = document.getElementById('report-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            // Show loading state
            const exportBtn = document.getElementById('export-report');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Generating PDF...';
            exportBtn.disabled = true;
            
            try {
                // Create a form to open in new tab for PDF
                const form = document.createElement('form');
                form.method = 'GET';
                form.action = 'api/export_report.php';
                form.target = '_blank';
                
                // Add parameters
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'type';
                typeInput.value = reportType;
                form.appendChild(typeInput);
                
                const startInput = document.createElement('input');
                startInput.type = 'hidden';
                startInput.name = 'start';
                startInput.value = startDate;
                form.appendChild(startInput);
                
                const endInput = document.createElement('input');
                endInput.type = 'hidden';
                endInput.name = 'end';
                endInput.value = endDate;
                form.appendChild(endInput);
                
                // Add to document and submit
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                
            } catch (error) {
                console.error('Error exporting report:', error);
                alert('Error exporting report. Please try again.');
            } finally {
                // Restore button state after a short delay
                setTimeout(() => {
                    exportBtn.innerHTML = originalText;
                    exportBtn.disabled = false;
                }, 2000);
            }
        }

        // Initialize with real data
        fetchTrafficData();
        
        // Update data every 10 seconds
        setInterval(fetchTrafficData, 10000);
    </script>
</body>
</html>