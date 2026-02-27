<?php
// includes/functions.php
echo "<!-- DEBUG: Loading functions.php -->";
require_once __DIR__ . '/db.php';

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Generate slug from string
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Get categories by type for navigation
function getCategoriesByType($type) {
    global $conn;
    
    $type = mysqli_real_escape_string($conn, $type);
    $query = "SELECT * FROM categories WHERE type = '$type' ORDER BY name ASC";
    $result = mysqli_query($conn, $query);
    
    $categories = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }
    }
    return $categories;
}

// Get image URL
function getImageUrl($image, $type = 'content') {
    global $SITE_URL;
    
    if (empty($image)) {
        return $SITE_URL . '/assets/images/techinhausa-about.jpg';
    }
    
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }
    
    $image = ltrim($image, '/');
    
    // Map types to folders
    $folders = [
        'video' => 'videos',
        'blog' => 'blog',
        'news' => 'news',
        'research' => 'research',
        'media' => 'media',
        'sponsor' => 'sponsors',
        'creator' => 'creator',
        'content' => 'content'
    ];
    
    $folder = $folders[$type] ?? 'content';
    
    // If image already includes uploads/ path
    if (strpos($image, 'uploads/') === 0) {
        return $SITE_URL . '/' . $image;
    }
    
    return $SITE_URL . '/uploads/' . $folder . '/' . $image;
}

// Format date in Hausa
function formatDateHausa($date) {
    if (empty($date)) return 'Ba kwanan wata ba';
    
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Ba kwanan wata ba';
    
    $months = [
        'Janairu', 'Fabrairu', 'Maris', 'Afrilu', 'Mayu', 'Yuni',
        'Yuli', 'Agusta', 'Satumba', 'Oktoba', 'Nuwamba', 'Disamba'
    ];
    
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day $month, $year";
}

