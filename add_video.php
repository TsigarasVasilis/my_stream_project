<?php
// add_video.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php?message=login_required_for_Youtube');
    exit;
}

require_once 'php/db_connect.php';

$playlist_id = $_GET['playlist_id'] ?? 0;
$playlist = null;
$search_results = [];
$search_query = '';
$errors = [];
$success_message = '';

if (!$playlist_id) {
    header('Location: my_playlists.php');
    exit;
}

// Έλεγχος αν η λίστα ανήκει στον τρέχοντα χρήστη
try {
    $stmt = $pdo->prepare("SELECT * FROM playlists WHERE playlist_id = ? AND user_id = ?");
    $stmt->execute([$playlist_id, $_SESSION['user_id']]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$playlist) {
        header('Location: my_playlists.php');
        exit;
    }
} catch (PDOException $e) {
    $errors['db_error'] = "Σφάλμα κατά την ανάκτηση της λίστας: " . $e->getMessage();
}

// Προσθήκη βίντεο στη λίστα
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_video'])) {
    $video_id = trim($_POST['video_id'] ?? '');
    $video_title = trim($_POST['video_title'] ?? '');
    $video_thumbnail = trim($_POST['video_thumbnail'] ?? '');
    
    if (empty($video_id)) {
        $errors['add_video'] = "Το ID του βίντεο είναι υποχρεωτικό.";
    } elseif (empty($video_title)) {
        $errors['add_video'] = "Ο τίτλος του βίντεο είναι υποχρεωτικός.";
    } else {
        // Έλεγχος αν το βίντεο υπάρχει ήδη στη λίστα
        try {
            $stmt = $pdo->prepare("SELECT item_id FROM playlist_items WHERE playlist_id = ? AND video_id = ?");
            $stmt->execute([$playlist_id, $video_id]);
            
            if ($stmt->fetch()) {
                $errors['add_video'] = "Αυτό το βίντεο υπάρχει ήδη στη λίστα.";
            } else {
                // Προσθήκη βίντεο στη λίστα
                $stmt = $pdo->prepare("INSERT INTO playlist_items (playlist_id, video_id, video_title, video_thumbnail) VALUES (?, ?, ?, ?)");
                $stmt->execute([$playlist_id, $video_id, $video_title, $video_thumbnail]);
                
                $success_message = "Το βίντεο προστέθηκε με επιτυχία στη λίστα!";
            }
        } catch (PDOException $e) {
            $errors['add_video'] = "Σφάλμα κατά την προσθήκη του βίντεο: " . $e->getMessage();
        }
    }
}

// Αναζήτηση στο YouTube (απλή υλοποίηση)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search_query = trim($_GET['q'] ?? '');
    
    if (!empty($search_query)) {
        // Για την εργασία, θα χρησιμοποιήσουμε YouTube Data API
        // Εδώ είναι μια απλή υλοποίηση - στην πραγματικότητα θα πρέπει να χρησιμοποιήσετε το YouTube Data API
        $search_results = searchYouTube($search_query);
    }
}

