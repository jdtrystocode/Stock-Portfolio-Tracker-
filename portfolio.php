<?php
session_start();
include('config.php');  

function get_stock_price($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/$symbol?interval=1d";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Set a user-agent so Yahoo doesn't block it
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
        return $data['chart']['result'][0]['meta']['regularMarketPrice'];
    }

    return null;
}

function get_stock_history($symbol) {
    // Get the last 1 month of data with daily interval
    $period2 = time();
    $period1 = $period2 - (30 * 24 * 60 * 60); // 30 days ago
    
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/$symbol?interval=1d&period1=$period1&period2=$period2";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    
    if (isset($data['chart']['result'][0])) {
        $result = $data['chart']['result'][0];
        $timestamps = $result['timestamp'];
        $quotes = $result['indicators']['quote'][0];
        
        $chartData = [];
        for ($i = 0; $i < count($timestamps); $i++) {
            if (isset($quotes['close'][$i])) {
                $chartData[] = [
                    'date' => date('Y-m-d', $timestamps[$i]),
                    'price' => $quotes['close'][$i]
                ];
            }
        }
        
        return $chartData;
    }
    
    return null;
}


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM stocks WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Calculate total portfolio value
$total_portfolio_value = 0;
$total_investment_value = 0;
$total_profit_loss = 0;

// Clone the result for second loop
$result_clone = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result_clone)) {
    $current_price = get_stock_price($row['stock_name']);
    if ($current_price !== null) {
        $stock_value = $current_price * $row['quantity'];
        $total_portfolio_value += $stock_value;
        
        $investment_value = $row['purchase_price'] * $row['quantity'];
        $total_investment_value += $investment_value;
    }
}