// Truncate text
function truncateText($text, $length = 100) {
    if (empty($text)) return '';
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// Get YouTube video ID from URL
function getYouTubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

// Get featured content for hero slider
function getFeaturedContent($limit = 5) {
    global $conn;
    
    $featuredItems = [];
    $limit = (int)$limit;
    
    // Get featured videos
    $videos = mysqli_query($conn, "
        SELECT id, title, slug, 'video' as content_type, excerpt, featured_image, author, published_at 
        FROM videos 
        WHERE is_featured = 1 AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($videos && mysqli_num_rows($videos) > 0) {
        while ($row = mysqli_fetch_assoc($videos)) {
            $featuredItems[] = $row;
        }
    }
    
    // Get featured blog posts
    $blogs = mysqli_query($conn, "
        SELECT id, title, slug, 'blog' as content_type, excerpt, featured_image, author, published_at 
        FROM blog_posts 
        WHERE is_featured = 1 AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($blogs && mysqli_num_rows($blogs) > 0) {
        while ($row = mysqli_fetch_assoc($blogs)) {
            $featuredItems[] = $row;
        }
    }
    
    // Get featured news
    $news = mysqli_query($conn, "
        SELECT id, title, slug, 'news' as content_type, excerpt, featured_image, author, published_at 
        FROM news 
        WHERE is_featured = 1 AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($news && mysqli_num_rows($news) > 0) {
        while ($row = mysqli_fetch_assoc($news)) {
            $featuredItems[] = $row;
        }
    }
    
    // Get featured research
    $research = mysqli_query($conn, "
        SELECT id, title, slug, 'research' as content_type, excerpt, featured_image, author, published_at 
        FROM research 
        WHERE is_featured = 1 AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($research && mysqli_num_rows($research) > 0) {
        while ($row = mysqli_fetch_assoc($research)) {
            $featuredItems[] = $row;
        }
    }
    
    // Sort by date
    usort($featuredItems, function($a, $b) {
        return strtotime($b['published_at']) - strtotime($a['published_at']);
    });
    
    return array_slice($featuredItems, 0, $limit);
}

// Get default hero slides
function getDefaultHeroSlides() {
    global $SITE_URL;
    
    return [
        [
            'id' => 0,
            'title' => 'TechInHausa - Koyan Tech cikin Hausa',
            'slug' => '#',
            'content_type' => 'default',
            'excerpt' => 'Ku zo ku koyi fasahar zamani da AI cikin harshenku na asali. MalamIromba yana koyar da ku.',
            'featured_image' => '/assets/images/hero-tech-1.jpg',
            'author' => 'MalamIromba',
            'published_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 1,
            'title' => 'Hausa AI - Fahimtar Artificial Intelligence',
            'slug' => '#',
            'content_type' => 'default',
            'excerpt' => 'Koyi yadda AI ke aiki da yadda zaka iya amfani da shi a rayuwar ka.',
            'featured_image' => '/assets/images/hero-ai-2.jpg',
            'author' => 'MalamIromba',
            'published_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'title' => 'Shirye-shiryen Kwamfuta ga Sababbi',
            'slug' => '#',
            'content_type' => 'default',
            'excerpt' => 'Fara koyon shirye-shiryen kwamfuta daga tushe. PHP, JavaScript, Python da sauransu.',
            'featured_image' => '/assets/images/hero-coding-3.jpg',
            'author' => 'MalamIromba',
            'published_at' => date('Y-m-d H:i:s')
        ]
    ];
}

// Get videos
function getVideos($type = 'latest', $limit = 4) {
    global $conn;
    
    $limit = (int)$limit;
    
    if ($type === 'popular') {
        $query = "SELECT * FROM videos WHERE is_published = 1 ORDER BY views DESC LIMIT $limit";
    } else {
        $query = "SELECT * FROM videos WHERE is_published = 1 ORDER BY published_at DESC LIMIT $limit";
    }
    
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get blog posts
function getBlogPosts($limit = 4) {
    global $conn;
    
    $limit = (int)$limit;
    $query = "SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get news
function getNews($limit = 4) {
    global $conn;
    
    $limit = (int)$limit;
    $query = "SELECT * FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get research
function getResearch($limit = 4) {
    global $conn;
    
    $limit = (int)$limit;
    $query = "SELECT * FROM research WHERE is_published = 1 ORDER BY published_at DESC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get content by author
function getContentByAuthor($author, $limit = 6) {
    global $conn;
    
    $author = mysqli_real_escape_string($conn, $author);
    $limit = (int)$limit;
    
    $allItems = [];
    
    // Get videos by author
    $videos = mysqli_query($conn, "
        SELECT id, title, slug, 'video' as content_type, excerpt, featured_image, author, published_at 
        FROM videos 
        WHERE author LIKE '%$author%' AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($videos && mysqli_num_rows($videos) > 0) {
        while ($row = mysqli_fetch_assoc($videos)) {
            $allItems[] = $row;
        }
    }
    
    // Get blog posts by author
    $blogs = mysqli_query($conn, "
        SELECT id, title, slug, 'blog' as content_type, excerpt, featured_image, author, published_at 
        FROM blog_posts 
        WHERE author LIKE '%$author%' AND is_published = 1 
        ORDER BY published_at DESC 
        LIMIT $limit
    ");
    
    if ($blogs && mysqli_num_rows($blogs) > 0) {
        while ($row = mysqli_fetch_assoc($blogs)) {
            $allItems[] = $row;
        }
    }
    
    // Sort by date
    usort($allItems, function($a, $b) {
        return strtotime($b['published_at']) - strtotime($a['published_at']);
    });
    
    return array_slice($allItems, 0, $limit);
}

// Get media features
function getMediaFeatures($limit = 6) {
    global $conn;
    
    $limit = (int)$limit;
    $query = "SELECT * FROM media_features WHERE is_active = 1 ORDER BY is_featured DESC, feature_date DESC, display_order ASC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get sponsors
function getSponsors($limit = 8) {
    global $conn;
    
    $limit = (int)$limit;
    $query = "SELECT * FROM sponsors WHERE is_active = 1 ORDER BY FIELD(sponsor_level, 'platinum', 'gold', 'silver', 'bronze'), display_order ASC LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $items = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Get about content
function getAboutContent() {
    return [
        'title' => 'TechInHausa | Hausa AI | MalamIromba',
        'subtitle' => 'Personal Education Initiative by Ibrahim Zubairu',
        'description' => 'TechInHausa is a pioneering initiative dedicated to making technology and AI education accessible to Hausa-speaking communities. Founded by Ibrahim Zubairu (MalamIromba), we provide high-quality tech content in the Hausa language, breaking down complex concepts into easy-to-understand lessons.',
        'mission' => 'To democratize tech education and empower Hausa speakers with the skills needed to thrive in the digital economy.',
        'vision' => 'A future where language is no barrier to accessing world-class technology education.',
        'image' => '/assets/images/about/ibrahim-zubairu.jpg',
        'years_active' => 2,
        'students_reached' => '50,000+',
        'content_items' => '200+',
        'partners' => '15+'
    ];
}

