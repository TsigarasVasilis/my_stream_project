<?php

// YouTube Data API Key (για δημόσιες αναζητήσεις)
define('YOUTUBE_API_KEY', 'YOUR_YOUTUBE_API_KEY_HERE');

// OAuth 2.0 Credentials (για authenticated requests)
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', 'http://localhost/your-project/youtube_callback.php');

// API Endpoints
define('YOUTUBE_SEARCH_URL', 'https://www.googleapis.com/youtube/v3/search');
define('YOUTUBE_VIDEOS_URL', 'https://www.googleapis.com/youtube/v3/videos');
define('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

// Συνάρτηση αναζήτησης στο YouTube με API
function searchYouTubeAPI($query, $maxResults = 20) {
    if (!defined('YOUTUBE_API_KEY') || YOUTUBE_API_KEY === 'YOUR_YOUTUBE_API_KEY_HERE') {
        throw new Exception('YouTube API Key is not configured');
    }
    
    $params = [
        'part' => 'snippet',
        'q' => $query,
        'type' => 'video',
        'maxResults' => $maxResults,
        'key' => YOUTUBE_API_KEY,
        'order' => 'relevance',
        'videoEmbeddable' => 'true'
    ];
    
    $url = YOUTUBE_SEARCH_URL . '?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Roimi-YouTube-Search/1.0'
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch data from YouTube API');
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('YouTube API Error: ' . $data['error']['message']);
    }
    
    $results = [];
    if (isset($data['items'])) {
        foreach ($data['items'] as $item) {
            $results[] = [
                'id' => $item['id']['videoId'],
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channel' => $item['snippet']['channelTitle'],
                'published' => $item['snippet']['publishedAt']
            ];
        }
    }
    
    return $results;
}

// Συνάρτηση για λήψη λεπτομερειών βίντεο
function getVideoDetails($videoId) {
    if (!defined('YOUTUBE_API_KEY') || YOUTUBE_API_KEY === 'YOUR_YOUTUBE_API_KEY_HERE') {
        throw new Exception('YouTube API Key is not configured');
    }
    
    $params = [
        'part' => 'snippet,contentDetails,statistics',
        'id' => $videoId,
        'key' => YOUTUBE_API_KEY
    ];
    
    $url = YOUTUBE_VIDEOS_URL . '?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Roimi-YouTube-Details/1.0'
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to fetch video details from YouTube API');
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception('YouTube API Error: ' . $data['error']['message']);
    }
    
    if (!isset($data['items'][0])) {
        return null;
    }
    
    $video = $data['items'][0];
    
    return [
        'id' => $video['id'],
        'title' => $video['snippet']['title'],
        'description' => $video['snippet']['description'],
        'channel' => $video['snippet']['channelTitle'],
        'thumbnail' => $video['snippet']['thumbnails']['medium']['url'] ?? $video['snippet']['thumbnails']['default']['url'],
        'duration' => $video['contentDetails']['duration'] ?? '',
        'view_count' => $video['statistics']['viewCount'] ?? 0,
        'like_count' => $video['statistics']['likeCount'] ?? 0,
        'published' => $video['snippet']['publishedAt']
    ];
}

// OAuth URL Generation (για μελλοντική χρήση)
function getGoogleOAuthURL() {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
        'response_type' => 'code',
        'access_type' => 'offline',
        'approval_prompt' => 'force'
    ];
    
    return GOOGLE_OAUTH_URL . '?' . http_build_query($params);
}

// Έλεγχος αν το API είναι διαμορφωμένο
function isYouTubeAPIConfigured() {
    return defined('YOUTUBE_API_KEY') && 
           YOUTUBE_API_KEY !== 'YOUR_YOUTUBE_API_KEY_HERE' &&
           !empty(YOUTUBE_API_KEY);
}

?>