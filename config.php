<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "smart_parking";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate booking amount
function calculate_booking_amount($start_time, $end_time, $hourly_rate) {
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    $duration = $end - $start;
    $hours = ceil($duration / 3600); // Convert seconds to hours and round up
    return round($hours * $hourly_rate, 2);
}

// Function to calculate current amount for active bookings
function calculateCurrentAmount($booking_id) {
    global $conn;

    // Get booking details
    $sql = "SELECT b.*, p.hourly_rate
            FROM bookings b
            JOIN parking_spots p ON b.spot_id = p.id
            WHERE b.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        return null;
    }

    // Calculate current amount based on actual duration for active bookings
    if ($booking['status'] === 'active') {
        $current_time = date('Y-m-d H:i:s');
        $current_amount = calculate_booking_amount(
            $booking['start_time'],
            $current_time,
            $booking['hourly_rate']
        );
        $booking['current_amount'] = $current_amount;
    }

    return $booking;
}

// Function to update expired bookings
function updateExpiredBookings() {
    global $conn;

    // Get current time
    $current_time = date('Y-m-d H:i:s');

    // Start transaction
    $conn->begin_transaction();

    try {
        // Find all active bookings that have passed their end time
        $find_expired_sql = "SELECT b.id, b.spot_id
                            FROM bookings b
                            WHERE b.status = 'active'
                            AND b.end_time < ?";
        $find_stmt = $conn->prepare($find_expired_sql);
        $find_stmt->bind_param("s", $current_time);
        $find_stmt->execute();
        $expired_result = $find_stmt->get_result();

        // Update each expired booking
        while ($booking = $expired_result->fetch_assoc()) {
            // Update booking status to completed
            $update_booking_sql = "UPDATE bookings SET status = 'completed' WHERE id = ?";
            $update_booking_stmt = $conn->prepare($update_booking_sql);
            $update_booking_stmt->bind_param("i", $booking['id']);
            $update_booking_stmt->execute();

            // Free up the parking spot
            $update_spot_sql = "UPDATE parking_spots SET is_occupied = 0, vehicle_number = NULL WHERE id = ?";
            $update_spot_stmt = $conn->prepare($update_spot_sql);
            $update_spot_stmt->bind_param("i", $booking['spot_id']);
            $update_spot_stmt->execute();
        }

        // Commit transaction
        $conn->commit();

        return $expired_result->num_rows; // Return number of updated bookings
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error updating expired bookings: " . $e->getMessage());
        return false;
    }
}
?>
