<?php
session_start();
require_once 'config.php';

// Update expired bookings
updateExpiredBookings();

// Get current user ID
$user_id = $_SESSION['user_id'];

// Initialize filters
$status_filter = $_GET['status'] ?? 'all';

// Query to get bookings with spot number
$query = "SELECT b.*, p.spot_number, pay.payment_method, pay.payment_status FROM bookings b
          JOIN parking_spots p ON b.spot_id = p.id
          LEFT JOIN payments pay ON b.id = pay.booking_id
          WHERE b.user_id = ?";
if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
}
$query .= " ORDER BY b.id DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bind_param('is', $user_id, $status_filter);
} else {
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Bookings - Smart Parking</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
 <div class="dashboard-container">
    <?php include 'sidebar.php'; ?>


    <div class="main-content">
        <h1 class="page-title">My Bookings</h1>

        <!-- Filter Section -->
        <div class="filters">
            <a id="all" href="?status=all" class="<?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
            <a id="active" href="?status=active" class="<?php echo $status_filter === 'active' ? 'active' : ''; ?>">Active</a>
            <a id="completed" href="?status=completed" class="<?php echo $status_filter === 'completed' ? 'active' : ''; ?>">Completed</a>
            <a id="cancelled" href="?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
        </div>

        <!-- Bookings List -->
        <div class="bookings-list">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($booking = $result->fetch_assoc()): ?>
                    <div class="booking-card">
                        <h3>Booking #<?php echo $booking['id']; ?></h3>
                        <p>Spot: <?php echo $booking['spot_number']; ?></p>
                        <p>Vehicle: <?php echo $booking['vehicle_number']; ?></p>
                        <p>Start: <?php echo date('M d, g:i A', strtotime($booking['start_time'])); ?></p>
                        <p>End: <?php echo date('M d, g:i A', strtotime($booking['end_time'])); ?></p>
                        <p>Amount: $<?php echo number_format($booking['amount'], 2); ?></p>
                        <p>Status: <span class="status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></p>
                        <p>Payment Method: <?php echo $booking['payment_method']; ?></p>
                        <p>Payment Status: <?php echo ucfirst($booking['payment_status']); ?></p>

                        <?php if ($booking['status'] === 'active'): ?>
                            <div class="actions">
                                <button onclick="openCancelModal(<?php echo $booking['id']; ?>)">Cancel</button>
                                <button onclick="openExtendModal(<?php echo $booking['id']; ?>)">Extend</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No bookings found.</p>
            <?php endif; ?>
        </div>


        <!-- Cancel Booking Modal -->
        <div id="cancelModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeCancelModal()">&times;</span>
                <h2>Cancel Booking</h2>
                <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
                <form id="cancelForm" action="cancel-booking.php" method="POST">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    <div class="modal-buttons">
                        <button type="button" class="btn-secondary" onclick="closeCancelModal()">No, Keep Booking</button>
                        <button type="submit" class="btn-danger">Yes, Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Extend Booking Modal -->
        <div id="extendModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeExtendModal()">&times;</span>
                <h2>Extend Booking</h2>
                <p>How many additional hours would you like to extend your booking?</p>
                <form id="extendForm" action="extend-booking.php" method="POST">
                    <input type="hidden" name="booking_id" id="extendBookingId">
                    <div class="form-group">
                        <label for="extendDuration">Additional Hours:</label>
                        <input type="number" name="extend_hours" id="extendDuration" min="1" max="24" value="1" required>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn-secondary" onclick="closeExtendModal()">Cancel</button>
                        <button type="submit" class="btn-primary">Confirm Extension</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

 </div>
    <script>
        function openCancelModal(bookingId) {
            document.getElementById('cancelBookingId').value = bookingId;
            const modal = document.getElementById('cancelModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'translateY(0)';
            }, 10);
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.querySelector('.modal-content').style.transform = 'translateY(20px)';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function openExtendModal(bookingId) {
            document.getElementById('extendBookingId').value = bookingId;
            const modal = document.getElementById('extendModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.querySelector('.modal-content').style.transform = 'translateY(0)';
            }, 10);
        }

        function closeExtendModal() {
            const modal = document.getElementById('extendModal');
            modal.querySelector('.modal-content').style.transform = 'translateY(20px)';
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Close modals when clicking outside the modal content
        window.addEventListener('click', function(event) {
            const cancelModal = document.getElementById('cancelModal');
            const extendModal = document.getElementById('extendModal');

            if (event.target === cancelModal) {
                closeCancelModal();
            }

            if (event.target === extendModal) {
                closeExtendModal();
            }
        });
    </script>
</body>
</html>