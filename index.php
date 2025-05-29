<?php
// index.php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Για το header partial, αν και το κάνει κι αυτό
}
// Εδώ μπορείς να προσθέσεις κώδικα PHP αν η αρχική σου σελίδα γίνει πιο δυναμική
// π.χ., εμφάνιση κάποιων δημόσιων λιστών
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Καλώς ήρθατε στο Ροή μου</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <?php // Εδώ θα μπορούσες να προσθέσεις CSS ειδικά για το index αν χρειάζεται ?>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <h2>Καλώς ήρθατε στην πλατφόρμα "Ροή μου"!</h2>
        <p>Δημιουργήστε το προσωπικό σας προφίλ, οργανώστε τα αγαπημένα σας βίντεο από το YouTube σε λίστες, και μοιραστείτε τις δημόσιες δημιουργίες σας με άλλους χρήστες.</p>
        <p>Μπορείτε να περιηγηθείτε στις <a href="search_content.php?show_public=true">δημόσιες λίστες</a> ή να <a href="register.php">κάνετε εγγραφή</a> για να ξεκινήσετε.</p>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
            <p class="success-message" style="margin-top:15px; color: green; background-color: #e6ffe6; border: 1px solid green; padding: 10px; border-radius: 4px;">
                Έχετε αποσυνδεθεί με επιτυχία.
            </p>
        <?php endif; ?>
        <?php if (isset($_GET['message']) && $_GET['message'] == 'profile_deleted_successfully'): ?>
            <p class="success-message" style="margin-top:15px; color: green; background-color: #e6ffe6; border: 1px solid green; padding: 10px; border-radius: 4px;">
                Το προφίλ σας διαγράφηκε επιτυχώς.
            </p>
        <?php endif; ?>

        <?php
        // Προαιρετικά: Εμφάνιση μερικών δημόσιων λιστών αν θέλεις
        /*
        require_once 'php/db_connect.php';
        try {
            $stmt_public = $pdo->query("SELECT p.playlist_id, p.playlist_name, u.username 
                                        FROM playlists p 
                                        JOIN users u ON p.user_id = u.user_id 
                                        WHERE p.is_public = TRUE 
                                        ORDER BY p.creation_date DESC LIMIT 5");
            $public_lists = $stmt_public->fetchAll(PDO::FETCH_ASSOC);
            if ($public_lists) {
                echo "<h3>Πρόσφατες Δημόσιες Λίστες:</h3><ul>";
                foreach ($public_lists as $list) {
                    echo "<li><a href='view_playlist_items.php?playlist_id=" . $list['playlist_id'] . "'>" . htmlspecialchars($list['playlist_name']) . "</a> (από " . htmlspecialchars($list['username']) . ")</li>";
                }
                echo "</ul>";
            }
        } catch (PDOException $e) {
            // echo "<p>Error fetching public lists.</p>";
        }
        */
        ?>
    </main>

    <?php include 'php/partials/footer.php'; ?>
    
    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
</body>
</html>