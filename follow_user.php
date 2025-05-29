<?php
// follow_user.php - Βελτιωμένη λειτουργία ακολούθησης
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php?message=login_required_to_follow');
    exit;
}

require_once 'php/db_connect.php';

$target_user_id = $_GET['user_id'] ?? 0;
$action = $_GET['action'] ?? 'follow';
$redirect_url = $_GET['redirect'] ?? 'search_content.php';
$message = '';
$error = '';

// Validation
if (!$target_user_id || !is_numeric($target_user_id)) {
    header('Location: ' . $redirect_url . '?error=' . urlencode('Μή έγκυρος χρήστης.'));
    exit;
}

if ($target_user_id == $_SESSION['user_id']) {
    header('Location: ' . $redirect_url . '?error=' . urlencode('Δεν μπορείτε να ακολουθήσετε τον εαυτό σας.'));
    exit;
}

try {
    // Έλεγχος αν ο χρήστης προς ακολούθηση υπάρχει
    $stmt = $pdo->prepare("SELECT user_id, username, first_name, last_name FROM users WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        header('Location: ' . $redirect_url . '?error=' . urlencode('Ο χρήστης δεν βρέθηκε.'));
        exit;
    }
    
    // Έλεγχος τρέχουσας κατάστασης ακολούθησης
    $stmt = $pdo->prepare("SELECT follow_id FROM follows WHERE follower_user_id = ? AND followed_user_id = ?");
    $stmt->execute([$_SESSION['user_id'], $target_user_id]);
    $existing_follow = $stmt->fetch();
    
    if ($action === 'follow') {
        if ($existing_follow) {
            $message = "Ακολουθείτε ήδη τον χρήστη " . htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']) . ".";
        } else {
            // Προσθήκη ακολούθησης
            $stmt = $pdo->prepare("INSERT INTO follows (follower_user_id, followed_user_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $target_user_id]);
            $message = "Ακολουθείτε τώρα τον χρήστη " . htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']) . "!";
        }
    } elseif ($action === 'unfollow') {
        if (!$existing_follow) {
            $message = "Δεν ακολουθείτε αυτόν τον χρήστη.";
        } else {
            // Αφαίρεση ακολούθησης
            $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_user_id = ? AND followed_user_id = ?");
            $stmt->execute([$_SESSION['user_id'], $target_user_id]);
            $message = "Σταματήσατε να ακολουθείτε τον χρήστη " . htmlspecialchars($target_user['first_name'] . ' ' . $target_user['last_name']) . ".";
        }
    } else {
        $error = "Μή έγκυρη ενέργεια.";
    }
    
} catch (PDOException $e) {
    error_log("Follow/Unfollow Error: " . $e->getMessage());
    $error = "Προέκυψε σφάλμα κατά την επεξεργασία του αιτήματος.";
}

// Ανακατεύθυνση πίσω με μήνυμα
$separator = strpos($redirect_url, '?') !== false ? '&' : '?';
if ($error) {
    header('Location: ' . $redirect_url . $separator . 'error=' . urlencode($error));
} else {
    header('Location: ' . $redirect_url . $separator . 'message=' . urlencode($message));
}
exit;
?>