<?php
if (session_status() == PHP_SESSION_NONE) { // Ξεκινάει το session μόνο αν δεν έχει ήδη ξεκινήσει
    session_start();
}
?>
<header>
    <h1><a href="<?php echo isset($_SESSION['user_id']) ? 'profile.php' : 'index.php'; ?>" style="text-decoration: none; color: inherit;">Ροή μου</a></h1>
    <nav>
        <ul>
            <li><a href="index.php">Αρχική</a></li>
            <li><a href="about.php">Σχετικά</a></li> <?php // Άλλαξε από about.html ?>
            <li><a href="help.php">Βοήθεια</a></li> <?php // Άλλαξε από help.html ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="profile.php">Προφίλ (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <li><a href="my_playlists.php">Οι Λίστες μου</a></li>
                <li><a href="search_content.php">Αναζήτηση</a></li> <?php // Προσθήκη link αναζήτησης ?>
                <li><a href="export_yaml.php">Εξαγωγή YAML</a></li>
                <li><a href="logout.php">Αποσύνδεση</a></li>
            <?php else: ?>
                <li><a href="search_content.php">Αναζήτηση</a></li> <?php // Προσθήκη link αναζήτησης ?>
                <li><a href="register.php">Εγγραφή</a></li>
                <li><a href="login.php">Σύνδεση</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <button id="theme-toggle-button">Εναλλαγή Θέματος</button>
</header>