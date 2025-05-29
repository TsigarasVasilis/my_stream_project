<?php
// follow_user.php - Placeholder για τη λειτουργία ακολούθησης
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}

require_once 'php/db_connect.php';

$target_user_id = $_GET['user_id'] ?? 0;
$action = $_GET['action'] ?? 'follow';
$redirect_url = $_GET['redirect'] ?? 'search_content.php';

if (!$target_user_id || $target_user_id == $_SESSION['user_id']) {
    header('Location: ' . $redirect_url);
    exit;
}

try {
    // Έλεγχος αν ο χρήστης προς ακολούθηση υπάρχει
    $stmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        header('Location: ' . $redirect_url);
        exit;
    }
    
    if ($action === 'follow') {
        // Προσθήκη ακολούθησης (αν δεν υπάρχει ήδη)
        $stmt = $pdo->prepare("INSERT IGNORE INTO follows (follower_user_id, followed_user_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $target_user_id]);
        $message = "Ακολουθείτε τώρα τον χρήστη " . htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']);
    } else {
        // Αφαίρεση ακολούθησης
        $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_user_id = ? AND followed_user_id = ?");
        $stmt->execute([$_SESSION['user_id'], $target_user_id]);
        $message = "Σταματήσατε να ακολουθείτε τον χρήστη " . htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']);
    }
    
} catch (PDOException $e) {
    // Σφάλμα - απλά επιστρέφουμε
}

// Ανακατεύθυνση πίσω με μήνυμα
$separator = strpos($redirect_url, '?') !== false ? '&' : '?';
header('Location: ' . $redirect_url . $separator . 'message=' . urlencode($message ?? ''));
exit;
?>