// Συνάρτηση αναζήτησης YouTube (χρησιμοποιεί πραγματικό API αν είναι διαθέσιμο)
function searchYouTube($query) {
    // Έλεγχος αν υπάρχει το YouTube API configuration
    if (file_exists('youtube_config.php')) {
        require_once 'youtube_config.php';
        
        if (function_exists('isYouTubeAPIConfigured') && isYouTubeAPIConfigured()) {
            try {
                return searchYouTubeAPI($query, 20);
            } catch (Exception $e) {
                // Fallback to demo data if API fails
                error_log("YouTube API Error: " . $e->getMessage());
            }
        }
    }
    
    // Fallback: Demo δεδομένα για development/testing
    $fake_results = [
        [
            'id' => 'dQw4w9WgXcQ',
            'title' => 'Rick Astley - Never Gonna Give You Up (Official Video)',
            'thumbnail' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/mqdefault.jpg',
            'description' => 'The official video for "Never Gonna Give You Up" by Rick Astley',
            'channel' => 'RickAstleyVEVO'
        ],
        [
            'id' => 'ZZ5LpwO-An4',
            'title' => 'HEYYEYAAEYAAAEYAEYAA',
            'thumbnail' => 'https://img.youtube.com/vi/ZZ5LpwO-An4/mqdefault.jpg', 
            'description' => 'He-Man version',
            'channel' => 'FabulousFerd'
        ],
        [
            'id' => 'kffacxfA7G4',
            'title' => 'Baby Shark Dance | Sing and Dance! | @Baby Shark Official | PINKFONG Songs for Children',
            'thumbnail' => 'https://img.youtube.com/vi/kffacxfA7G4/mqdefault.jpg',
            'description' => 'Baby Shark Dance! Sing and Dance!',
            'channel' => 'PINKFONG Baby Shark - Kids\' Songs & Stories'
        ]
    ];
    
    // Filter results based on search query
    return array_filter($fake_results, function($video) use ($query) {
        return stripos($video['title'], $query) !== false || 
               stripos($video['description'], $query) !== false ||
               stripos($video['channel'], $query) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Βίντεο - <?php echo htmlspecialchars($playlist['playlist_name'] ?? 'Λίστα'); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        .add-video-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .playlist-info {
            background-color: var(--current-accordion-header-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .search-section {
            background-color: var(--current-accordion-content-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--current-border-color);
            border-radius: 4px;
            font-size: 1em;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--nav-link);
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
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
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .search-results {
            margin-top: 25px;
        }
        
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
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
            height: 180px;
            background-color: #000;
        }
        
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        .video-description {
            color: var(--text-color);
            font-size: 0.85em;
            opacity: 0.8;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .video-actions {
            display: flex;
            gap: 8px;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.9em;
        }
        
        .manual-add {
            background-color: var(--current-accordion-header-bg);
            border: 1px solid var(--current-border-color);
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--current-accordion-header-text);
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--current-border-color);
            border-radius: 4px;
            background-color: var(--bg-color);
            color: var(--text-color);
            box-sizing: border-box;
        }
        
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .success-message {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
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
        
        .api-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .api-notice h4 {
            margin-top: 0;
            color: #856404;
        }
        
        .empty-search {
            text-align: center;
            color: var(--text-color);
            opacity: 0.7;
            padding: 40px;
        }
    </style>
</head>
<body>
    <?php include 'php/partials/header.php'; ?>

    <main>
        <div class="add-video-container">
            <div class="back-link">
                <a href="view_playlist_items.php?playlist_id=<?php echo $playlist_id; ?>">← Επιστροφή στη Λίστα</a>
            </div>

            <?php if ($playlist): ?>
                <div class="playlist-info">
                    <h2>🎥 Προσθήκη Βίντεο στη Λίστα</h2>
                    <p><strong>Λίστα:</strong> <?php echo htmlspecialchars($playlist['playlist_name']); ?></p>
                    <p><strong>Τύπος:</strong> <?php echo $playlist['is_public'] ? 'Δημόσια' : 'Ιδιωτική'; ?></p>
                </div>

                <?php if ($success_message): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $error): ?>
                            <?php echo htmlspecialchars($error); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="api-notice">
                    <h4>📋 Σημείωση για την Αναζήτηση YouTube</h4>
                    <?php if (file_exists('youtube_config.php')): ?>
                        <?php 
                        require_once 'youtube_config.php';
                        if (function_exists('isYouTubeAPIConfigured') && isYouTubeAPIConfigured()): ?>
                            <p>✅ Η αναζήτηση συνδέεται με το πραγματικό YouTube Data API v3.</p>
                        <?php else: ?>
                            <p>⚠️ Το YouTube API δεν είναι ρυθμισμένο. Χρησιμοποιούνται demo δεδομένα.</p>
                            <p>Για πραγματική αναζήτηση, ρυθμίστε το <code>youtube_config.php</code> με το API key σας.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>⚠️ Αυτή είναι μια demo έκδοση με στατικά δεδομένα.</p>
                        <p>Για πραγματική αναζήτηση YouTube, αντιγράψτε το <code>youtube_config.php.sample</code> ως <code>youtube_config.php</code> και ρυθμίστε το με τα στοιχεία του YouTube Data API v3.</p>
                    <?php endif; ?>
                </div>

                <!-- Αναζήτηση YouTube -->
                <div class="search-section">
                    <h3>🔍 Αναζήτηση στο YouTube</h3>
                    <form method="GET" class="search-form">
                        <input type="hidden" name="playlist_id" value="<?php echo $playlist_id; ?>">
                        <input type="text" name="q" class="search-input" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Αναζητήστε βίντεο στο YouTube..." required>
                        <button type="submit" name="search" class="btn btn-primary">🔍 Αναζήτηση</button>
                    </form>

                    <?php if (!empty($search_results)): ?>
                        <div class="search-results">
                            <h4>Αποτελέσματα Αναζήτησης για: "<?php echo htmlspecialchars($search_query); ?>"</h4>
                            <div class="video-grid">
                                <?php foreach ($search_results as $video): ?>
                                    <div class="video-card">
                                        <div class="video-thumbnail">
                                            <img src="<?php echo htmlspecialchars($video['thumbnail']); ?>" 
                                                 alt="<?php echo htmlspecialchars($video['title']); ?>">
                                        </div>
                                        <div class="video-info">
                                            <h4 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h4>
                                            <?php if (!empty($video['description'])): ?>
                                                <p class="video-description"><?php echo htmlspecialchars($video['description']); ?></p>
                                            <?php endif; ?>
                                            <div class="video-actions">
                                                <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['id']); ?>" 
                                                   target="_blank" class="btn btn-secondary btn-small">Προβολή</a>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($video['id']); ?>">
                                                    <input type="hidden" name="video_title" value="<?php echo htmlspecialchars($video['title']); ?>">
                                                    <input type="hidden" name="video_thumbnail" value="<?php echo htmlspecialchars($video['thumbnail']); ?>">
                                                    <button type="submit" name="add_video" class="btn btn-success btn-small">+ Προσθήκη</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif (isset($_GET['search'])): ?>
                        <div class="empty-search">
                            <p>Δεν βρέθηκαν αποτελέσματα για "<?php echo htmlspecialchars($search_query); ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Χειροκίνητη Προσθήκη -->
                <div class="manual-add">
                    <h3>➕ Χειροκίνητη Προσθήκη Βίντεο</h3>
                    <p>Αν γνωρίζετε το YouTube URL ή Video ID, μπορείτε να προσθέσετε το βίντεο απευθείας:</p>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="manual_video_id">YouTube Video ID ή URL *</label>
                            <input type="text" id="manual_video_id" name="video_id" 
                                   placeholder="π.χ. dQw4w9WgXcQ ή https://www.youtube.com/watch?v=dQw4w9WgXcQ" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="manual_video_title">Τίτλος Βίντεο *</label>
                            <input type="text" id="manual_video_title" name="video_title" 
                                   placeholder="Εισάγετε τον τίτλο του βίντεο" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="manual_video_thumbnail">Thumbnail URL (προαιρετικό)</label>
                            <input type="url" id="manual_video_thumbnail" name="video_thumbnail" 
                                   placeholder="https://img.youtube.com/vi/VIDEO_ID/mqdefault.jpg">
                        </div>
                        
                        <button type="submit" name="add_video" class="btn btn-success">➕ Προσθήκη Βίντεο</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'php/partials/footer.php'; ?>

    <script src="js/theme_switcher.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Auto-extract video ID from YouTube URL
        document.getElementById('manual_video_id').addEventListener('input', function() {
            let input = this.value.trim();
            let videoId = '';
            
            // Extract video ID from various YouTube URL formats
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
                /^([a-zA-Z0-9_-]{11})$/ // Direct video ID
            ];
            
            for (let pattern of patterns) {
                const match = input.match(pattern);
                if (match) {
                    videoId = match[1];
                    break;
                }
            }
            
            if (videoId && videoId !== input) {
                this.value = videoId;
                
                // Auto-generate thumbnail URL
                const thumbnailInput = document.getElementById('manual_video_thumbnail');
                if (!thumbnailInput.value) {
                    thumbnailInput.value = `https://img.youtube.com/vi/${videoId}/mqdefault.jpg`;
                }
            }
        });
        
        // Auto-focus search input on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>