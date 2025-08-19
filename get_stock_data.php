<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get stock symbol from request
$symbol = isset($_GET['symbol']) ? $_GET['symbol'] : '';
if (empty($symbol)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No symbol provided']);
    exit();
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

function get_stock_price($symbol) {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/$symbol?interval=1d";

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

    if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
        return $data['chart']['result'][0]['meta']['regularMarketPrice'];
    }

    return null;
}

// Get the data
$chartData = get_stock_history($symbol);
$currentPrice = get_stock_price($symbol);

if ($chartData === null) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unable to fetch stock data']);
    exit();
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'symbol' => $symbol,
    'chartData' => $chartData,
    'currentPrice' => $currentPrice
]);