<?php
// profile.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; // Αποθήκευση της τρέχουσας σελίδας για ανακατεύθυνση μετά το login
    header('Location: login.php');
    exit;
}

require_once 'php/db_connect.php'; // Σύνδεση με τη βάση

$user_info = null;
$error_message = '';

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, username, email, registration_date FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        // Αυτό δεν θα έπρεπε να συμβεί αν το user_id στο session είναι έγκυρο
        $error_message = "Δεν βρέθηκαν πληροφορίες για τον χρήστη.";
        // Εδώ θα μπορούσες να καταστρέψεις το session και να τον στείλεις για login
        // session_destroy(); header('Location: login.php'); exit;
    }
} catch (PDOException $e) {
    $error_message = "Σφάλμα κατά την ανάκτηση πληροφοριών χρήστη: " . $e->getMessage();
    // error_log($error_message);
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προφίλ Χρήστη - <?php echo isset($user_info['username']) ? htmlspecialchars($user_info['username']) : 'Ροή μου'; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .profile-info { margin-top: 20px; }
        .profile-info p { margin-bottom: 10px; }
        .profile-info strong { min-width: 150px; display: inline-block; }
        .actions a { margin-right: 10px; }
        .user-playlists, .followed-playlists { margin-top: 30px; }
    </style>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <h2>Το Προφίλ μου</h2>

        <?php if ($error_message): ?>
            <p class="general-error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if ($user_info): ?>
            <div class="profile-info">
                <p><strong>Όνομα Χρήστη (Username):</strong> <?php echo htmlspecialchars($user_info['username']); ?></p>
                <p><strong>Όνομα:</strong> <?php echo htmlspecialchars($user_info['first_name']); ?></p>
                <p><strong>Επώνυμο:</strong> <?php echo htmlspecialchars($user_info['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                <p><strong>Ημερομηνία Εγγραφής:</strong> 
                    <?php 
                        $date = new DateTime($user_info['registration_date']);
                        echo htmlspecialchars($date->format('d/m/Y H:i:s')); 
                    ?>
                </p>
            </div>
            <div class="actions" style="margin-top:20px;">
                <a href="edit_profile.php" class="button">Επεξεργασία Προφίλ</a>
                </div>

            <div class="user-playlists">
                <h3>Οι Λίστες μου</h3>
                <p>Δεν έχετε δημιουργήσει ακόμα λίστες. <a href="create_playlist.php">Δημιουργήστε την πρώτη σας λίστα!</a></p>
                 </div>

            <div class="followed-playlists">
                <h3>Λίστες που Ακολουθώ</h3>
                <p>Δεν ακολουθείτε ακόμα λίστες άλλων χρηστών.</p>
            </div>

        <?php elseif(!$error_message): ?>
            <p>Δεν ήταν δυνατή η φόρτωση των πληροφοριών του προφίλ σας.</p>
        <?php endif; ?>

    </main>

    <?php include 'php/partials/footer.php'; ?>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
</body>
</html>