$total_profit_loss = $total_portfolio_value - $total_investment_value;
$profit_loss_percentage = ($total_investment_value > 0) ? ($total_profit_loss / $total_investment_value) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --accent: #6D28D9;
            --accent-light: #8B5CF6;
            --success: #10B981;
            --danger: #EF4444;
            --card-bg: #1e1e1e;
            --border: #2d2d2d;
        }
        
        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }
        
        .navbar {
            background-color: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
        }
        
        .dashboard-container {
            padding: 2rem 0;
        }
        
        .summary-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .value-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .value-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .value-amount {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .value-change {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .stats-label {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .stocks-table {
            background-color: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        .table {
            margin-bottom: 0;
            color: var(--text-primary);
        }
        
        .table th {
            background-color: var(--bg-secondary);
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        
        .profit {
            color: var(--success);
            font-weight: 600;
        }
        
        .loss {
            color: var(--danger);
            font-weight: 600;
        }
        
        .btn-custom {
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-custom:hover {
            background-color: var(--accent-light);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-outline:hover {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .btn-danger-outline {
            background-color: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-danger-outline:hover {
            background-color: var(--danger);
            color: var(--text-primary);
        }
        
        .modal-content {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 1.5rem;
        }
        
        .form-control {
            background-color: var(--bg-primary);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 0.8rem 1rem;
        }
        
        .form-control:focus {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.2);
        }
        
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .stock-symbol {
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .stock-symbol:hover {
            color: var(--accent-light);
            text-decoration: underline;
        }
        
        .stock-details {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand text-white fw-bold" href="#">
            <i class="bi bi-graph-up"></i> StockTracker
        </a>
        <div>
            <button class="btn btn-outline me-2" data-bs-toggle="modal" data-bs-target="#addStockModal">
                <i class="bi bi-plus-lg"></i> Add Stock
            </button>
            <a href="logout.php" class="btn btn-danger-outline">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container dashboard-container">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="summary-card value-card">
                <div class="value-label">Portfolio Value</div>
                <div class="value-amount">₹<?php echo number_format($total_portfolio_value, 2); ?></div>
                <div class="value-change <?php echo ($total_profit_loss >= 0) ? 'profit' : 'loss'; ?>">
                    <?php echo ($total_profit_loss >= 0) ? '+' : ''; ?>₹<?php echo number_format($total_profit_loss, 2); ?> 
                    (<?php echo number_format($profit_loss_percentage, 2); ?>%)
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card value-card">
                <div class="value-label">Total Investment</div>
                <div class="value-amount">₹<?php echo number_format($total_investment_value, 2); ?></div>
                <div class="value-change stats-label">Initial Capital</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card value-card">
                <div class="value-label">Stocks Count</div>
                <div class="value-amount"><?php echo mysqli_num_rows($result); ?></div>
                <div class="value-change stats-label">In Portfolio</div>
            </div>
        </div>
    </div>

    <div class="stocks-table">
        <table class="table">
            <thead>
                <tr>
                    <th>Stock</th>
                    <th class="text-end">Purchase Price</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Investment</th>
                    <th class="text-end">Current Price</th>
                    <th class="text-end">Current Value</th>
                    <th class="text-end">Profit/Loss</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_count = 0;
                mysqli_data_seek($result, 0); // Reset result pointer
                
                while ($row = mysqli_fetch_assoc($result)) {
                    $stock_name = $row['stock_name'];
                    $purchase_price = $row['purchase_price'];
                    $quantity = $row['quantity'];
                    $current_price = get_stock_price($stock_name);

                    if ($current_price !== null) {
                        $total_investment = $purchase_price * $quantity;
                        $current_value = $current_price * $quantity;
                        $profit_loss = $current_value - $total_investment;
                        $profit_loss_percentage = ($total_investment > 0) ? ($profit_loss / $total_investment) * 100 : 0;
                        $profit_loss_class = ($profit_loss >= 0) ? "profit" : "loss";

                        echo "<tr>
                            <td>
                                <div class='stock-symbol' onclick='showStockChart(\"$stock_name\")'>$stock_name</div>
                            </td>
                            <td class='text-end'>₹" . number_format($purchase_price, 2) . "</td>
                            <td class='text-end'>" . number_format($quantity) . "</td>
                            <td class='text-end'>₹" . number_format($total_investment, 2) . "</td>
                            <td class='text-end'>₹" . number_format($current_price, 2) . "</td>
                            <td class='text-end'>₹" . number_format($current_value, 2) . "</td>
                            <td class='text-end $profit_loss_class'>
                                " . (($profit_loss >= 0) ? '+' : '') . "₹" . number_format($profit_loss, 2) . "
                                <div class='stock-details'>" . (($profit_loss_percentage >= 0) ? '+' : '') . number_format($profit_loss_percentage, 2) . "%</div>
                            </td>
                        </tr>";
                        $row_count++;
                    } else {
                        echo "<tr>
                            <td>
                                <div class='stock-symbol'>$stock_name</div>
                            </td>
                            <td class='text-end'>₹" . number_format($purchase_price, 2) . "</td>
                            <td class='text-end'>" . number_format($quantity) . "</td>
                            <td class='text-end'>₹" . number_format($purchase_price * $quantity, 2) . "</td>
                            <td colspan='3' class='text-center text-warning'>Price data unavailable</td>
                        </tr>";
                        $row_count++;
                    }
                }

                if ($row_count == 0) {
                    echo "<tr>
                        <td colspan='7'>
                            <div class='empty-state'>
                                <div class='empty-icon'><i class='bi bi-bar-chart'></i></div>
                                <h5>No stocks in your portfolio</h5>
                                <p class='text-muted'>Add some stocks to start tracking your investments</p>
                                <button class='btn btn-custom mt-2' data-bs-toggle='modal' data-bs-target='#addStockModal'>
                                    <i class='bi bi-plus-lg'></i> Add Your First Stock
                                </button>
                            </div>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStockModalLabel">Add New Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="add_stock.php" method="POST">
                    <div class="mb-3">
                        <label for="stock_name" class="form-label">Stock Symbol</label>
                        <input type="text" class="form-control" id="stock_name" name="stock_name" placeholder="e.g. RELIANCE" required>
                    </div>
                    <div class="mb-3">
                        <label for="purchase_price" class="form-label">Purchase Price (₹)</label>
                        <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" placeholder="0" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-custom">Add to Portfolio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Stock Chart Modal -->
<div class="modal fade" id="stockChartModal" tabindex="-1" aria-labelledby="stockChartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockChartModalLabel">Stock Price History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="chart-container">
                    <canvas id="stockChart"></canvas>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <div>
                        <div class="value-label">Current Price</div>
                        <div class="value-amount" id="currentPrice">₹0.00</div>
                    </div>
                    <div>
                        <div class="value-label">30-Day Change</div>
                        <div class="value-amount" id="priceChange">0.00%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let stockChart;
    
    function showStockChart(symbol) {
        // Show the modal
        const stockChartModal = new bootstrap.Modal(document.getElementById('stockChartModal'));
        stockChartModal.show();
        
        // Update modal title
        document.getElementById('stockChartModalLabel').textContent = symbol + ' - Price History';
        
        // Fetch stock data
        fetch('get_stock_data.php?symbol=' + symbol)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    return;
                }
                
                // Destroy previous chart if exists
                if (stockChart) {
                    stockChart.destroy();
                }
                
                // Prepare data for Chart.js
                const dates = data.chartData.map(item => item.date);
                const prices = data.chartData.map(item => item.price);
                
                // Create chart
                const ctx = document.getElementById('stockChart').getContext('2d');
                stockChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: symbol + ' Price (₹)',
                            data: prices,
                            borderColor: '#8B5CF6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.2,
                            fill: true,
                            pointBackgroundColor: '#6D28D9',
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#adb5bd',
                                    callback: function(value) {
                                        return '₹' + value.toLocaleString();
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#adb5bd',
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: '#f8f9fa'
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Update price info
                document.getElementById('currentPrice').textContent = '₹' + data.currentPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                // Calculate price change
                if (data.chartData.length > 1) {
                    const firstPrice = data.chartData[0].price;
                    const lastPrice = data.chartData[data.chartData.length - 1].price;
                    const priceChange = ((lastPrice - firstPrice) / firstPrice) * 100;
                    
                    const priceChangeElement = document.getElementById('priceChange');
                    priceChangeElement.textContent = (priceChange >= 0 ? '+' : '') + priceChange.toFixed(2) + '%';
                    priceChangeElement.className = 'value-amount ' + (priceChange >= 0 ? 'profit' : 'loss');
                }
            })
            .catch(error => {
                console.error('Error fetching stock data:', error);
            });
    }
</script>
</body>
</html>