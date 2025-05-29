<?php
// php/db_connect.php

$db_host = 'localhost'; // ή η IP του server αν δεν είναι τοπικά
$db_name = 'my_stream_db'; // Το όνομα της βάσης που δημιούργησες
$db_user = 'root';       // Ο χρήστης της βάσης (π.χ., 'root' για τοπικό XAMPP)
$db_pass = '';           // Ο κωδικός του χρήστη (π.χ., κενός για τοπικό XAMPP)

$charset = 'utf8mb4';

// Data Source Name
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Ρίχνει exceptions για σφάλματα αντί για warnings
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Επιστρέφει τα αποτελέσματα ως associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Απενεργοποίηση emulation για πραγματικά prepared statements
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Έλεγχος αν η βάση υπάρχει και έχει πίνακες
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Ο πίνακας users δεν υπάρχει
        error_log("Database Warning: 'users' table does not exist. Please run setup_database.php");
    }
    
} catch (\PDOException $e) {
    // Καλύτερο error handling
    $error_message = "Σφάλμα σύνδεσης με τη βάση δεδομένων.";
    
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        $error_message = "Η βάση δεδομένων '$db_name' δεν υπάρχει. Παρακαλώ εκτελέστε το setup_database.php πρώτα.";
    } elseif (strpos($e->getMessage(), "Access denied") !== false) {
        $error_message = "Δεν είναι δυνατή η σύνδεση με τη βάση δεδομένων. Ελέγξτε τα στοιχεία σύνδεσης.";
    } elseif (strpos($e->getMessage(), "Connection refused") !== false) {
        $error_message = "Ο MySQL server δεν τρέχει. Ξεκινήστε τον MySQL/XAMPP server.";
    }
    
    // Log το πραγματικό σφάλμα για debugging
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Εμφάνιση φιλικού μηνύματος στον χρήστη
    die($error_message);
}

// Το $pdo object είναι τώρα διαθέσιμο για χρήση στα αρχεία που κάνουν include αυτό το αρχείο.
?>