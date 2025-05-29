<?php
// search_content.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php/db_connect.php';

// Παράμετροι αναζήτησης
$search_query = $_GET['q'] ?? '';
$search_creator = $_GET['creator'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_public = isset($_GET['show_public']) && $_GET['show_public'] == 'true';
$per_page = (int)($_GET['per_page'] ?? 10);
$page = max(1, (int)($_GET['page'] ?? 1));

// Validations
if ($per_page < 5 || $per_page > 50) {
    $per_page = 10;
}

$offset = ($page - 1) * $per_page;
$results = [];
$total_results = 0;
$error_message = '';
$success_message = $_GET['message'] ?? '';

try {
    // Δημιουργία WHERE clause
    $where_conditions = [];
    $params = [];
    
    // Βασικό φιλτράρισμα: δημόσιες λίστες ή λίστες χρήστη
    if (isset($_SESSION['user_id']) && !$show_public) {
        $where_conditions[] = "(p.is_public = 1 OR p.user_id = ?)";
        $params[] = $_SESSION['user_id'];
    } else {
        $where_conditions[] = "p.is_public = 1";
    }
    
    // Αναζήτηση κειμένου (στον τίτλο λίστας ή τίτλους βίντεο)
    if (!empty($search_query)) {
        $where_conditions[] = "(p.playlist_name LIKE ? OR EXISTS (
            SELECT 1 FROM playlist_items pi 
            WHERE pi.playlist_id = p.playlist_id 
            AND pi.video_title LIKE ?
        ))";
        $search_term = '%' . $search_query . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Αναζήτηση δημιουργού
    if (!empty($search_creator)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $creator_term = '%' . $search_creator . '%';
        $params[] = $creator_term;
        $params[] = $creator_term;
        $params[] = $creator_term;
        $params[] = $creator_term;
    }
    
    // Φιλτράρισμα ημερομηνίας
    if (!empty($date_from)) {
        $where_conditions[] = "p.creation_date >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "p.creation_date <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Μέτρηση συνολικών αποτελεσμάτων
    $count_sql = "
        SELECT COUNT(DISTINCT p.playlist_id) as total
        FROM playlists p 
        JOIN users u ON p.user_id = u.user_id 
        $where_clause
    ";
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_results = $stmt->fetch()['total'];
    
    // Λήψη αποτελεσμάτων με pagination
    $search_sql = "
        SELECT DISTINCT
            p.playlist_id,
            p.playlist_name,
            p.is_public,
            p.creation_date,
            u.user_id,
            u.username,
            u.first_name,
            u.last_name,
            COUNT(pi.item_id) as video_count
        FROM playlists p 
        JOIN users u ON p.user_id = u.user_id 
        LEFT JOIN playlist_items pi ON p.playlist_id = pi.playlist_id
        $where_clause
        GROUP BY p.playlist_id
        ORDER BY p.creation_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($search_sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Σφάλμα κατά την αναζήτηση: " . $e->getMessage();
}

// Υπολογισμός pagination
$total_pages = ceil($total_results / $per_page);
$has_next = $page < $total_pages;
$has_prev = $page > 1;

// Δημιουργία URL για pagination
function buildUrl($params) {
    $current_params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($current_params[$key]);
        } else {
            $current_params[$key] = $value;
        }
    }
    return 'search_content.php?' . http_build_query($current_params);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αναζήτηση Περιεχομένου - Ροή μου</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .search-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .search-form {
            background-color: var(--current-accordion-header-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--current-accordion-header-text);
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid var(--current-border-color);
            border-radius: 4px;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .search-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--button-bg);
            color: var(--button-text);
        }
        
        .btn-primary:hover {
            background-color: var(--button-hover-bg);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .results-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .results-info {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .playlist-card {
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 15px;
            background-color: var(--current-accordion-content-bg);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .playlist-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .playlist-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .playlist-title {
            font-size: 1.1em;
            font-weight: bold;
            margin: 0;
            flex-grow: 1;
        }
        
        .playlist-title a {
            color: var(--nav-link);
            text-decoration: none;
        }
        
        .playlist-title a:hover {
            color: var(--nav-link-hover);
        }
        
        .privacy-badge {
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        .privacy-public {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .privacy-private {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .playlist-meta {
            color: var(--text-color);
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .creator-info {
            color: var(--nav-link);
            font-weight: bold;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--current-border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .pagination a:hover {
            background-color: var(--current-accordion-header-bg);
        }
        
        .pagination .current {
            background-color: var(--button-bg);
            color: var(--button-text);
            border-color: var(--button-bg);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-color);
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 5px 12px;
            font-size: 0.9em;
            border-radius: 15px;
            border: 1px solid var(--current-border-color);
            background-color: var(--bg-color);
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background-color: var(--button-bg);
            color: var(--button-text);
            border-color: var(--button-bg);
        }
    </style>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <div class="search-container">
            <h2>🔍 Αναζήτηση Περιεχομένου</h2>

            <?php if ($success_message): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Φόρμα Αναζήτησης -->
            <form class="search-form" method="GET">
                <h3>Κριτήρια Αναζήτησης</h3>
                
                <div class="quick-filters">
                    <button type="button" class="filter-btn <?php echo $show_public ? 'active' : ''; ?>" 
                            onclick="togglePublicFilter()">
                        🌐 Μόνο Δημόσιες Λίστες
                    </button>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="q">Αναζήτηση στις λίστες/βίντεο:</label>
                        <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="π.χ. μουσική, comedy, tutorial...">
                    </div>
                    
                    <div class="form-group">
                        <label for="creator">Δημιουργός (όνομα/username/email):</label>
                        <input type="text" id="creator" name="creator" value="<?php echo htmlspecialchars($search_creator); ?>" 
                               placeholder="π.χ. Γιάννης, john123...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_from">Από ημερομηνία:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Έως ημερομηνία:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">🔍 Αναζήτηση</button>
                    <a href="search_content.php" class="btn btn-secondary">🔄 Καθαρισμός</a>
                </div>
                
                <input type="hidden" name="show_public" value="<?php echo $show_public ? 'true' : 'false'; ?>">
            </form>

            <!-- Αποτελέσματα -->
            <?php if (!empty($search_query) || !empty($search_creator) || !empty($date_from) || !empty($date_to) || $show_public): ?>
                <div class="results-header">
                    <div class="results-info">
                        <strong>Αποτελέσματα:</strong> <?php echo $total_results; ?> λίστες
                        <?php if ($total_results > 0): ?>
                            (σελίδα <?php echo $page; ?> από <?php echo $total_pages; ?>)
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($total_results > 0): ?>
                        <div class="per-page-selector">
                            <label for="per_page">Ανά σελίδα:</label>
                            <select id="per_page" name="per_page" onchange="changePerPage(this.value)">
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <h3>😔 Δεν βρέθηκαν αποτελέσματα</h3>
                        <p>Δοκιμάστε να αλλάξετε τα κριτήρια αναζήτησης ή να καθαρίσετε τα φίλτρα.</p>
                    </div>
                <?php else: ?>
                    <div class="results-grid">
                        <?php foreach ($results as $playlist): ?>
                            <div class="playlist-card">
                                <div class="playlist-header">
                                    <h3 class="playlist-title">
                                        <a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>">
                                            <?php echo htmlspecialchars($playlist['playlist_name']); ?>
                                        </a>
                                    </h3>
                                    <span class="privacy-badge <?php echo $playlist['is_public'] ? 'privacy-public' : 'privacy-private'; ?>">
                                        <?php echo $playlist['is_public'] ? 'Δημόσια' : 'Ιδιωτική'; ?>
                                    </span>
                                </div>
                                
                                <div class="playlist-meta">
                                    <p><strong>Δημιουργός:</strong> 
                                        <span class="creator-info">
                                            <?php echo htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']); ?>
                                            (@<?php echo htmlspecialchars($playlist['username']); ?>)
                                        </span>
                                    </p>
                                    <p><strong>Βίντεο:</strong> <?php echo $playlist['video_count']; ?></p>
                                    <p><strong>Δημιουργήθηκε:</strong> <?php 
                                        $date = new DateTime($playlist['creation_date']);
                                        echo $date->format('d/m/Y H:i'); 
                                    ?></p>
                                </div>
                                
                                <div style="margin-top: 15px;">
                                    <a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>" 
                                       class="btn btn-primary">👁️ Προβολή Λίστας</a>
                                    
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $playlist['user_id']): ?>
                                        <!-- Μελλοντικά: κουμπί Follow/Unfollow -->
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($has_prev): ?>
                                <a href="<?php echo buildUrl(['page' => $page - 1]); ?>">‹ Προηγούμενη</a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                                if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                                <?php endif;
                            endfor; ?>
                            
                            <?php if ($has_next): ?>
                                <a href="<?php echo buildUrl(['page' => $page + 1]); ?>">Επόμενη ›</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <!-- Προβολή δημόσιων λιστών όταν δεν υπάρχει αναζήτηση -->
                <div class="empty-state">
                    <h3>🎵 Ανακαλύψτε περιεχόμενο</h3>
                    <p>Χρησιμοποιήστε τη φόρμα παραπάνω για να αναζητήσετε λίστες περιεχομένου.</p>
                    <p>Ή δείτε όλες τις <button onclick="showPublicPlaylists()" class="filter-btn">δημόσιες λίστες</button></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'php/partials/footer.php'; ?>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
    <script>
        function togglePublicFilter() {
            const currentUrl = new URL(window.location);
            const isPublic = currentUrl.searchParams.get('show_public') === 'true';
            
            if (isPublic) {
                currentUrl.searchParams.delete('show_public');
            } else {
                currentUrl.searchParams.set('show_public', 'true');
            }
            currentUrl.searchParams.delete('page'); // Reset to first page
            
            window.location.href = currentUrl.toString();
        }
        
        function showPublicPlaylists() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('show_public', 'true');
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }
        
        function changePerPage(newPerPage) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('per_page', newPerPage);
            currentUrl.searchParams.set('page', '1'); // Reset to first page
            window.location.href = currentUrl.toString();
        }
        
        // Auto-submit form on date change (optional UX improvement)
        document.getElementById('date_from').addEventListener('change', function() {
            if (this.value && document.getElementById('date_to').value) {
                // Auto-submit if both dates are set
                // this.form.submit();
            }
        });
        
        document.getElementById('date_to').addEventListener('change', function() {
            if (this.value && document.getElementById('date_from').value) {
                // Auto-submit if both dates are set
                // this.form.submit();
            }
        });
    </script>
</body>
</html>