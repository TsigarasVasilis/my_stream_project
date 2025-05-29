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
        
        .playlists-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .playlist-card {
            background-color: var(--current-accordion-header-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 6px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .playlist-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .playlist-card h4 {
            margin: 0 0 8px 0;
        }
        
        .playlist-card h4 a {
            color: var(--nav-link);
            text-decoration: none;
        }
        
        .playlist-card h4 a:hover {
            color: var(--nav-link-hover);
        }
        
        .playlist-card p {
            margin: 5px 0;
            color: var(--text-color);
            font-size: 0.9em;
        }
        
        .playlist-card small {
            color: var(--text-color);
            opacity: 0.7;
            font-size: 0.8em;
        }
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
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT p.playlist_id, p.playlist_name, p.is_public, p.creation_date,
                               COUNT(pi.item_id) as item_count
                        FROM playlists p 
                        LEFT JOIN playlist_items pi ON p.playlist_id = pi.playlist_id
                        WHERE p.user_id = ? 
                        GROUP BY p.playlist_id
                        ORDER BY p.creation_date DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($user_playlists): ?>
                        <div class="playlists-grid">
                            <?php foreach ($user_playlists as $playlist): ?>
                                <div class="playlist-card">
                                    <h4><a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>">
                                        <?php echo htmlspecialchars($playlist['playlist_name']); ?>
                                    </a></h4>
                                    <p><?php echo $playlist['item_count']; ?> βίντεο • 
                                       <?php echo $playlist['is_public'] ? 'Δημόσια' : 'Ιδιωτική'; ?></p>
                                    <small><?php 
                                        $date = new DateTime($playlist['creation_date']);
                                        echo $date->format('d/m/Y'); 
                                    ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="text-align: center; margin-top: 15px;">
                            <a href="my_playlists.php">Προβολή όλων των λιστών →</a>
                        </p>
                    <?php else: ?>
                        <p>Δεν έχετε δημιουργήσει ακόμα λίστες. <a href="create_playlist.php">Δημιουργήστε την πρώτη σας λίστα!</a></p>
                    <?php endif;
                } catch (PDOException $e) {
                    echo "<p>Σφάλμα κατά την ανάκτηση λιστών.</p>";
                }
                ?>
            </div>

            <div class="followed-playlists">
                <h3>Δημόσιες Λίστες που μπορείτε να Παρακολουθήσετε</h3>
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT p.playlist_id, p.playlist_name, p.creation_date,
                               u.username, u.first_name, u.last_name,
                               COUNT(pi.item_id) as item_count
                        FROM playlists p 
                        JOIN users u ON p.user_id = u.user_id
                        LEFT JOIN playlist_items pi ON p.playlist_id = pi.playlist_id
                        WHERE p.is_public = 1 AND p.user_id != ?
                        GROUP BY p.playlist_id
                        ORDER BY p.creation_date DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $public_playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($public_playlists): ?>
                        <div class="playlists-grid">
                            <?php foreach ($public_playlists as $playlist): ?>
                                <div class="playlist-card">
                                    <h4><a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>">
                                        <?php echo htmlspecialchars($playlist['playlist_name']); ?>
                                    </a></h4>
                                    <p>από <?php echo htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']); ?></p>
                                    <p><?php echo $playlist['item_count']; ?> βίντεο</p>
                                    <small><?php 
                                        $date = new DateTime($playlist['creation_date']);
                                        echo $date->format('d/m/Y'); 
                                    ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="text-align: center; margin-top: 15px;">
                            <a href="search_content.php?show_public=true">Περιήγηση σε όλες τις δημόσιες λίστες →</a>
                        </p>
                    <?php else: ?>
                        <p>Δεν υπάρχουν δημόσιες λίστες άλλων χρηστών αυτή τη στιγμή.</p>
                    <?php endif;
                } catch (PDOException $e) {
                    echo "<p>Σφάλμα κατά την ανάκτηση δημόσιων λιστών.</p>";
                }
                ?>
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