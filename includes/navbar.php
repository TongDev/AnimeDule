<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ตัวแปร $user ควรถูกกำหนดก่อน include ไฟล์นี้จากไฟล์หลัก เช่น index.php
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">AnimeDule</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu"
            aria-controls="navmenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navmenu">
            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (is_array($user) && isset($user['name'])): ?>
                    <!-- Logged in: show username and Logout -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="favorite.php">รายการโปรด</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Not logged in: show Login and Register -->
                    <li class="nav-item"><a class="nav-link" href="login.php">เข้าสู่ระบบ</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">สมัครสมาชิก</a></li>
                <?php endif; ?>
                <!-- Notification -->
                <li class="nav-item dropdown me-3">
                    <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        🔔
                        <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown" style="max-height:300px; overflow-y:auto;" id="notifList">
                        <li><span class="dropdown-item-text">ไม่มีแจ้งเตือนใหม่</span></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
