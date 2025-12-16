<?php
session_start();

// ADMIN ACCESS CONTROL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once "../classes/database.php";
require_once "../classes/laundryorder.php";

$database = new Database();
$pdo_conn = $database->connect();

$orderObj = new LaundryOrder($pdo_conn);

// Helper function for PHP currency formatting
function format_php($amount) {
    return '₱' . number_format($amount, 2);
}


// --- 1. Data Fetching for ORDER VOLUME (Daily/Monthly) ---
$daily_data = $orderObj->getOrderVolumeAnalysis('DATE(date_created)', 'order_date');

$daily_labels = [];
$daily_counts = [];
foreach ($daily_data as $row) {
    $daily_labels[] = htmlspecialchars($row['order_date']); 
    $daily_counts[] = (int)$row['total_orders'];
}
$jsonDailyLabels = json_encode($daily_labels);
$jsonDailyCounts = json_encode($daily_counts);
$totalOrdersDaily = array_sum($daily_counts); // Total Orders (All Time)

$monthly_data = $orderObj->getOrderVolumeAnalysis('DATE_FORMAT(date_created, "%Y-%m")', 'order_month');

$monthly_labels = [];
$monthly_counts = [];
foreach ($monthly_data as $row) {
    $timestamp = strtotime($row['order_month'] . '-01'); 
    $monthly_labels[] = date('M Y', $timestamp);
    $monthly_counts[] = (int)$row['total_orders'];
}
$jsonMonthlyLabels = json_encode($monthly_labels);
$jsonMonthlyCounts = json_encode($monthly_counts);


// --- 2. Data Fetching for REVENUE VOLUME (Daily/Monthly) ---
$daily_revenue_data = $orderObj->getRevenueAnalysis('DATE(date_created)', 'order_date');

$daily_revenue_labels = [];
$daily_revenue_amounts = [];
foreach ($daily_revenue_data as $row) {
    $daily_revenue_labels[] = htmlspecialchars($row['order_date']); 
    $daily_revenue_amounts[] = (float)$row['total_revenue'];
}
$jsonDailyRevenueLabels = json_encode($daily_revenue_labels);
$jsonDailyRevenueAmounts = json_encode($daily_revenue_amounts);

$monthly_revenue_data = $orderObj->getRevenueAnalysis('DATE_FORMAT(date_created, "%Y-%m")', 'order_month');

$monthly_revenue_labels = [];
$monthly_revenue_amounts = [];
foreach ($monthly_revenue_data as $row) {
    $timestamp = strtotime($row['order_month'] . '-01');
    $monthly_revenue_labels[] = date('M Y', $timestamp);
    $monthly_revenue_amounts[] = (float)$row['total_revenue'];
}
$jsonMonthlyRevenueLabels = json_encode($monthly_revenue_labels);
$jsonMonthlyRevenueAmounts = json_encode($monthly_revenue_amounts);


// --- 3. Data Fetching for SERVICE TYPE (PIE CHART) ---
$service_data = $orderObj->getServiceTypeAnalysis();

$service_labels = [];
$service_counts = [];
foreach ($service_data as $row) {
    $service_labels[] = htmlspecialchars($row['service_type']);
    $service_counts[] = (int)$row['total_orders'];
}
$jsonServiceLabels = json_encode($service_labels);
$jsonServiceCounts = json_encode($service_counts);
$totalOrdersService = array_sum($service_counts);


