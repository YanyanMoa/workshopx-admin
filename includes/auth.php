<?php
/**
 * WorkshopX Admin - Auth Guard
 * Include this at the very top of any page that requires a logged-in admin/staff.
 */

require_once __DIR__ . '/../config.php';

function require_login(): void
{
    if (empty($_SESSION['sb_access_token']) || empty($_SESSION['sb_user'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['sb_user'] ?? null;
}

function current_token(): ?string
{
    return $_SESSION['sb_access_token'] ?? null;
}

function current_role(): string
{
    return $_SESSION['sb_role'] ?? 'staff';
}

/** Restrict a page to specific roles, e.g. require_role(['admin']) */
function require_role(array $allowedRoles): void
{
    require_login();
    if (!in_array(current_role(), $allowedRoles, true)) {
        http_response_code(403);
        echo '<div style="padding:40px;font-family:sans-serif;text-align:center;">
                <h2>Access Denied</h2>
                <p>You do not have permission to view this page.</p>
                <a href="dashboard.php">Back to Dashboard</a>
              </div>';
        exit;
    }
}
