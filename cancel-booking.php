<?php
session_start();
require_once 'config.php';
require_once 'calculate-amount.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the booking ID from the form submission
    $booking_id = $_POST['booking_id'];

    // Verify that the booking belongs to the user and is active
    $verify_sql = "SELECT b.*, p.spot_number, p.floor_number 
                  FROM bookings b 
                  JOIN parking_spots p ON b.spot_id = p.id 
                  WHERE b.id = ? AND b.user_id = ? AND b.status = 'active'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $booking_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Invalid booking or booking is not active.";
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Get the current calculated amount for partial payment if needed
    $current_booking = calculateCurrentAmount($booking_id);
    $current_amount = $current_booking['current_amount'] ?? $booking['amount'];
    
    // Prepare the SQL statement to update the booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', amount = ? WHERE id = ?");
    $stmt->bind_param('di', $current_amount, $booking_id);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Execute the statement
        if ($stmt->execute()) {
            // Free up the parking spot
            $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
            $update_spot_stmt = $conn->prepare($update_spot_sql);
            $update_spot_stmt->bind_param("i", $booking['spot_id']);
            $update_spot_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Successful cancellation
            $_SESSION['message'] = 'Booking cancelled successfully.';
            
            // If there's an unpaid amount, prompt for payment
            if ($booking['payment_status'] === 'pending' && $current_amount > 0) {
                $_SESSION['message'] = "Booking cancelled. Please complete payment for the time used.";
                $_SESSION['message_type'] = "info";
                header("Location: my-bookings.php?filter=cancelled");
            } else {
                $_SESSION['message_type'] = "success";
                header("Location: my-bookings.php");
            }
        } else {
            // Error handling
            $_SESSION['message'] = 'Error cancelling booking. Please try again.';
            $_SESSION['message_type'] = "error";
            header("Location: my-bookings.php");
        }
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION['message'] = "Error cancelling booking: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        header("Location: my-bookings.php");
        exit();
    }
} else {
    header("Location: my-bookings.php");
    exit();
}
?>
