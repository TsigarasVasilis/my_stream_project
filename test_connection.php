<?php
// test_connection.php - Εκτελέστε αυτό για να ελέγξετε τη σύνδεση με τη βάση
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Test Database Connection</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffe8e8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #e8f0ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Έλεγχος Σύνδεσης Βάσης Δεδομένων</h1>
    
    <?php
    $db_host = 'localhost';
    $db_name = 'my_stream_db';
    $db_user = 'root';
    $db_pass = '';
    
    echo "<div class='info'><strong>Στοιχεία σύνδεσης:</strong><br>";
    echo "Host: $db_host<br>";
    echo "Database: $db_name<br>";
    echo "User: $db_user<br>";
    echo "Password: " . (empty($db_pass) ? "[κενό]" : "[οριστεί]") . "</div>";
    
    try {
        // Βήμα 1: Σύνδεση χωρίς βάση
        echo "<h3>Βήμα 1: Σύνδεση με MySQL Server</h3>";
        $pdo_test = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<div class='success'>✓ Επιτυχής σύνδεση με MySQL server</div>";
        
        // Βήμα 2: Έλεγχος αν υπάρχει η βάση
        echo "<h3>Βήμα 2: Έλεγχος ύπαρξης βάσης δεδομένων</h3>";
        $stmt = $pdo_test->query("SHOW DATABASES LIKE '$db_name'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✓ Η βάση '$db_name' υπάρχει</div>";
            
            // Βήμα 3: Σύνδεση με την βάση
            echo "<h3>Βήμα 3: Σύνδεση με τη βάση δεδομένων</h3>";
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "<div class='success'>✓ Επιτυχής σύνδεση με τη βάση '$db_name'</div>";
            
            // Βήμα 4: Έλεγχος πινάκων
            echo "<h3>Βήμα 4: Έλεγχος πινάκων</h3>";
            $tables = ['users', 'playlists', 'playlist_items', 'follows'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<div class='success'>✓ Ο πίνακας '$table' υπάρχει</div>";
                } else {
                    echo "<div class='error'>✗ Ο πίνακας '$table' ΔΕΝ υπάρχει</div>";
                }
            }
            
            // Βήμα 5: Έλεγχος εγγραφών στον πίνακα users
            echo "<h3>Βήμα 5: Έλεγχος εγγραφών χρηστών</h3>";
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            echo "<div class='info'>Συνολικοί εγγεγραμμένοι χρήστες: " . $result['count'] . "</div>";
            
            if ($result['count'] > 0) {
                $stmt = $pdo->query("SELECT username, first_name, registration_date FROM users ORDER BY registration_date DESC LIMIT 5");
                echo "<strong>Τελευταίοι εγγεγραμμένοι χρήστες:</strong><br>";
                while ($user = $stmt->fetch()) {
                    echo "- " . htmlspecialchars($user['username']) . " (" . htmlspecialchars($user['first_name']) . ") - " . $user['registration_date'] . "<br>";
                }
            }
            
        } else {
            echo "<div class='error'>✗ Η βάση '$db_name' ΔΕΝ υπάρχει</div>";
            echo "<div class='info'><strong>Λύση:</strong> Εκτελέστε το αρχείο setup_database.php για να δημιουργήσετε τη βάση και τους πίνακες.</div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'><strong>Σφάλμα:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        
        if (strpos($e->getMessage(), "Connection refused") !== false) {
            echo "<div class='info'><strong>Λύση:</strong> Ο MySQL server δεν τρέχει. Ξεκινήστε τον XAMPP/WAMP/MAMP.</div>";
        } elseif (strpos($e->getMessage(), "Access denied") !== false) {
            echo "<div class='info'><strong>Λύση:</strong> Ελέγξτε τα στοιχεία σύνδεσης (username/password) στο db_connect.php.</div>";
        }
    }
    ?>
    
    <hr>
    <h3>Οδηγίες επίλυσης προβλημάτων:</h3>
    <ol>
        <li><strong>Αν ο MySQL server δεν τρέχει:</strong> Ξεκινήστε τον XAMPP/WAMP/MAMP</li>
        <li><strong>Αν η βάση δεν υπάρχει:</strong> Εκτελέστε το <code>setup_database.php</code></li>
        <li><strong>Αν οι πίνακες δεν υπάρχουν:</strong> Εκτελέστε το <code>setup_database.php</code></li>
        <li><strong>Αν έχετε προβλήματα σύνδεσης:</strong> Ελέγξτε τις ρυθμίσεις στο <code>php/db_connect.php</code></li>
    </ol>
    
    <p><a href="index.php">← Επιστροφή στην αρχική</a></p>
</body>
</html>