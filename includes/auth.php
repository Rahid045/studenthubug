<?php

/** Redirect visitors who are not signed in. */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
}

/**
 * Refresh the signed-in user's role from the database and allow admins only.
 * This prevents a student from gaining upload access by visiting an upload URL.
 */
function requireAdmin(mysqli $connect): void {
    requireLogin();

    $userId = (int) $_SESSION['user_id'];
    $statement = mysqli_prepare($connect, 'SELECT role FROM users WHERE user_id = ? LIMIT 1');
    if (!$statement) {
        http_response_code(500);
        exit('Unable to verify account permissions.');
    }

    mysqli_stmt_bind_param($statement, 'i', $userId);
    mysqli_stmt_execute($statement);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    $_SESSION['role'] = $user['role'] ?? 'student';

    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        exit('Only administrators can upload study materials.');
    }
}
