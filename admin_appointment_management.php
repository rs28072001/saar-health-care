<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    echo '<div class="error-message">Session expired. Please login again.</div>';
    exit;
}

// ---------- FUNCTIONS ----------

// Get appointments by status
function getAppointmentsByStatus($conn, $status_type) {
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    switch($status_type) {
        case 'live':
            $sql = "SELECT a.*, u.name, u.email, u.contact_no, ump.first_name, ump.last_name
                    FROM appointments a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    LEFT JOIN user_medical_profiles ump ON a.user_id = ump.user_id
                    WHERE a.meet_link IS NOT NULL 
                    AND a.meet_link != '' 
                    AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time > ?))
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC";
            break;
            
        case 'completed':
            $sql = "SELECT a.*, u.name, u.email, u.contact_no, ump.first_name, ump.last_name
                    FROM appointments a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    LEFT JOIN user_medical_profiles ump ON a.user_id = ump.user_id
                    WHERE (a.appointment_date < ? OR (a.appointment_date = ? AND a.appointment_time <= ?))
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            break;
            
        case 'pending':
            $sql = "SELECT a.*, u.name, u.email, u.contact_no, ump.first_name, ump.last_name
                    FROM appointments a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    LEFT JOIN user_medical_profiles ump ON a.user_id = ump.user_id
                    WHERE (a.meet_link IS NULL OR a.meet_link = '') 
                    AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time > ?))
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC";
            break;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $current_date, $current_date, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Helper to get initials
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) $initials .= strtoupper($word[0]);
    }
    return substr($initials, 0, 2);
}

// ---------- FETCH APPOINTMENTS ----------
$live_appointments = getAppointmentsByStatus($conn, 'live');
$completed_appointments = getAppointmentsByStatus($conn, 'completed');
$pending_appointments = getAppointmentsByStatus($conn, 'pending');

