<?php
// setup_database.php - Εκτελέστε αυτό το αρχείο μια φορά για να δημιουργήσετε τη βάση
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'my_stream_db';

try {
    // Σύνδεση χωρίς να καθορίσουμε βάση για να μπορούμε να τη δημιουργήσουμε
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Δημιουργία βάσης δεδομένων
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Βάση δεδομένων '$db_name' δημιουργήθηκε επιτυχώς.\n<br>";
    
    // Σύνδεση στη νέα βάση
    $pdo->exec("USE $db_name");
    
    // Δημιουργία πίνακα users
    $sql_users = "
    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        username VARCHAR(20) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email)
    )";
    $pdo->exec($sql_users);
    echo "Πίνακας 'users' δημιουργήθηκε επιτυχώς.\n<br>";
    
    // Δημιουργία πίνακα playlists
    $sql_playlists = "
    CREATE TABLE IF NOT EXISTS playlists (
        playlist_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        playlist_name VARCHAR(100) NOT NULL,
        is_public BOOLEAN DEFAULT FALSE,
        creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_playlists); 
    echo "Πίνακας 'playlists' δημιουργήθηκε επιτυχώς.\n<br>";
    
    // Δημιουργία πίνακα playlist_items
    $sql_items = "
    CREATE TABLE IF NOT EXISTS playlist_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        playlist_id INT NOT NULL,
        video_id VARCHAR(20) NOT NULL,
        video_title VARCHAR(200) NOT NULL,
        video_thumbnail VARCHAR(500),
        added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (playlist_id) REFERENCES playlists(playlist_id) ON DELETE CASCADE
    )";
    $pdo->exec($sql_items);
    echo "Πίνακας 'playlist_items' δημιουργήθηκε επιτυχώς.\n<br>";
    
    // Δημιουργία πίνακα follows
    $sql_follows = "
    CREATE TABLE IF NOT EXISTS follows (
        follow_id INT AUTO_INCREMENT PRIMARY KEY,
        follower_user_id INT NOT NULL,
        followed_user_id INT NOT NULL,
        follow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (followed_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_follow (follower_user_id, followed_user_id)
    )";
    $pdo->exec($sql_follows);
    echo "Πίνακας 'follows' δημιουργήθηκε επιτυχώς.\n<br>";

    // Δημιουργία πίνακα videos
$sql_videos = "
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
$pdo->exec($sql_videos);
echo "Πίνακας 'videos' δημιουργήθηκε επιτυχώς.\n<br>";

    
    echo "\n<br><strong>Η βάση δεδομένων δημιουργήθηκε επιτυχώς! Μπορείτε τώρα να χρησιμοποιήσετε την εφαρμογή.</strong>";
    
} catch (PDOException $e) {
    echo "Σφάλμα κατά τη δημιουργία της βάσης: " . $e->getMessage();
}
?>