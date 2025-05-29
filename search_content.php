<?php
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
$per_page = (int)($_GET['per_page'] ?? 12);
$page = max(1, (int)($_GET['page'] ?? 1));

// Validations
if ($per_page < 6 || $per_page > 60) {
    $per_page = 12;
}

$offset = ($page - 1) * $per_page;
$results = [];
$total_results = 0;
$error_message = '';
$success_message = $_GET['message'] ?? '';

// Λήψη follow relationships αν είναι συνδεδεμένος
$follow_status = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT followed_user_id FROM follows WHERE follower_user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $following = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $follow_status = array_flip($following);
    } catch (PDOException $e) {
        // Non-critical error
    }
}

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
            COUNT(pi.item_id) as video_count,
            (SELECT COUNT(*) FROM follows WHERE followed_user_id = u.user_id) as followers_count
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .search-header {
            background: linear-gradient(135deg, var(--current-accordion-header-bg) 0%, var(--current-accordion-content-bg) 100%);
            border: 1px solid var(--current-border-color);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .search-header h2 {
            margin: 0 0 20px 0;
            color: var(--current-accordion-header-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-form {
            background-color: var(--bg-color);
            border: 1px solid var(--current-border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
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
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--current-accordion-header-text);
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--nav-link);
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .search-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--button-bg) 0%, var(--nav-link) 100%);
            color: var(--button-text);
            box-shadow: 0 2px 10px rgba(0,123,255,0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,123,255,0.4);
        }
        
        .btn-secondary {
            background-color: var(--current-accordion-header-bg);
            color: var(--text-color);
            border: 1px solid var(--current-border-color);
        }
        
        .btn-secondary:hover {
            background-color: var(--current-border-color);
            transform: translateY(-1px);
        }
        
        .btn-follow {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        .btn-follow:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(40,167,69,0.3);
        }
        
        .btn-unfollow {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        .btn-unfollow:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220,53,69,0.3);
        }
        
        .quick-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            font-size: 0.85em;
            border-radius: 20px;
            border: 1px solid var(--current-border-color);
            background-color: var(--bg-color);
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, var(--button-bg) 0%, var(--nav-link) 100%);
            color: var(--button-text);
            border-color: var(--button-bg);
            box-shadow: 0 2px 8px rgba(0,123,255,0.2);
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .results-info {
            color: var(--text-color);
            font-weight: 500;
        }
        
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .playlist-card {
            background-color: var(--current-accordion-content-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .playlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .playlist-thumbnail {
            height: 140px;
            background: linear-gradient(135deg, var(--nav-link) 0%, var(--button-bg) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5em;
            position: relative;
        }
        
        .playlist-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .playlist-card:hover .playlist-overlay {
            opacity: 1;
        }
        
        .play-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.9);
            color: var(--nav-link);
            border: none;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .play-button:hover {
            transform: scale(1.1);
            background-color: white;
        }
        
        .privacy-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
            backdrop-filter: blur(10px);
        }
        
        .privacy-public {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .privacy-private {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .playlist-info {
            padding: 20px;
        }
        
        .playlist-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            gap: 10px;
        }
        
        .playlist-title {
            font-size: 1.2em;
            font-weight: 600;
            margin: 0;
            flex-grow: 1;
        }
        
        .playlist-title a {
            color: var(--text-color);
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        
        .playlist-title a:hover {
            color: var(--nav-link);
        }
        
        .creator-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 10px;
            background-color: var(--current-accordion-header-bg);
            border-radius: 8px;
        }
        
        .creator-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .creator-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--nav-link) 0%, var(--button-bg) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        
        .creator-details {
            flex-grow: 1;
        }
        
        .creator-name {
            font-weight: 600;
            color: var(--current-accordion-header-text);
            margin: 0;
            font-size: 0.9em;
        }
        
        .creator-username {
            color: var(--nav-link);
            font-size: 0.8em;
            margin: 0;
        }
        
        .playlist-meta {
            color: var(--text-color);
            font-size: 0.85em;
            opacity: 0.8;
            margin-bottom: 15px;
        }
        
        .playlist-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: linear-gradient(135deg, var(--current-accordion-header-bg) 0%, var(--current-border-color) 100%);
            transform: translateY(-1px);
        }
        
        .pagination .current {
            background: linear-gradient(135deg, var(--button-bg) 0%, var(--nav-link) 100%);
            color: var(--button-text);
            border-color: var(--button-bg);
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-color);
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: var(--current-accordion-header-text);
            font-size: 1.5em;
        }
        
        .empty-state p {
            margin-bottom: 25px;
            font-size: 1.1em;
            opacity: 0.8;
        }
        
        .success-message,
        .error-message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .results-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .results-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quick-filters {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <div class="search-container">
            <div class="search-header">
                <h2>🔍 Ανακαλύψτε Περιεχόμενο</h2>
                <p>Αναζητήστε λίστες, δημιουργούς και περιεχόμενο που σας ενδιαφέρει!</p>
            </div>

            <?php if ($success_message): ?>
                <div class="success-message">✅ <?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="error-message">❌ <?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Φόρμα Αναζήτησης -->
            <form class="search-form" method="GET">
                <div class="quick-filters">
                    <button type="button" class="filter-btn <?php echo $show_public ? 'active' : ''; ?>" 
                            onclick="togglePublicFilter()">
                        🌐 Δημόσιες Λίστες
                    </button>
                    <button type="button" class="filter-btn" onclick="clearSearch()">
                        🔄 Καθαρισμός
                    </button>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="q">🎵 Αναζήτηση στις λίστες/βίντεο:</label>
                        <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="π.χ. μουσική, comedy, tutorial...">
                    </div>
                    
                    <div class="form-group">
                        <label for="creator">👤 Δημιουργός:</label>
                        <input type="text" id="creator" name="creator" value="<?php echo htmlspecialchars($search_creator); ?>" 
                               placeholder="π.χ. Γιάννης, john123...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_from">📅 Από ημερομηνία:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">📅 Έως ημερομηνία:</label>
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
                                <option value="12" <?php echo $per_page == 12 ? 'selected' : ''; ?>>12</option>
                                <option value="24" <?php echo $per_page == 24 ? 'selected' : ''; ?>>24</option>
                                <option value="48" <?php echo $per_page == 48 ? 'selected' : ''; ?>>48</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <h3>😔 Δεν βρέθηκαν αποτελέσματα</h3>
                        <p>Δοκιμάστε να αλλάξετε τα κριτήρια αναζήτησης ή να καθαρίσετε τα φίλτρα.</p>
                        <button onclick="clearSearch()" class="btn btn-primary">🔄 Καθαρισμός Φίλτρων</button>
                    </div>
                <?php else: ?>
                    <div class="results-grid">
                        <?php foreach ($results as $playlist): ?>
                            <div class="playlist-card">
                                <div class="playlist-thumbnail">
                                    🎵
                                    <div class="playlist-overlay">
                                        <button class="play-button" onclick="playPlaylist(<?php echo $playlist['playlist_id']; ?>)">
                                            ▶️
                                        </button>
                                    </div>
                                    <div class="privacy-badge <?php echo $playlist['is_public'] ? 'privacy-public' : 'privacy-private'; ?>">
                                        <?php echo $playlist['is_public'] ? 'Δημόσια' : 'Ιδιωτική'; ?>
                                    </div>
                                </div>
                                
                                <div class="playlist-info">
                                    <div class="playlist-header">
                                        <h3 class="playlist-title">
                                            <a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>">
                                                <?php echo htmlspecialchars($playlist['playlist_name']); ?>
                                            </a>
                                        </h3>
                                    </div>
                                    
                                    <div class="creator-section">
                                        <div class="creator-info">
                                            <div class="creator-avatar">
                                                <?php echo strtoupper(substr($playlist['first_name'], 0, 1) . substr($playlist['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="creator-details">
                                                <p class="creator-name"><?php echo htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']); ?></p>
                                                <p class="creator-username">@<?php echo htmlspecialchars($playlist['username']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $playlist['user_id']): ?>
                                            <?php if (isset($follow_status[$playlist['user_id']])): ?>
                                                <a href="follow_user.php?user_id=<?php echo $playlist['user_id']; ?>&action=unfollow&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                                   class="btn btn-unfollow">
                                                    ❌ Unfollow
                                                </a>
                                            <?php else: ?>
                                                <a href="follow_user.php?user_id=<?php echo $playlist['user_id']; ?>&action=follow&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                                   class="btn btn-follow">
                                                    ➕ Follow
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="playlist-meta">
                                        <strong>📹 Βίντεο:</strong> <?php echo $playlist['video_count']; ?> • 
                                        <strong>👥 Followers:</strong> <?php echo $playlist['followers_count']; ?> • 
                                        <strong>📅 Δημιουργήθηκε:</strong> <?php 
                                            $date = new DateTime($playlist['creation_date']);
                                            echo $date->format('d/m/Y'); 
                                        ?>
                                    </div>
                                    
                                    <div class="playlist-actions">
                                        <a href="view_playlist_items.php?playlist_id=<?php echo $playlist['playlist_id']; ?>" 
                                           class="btn btn-primary">👁️ Προβολή Λίστας</a>
                                    </div>
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
                    <p>Χρησιμοποιήστε τη φόρμα παραπάνω για να αναζητήσετε λίστες περιεχομένου και δημιουργούς.</p>
                    <button onclick="showPublicPlaylists()" class="btn btn-primary">🌐 Δείτε όλες τις δημόσιες λίστες</button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'php/partials/footer.php'; ?>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
    <script src="js/follow_system.js"></script>
    <script>
        function togglePublicFilter() {
            const currentUrl = new URL(window.location);
            const isPublic = currentUrl.searchParams.get('show_public') === 'true';
            
            if (isPublic) {
                currentUrl.searchParams.delete('show_public');
            } else {
                currentUrl.searchParams.set('show_public', 'true');
            }
            currentUrl.searchParams.delete('page');
            
            window.location.href = currentUrl.toString();
        }
        
        function showPublicPlaylists() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('show_public', 'true');
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        }
        
        function clearSearch() {
            window.location.href = 'search_content.php';
        }
        
        function changePerPage(newPerPage) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('per_page', newPerPage);
            currentUrl.searchParams.set('page', '1');
            window.location.href = currentUrl.toString();
        }
        
        function playPlaylist(playlistId) {
            window.location.href = `view_playlist_items.php?playlist_id=${playlistId}&autoplay=1`;
        }
        
        // Animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.playlist-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>