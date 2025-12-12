<?php
session_start();
require_once 'config.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$dept_id = getUserDepartment();
$is_admin = isAdmin();

// ==================== CHECK WIDGET PERMISSIONS ====================
$can_view_total = hasPermission('view_widget_total_licenses');
$can_view_active = hasPermission('view_widget_active_licenses');
$can_view_expiring_30 = hasPermission('view_widget_expiring_30days');
$can_view_expired = hasPermission('view_widget_expired_licenses');
$can_view_renewal_cost_45 = hasPermission('view_widget_renewal_cost_45days');
$can_view_dept_breakdown = hasPermission('view_widget_dept_breakdown');

// Check if user can see ALL departments combined data
$can_view_all_depts = canViewAllDepartments();

// ==================== DEPARTMENT FILTER ====================
$dept_filter = "";
if (!$can_view_all_depts) {
    $dept_filter = " AND l.managed_by_dept_id = $dept_id";
}

// ==================== WIDGET 1: TOTAL LICENSES ====================
$total_licenses = null;
if ($can_view_total) {
    $total_licenses_result = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(DISTINCT l.license_id) as total 
        FROM licenses l 
        WHERE 1=1 $dept_filter
    "));
    $total_licenses = $total_licenses_result['total'];
}

// ==================== WIDGET 2: ACTIVE LICENSES ====================
$active_licenses = null;
if ($can_view_active) {
    $active_licenses_result = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(DISTINCT l.license_id) as active 
        FROM licenses l 
        WHERE l.status = 'active' $dept_filter
    "));
    $active_licenses = $active_licenses_result['active'];
}

// ==================== WIDGET 3: EXPIRING IN 45 DAYS ====================
$expiring_soon = null;
if ($can_view_expiring_30) {
    $expiring_soon_result = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(DISTINCT l.license_id) as expiring 
        FROM licenses l 
        WHERE l.status NOT IN ('inactive', 'closed')
        AND l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
        $dept_filter
    "));
    $expiring_soon = $expiring_soon_result['expiring'];
}

// ==================== WIDGET 4: EXPIRED LICENSES ====================
$expired = null;
if ($can_view_expired) {
    $expired_result = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(DISTINCT l.license_id) as expired 
        FROM licenses l 
        WHERE l.status = 'expired' $dept_filter
    "));
    $expired = $expired_result['expired'];
}

// ==================== WIDGET 5: RENEWAL COST (45 DAYS + EXPIRED) ====================
$renewal_cost_45 = null;
$renewal_cost_label = "";
$renewal_count_45 = 0;

if ($can_view_renewal_cost_45) {
    if ($can_view_all_depts) {
        $renewal_result = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT 
                COUNT(DISTINCT l.license_id) as count,
                COALESCE(SUM(l.license_cost), 0) as total_cost 
            FROM licenses l
            WHERE l.status NOT IN ('inactive', 'closed')
            AND (
                l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
                OR l.status = 'expired'
            )
        "));
        $renewal_cost_45 = $renewal_result['total_cost'];
        $renewal_count_45 = $renewal_result['count'];
        $renewal_cost_label = "All Departments";
    } else {
        $renewal_result = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT 
                COUNT(DISTINCT l.license_id) as count,
                COALESCE(SUM(l.license_cost), 0) as total_cost 
            FROM licenses l
            WHERE l.status NOT IN ('inactive', 'closed')
            AND (
                l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
                OR l.status = 'expired'
            )
            $dept_filter
        "));
        $renewal_cost_45 = $renewal_result['total_cost'];
        $renewal_count_45 = $renewal_result['count'];
        
        $dept_name_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT dept_name FROM departments WHERE dept_id = $dept_id"));
        $renewal_cost_label = $dept_name_result['dept_name'] ?? "My Department";
    }
}

