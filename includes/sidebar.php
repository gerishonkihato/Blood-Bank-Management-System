<?php
$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? 'User';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">🩸 KNBTS</div>
        <div class="sidebar-user">
            <span class="sidebar-username"><?php echo htmlspecialchars($username); ?></span>
            <span class="sidebar-role"><?php echo htmlspecialchars($role); ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php if ($role === 'ADMIN'): ?>
            <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span> Dashboard
            </a>
            <a href="inventory.php" class="sidebar-link <?php echo $currentPage === 'inventory.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🩸</span> Inventory
            </a>
            <a href="active_donors.php" class="sidebar-link <?php echo $currentPage === 'active_donors.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">👥</span> Active Donors
            </a>
            <a href="recipients.php" class="sidebar-link <?php echo $currentPage === 'recipients.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🏥</span> Recipients
            </a>
            <a href="admins.php" class="sidebar-link <?php echo $currentPage === 'admins.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🛡️</span> Admins
            </a>
        <?php elseif ($role === 'DONOR'): ?>
            <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span> Dashboard
            </a>
            <a href="register.php" class="sidebar-link <?php echo $currentPage === 'register.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📝</span> Update Profile
            </a>
            <a href="donation_history.php" class="sidebar-link <?php echo $currentPage === 'donation_history.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📋</span> Donation History
            </a>
        <?php elseif ($role === 'RECIPIENT'): ?>
            <a href="dashboard.php" class="sidebar-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span> Dashboard
            </a>
            <a href="register.php" class="sidebar-link <?php echo $currentPage === 'register.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📝</span> Update Profile
            </a>
            <a href="request.php" class="sidebar-link <?php echo $currentPage === 'request.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🩸</span> Request Blood
            </a>
            <a href="track_request.php" class="sidebar-link <?php echo $currentPage === 'track_request.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span> Track Requests
            </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../../logout.php" class="sidebar-logout">
            <span class="sidebar-icon">🚪</span> Logout
        </a>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<button type="button" class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
    ☰
</button>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }
}
</script>

