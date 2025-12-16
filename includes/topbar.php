<?php
// Assumes $pdo_conn is available in the page including this
require_once __DIR__ . "/../classes/notification.php";
$notifyObj = new Notification($pdo_conn);
$unread_count = $notifyObj->countUnread();
$notifications = $notifyObj->getUnreadNotifications();
?>

<style>
    /* Topbar Container */
    .topbar {
        display: flex;
        justify-content: flex-end; /* Push content to the right */
        align-items: center;
        padding: 10px 20px;
        background-color: #fff;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 20px;
    }

    /* Notification Wrapper */
    .notification-wrapper {
        position: relative;
        cursor: pointer;
        margin-right: 20px;
    }

    /* Bell Icon */
    .bell-icon {
        font-size: 1.5rem;
        color: #555;
        transition: color 0.3s;
    }
    .bell-icon:hover { color: #007bff; }

    /* Red Badge */
    .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 0.75rem;
        font-weight: bold;
        border: 2px solid #fff;
    }

    /* Dropdown Menu */
    .notif-dropdown {
        display: none; /* Hidden by default */
        position: absolute;
        right: 0;
        top: 30px;
        width: 300px;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow: hidden;
    }

    /* Show dropdown on hover (simple CSS method) */
    .notification-wrapper:hover .notif-dropdown {
        display: block;
    }

    .notif-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        font-weight: bold;
        font-size: 0.9rem;
        border-bottom: 1px solid #dee2e6;
        color: #495057;
    }

    .notif-item {
        display: block;
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
        text-decoration: none;
        color: #333;
        transition: background 0.2s;
        font-size: 0.9rem;
    }
    .notif-item:hover { background-color: #f8f9fa; }
    .notif-item:last-child { border-bottom: none; }
    
    .notif-title { font-weight: bold; display: block; margin-bottom: 2px; }
    .notif-time { font-size: 0.75rem; color: #999; float: right; }
    .notif-desc { font-size: 0.85rem; color: #666; display: block; }

    /* Type Colors */
    .type-success .notif-title { color: #28a745; }
    .type-warning .notif-title { color: #ffc107; }
</style>

<div class="topbar">
    <div class="notification-wrapper">
        <span class="bell-icon">🔔</span>
        <?php if ($unread_count > 0): ?>
            <span class="badge"><?= $unread_count ?></span>
        <?php endif; ?>

        <div class="notif-dropdown">
            <div class="notif-header">Notifications (<?= $unread_count ?>)</div>
            
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="../laundryShopOrd/admin/mark_read.php?id=<?= $notif['id'] ?>&redirect=<?= urlencode($notif['link']) ?>" 
                       class="notif-item type-<?= $notif['type'] ?>">
                        <span class="notif-time"><?= date('M d, H:i', strtotime($notif['created_at'])) ?></span>
                        <span class="notif-title"><?= htmlspecialchars($notif['title']) ?></span>
                        <span class="notif-desc"><?= $notif['message'] // Contains HTML bold tags ?></span>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notif-item" style="text-align: center; color: #999;">No new notifications</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="font-weight: bold; color: #555;">
        👤 <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
    </div>
</div>