<?php
// view_playlist_items.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'php/db_connect.php';

$playlist_id = $_GET['playlist_id'] ?? 0;
$playlist = null;
$playlist_items = [];
$error_message = '';
$is_owner = false;

if (!$playlist_id) {
    header('Location: index.php');
    exit;
}

try {
    // Λήψη πληροφοριών λίστας
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.first_name, u.last_name 
        FROM playlists p 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.playlist_id = ?
    ");
    $stmt->execute([$playlist_id]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playlist) {
        header('Location: index.php');
        exit;
    }

    // Έλεγχος δικαιωμάτων πρόσβασης
    $is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $playlist['user_id'];
    
    if (!$playlist['is_public'] && !$is_owner) {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php?message=login_required_to_view_playlist');
            exit;
        } else {
            header('Location: index.php');
            exit;
        }
    }

    // Λήψη περιεχομένων λίστας
    $stmt = $pdo->prepare("
        SELECT * FROM playlist_items 
        WHERE playlist_id = ? 
        ORDER BY added_date DESC
    ");
    $stmt->execute([$playlist_id]);
    $playlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Σφάλμα κατά την ανάκτηση της λίστας: " . $e->getMessage();
}

// Διαχείριση διαγραφής βίντεο
if (isset($_POST['delete_video']) && $is_owner) {
    try {
        $stmt = $pdo->prepare("DELETE FROM playlist_items WHERE item_id = ? AND playlist_id = ?");
        $stmt->execute([$_POST['item_id'], $playlist_id]);
        
        header("Location: view_playlist_items.php?playlist_id=" . $playlist_id);
        exit;
    } catch (PDOException $e) {
        $error_message = "Σφάλμα κατά τη διαγραφή του βίντεο: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $playlist ? htmlspecialchars($playlist['playlist_name']) : 'Λίστα'; ?> - Ροή μου</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .playlist-header {
            background-color: var(--current-accordion-header-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .playlist-title {
            margin: 0 0 10px 0;
            color: var(--current-accordion-header-text);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .privacy-badge {
            font-size: 0.8em;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: normal;
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
            margin-bottom: 15px;
        }
        
        .playlist-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
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
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .video-card {
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: var(--current-accordion-content-bg);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .video-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .video-thumbnail {
            position: relative;
            width: 100%;
            height: 200px;
            background-color: #000;
            overflow: hidden;
        }
        
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .video-thumbnail .play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 0, 0, 0.8);
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .video-thumbnail .play-overlay:hover {
            background-color: rgba(255, 0, 0, 1);
        }
        
        .video-info {
            padding: 15px;
        }
        
        .video-title {
            font-weight: bold;
            margin: 0 0 8px 0;
            color: var(--text-color);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .video-meta {
            color: var(--text-color);
            font-size: 0.8em;
            opacity: 0.8;
            margin-bottom: 10px;
        }
        
        .video-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 0.8em;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-color);
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: var(--current-accordion-header-text);
        }
        
        .error-message {
            color: red;
            background-color: #ffe6e6;
            border: 1px solid red;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .back-link {
            margin-bottom: 20px;
        }
        
        .back-link a {
            color: var(--nav-link);
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: var(--nav-link-hover);
        }
        
        /* YouTube embed styling */
        .video-player {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin-bottom: 15px;
        }
        
        .video-player iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <div class="back-link">
            <a href="<?php echo $is_owner ? 'my_playlists.php' : 'search_content.php'; ?>">
                ← Επιστροφή στις λίστες
            </a>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($playlist): ?>
            <div class="playlist-header">
                <h2 class="playlist-title">
                    <?php echo htmlspecialchars($playlist['playlist_name']); ?>
                    <span class="privacy-badge <?php echo $playlist['is_public'] ? 'privacy-public' : 'privacy-private'; ?>">
                        <?php echo $playlist['is_public'] ? 'Δημόσια' : 'Ιδιωτική'; ?>
                    </span>
                </h2>
                
                <div class="playlist-meta">
                    <p><strong>Δημιουργός:</strong> <?php echo htmlspecialchars($playlist['first_name'] . ' ' . $playlist['last_name']); ?> (@<?php echo htmlspecialchars($playlist['username']); ?>)</p>
                    <p><strong>Δημιουργήθηκε:</strong> <?php 
                        $date = new DateTime($playlist['creation_date']);
                        echo $date->format('d/m/Y H:i'); 
                    ?></p>
                    <p><strong>Περιεχόμενα:</strong> <?php echo count($playlist_items); ?> βίντεο</p>
                </div>
                
                <?php if ($is_owner): ?>
                    <div class="playlist-actions">
                        <a href="add_video.php?playlist_id=<?php echo $playlist_id; ?>" class="btn btn-primary">+ Προσθήκη Βίντεο</a>
                        <a href="edit_playlist.php?playlist_id=<?php echo $playlist_id; ?>" class="btn btn-secondary">Επεξεργασία Λίστας</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($playlist_items)): ?>
                <div class="empty-state">
                    <h3>Η λίστα είναι κενή</h3>
                    <?php if ($is_owner): ?>
                        <p>Προσθέστε βίντεο από το YouTube για να ξεκινήσετε!</p>
                        <a href="add_video.php?playlist_id=<?php echo $playlist_id; ?>" class="btn btn-primary">+ Προσθήκη Πρώτου Βίντεο</a>
                    <?php else: ?>
                        <p>Ο δημιουργός δεν έχει προσθέσει ακόμα περιεχόμενο σε αυτή τη λίστα.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="video-grid">
                    <?php foreach ($playlist_items as $item): ?>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <?php if ($item['video_thumbnail']): ?>
                                    <img src="<?php echo htmlspecialchars($item['video_thumbnail']); ?>" alt="Thumbnail">
                                <?php endif; ?>
                                <div class="play-overlay" onclick="playVideo('<?php echo htmlspecialchars($item['video_id']); ?>')">
                                    ▶
                                </div>
                            </div>
                            
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($item['video_title']); ?></h3>
                                <div class="video-meta">
                                    Προστέθηκε: <?php 
                                        $date = new DateTime($item['added_date']);
                                        echo $date->format('d/m/Y H:i'); 
                                    ?>
                                </div>
                                
                                <div class="video-actions">
                                    <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($item['video_id']); ?>" 
                                       target="_blank" class="btn btn-primary btn-small">Προβολή στο YouTube</a>
                                    
                                    <?php if ($is_owner): ?>
                                        <form style="display: inline;" method="POST" 
                                              onsubmit="return confirm('Είστε σίγουροι ότι θέλετε να αφαιρέσετε αυτό το βίντεο;');">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_video" class="btn btn-danger btn-small">Αφαίρεση</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Video Player Modal -->
    <div id="videoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; max-width: 800px;">
            <div style="position: relative;">
                <button onclick="closeVideo()" style="position: absolute; top: -40px; right: 0; background: white; border: none; padding: 10px; border-radius: 50%; cursor: pointer; font-size: 20px;">×</button>
                <div class="video-player">
                    <iframe id="videoFrame" src="" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>

    <?php include 'php/partials/footer.php'; ?>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
    <script>
        function playVideo(videoId) {
            const modal = document.getElementById('videoModal');
            const iframe = document.getElementById('videoFrame');
            
            iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
            modal.style.display = 'block';
            
            // Prevent body scrolling
            document.body.style.overflow = 'hidden';
        }
        
        function closeVideo() {
            const modal = document.getElementById('videoModal');
            const iframe = document.getElementById('videoFrame');
            
            iframe.src = '';
            modal.style.display = 'none';
            
            // Restore body scrolling
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVideo();
            }
        });
        
        // Close modal on click outside
        document.getElementById('videoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeVideo();
            }
        });
    </script>
</body>
</html>