<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Καλώς ήρθατε στο Ροή μου</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    </head>
<body>
    <header>
        <h1>Ροή μου</h1>
        <nav>
            <ul>
                <li><a href="index.php">Αρχική</a></li>
                <li><a href="about.html">Σχετικά</a></li>
                <li><a href="help.html">Βοήθεια</a></li>
                <?php
                // session_start(); // Θα το χρειαστούμε αργότερα
                // if (isset($_SESSION['user_id'])) {
                //     echo '<li><a href="profile.php">Προφίλ</a></li>';
                //     echo '<li><a href="php/logout.php">Αποσύνδεση</a></li>';
                // } else {
                //     echo '<li><a href="php/register_form.php">Εγγραφή</a></li>';
                //     echo '<li><a href="php/login_form.php">Σύνδεση</a></li>';
                // }
                ?>
            </ul>
        </nav>
        <button id="theme-toggle-button">Εναλλαγή Θέματος</button>
    </header>

    <main>
        <h2>Καλώς ήρθατε!</h2>
        <p>Αυτή είναι η αρχική σελίδα της εφαρμογής "Ροή μου". Εξερευνήστε τις επιλογές στο μενού για να μάθετε περισσότερα.</p>
        <p>Σύντομα εδώ θα μπορείτε να δείτε προτεινόμενες λίστες ή άλλες πληροφορίες.</p>
        </main>

    <footer>
        <p>&copy; <span id="year"></span> Ροή μου - Εργασία Εξαμήνου</p>
    </footer>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
</body>
</html>