$live_count = count($live_appointments);
$completed_count = count($completed_appointments);
$pending_count = count($pending_appointments);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard - Saar Healthcare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;600&family=Comfortaa:wght@500;700&display=swap" rel="stylesheet">
    <style>
        /* Reset & base */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Lexend', sans-serif;
            background-color: #f8f9fa;
            color: #343a40;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            font-family: 'Comfortaa', cursive;
            background: linear-gradient(135deg, #1d972dff 0%, #2ba170ff 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .navbar-brand { font-size: 1.25rem; font-weight: 600; display:flex; align-items:center; gap:10px; }
        .navbar-actions { display:flex; align-items:center; gap:1rem; }
        .welcome-text { font-size: 1rem; opacity: 0.95; }
        .btn-logout {
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .18s ease, background .18s ease;
        }
        .btn-logout:hover { transform: translateY(-2px); background: rgba(255,255,255,0.25); }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" role="navigation" aria-label="Main Navigation">
        <div class="navbar-content">
            <div class="navbar-brand">
                <i class="fas fa-heartbeat" aria-hidden="true"></i>
                Saar Healthcare - Appointment Management
            </div>
            <div class="navbar-actions">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                <a href="?logout=true" class="btn-logout" aria-label="Logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>
    <!-- Navigation Buttons Section -->
    <div style="display: flex; justify-content: space-around; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 1.2rem 2rem; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <a href="index.php" 
        style="display: inline-flex; align-items: center; gap: 8px; 
                background: #2ba170; color: white; text-decoration: none; 
                padding: 0.55rem 1.2rem; border-radius: 8px; font-weight: 500; 
                box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: background 0.2s ease;">
            <i class="fas fa-home"></i> Home
        </a>

        <a href="admin_dashboard.php" 
        style="display: inline-flex; align-items: center; gap: 8px; 
                background: #0db3b9; color: white; text-decoration: none; 
                padding: 0.55rem 1.2rem; border-radius: 8px; font-weight: 500; 
                box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: background 0.2s ease;">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <a href="admin_doctor_availability.php" 
        style="display: inline-flex; align-items: center; gap: 8px; 
                background: #007bff; color: white; text-decoration: none; 
                padding: 0.55rem 1.2rem; border-radius: 8px; font-weight: 500; 
                box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: background 0.2s ease;">
            <i class="fas fa-user-md"></i> Availability
        </a>

        <a href="admin_user_management.php" 
        style="display: inline-flex; align-items: center; gap: 8px; 
                background: #e25886; color: white; text-decoration: none; 
                padding: 0.55rem 1.2rem; border-radius: 8px; font-weight: 500; 
                box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: background 0.2s ease;">
            <i class="fas fa-users"></i> User Details
        </a>
    </div>

<div class="appointment-management">
    <link rel="stylesheet" href="appointement.css">

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card completed">
            <span class="stat-number"><?= $completed_count ?></span>
            <div class="stat-label">Completed</div>
            <div class="stat-description">Past appointments</div>
        </div>
        <div class="stat-card live">
            <span class="stat-number"><?= $live_count ?></span>
            <div class="stat-label">Upcoming</div>
            <div class="stat-description">Scheduled meetings</div>
        </div>
        <div class="stat-card pending">
            <span class="stat-number"><?= $pending_count ?></span>
            <div class="stat-label">Pending Approval</div>
            <div class="stat-description">Waiting for meeting links</div>
        </div>
    </div>

        <!-- ✅ PENDING SECTION -->
    <div class="appointments-section pending-section">
        <div class="section-header">
            <h3><i class="fas fa-clock section-icon"></i> Pending Approval</h3>
        </div>
        <?php if (empty($pending_appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-check-double"></i>
                <h3>No Pending Approvals</h3>
                <p>All appointments have been approved</p>
            </div>
        <?php else: ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service Type</th>
                        <th>Requested Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="pendingTableBody">
                    <?php foreach ($pending_appointments as $appointment): 
                        $patient_name = !empty($appointment['first_name']) ? 
                            $appointment['first_name'].' '.$appointment['last_name'] : $appointment['name'];
                        $initials = getInitials($patient_name);
                    ?>
                    <tr id="row-<?= $appointment['id'] ?>">
                        <td>
                            <div class="patient-info">
                                <div class="avatar"><?= $initials ?></div>
                                <div class="patient-details">
                                    <h4><?= htmlspecialchars($patient_name) ?></h4>
                                    <p class="contact-info">
                                        📞 <?= htmlspecialchars($appointment['contact_no'] ?? 'N/A') ?> | 
                                        ✉️ <?= htmlspecialchars($appointment['email'] ?? 'N/A') ?>
                                    </p>
                                    <p class="contact-info">User ID: <?= $appointment['user_id'] ?></p>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($appointment['service_type']) ?></td>
                        <td>
                            <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?> at 
                            <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                        </td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td>
                            <button class="btn btn-success" onclick="openApproveModal(<?= $appointment['id'] ?>, '<?= htmlspecialchars($patient_name) ?>')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
       <!-- Live Appointments Section -->
    <div class="appointments-section live-section">
        <div class="section-header">
            <h3>
                <i class="fas fa-video section-icon"></i>
                Upcoming Appointments
            </h3>
        </div>
        <?php if (empty($live_appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <h3>No Upcoming Appointments</h3>
                <p>All scheduled appointments will appear here</p>
            </div>
        <?php else: ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service Type</th>
                        <th>Meeting Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($live_appointments as $appointment): 
                        $appointment_datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
                        $is_live_now = strtotime($appointment_datetime) <= time() + 1800; // Within 30 minutes
                        $patient_name = !empty($appointment['first_name']) ? 
                            $appointment['first_name'] . ' ' . $appointment['last_name'] : 
                            $appointment['name'];
                        $initials = getInitials($patient_name);
                    ?>
                    <tr>
                        <td>
                            <div class="patient-info">
                                <div class="avatar">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="patient-details">
                                    <h4><?php echo htmlspecialchars($patient_name); ?></h4>
                                    <p class="contact-info">
                                        📞 <?php echo htmlspecialchars($appointment['contact_no'] ?? 'N/A'); ?> | 
                                        ✉️ <?php echo htmlspecialchars($appointment['email'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="contact-info">
                                        User ID: <?php echo $appointment['user_id']; ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($appointment['service_type']); ?></td>
                        <td>
                            <span class="meeting-time">
                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </span><br>
                            <span class="countdown" data-datetime="<?php echo $appointment_datetime; ?>">
                                <?php echo $is_live_now ? 'Starting soon' : 'Upcoming'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $is_live_now ? 'status-live' : 'status-upcoming'; ?>">
                                <?php echo $is_live_now ? 'Starting Soon' : 'Upcoming'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-primary" onclick="joinMeeting('<?php echo htmlspecialchars($appointment['meet_link']); ?>')">
                                <i class="fas fa-video"></i> Join Meeting
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Completed Appointments Section -->
    <div class="appointments-section completed-section">
        <div class="section-header">
            <h3>
                <i class="fas fa-check-circle section-icon"></i>
                Completed Appointments
            </h3>
        </div>
        <?php if (empty($completed_appointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h3>No Completed Appointments</h3>
                <p>Completed appointments will appear here</p>
            </div>
        <?php else: ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service Type</th>
                        <th>Appointment Date & Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed_appointments as $appointment): 
                        $patient_name = !empty($appointment['first_name']) ? 
                            $appointment['first_name'] . ' ' . $appointment['last_name'] : 
                            $appointment['name'];
                        $initials = getInitials($patient_name);
                    ?>
                    <tr>
                        <td>
                            <div class="patient-info">
                                <div class="avatar">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="patient-details">
                                    <h4><?php echo htmlspecialchars($patient_name); ?></h4>
                                    <p class="contact-info">
                                        📞 <?php echo htmlspecialchars($appointment['contact_no'] ?? 'N/A'); ?> | 
                                        ✉️ <?php echo htmlspecialchars($appointment['email'] ?? 'N/A'); ?>
                                    </p>
                                    <p class="contact-info">
                                        User ID: <?php echo $appointment['user_id']; ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($appointment['service_type']); ?></td>
                        <td>
                            <span class="meeting-time">
                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </span>
                        </td>
                        <td><span class="status-badge status-completed">Completed</span></td>
                        <td>
                            <?php if (!empty($appointment['meet_link'])): ?>
                                <button class="btn btn-outline" onclick="viewMeetingDetails('<?php echo htmlspecialchars($appointment['meet_link']); ?>')">
                                    <i class="fas fa-eye"></i> View Invoice
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline" disabled>
                                    <i class="fas fa-eye"></i> No Meeting Link
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

   

</div>

<!-- ✅ Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve Appointment</h3>
            <span class="close" onclick="closeApproveModal()">&times;</span>
        </div>
        <form id="approveForm">
            <input type="hidden" id="appointment_id">
            <div class="form-group">
                <label for="meet_link">Meeting Link</label>
                <input type="url" id="meet_link" placeholder="https://meet.google.com/abc-def-ghi" required>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve Appointment
                </button>
            </div>
        </form>
    </div>
</div>
</body>

<!-- ✅ JavaScript -->
<script>
function joinMeeting(link) {
    if (!link) {
        alert("Meeting link not available.");
        return;
    }
    window.open(link, "_blank", "noopener,noreferrer");
}

function openApproveModal(id, name) {
    document.getElementById('appointment_id').value = id;
    document.getElementById('approveModal').style.display = 'block';
    document.querySelector('.modal-header h3').textContent = 'Approve Appointment - ' + name;
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
    document.getElementById('approveForm').reset();
    document.querySelector('.modal-header h3').textContent = 'Approve Appointment';
}

// ✅ Handle form submit via AJAX
document.getElementById('approveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const appointmentId = document.getElementById('appointment_id').value;
    const meetingLink = document.getElementById('meet_link').value;

    fetch('approve_appointment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            appointment_id: appointmentId,
            meeting_link: meetingLink
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAppointmentMessage(data.message, 'success');
            // Remove the row immediately
            document.getElementById('row-' + appointmentId)?.remove();
            closeApproveModal();
        } else {
            showAppointmentMessage(data.message, 'error');
        }
    })
    .catch(() => {
        showAppointmentMessage('Network error. Please try again.', 'error');
    });
});

// ✅ Show success/error messages
function showAppointmentMessage(message, type) {
    const msg = document.createElement('div');
    msg.className = 'appointment-message ' + type;
    msg.textContent = message;
    document.body.appendChild(msg);
    setTimeout(() => msg.remove(), 3000);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('approveModal');
    if (event.target === modal) closeApproveModal();
};
</script>

<style>
/* simple toast message */
.appointment-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 6px;
    font-weight: 500;
    color: #fff;
    z-index: 9999;
    animation: fadein 0.3s;
}
.appointment-message.success { background: #28a745; }
.appointment-message.error { background: #dc3545; }
@keyframes fadein { from { opacity: 0; } to { opacity: 1; } }
</style>
