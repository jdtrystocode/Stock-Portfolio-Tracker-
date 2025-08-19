<?php
session_start();
include('config.php');  // Including database connection file

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stock_name = mysqli_real_escape_string($conn, $_POST['stock_name']);
    $purchase_price = mysqli_real_escape_string($conn, $_POST['purchase_price']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $user_id = $_SESSION['user_id'];

    // Insert stock data into the database
    $query = "INSERT INTO stocks (user_id, stock_name, purchase_price, quantity) VALUES ('$user_id', '$stock_name', '$purchase_price', '$quantity')";
    
    if (mysqli_query($conn, $query)) {
        header("Location: portfolio.php"); // Redirect to portfolio page after successful addition
    } else {
        echo "Error: " . $query . "<br>" . mysqli_error($conn);
    }
}
?>
