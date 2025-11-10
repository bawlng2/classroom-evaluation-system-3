    <!-- ...existing code... -->
    <!-- ...existing code... -->
<nav class="sidebar">
    <div class="sidebar-header">
        <h4>SMCC Classroom Eval</h4>
        <p class="user-info"><?php echo $_SESSION['name']; ?></p>
        <p class="user-role">
            <?php echo $_SESSION['department'] . ' ' . $_SESSION['role']; ?>
        </p>
    </div>
    
    <ul class="sidebar-nav">
        <?php if($_SESSION['role'] == 'edp'): ?>
            <li><a href="../edp/users.php" class="nav-link"><i class="fas fa-users"></i> Manage Evaluators</a></li>
            <li><a href="../edp/teachers.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</a></li>
            <li><a href="../edp/deactivated_teachers.php" class="nav-link"><i class="fas fa-user-slash"></i> Deactivated Teachers</a></li>
            <li><a href="../edp/deactivated_evaluators.php" class="nav-link"><i class="fas fa-user-slash"></i> Deactivated Evaluators</a></li>
        <?php elseif(in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator'])): ?>
            <li><a href="../edp/users.php" class="nav-link"><i class="fas fa-users"></i> Dashboard</a></li>
            <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php elseif(in_array($_SESSION['role'], ['president', 'vice_president'])): ?>
            <li><a href="../leaders/dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="../evaluators/reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php else: ?>
            <li><a href="evaluation.php" class="nav-link"><i class="fas fa-clipboard-check"></i> Evaluation</a></li>
            <li><a href="teachers.php" class="nav-link"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
            <li><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php endif; ?>
        <li class="nav-divider"></li>
        <li><a href="../auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>