// ==================== WIDGET 6: DEPARTMENT BREAKDOWN ====================
$dept_breakdown = null;
if ($can_view_dept_breakdown) {
    if ($can_view_all_depts) {
        $dept_breakdown = mysqli_query($conn, "
            SELECT d.dept_name, 
                   COUNT(DISTINCT l.license_id) as license_count,
                   COALESCE(SUM(l.license_cost), 0) as total_cost,
                   SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_count,
                   SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) as expired_count
            FROM departments d
            LEFT JOIN licenses l ON d.dept_id = l.managed_by_dept_id
            GROUP BY d.dept_id, d.dept_name
            HAVING license_count > 0
            ORDER BY license_count DESC
            LIMIT 5
        ");
    } else {
        $dept_breakdown = mysqli_query($conn, "
            SELECT d.dept_name, 
                   COUNT(DISTINCT l.license_id) as license_count,
                   COALESCE(SUM(l.license_cost), 0) as total_cost,
                   SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_count,
                   SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) as expired_count
            FROM departments d
            LEFT JOIN licenses l ON d.dept_id = l.managed_by_dept_id
            WHERE d.dept_id = $dept_id
            GROUP BY d.dept_id, d.dept_name
        ");
    }
}

// ==================== LICENSE TYPE BREAKDOWN ====================
$type_breakdown = mysqli_query($conn, "
    SELECT lt.type_name, 
           COUNT(DISTINCT l.license_id) as count,
           SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_count,
           SUM(CASE WHEN l.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
           COALESCE(SUM(l.license_cost), 0) as total_cost
    FROM license_types lt
    LEFT JOIN licenses l ON lt.type_id = l.license_type_id
    WHERE 1=1 $dept_filter
    GROUP BY lt.type_id, lt.type_name
    HAVING count > 0
    ORDER BY count DESC
    LIMIT 5
");

// ==================== RECENT ACTIVITIES ====================
$recent_activities = mysqli_query($conn, "
    SELECT a.*, 
           u.full_name as performed_by_name, 
           l.license_name
    FROM audit_log a
    LEFT JOIN users u ON a.performed_by = u.user_id
    LEFT JOIN licenses l ON a.license_id = l.license_id
    WHERE 1=1 $dept_filter
    ORDER BY a.performed_at DESC
    LIMIT 10
");

// ==================== MONTHLY RENEWAL TREND (LAST 6 MONTHS) ====================
$monthly_renewals = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(lr.renewal_date, '%b %Y') as month_year, 
        COUNT(*) as renewal_count,
        COALESCE(SUM(lr.renewal_cost), 0) as total_cost
    FROM license_renewals lr
    JOIN licenses l ON lr.license_id = l.license_id
    WHERE lr.renewal_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    $dept_filter
    GROUP BY DATE_FORMAT(lr.renewal_date, '%Y-%m'), DATE_FORMAT(lr.renewal_date, '%b %Y')
    ORDER BY lr.renewal_date ASC
    LIMIT 6
");

$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_renewals)) {
    $monthly_data[] = $row;
}

// ==================== EXPIRING LICENSES (NEXT 45 DAYS) ====================
$expiring_45_days = null;
if ($can_view_all_depts || $can_view_expiring_30) {
    $expiring_45_query = "
        SELECT l.license_name, 
               l.license_no,
               l.expiry_date,
               l.status,
               d.dept_name,
               DATEDIFF(l.expiry_date, CURDATE()) as days_remaining
        FROM licenses l
        JOIN departments d ON l.managed_by_dept_id = d.dept_id
        WHERE l.status NOT IN ('inactive', 'closed')
        AND (
            l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
            OR l.status = 'expired'
        )
        $dept_filter
        ORDER BY l.expiry_date ASC
        LIMIT 10
    ";
    $expiring_45_days = mysqli_query($conn, $expiring_45_query);
}

// Count widgets visible to user
$visible_widgets = 0;
if ($can_view_total) $visible_widgets++;
if ($can_view_active) $visible_widgets++;
if ($can_view_expiring_30) $visible_widgets++;
if ($can_view_expired) $visible_widgets++;
if ($can_view_renewal_cost_45) $visible_widgets++;

$has_any_widget = ($visible_widgets > 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - License Management System</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }

        /* Quick Actions Bar */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .quick-action-btn.secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 2px 6px rgba(240, 147, 251, 0.3);
        }

        .quick-action-btn.secondary:hover {
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
        }

        .quick-action-btn.success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            box-shadow: 0 2px 6px rgba(67, 233, 123, 0.3);
        }

        .quick-action-btn.success:hover {
            box-shadow: 0 4px 12px rgba(67, 233, 123, 0.4);
        }

        .quick-action-btn.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            box-shadow: 0 2px 6px rgba(250, 112, 154, 0.3);
        }

        .quick-action-btn.warning:hover {
            box-shadow: 0 4px 12px rgba(250, 112, 154, 0.4);
        }

        .quick-action-btn .icon {
            font-size: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stat-card h4 {
            font-size: 13px;
            color: #6c757d;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 5px 0;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #95a5a6;
            margin: 0;
        }

        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .dashboard-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .dashboard-card.full-width {
            grid-column: 1 / -1;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8f9fa;
        }

        .data-table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 12px;
            font-size: 14px;
            color: #495057;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table tbody tr:hover {
            background: #f8f9ff;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .activity-item:hover {
            background: #f8f9ff;
        }

        .activity-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .activity-details strong {
            color: #2c3e50;
            font-size: 14px;
        }

        .activity-details p {
            margin: 5px 0;
            font-size: 13px;
            color: #6c757d;
        }

        .activity-details small {
            color: #95a5a6;
            font-size: 12px;
        }

        .no-access-message {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .no-access-message h3 {
            font-size: 24px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .no-access-message p {
            font-size: 16px;
            color: #6c757d;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="dashboard-container">
        <div class="page-header">
            <h2>📊 Dashboard</h2>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions">
            <?php if (hasPermission('add_license')): ?>
            <a href="add_license.php" class="quick-action-btn">
                <span class="icon">➕</span>
                <span>Add License</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('view_licenses')): ?>
            <a href="view_licenses.php" class="quick-action-btn secondary">
                <span class="icon">📋</span>
                <span>View All Licenses</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('manage_approvals')): ?>
            <a href="approvals.php" class="quick-action-btn success">
                <span class="icon">✅</span>
                <span>Approvals</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('view_reports')): ?>
            <a href="reports.php" class="quick-action-btn warning">
                <span class="icon">📊</span>
                <span>Reports</span>
            </a>
            <?php endif; ?>

            <a href="alerts.php" class="quick-action-btn" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <span class="icon">🔔</span>
                <span>Alerts</span>
            </a>
        </div>

        <?php if (!$has_any_widget): ?>
            <!-- No Widget Access Message -->
            <div class="no-access-message">
                <h3>⚠️ Limited Dashboard Access</h3>
                <p>You don't have permission to view any dashboard widgets.</p>
                <p>Please contact your system administrator to grant you appropriate permissions.</p>
            </div>
        <?php else: ?>
            <!-- Dashboard Statistics Cards -->
            <div class="stats-grid">
                <?php if ($can_view_total): ?>
                <div class="stat-card">
                    <h4>📋 Total Licenses</h4>
                    <p class="stat-number"><?php echo number_format($total_licenses); ?></p>
                    <p class="stat-label">All statuses</p>
                </div>
                <?php endif; ?>

                <?php if ($can_view_active): ?>
                <div class="stat-card success">
                    <h4>✅ Active Licenses</h4>
                    <p class="stat-number"><?php echo number_format($active_licenses); ?></p>
                    <p class="stat-label">Currently active</p>
                </div>
                <?php endif; ?>

                <?php if ($can_view_expiring_30): ?>
                <div class="stat-card warning">
                    <h4>⚠️ Expiring in 45 Days</h4>
                    <p class="stat-number"><?php echo number_format($expiring_soon); ?></p>
                    <p class="stat-label">Requires attention</p>
                </div>
                <?php endif; ?>

                <?php if ($can_view_expired): ?>
                <div class="stat-card danger">
                    <h4>❌ Expired Licenses</h4>
                    <p class="stat-number"><?php echo number_format($expired); ?></p>
                    <p class="stat-label">Needs renewal</p>
                </div>
                <?php endif; ?>

                <?php if ($can_view_renewal_cost_45): ?>
                <div class="stat-card info">
                    <h4>💰 Renewal Cost (Expiring + Expired)</h4>
                    <p class="stat-number">₹<?php echo number_format($renewal_cost_45, 2); ?></p>
                    <p class="stat-label"><?php echo $renewal_count_45; ?> licenses - <?php echo $renewal_cost_label; ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Dashboard Charts and Tables -->
            <div class="dashboard-grid">
                <!-- Department Breakdown -->
                <?php if ($can_view_dept_breakdown && $dept_breakdown && mysqli_num_rows($dept_breakdown) > 0): ?>
                <div class="dashboard-card">
                    <h3>🏢 Department Breakdown</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th style="text-align: center;">Total</th>
                                <th style="text-align: center;">Active</th>
                                <th style="text-align: right;">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dept = mysqli_fetch_assoc($dept_breakdown)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong></td>
                                <td style="text-align: center;"><?php echo number_format($dept['license_count']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-success"><?php echo $dept['active_count']; ?></span>
                                </td>
                                <td style="text-align: right;"><strong>₹<?php echo number_format($dept['total_cost'], 2); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- License Type Breakdown -->
                <?php if (mysqli_num_rows($type_breakdown) > 0): ?>
                <div class="dashboard-card">
                    <h3>📑 License Type Breakdown</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th style="text-align: center;">Total</th>
                                <th style="text-align: center;">Active</th>
                                <th style="text-align: right;">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($type = mysqli_fetch_assoc($type_breakdown)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($type['type_name']); ?></strong></td>
                                <td style="text-align: center;"><?php echo number_format($type['count']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-success"><?php echo $type['active_count']; ?></span>
                                </td>
                                <td style="text-align: right;"><strong>₹<?php echo number_format($type['total_cost'], 2); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Licenses Needing Renewal -->
                <?php if ($expiring_45_days && mysqli_num_rows($expiring_45_days) > 0): ?>
                <div class="dashboard-card full-width">
                    <h3>🔔 Licenses Needing Renewal (Expiring in 45 Days + Expired)</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>License Name</th>
                                <th>License No.</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th style="text-align: center;">Days Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exp = mysqli_fetch_assoc($expiring_45_days)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($exp['license_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($exp['license_no']); ?></td>
                                <td><?php echo htmlspecialchars($exp['dept_name']); ?></td>
                                <td>
                                    <?php
                                    $status_class = $exp['status'] == 'expired' ? 'badge-danger' : 'badge-warning';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($exp['status']); ?></span>
                                </td>
                                <td><?php echo date('d-M-Y', strtotime($exp['expiry_date'])); ?></td>
                                <td style="text-align: center;">
                                    <?php
                                    $days = $exp['days_remaining'];
                                    if ($days < 0) {
                                        echo '<span class="badge badge-danger">Expired ' . abs($days) . ' days ago</span>';
                                    } else {
                                        $badge_class = $days <= 15 ? 'badge-danger' : ($days <= 30 ? 'badge-warning' : 'badge-info');
                                        echo '<span class="badge ' . $badge_class . '">' . $days . ' days left</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Recent Activities -->
                <?php if (mysqli_num_rows($recent_activities) > 0): ?>
                <div class="dashboard-card">
                    <h3>📝 Recent Activities</h3>
                    <div class="activity-list">
                        <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
                        <div class="activity-item">
                            <span class="activity-icon">
                                <?php
                                $icons = [
                                    'CREATE' => '➕',
                                    'UPDATE' => '✏️',
                                    'DELETE' => '🗑️',
                                    'RENEW' => '🔄',
                                    'APPROVE' => '✅',
                                    'REJECT' => '❌',
                                    'USER_CREATE' => '👤',
                                    'USER_UPDATE' => '✏️',
                                    'USER_DELETE' => '🗑️',
                                    'PERMISSION_UPDATE' => '🔐',
                                    'LOGIN' => '🔑'
                                ];
                                echo $icons[$activity['action_type']] ?? '📝';
                                ?>
                            </span>
                            <div class="activity-details">
                                <strong><?php echo htmlspecialchars($activity['performed_by_name'] ?? 'System'); ?></strong>
                                <p><?php echo htmlspecialchars($activity['action_details']); ?></p>
                                <small><?php echo date('M d, Y H:i', strtotime($activity['performed_at'])); ?></small>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Monthly Renewal Trend -->
                <?php if (count($monthly_data) > 0): ?>
                <div class="dashboard-card full-width">
                    <h3>📈 Monthly Renewal Trend (Last 6 Months)</h3>
                    <canvas id="renewalTrendChart" height="80"></canvas>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($monthly_data) > 0): ?>
    <script>
        const ctx = document.getElementById('renewalTrendChart').getContext('2d');
        const renewalData = <?php echo json_encode($monthly_data); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: renewalData.map(d => d.month_year),
                datasets: [{
                    label: 'Renewal Count',
                    data: renewalData.map(d => d.renewal_count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }, {
                    label: 'Total Cost (₹)',
                    data: renewalData.map(d => d.total_cost),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Renewal Count',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Cost (₹)',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
