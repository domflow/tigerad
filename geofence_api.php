<?php
require_once 'geofence_config.php';
require_once 'geofence_auth.php';
require_once 'geofence_location.php';
require_once 'geofence_payment.php';

// Set content type and security headers
header('Content-Type: application/json');
setSecurityHeaders();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$endpoint = isset($pathParts[2]) ? $pathParts[2] : '';

// Initialize database and services
$db = getDB();
$auth = new GeofenceAuth();
$locationService = new GeofenceLocationService();
$paymentService = new GeofencePaymentService();

// Route requests
try {
    switch ($endpoint) {
        case 'register-store-owner':
            handleStoreOwnerRegistration($db);
            break;
        case 'login':
            handleLogin($db, $auth);
            break;
        case 'verify-location':
            handleLocationVerification($db, $auth);
            break;
        case 'stores':
            handleStores($db, $auth, $method, $pathParts);
            break;
        case 'advertisements':
            handleAdvertisements($db, $auth, $method, $pathParts);
            break;
        case 'nearby-ads':
            handleNearbyAds($db, $locationService);
            break;
        case 'track-view':
            handleTrackView($db, $locationService);
            break;
        case 'credit-packages':
            handleCreditPackages($db);
            break;
        case 'purchase-credits':
            handlePurchaseCredits($db, $auth, $paymentService);
            break;
        case 'refund-credits':
            handleRefundCredits($db, $auth, $paymentService);
            break;
        case 'geofence-events':
            handleGeofenceEvents($db, $locationService);
            break;
        case 'upload-image':
            handleImageUpload($db, $auth);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => 'An unexpected error occurred']);
}

// Store Owner Registration
function handleStoreOwnerRegistration($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['business_name', 'owner_name', 'email', 'phone', 'password'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }

    // Validate phone format
    if (!preg_match('/^\+?[1-9]\d{1,14}$/', $data['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid phone format']);
        return;
    }

    // Validate password strength
    if (strlen($data['password']) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        return;
    }

    try {
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM store_owners WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // Insert store owner
        $stmt = $db->prepare("
            INSERT INTO store_owners (business_name, owner_name, email, phone, password_hash) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            sanitizeInput($data['business_name']),
            sanitizeInput($data['owner_name']),
            sanitizeInput($data['email']),
            sanitizeInput($data['phone']),
            $passwordHash
        ]);

        $ownerId = $db->lastInsertId();

        // Generate API key
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO api_keys (store_owner_id, api_key, key_name) VALUES (?, ?, 'Default')");
        $stmt->execute([$ownerId, $apiKey]);

        // Generate JWT token
        $auth = new GeofenceAuth();
        $token = $auth->generateToken($ownerId, 'store_owner');

        http_response_code(201);
        echo json_encode([
            'message' => 'Store owner registered successfully',
            'owner_id' => $ownerId,
            'token' => $token,
            'api_key' => $apiKey
        ]);

    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed']);
    }
}