// --- 4. Data Fetching for SUMMARY KPIs (Total Revenue & AOV) ---
$kpi_summary = $orderObj->getRevenueSummary();
$total_revenue_all_time = (float)($kpi_summary['total_revenue'] ?? 0.00);
$total_completed_orders = (int)($kpi_summary['total_completed_orders'] ?? 0);
$average_order_value = ($total_completed_orders > 0) ? ($total_revenue_all_time / $total_completed_orders) : 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- LAYOUT STYLES --- */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: var(--background-light); 
            color: var(--text-dark);
        }
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40; 
            color: #fff;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        /* --- END LAYOUT STYLES --- */

        :root {
            --primary-color: #007bff;
            --success-color: #28a745; 
            --background-light: #f4f7fa;
            --card-background: #ffffff;
            --text-dark: #343a40;
            --border-color: #e9ecef;
        }
        .container { 
            width: 100%; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 30px; 
            background-color: var(--card-background); 
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); 
        }
        h1 { 
            color: var(--primary-color); 
            border-bottom: 3px solid var(--primary-color); 
            padding-bottom: 10px; 
            margin-bottom: 25px; 
            font-size: 2em;
        }
        .kpi-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-box {
            flex: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            text-align: center;
            border-bottom: 4px solid var(--success-color);
        }
        .kpi-box h4 {
            color: #6c757d;
            font-size: 0.85em;
            margin-top: 0;
            text-transform: uppercase;
        }
        .kpi-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
        }
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        a:hover {
            color: #0056b3;
        }
        .chart-row {
            display: flex;
            justify-content: space-between;
            gap: 30px; 
            margin-bottom: 40px;
            flex-wrap: wrap; 
        }
        .chart-box {
            flex: 1;
            min-width: 350px; 
            background: var(--card-background); 
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); 
            border-left: 5px solid var(--primary-color); 
        }
        h2 {
            color: var(--text-dark);
            font-size: 1.5em;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .data-table th, .data-table td {
            border: 1px solid var(--border-color);
            padding: 12px 15px;
            text-align: left;
        }
        .data-table th {
            background-color: #e9ecef;
            color: var(--text-dark);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa; 
        }
        .data-table td:nth-child(2),
        .data-table td:nth-child(3) {
            text-align: center; 
            font-weight: bold;
        }
        /* Style for the new radio button toggle */
        .chart-toggle {
            margin-bottom: 10px;
            text-align: right;
            font-size: 0.9em;
        }
        .chart-toggle label {
            margin-left: 15px;
            cursor: pointer;
            font-weight: normal;
        }
        .chart-toggle input[type="radio"] {
            margin-right: 5px;
        }
        
        /* ---------------------------------------------------- */
        /* --- PRINT STYLES --- */
        /* ---------------------------------------------------- */
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; } /* Hide sidebar on print */
            .content { padding: 0 !important; }
            .container {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .kpi-box, .chart-box {
                box-shadow: none;
                border: 1px solid #ccc;
                border-left: none !important;
                page-break-inside: avoid;
            }
            .kpi-row, .chart-row { display: block; gap: 0; }
            .kpi-box { border-bottom: 1px solid #ccc !important; margin-bottom: 10px; }
            .chart-box { margin-bottom: 20px; min-width: 100%; width: 100%; height: auto; }
            .data-table th, .data-table td { border-color: #000; }
            .data-table th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include "../includes/sidebar.php"; ?>
        
        <div class="content">
            <div class="container">
                <h1>📊 Reports & Analytics Dashboard</h1>
                
                <button class="no-print" onclick="window.print()" style="padding: 10px 20px; background-color: var(--success-color); color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px;">
                    🖨️ Print Report
                </button>

                <hr>

                <div class="kpi-row">
                     <div class="kpi-box" style="border-bottom-color: #007bff;">
                        <h4>Total Orders (Completed)</h4>
                        <div class="kpi-value"><?= $total_completed_orders ?></div>
                    </div>
                    <div class="kpi-box" style="border-bottom-color: #28a745;">
                        <h4>Total Revenue (All Time)</h4>
                        <div class="kpi-value"><?= format_php($total_revenue_all_time) ?></div>
                    </div>
                    <div class="kpi-box" style="border-bottom-color: #ffc107;">
                        <h4>Average Order Value (AOV)</h4>
                        <div class="kpi-value"><?= format_php($average_order_value) ?></div>
                    </div>
                </div>
                
                <div class="chart-row">
                    <div class="chart-box">
                        <div class="chart-toggle no-print">
                            <input type="radio" id="dailyRadio" name="timeframe" value="daily" checked>
                            <label for="dailyRadio">Daily</label>
                            <input type="radio" id="monthlyRadio" name="timeframe" value="monthly">
                            <label for="monthlyRadio">Monthly</label>
                        </div>
                        
                        <h2 id="volumeTitle">Daily Order Volume</h2>
                        <div style="height: 300px;">
                            <canvas id="dailyOrderChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-box" style="border-left-color: #dc3545;">
                         <h2 id="revenueTitle">Daily Revenue Volume</h2>
                         <div style="height: 300px;">
                            <canvas id="dailyRevenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-box" style="flex: 0 0 45%; border-left-color: #6f42c1;">
                        <h2>Service Type Distribution</h2>
                        <div style="height: 300px; max-width: 300px; margin: 0 auto;">
                            <canvas id="serviceChart"></canvas>
                        </div>
                    </div>
                    
                     <div class="chart-box" style="flex: 0 0 45%; border-left-color: #28a745;">
                        <h2>Detailed Revenue Breakdown</h2>
                        <table class="data-table">
                            <thead>
                                <tr><th>Date</th><th>Revenue</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($daily_revenue_data)): ?>
                                    <tr><td colspan="2" style="text-align: center;">No revenue data found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($daily_revenue_data as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['order_date']) ?></td>
                                        <td><?= format_php($row['total_revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr>

                <h2>Detailed Order Data</h2>
                
                <h3>Daily Order Counts</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Date</th><th>Orders Processed</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily_data)): ?>
                            <tr><td colspan="2" style="text-align: center;">No daily order data found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($daily_data as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['order_date']) ?></td>
                                <td><?= htmlspecialchars($row['total_orders']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Service Type Breakdown</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Service Type</th><th>Orders Processed</th><th>Percentage</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($service_data)): ?>
                            <tr><td colspan="3" style="text-align: center;">No service type data found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($service_data as $row): 
                            $percentage = ($totalOrdersService > 0) ? round(($row['total_orders'] / $totalOrdersService) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['service_type']) ?></td>
                                <td><?= htmlspecialchars($row['total_orders']) ?></td>
                                <td><?= $percentage ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Data injected from PHP
        const dailyLabels = <?= $jsonDailyLabels ?>;
        const dailyCounts = <?= $jsonDailyCounts ?>;
        const monthlyLabels = <?= $jsonMonthlyLabels ?>;
        const monthlyCounts = <?= $jsonMonthlyCounts ?>;
        
        // NEW REVENUE DATA
        const dailyRevenueLabels = <?= $jsonDailyRevenueLabels ?>;
        const dailyRevenueAmounts = <?= $jsonDailyRevenueAmounts ?>;
        const monthlyRevenueLabels = <?= $jsonMonthlyRevenueLabels ?>;
        const monthlyRevenueAmounts = <?= $jsonMonthlyRevenueAmounts ?>;
        
        // PIE CHART DATA (remains static)
        const serviceLabels = <?= $jsonServiceLabels ?>;
        const serviceCounts = <?= $jsonServiceCounts ?>;
        
        let dailyVolumeChart; // Bar Chart for Orders
        let dailyRevenueChart; // Bar Chart for Revenue

        // Function to create/update the Bar Chart for Order Volume
        function renderVolumeChart(labels, data, title) {
            const ctxDaily = document.getElementById('dailyOrderChart').getContext('2d');

            if (dailyVolumeChart) {
                // Update existing chart
                dailyVolumeChart.data.labels = labels;
                dailyVolumeChart.data.datasets[0].data = data;
                dailyVolumeChart.options.plugins.title.text = title;
                dailyVolumeChart.update();
            } else {
                // Create new chart instance
                dailyVolumeChart = new Chart(ctxDaily, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Orders',
                            data: data,
                            backgroundColor: 'rgba(0, 123, 255, 0.7)', 
                            borderColor: 'rgba(0, 123, 255, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                        plugins: { title: { display: true, text: title } }
                    }
                });
            }
        }
        
        // NEW: Function to create/update the Bar Chart for Revenue Volume
        function renderRevenueChart(labels, data, title) {
            const ctxRevenue = document.getElementById('dailyRevenueChart').getContext('2d');
            
            if (dailyRevenueChart) {
                dailyRevenueChart.data.labels = labels;
                dailyRevenueChart.data.datasets[0].data = data;
                dailyRevenueChart.options.plugins.title.text = title;
                dailyRevenueChart.update();
            } else {
                dailyRevenueChart = new Chart(ctxRevenue, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Revenue (PHP)',
                            data: data,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)', // Red color for revenue
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true } },
                        plugins: { title: { display: true, text: title } }
                    }
                });
            }
        }


        // Function to render the static Pie Chart
        function renderServiceChart() {
            const ctxService = document.getElementById('serviceChart').getContext('2d');
            const pieColors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8'];

            if(serviceLabels.length > 0) {
                new Chart(ctxService, {
                    type: 'pie',
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            label: 'Orders by Service Type',
                            data: serviceCounts,
                            backgroundColor: pieColors.slice(0, serviceLabels.length),
                            borderColor: '#fff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { title: { display: true, text: 'Service Distribution Percentage' } }
                    }
                });
            } else {
                ctxService.canvas.parentNode.innerHTML = '<p style="text-align: center;">No service chart data available yet.</p>';
            }
        }


        document.addEventListener('DOMContentLoaded', function() {
            // Initial Chart Renders (Daily is default)
            if (dailyLabels.length > 0) {
                renderVolumeChart(dailyLabels, dailyCounts, 'Daily Order Volume');
            } else {
                document.getElementById('dailyOrderChart').parentNode.innerHTML = '<p style="text-align: center;">No chart data available yet.</p>';
            }
            
            if (dailyRevenueLabels.length > 0) {
                renderRevenueChart(dailyRevenueLabels, dailyRevenueAmounts, 'Daily Revenue Volume');
            } else {
                 document.getElementById('dailyRevenueChart').parentNode.innerHTML = '<p style="text-align: center;">No revenue chart data available yet.</p>';
            }
            
            renderServiceChart(); // Always render the service chart

            
            // Event Listeners for Radio Buttons
            document.querySelectorAll('input[name="timeframe"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const volumeTitle = document.getElementById('volumeTitle');
                    const revenueTitle = document.getElementById('revenueTitle');

                    if (this.value === 'monthly') {
                        // Update Order Volume Chart
                        renderVolumeChart(monthlyLabels, monthlyCounts, 'Monthly Order Volume');
                        volumeTitle.textContent = 'Monthly Order Volume';
                        
                        // Update Revenue Volume Chart
                        renderRevenueChart(monthlyRevenueLabels, monthlyRevenueAmounts, 'Monthly Revenue Volume');
                        revenueTitle.textContent = 'Monthly Revenue Volume';
                        
                    } else { // daily
                        // Update Order Volume Chart
                        renderVolumeChart(dailyLabels, dailyCounts, 'Daily Order Volume');
                        volumeTitle.textContent = 'Daily Order Volume';

                        // Update Revenue Volume Chart
                        renderRevenueChart(dailyRevenueLabels, dailyRevenueAmounts, 'Daily Revenue Volume');
                        revenueTitle.textContent = 'Daily Revenue Volume';
                    }
                });
            });
        });
    </script>
</body>
</html>