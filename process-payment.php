<?php
session_start();
require_once 'config.php';
require_once 'calculate-amount.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $payment_method = $_POST['payment_method'];
    
    // Verify that the booking belongs to the user
    $verify_sql = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    // Get the booking with calculated amount
    $booking = calculateCurrentAmount($booking_id);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate a transaction ID
        $transaction_id = 'TXN' . time() . rand(1000, 9999);
        
        // Insert payment record
        $payment_sql = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, transaction_id) 
                        VALUES (?, ?, NOW(), ?, ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_amount = $booking['current_amount'] ?? $booking['amount'];
        $payment_stmt->bind_param("idss", $booking_id, $payment_amount, $payment_method, $transaction_id);
        $payment_stmt->execute();
        
        // Update booking payment status
        $update_sql = "UPDATE bookings SET payment_status = 'paid', amount = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $payment_amount, $booking_id);
        $update_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "Payment processed successfully! Transaction ID: " . $transaction_id;
        $_SESSION['message_type'] = "success";
        header("Location: view-receipt.php?booking_id=" . $booking_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Payment failed: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
} else {
    header("Location: my-bookings.php");
    exit();
}
?>