// User Login
function handleLogin($db, $auth) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        return;
    }

    try {
        // Get store owner by email
        $stmt = $db->prepare("SELECT id, password_hash, verification_status FROM store_owners WHERE email = ? AND verification_status != 'rejected'");
        $stmt->execute([$data['email']]);
        $owner = $stmt->fetch();

        if (!$owner || !password_verify($data['password'], $owner['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        // Generate JWT token
        $token = $auth->generateToken($owner['id'], 'store_owner');

        echo json_encode([
            'message' => 'Login successful',
            'token' => $token,
            'owner_id' => $owner['id'],
            'verification_status' => $owner['verification_status']
        ]);

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Login failed']);
    }
}

// Store Management
function handleStores($db, $auth, $method, $pathParts) {
    $ownerId = $auth->requireAuth('store_owner');
    
    switch ($method) {
        case 'GET':
            if (count($pathParts) === 2) {
                // Get all stores for owner
                $stmt = $db->prepare("
                    SELECT s.*, 
                           ST_X(s.location) as latitude, 
                           ST_Y(s.location) as longitude,
                           cb.total_credits, 
                           cb.available_credits
                    FROM stores s
                    LEFT JOIN credit_balances cb ON s.id = cb.store_id
                    WHERE s.owner_id = ?
                    ORDER BY s.created_at DESC
                ");
                $stmt->execute([$ownerId]);
                $stores = $stmt->fetchAll();
                
                echo json_encode(['stores' => $stores]);
            } elseif (count($pathParts) === 3) {
                // Get specific store
                $storeId = (int)$pathParts[3];
                $stmt = $db->prepare("
                    SELECT s.*, 
                           ST_X(s.location) as latitude, 
                           ST_Y(s.location) as longitude,
                           cb.total_credits, 
                           cb.available_credits
                    FROM stores s
                    LEFT JOIN credit_balances cb ON s.id = cb.store_id
                    WHERE s.id = ? AND s.owner_id = ?
                ");
                $stmt->execute([$storeId, $ownerId]);
                $store = $stmt->fetch();
                
                if ($store) {
                    echo json_encode($store);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Store not found']);
                }
            }
            break;
            
        case 'POST':
            // Create new store
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['store_name', 'address', 'latitude', 'longitude'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate coordinates
            if (!validateLatitude($data['latitude']) || !validateLongitude($data['longitude'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid coordinates']);
                return;
            }

            try {
                // Create geofence circle (circular polygon)
                $radius = $data['geofence_radius_meters'] ?? DEFAULT_GEOFENCE_RADIUS_METERS;
                $circle = $locationService->createCirclePolygon($data['latitude'], $data['longitude'], $radius);
                
                $stmt = $db->prepare("
                    INSERT INTO stores (owner_id, store_name, address, latitude, longitude, location, geofence_circle, phone, website, category) 
                    VALUES (?, ?, ?, ?, ?, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ST_GeomFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ?, ?, ?)
                ");
                $stmt->execute([
                    $ownerId,
                    sanitizeInput($data['store_name']),
                    sanitizeInput($data['address']),
                    $data['latitude'],
                    $data['longitude'],
                    "POINT({$data['longitude']} {$data['latitude']})",
                    $circle,
                    sanitizeInput($data['phone'] ?? ''),
                    sanitizeInput($data['website'] ?? ''),
                    sanitizeInput($data['category'] ?? '')
                ]);

                $storeId = $db->lastInsertId();
                
                // Initialize credit balance
                $stmt = $db->prepare("INSERT INTO credit_balances (store_id, total_credits, available_credits) VALUES (?, 0, 0)");
                $stmt->execute([$storeId]);

                http_response_code(201);
                echo json_encode([
                    'message' => 'Store created successfully',
                    'store_id' => $storeId
                ]);

            } catch (PDOException $e) {
                error_log("Store creation error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Store creation failed']);
            }
            break;
            
        case 'PUT':
            // Update store
            $storeId = (int)$pathParts[3];
            $data = json_decode(file_get_contents('php://input'), true);
            
            try {
                $updateFields = [];
                $params = [$storeId, $ownerId];
                
                if (isset($data['store_name'])) {
                    $updateFields[] = "store_name = ?";
                    $params[] = sanitizeInput($data['store_name']);
                }
                if (isset($data['address'])) {
                    $updateFields[] = "address = ?";
                    $params[] = sanitizeInput($data['address']);
                }
                if (isset($data['phone'])) {
                    $updateFields[] = "phone = ?";
                    $params[] = sanitizeInput($data['phone']);
                }
                if (isset($data['website'])) {
                    $updateFields[] = "website = ?";
                    $params[] = sanitizeInput($data['website']);
                }
                if (isset($data['is_active'])) {
                    $updateFields[] = "is_active = ?";
                    $params[] = $data['is_active'] ? 1 : 0;
                }
                
                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No fields to update']);
                    return;
                }
                
                $sql = "UPDATE stores SET " . implode(', ', $updateFields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND owner_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Store updated successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Store not found']);
                }

            } catch (PDOException $e) {
                error_log("Store update error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Store update failed']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Advertisement Management
function handleAdvertisements($db, $auth, $method, $pathParts) {
    $ownerId = $auth->requireAuth('store_owner');
    
    switch ($method) {
        case 'GET':
            if (count($pathParts) === 2) {
                // Get advertisements for owner's stores
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $status = isset($_GET['status']) ? $_GET['status'] : null;
                
                $whereClause = "WHERE s.owner_id = ?";
                $params = [$ownerId];
                
                if ($status && in_array($status, ['active', 'paused', 'completed', 'expired'])) {
                    $whereClause .= " AND a.status = ?";
                    $params[] = $status;
                }
                
                // Get total count
                $countStmt = $db->prepare("SELECT COUNT(*) as total FROM advertisements a JOIN stores s ON a.store_id = s.id $whereClause");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Get advertisements
                $offset = ($page - 1) * $limit;
                $stmt = $db->prepare("
                    SELECT a.*, s.store_name, s.latitude, s.longitude
                    FROM advertisements a
                    JOIN stores s ON a.store_id = s.id
                    $whereClause
                    ORDER BY a.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $advertisements = $stmt->fetchAll();
                
                echo json_encode([
                    'advertisements' => $advertisements,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]);
            }
            break;
            
        case 'POST':
            // Create advertisement
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['store_id', 'title', 'images', 'credits'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate rate limiting for ad creation
            $rateLimitKey = "ad_creation:{$ownerId}";
            if (!checkRateLimit($rateLimitKey, 'ad_creation', null, 15, 1)) {
                http_response_code(429);
                echo json_encode(['error' => 'Rate limit exceeded. Please wait 15 minutes before creating another advertisement.']);
                return;
            }

            try {
                // Verify store ownership and credits
                $stmt = $db->prepare("
                    SELECT s.id, cb.available_credits 
                    FROM stores s
                    JOIN credit_balances cb ON s.id = cb.store_id
                    WHERE s.id = ? AND s.owner_id = ? AND cb.available_credits >= ?
                ");
                $stmt->execute([$data['store_id'], $ownerId, $data['credits']]);
                $store = $stmt->fetch();
                
                if (!$store) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Insufficient credits or invalid store']);
                    return;
                }

                // Calculate views
                $viewsAllocated = $data['credits'] * 180; // $1 = 180 views

                // Insert advertisement
                $stmt = $db->prepare("
                    INSERT INTO advertisements (store_id, title, description, images, credits_purchased, views_allocated, call_to_action, link_url, start_date, end_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['store_id'],
                    sanitizeInput($data['title']),
                    sanitizeInput($data['description'] ?? ''),
                    json_encode($data['images']),
                    $data['credits'],
                    $viewsAllocated,
                    sanitizeInput($data['call_to_action'] ?? ''),
                    sanitizeInput($data['link_url'] ?? ''),
                    $data['start_date'] ?? null,
                    $data['end_date'] ?? null
                ]);

                $adId = $db->lastInsertId();

                // Deduct credits
                $stmt = $db->prepare("UPDATE credit_balances SET available_credits = available_credits - ?, used_credits = used_credits + ? WHERE store_id = ?");
                $stmt->execute([$data['credits'], $data['credits'], $data['store_id']]);

                // Record credit usage
                $stmt = $db->prepare("INSERT INTO credit_transactions (store_id, transaction_type, credits, amount) VALUES (?, 'usage', ?, 0)");
                $stmt->execute([$data['store_id'], $data['credits']]);

                // Increment rate limit
                incrementRateLimit($rateLimitKey, 'ad_creation', null, 15);

                http_response_code(201);
                echo json_encode([
                    'message' => 'Advertisement created successfully',
                    'advertisement_id' => $adId,
                    'views_allocated' => $viewsAllocated
                ]);

            } catch (PDOException $e) {
                error_log("Advertisement creation error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Advertisement creation failed']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Get Nearby Advertisements (for regular users)
function handleNearbyAds($db, $locationService) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $latitude = isset($_GET['latitude']) ? (float)$_GET['latitude'] : null;
    $longitude = isset($_GET['longitude']) ? (float)$_GET['longitude'] : null;
    $radius = isset($_GET['radius']) ? (int)$_GET['radius'] : DEFAULT_GEOFENCE_RADIUS_METERS;
    $userFingerprint = isset($_GET['user_fingerprint']) ? $_GET['user_fingerprint'] : null;

    if (!$latitude || !$longitude) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude required']);
        return;
    }

    if (!validateLatitude($latitude) || !validateLongitude($longitude)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coordinates']);
        return;
    }

    try {
        // Check rate limiting for geofence entries
        if ($userFingerprint) {
            $rateLimitKey = "geofence_entry:{$userFingerprint}";
            if (!checkRateLimit($rateLimitKey, 'geofence_entry', null, 60, 1)) {
                http_response_code(429);
                echo json_encode(['error' => 'Rate limit exceeded. Please wait before viewing more advertisements.']);
                return;
            }
        }

        // Find active advertisements within geofence
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.description, a.images, a.call_to_action, a.link_url, 
                   s.store_name, s.latitude, s.longitude,
                   ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) as distance_meters
            FROM advertisements a
            JOIN stores s ON a.store_id = s.id
            WHERE a.status = 'active' 
            AND a.views_remaining > 0
            AND s.is_active = TRUE
            AND ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) <= ?
            AND (a.end_date IS NULL OR a.end_date > NOW())
            ORDER BY distance_meters ASC
            LIMIT 50
        ");
        
        $pointWKT = "POINT($longitude $latitude)";
        $stmt->execute([$pointWKT, $pointWKT, $radius]);
        $advertisements = $stmt->fetchAll();

        // Increment rate limit if user fingerprint provided
        if ($userFingerprint) {
            incrementRateLimit($rateLimitKey, 'geofence_entry', null, 60);
        }

        echo json_encode([
            'advertisements' => $advertisements,
            'count' => count($advertisements)
        ]);

    } catch (PDOException $e) {
        error_log("Nearby ads error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve nearby advertisements']);
    }
}

// Track Advertisement View
function handleTrackView($db, $locationService) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['advertisement_id', 'user_fingerprint', 'latitude', 'longitude'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    if (!validateLatitude($data['latitude']) || !validateLongitude($data['longitude'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coordinates']);
        return;
    }

    try {
        // Verify advertisement exists and is active
        $stmt = $db->prepare("
            SELECT a.id, a.views_remaining, a.views_used, s.latitude as store_lat, s.longitude as store_lon, s.store_name
            FROM advertisements a
            JOIN stores s ON a.store_id = s.id
            WHERE a.id = ? AND a.status = 'active' AND a.views_remaining > 0
        ");
        $stmt->execute([$data['advertisement_id']]);
        $ad = $stmt->fetch();

        if (!$ad) {
            http_response_code(404);
            echo json_encode(['error' => 'Advertisement not found or inactive']);
            return;
        }

        // Calculate distance to store
        $distance = calculateDistance(
            $data['latitude'], 
            $data['longitude'], 
            $ad['store_lat'], 
            $ad['store_lon'], 
            'meters'
        );

        // Record interaction
        $stmt = $db->prepare("
            INSERT INTO user_interactions (advertisement_id, user_fingerprint, interaction_type, latitude, longitude, user_location, store_distance_meters, device_info, session_id) 
            VALUES (?, ?, 'view', ?, ?, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ?, ?, ?)
        ");
        
        $deviceInfo = json_encode([
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ]);
        
        $sessionId = md5($data['user_fingerprint'] . date('Y-m-d H:00:00'));
        $pointWKT = "POINT({$data['longitude']} {$data['latitude']})";
        
        $stmt->execute([
            $data['advertisement_id'],
            $data['user_fingerprint'],
            $data['latitude'],
            $data['longitude'],
            $pointWKT,
            $distance,
            $deviceInfo,
            $sessionId
        ]);

        // Update advertisement view count (if within reasonable distance)
        if ($distance <= DEFAULT_GEOFENCE_RADIUS_METERS) {
            $stmt = $db->prepare("UPDATE advertisements SET views_used = views_used + 1, views_remaining = views_remaining - 1, last_sent_at = NOW() WHERE id = ?");
            $stmt->execute([$data['advertisement_id']]);
        }

        echo json_encode([
            'message' => 'View tracked successfully',
            'distance_meters' => $distance,
            'store_name' => $ad['store_name']
        ]);

    } catch (PDOException $e) {
        error_log("Track view error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to track view']);
    }
}

// Credit Package Management
function handleCreditPackages($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT id, name, price, credits, views_per_credit, total_views 
            FROM credit_packages 
            WHERE is_active = TRUE 
            ORDER BY sort_order ASC, price ASC
        ");
        $stmt->execute();
        $packages = $stmt->fetchAll();
        
        echo json_encode(['packages' => $packages]);

    } catch (PDOException $e) {
        error_log("Credit packages error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve credit packages']);
    }
}

// Image Upload Handler
function handleImageUpload($db, $auth) {
    $ownerId = $auth->requireAuth('store_owner');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Check rate limiting for image uploads
    $rateLimitKey = "image_upload:{$ownerId}";
    if (!checkRateLimit($rateLimitKey, 'image_upload', null, 15, MAX_IMAGES_PER_AD)) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded. Please wait before uploading more images.']);
        return;
    }

    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No image uploaded or upload failed']);
            return;
        }

        $file = $_FILES['image'];
        
        // Validate file size
        if ($file['size'] > MAX_FILE_SIZE_BYTES) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE_BYTES / 1024 / 1024) . 'MB']);
            return;
        }

        // Validate file type
        $allowedTypes = explode(',', ALLOWED_IMAGE_TYPES);
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if (!in_array($extension, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Allowed types: ' . ALLOWED_IMAGE_TYPES]);
            return;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type']);
            return;
        }

        // Generate unique filename
        $filename = uniqid('ad_', true) . '.' . $extension;
        $uploadPath = UPLOAD_BASE_PATH . $filename;
        
        // Create upload directory if it doesn't exist
        if (!is_dir(UPLOAD_BASE_PATH)) {
            mkdir(UPLOAD_BASE_PATH, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save uploaded file']);
            return;
        }

        // Get image dimensions
        list($width, $height) = getimagesize($uploadPath);
        
        // Store image metadata
        $stmt = $db->prepare("
            INSERT INTO images (advertisement_id, original_filename, storage_path, file_size_bytes, mime_type, width, height) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['advertisement_id'] ?? null,
            $file['name'],
            $filename,
            $file['size'],
            $mimeType,
            $width,
            $height
        ]);

        $imageId = $db->lastInsertId();

        // Increment rate limit
        incrementRateLimit($rateLimitKey, 'image_upload', null, 15);

        echo json_encode([
            'message' => 'Image uploaded successfully',
            'image_id' => $imageId,
            'filename' => $filename,
            'url' => IMAGE_BASE_URL . $filename,
            'width' => $width,
            'height' => $height
        ]);

    } catch (PDOException $e) {
        error_log("Image upload error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Image upload failed']);
    }
}

// Payment Processing (Stub - needs actual implementation)
function handlePurchaseCredits($db, $auth, $paymentService) {
    // This would integrate with Stripe/Google Wallet
    // Implementation would handle payment processing, credit allocation, etc.
    echo json_encode(['error' => 'Payment processing not implemented']);
}

function handleRefundCredits($db, $auth, $paymentService) {
    // This would handle credit refunds
    // Implementation would process refunds according to refund policy
    echo json_encode(['error' => 'Refund processing not implemented']);
}

// Geofence Events Handler
function handleGeofenceEvents($db, $locationService) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['store_id', 'user_fingerprint', 'event_type', 'latitude', 'longitude'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    if (!in_array($data['event_type'], ['enter', 'exit', 'dwell'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid event type']);
        return;
    }

    try {
        // Verify store exists and is active
        $stmt = $db->prepare("SELECT id, latitude, longitude FROM stores WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$data['store_id']]);
        $store = $stmt->fetch();
        
        if (!$store) {
            http_response_code(404);
            echo json_encode(['error' => 'Store not found or inactive']);
            return;
        }

        // Calculate distance
        $distance = calculateDistance(
            $data['latitude'], 
            $data['longitude'], 
            $store['latitude'], 
            $store['longitude'], 
            'meters'
        );

        // Record geofence event
        $stmt = $db->prepare("
            INSERT INTO geofence_events (store_id, user_fingerprint, event_type, latitude, longitude, user_location, distance_to_store_meters) 
            VALUES (?, ?, ?, ?, ?, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ?)
        ");
        
        $pointWKT = "POINT({$data['longitude']} {$data['latitude']})";
        $stmt->execute([
            $data['store_id'],
            $data['user_fingerprint'],
            $data['event_type'],
            $data['latitude'],
            $data['longitude'],
            $pointWKT,
            $distance
        ]);

        echo json_encode([
            'message' => 'Geofence event recorded successfully',
            'event_id' => $db->lastInsertId(),
            'distance_meters' => $distance
        ]);

    } catch (PDOException $e) {
        error_log("Geofence event error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record geofence event']);
    }
}
?>