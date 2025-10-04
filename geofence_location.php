<?php
require_once 'geofence_config.php';

class GeofenceLocationService {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Create circular polygon for geofence
    public function createCirclePolygon($latitude, $longitude, $radiusMeters, $segments = 64) {
        $earthRadius = EARTH_RADIUS_KM * 1000; // Convert to meters
        
        // Convert degrees to radians
        $lat = deg2rad($latitude);
        $lon = deg2rad($longitude);
        $radius = $radiusMeters / $earthRadius; // Angular radius
        
        $points = [];
        
        for ($i = 0; $i < $segments; $i++) {
            $bearing = 2 * pi() * $i / $segments;
            
            $lat2 = asin(sin($lat) * cos($radius) + cos($lat) * sin($radius) * cos($bearing));
            $lon2 = $lon + atan2(sin($bearing) * sin($radius) * cos($lat), cos($radius) - sin($lat) * sin($lat2));
            
            // Normalize longitude
            $lon2 = fmod($lon2 + 3 * pi(), 2 * pi()) - pi();
            
            $points[] = rad2deg($lon2) . ' ' . rad2deg($lat2);
        }
        
        // Close the polygon by repeating the first point
        $points[] = $points[0];
        
        return 'POLYGON((' . implode(',', $points) . '))';
    }
    
    // Calculate distance between two points
    public function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'meters') {
        $earthRadius = ($unit === 'meters') ? EARTH_RADIUS_KM * 1000 : EARTH_RADIUS_MILES;
        
        // Convert degrees to radians
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        // Haversine formula
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($lat1) * cos($lat2) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;
        
        return $distance;
    }
    
    // Check if point is within geofence
    public function isWithinGeofence($userLat, $userLon, $storeLat, $storeLon, $radiusMeters) {
        $distance = $this->calculateDistance($userLat, $userLon, $storeLat, $storeLon, 'meters');
        return $distance <= $radiusMeters;
    }
    
    // Find nearby stores using spatial query
    public function findNearbyStores($latitude, $longitude, $radiusMeters, $limit = 50) {
        try {
            $sql = "
                SELECT s.id, s.store_name, s.address, s.latitude, s.longitude, 
                       s.geofence_radius_meters, s.category, s.is_active,
                       ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) as distance_meters
                FROM stores s
                WHERE s.is_active = TRUE
                AND ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) <= ?
                ORDER BY distance_meters ASC
                LIMIT ?
            ";
            
            $pointWKT = "POINT($longitude $latitude)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$pointWKT, $pointWKT, $radiusMeters, $limit]);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Find nearby stores error: " . $e->getMessage());
            return [];
        }
    }
    
    // Find active advertisements near location
    public function findNearbyAdvertisements($latitude, $longitude, $radiusMeters, $userFingerprint = null, $limit = 50) {
        try {
            // Check rate limiting if user fingerprint provided
            if ($userFingerprint) {
                $rateLimitKey = "geofence_entry:{$userFingerprint}";
                if (!checkRateLimit($rateLimitKey, 'geofence_entry', null, 60, 1)) {
                    return ['error' => 'Rate limit exceeded'];
                }
            }
            
            $sql = "
                SELECT a.id, a.title, a.description, a.images, a.call_to_action, a.link_url,
                       s.store_name, s.latitude, s.longitude, s.category,
                       ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) as distance_meters,
                       a.views_remaining
                FROM advertisements a
                JOIN stores s ON a.store_id = s.id
                WHERE a.status = 'active' 
                AND a.views_remaining > 0
                AND s.is_active = TRUE
                AND (a.end_date IS NULL OR a.end_date > NOW())
                AND ST_Distance_Sphere(s.location, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . ")) <= ?
                ORDER BY distance_meters ASC, a.created_at DESC
                LIMIT ?
            ";
            
            $pointWKT = "POINT($longitude $latitude)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$pointWKT, $pointWKT, $radiusMeters, $limit]);
            $advertisements = $stmt->fetchAll();
            
            // Increment rate limit if user fingerprint provided
            if ($userFingerprint) {
                incrementRateLimit($rateLimitKey, 'geofence_entry', null, 60);
            }
            
            return $advertisements;
        } catch (PDOException $e) {
            error_log("Find nearby advertisements error: " . $e->getMessage());
            return [];
        }
    }
    
    // Record geofence event
    public function recordGeofenceEvent($storeId, $userFingerprint, $eventType, $latitude, $longitude, $distanceMeters) {
        try {
            $sql = "
                INSERT INTO geofence_events (store_id, user_fingerprint, event_type, latitude, longitude, user_location, distance_to_store_meters) 
                VALUES (?, ?, ?, ?, ?, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ?)
            ";
            
            $pointWKT = "POINT($longitude $latitude)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$storeId, $userFingerprint, $eventType, $latitude, $longitude, $pointWKT, $distanceMeters]);
        } catch (PDOException $e) {
            error_log("Record geofence event error: " . $e->getMessage());
            return false;
        }
    }
    
    // Record advertisement interaction
    public function recordInteraction($advertisementId, $userFingerprint, $interactionType, $latitude, $longitude, $distanceMeters, $deviceInfo = []) {
        try {
            $sql = "
                INSERT INTO user_interactions (advertisement_id, user_fingerprint, interaction_type, latitude, longitude, user_location, store_distance_meters, device_info, session_id) 
                VALUES (?, ?, ?, ?, ?, ST_PointFromText(?, " . SPATIAL_REFERENCE_SYSTEM . "), ?, ?, ?)
            ";
            
            $deviceInfoJson = json_encode($deviceInfo);
            $sessionId = md5($userFingerprint . date('Y-m-d H:00:00'));
            $pointWKT = "POINT($longitude $latitude)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$advertisementId, $userFingerprint, $interactionType, $latitude, $longitude, $pointWKT, $distanceMeters, $deviceInfoJson, $sessionId]);
        } catch (PDOException $e) {
            error_log("Record interaction error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update advertisement view count
    public function updateAdvertisementViews($advertisementId, $increment = 1) {
        try {
            $sql = "UPDATE advertisements SET views_used = views_used + ?, views_remaining = views_remaining - ?, last_sent_at = NOW() WHERE id = ? AND views_remaining >= ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$increment, $increment, $advertisementId, $increment]);
        } catch (PDOException $e) {
            error_log("Update advertisement views error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get geofence statistics for store
    public function getStoreGeofenceStats($storeId, $days = 30) {
        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT user_fingerprint) as unique_users,
                    COUNT(*) as total_events,
                    AVG(distance_to_store_meters) as avg_distance_meters,
                    SUM(CASE WHEN event_type = 'enter' THEN 1 ELSE 0 END) as entries,
                    SUM(CASE WHEN event_type = 'exit' THEN 1 ELSE 0 END) as exits,
                    DATE(created_at) as event_date
                FROM geofence_events
                WHERE store_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY event_date DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$storeId, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get store geofence stats error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get advertisement performance metrics
    public function getAdvertisementMetrics($advertisementId, $days = 30) {
        try {
            $sql = "
                SELECT 
                    interaction_type,
                    COUNT(*) as count,
                    AVG(store_distance_meters) as avg_distance_meters,
                    DATE(created_at) as interaction_date
                FROM user_interactions
                WHERE advertisement_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY interaction_type, DATE(created_at)
                ORDER BY interaction_date DESC, count DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$advertisementId, $days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get advertisement metrics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Check if user has entered geofence recently
    public function hasUserEnteredRecently($userFingerprint, $storeId, $hours = 1) {
        try {
            $sql = "
                SELECT COUNT(*) as entry_count
                FROM geofence_events
                WHERE user_fingerprint = ? 
                AND store_id = ? 
                AND event_type = 'enter'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userFingerprint, $storeId, $hours]);
            $result = $stmt->fetch();
            
            return $result['entry_count'] > 0;
        } catch (PDOException $e) {
            error_log("Check recent entry error: " . $e->getMessage());
            return true; // Fail closed for security
        }
    }
    
    // Get user's current geofence status
    public function getUserGeofenceStatus($userFingerprint) {
        try {
            $sql = "
                SELECT DISTINCT s.id, s.store_name, s.latitude, s.longitude,
                       'inside' as status,
                       ST_Distance_Sphere(s.location, ge.user_location) as current_distance_meters
                FROM stores s
                JOIN geofence_events ge ON s.id = ge.store_id
                WHERE ge.user_fingerprint = ?
                AND ge.event_type = 'enter'
                AND ge.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND NOT EXISTS (
                    SELECT 1 FROM geofence_events ge2
                    WHERE ge2.user_fingerprint = ge.user_fingerprint
                    AND ge2.store_id = ge.store_id
                    AND ge2.event_type = 'exit'
                    AND ge2.created_at > ge.created_at
                )
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userFingerprint]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user geofence status error: " . $e->getMessage());
            return [];
        }
    }
    
    // Clean up old geofence events
    public function cleanupOldEvents($daysToKeep = 30) {
        try {
            $sql = "DELETE FROM geofence_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$daysToKeep]);
        } catch (PDOException $e) {
            error_log("Cleanup old events error: " . $e->getMessage());
            return false;
        }
    }
    
    // Validate coordinates
    public function validateCoordinates($latitude, $longitude) {
        return validateLatitude($latitude) && validateLongitude($longitude);
    }
    
    // Convert degrees to radians
    public function deg2rad($degrees) {
        return $degrees * (pi() / 180);
    }
    
    // Convert radians to degrees
    public function rad2deg($radians) {
        return $radians * (180 / pi());
    }
    
    // Calculate bearing between two points
    public function calculateBearing($lat1, $lon1, $lat2, $lon2) {
        $lat1 = $this->deg2rad($lat1);
        $lon1 = $this->deg2rad($lon1);
        $lat2 = $this->deg2rad($lat2);
        $lon2 = $this->deg2rad($lon2);
        
        $y = sin($lon2 - $lon1) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($lon2 - $lon1);
        
        $bearing = atan2($y, $x);
        return $this->rad2deg($bearing);
    }
    
    // Get destination point given start point, bearing and distance
    public function getDestinationPoint($lat1, $lon1, $bearing, $distanceMeters) {
        $earthRadius = EARTH_RADIUS_KM * 1000;
        
        $lat1 = $this->deg2rad($lat1);
        $lon1 = $this->deg2rad($lon1);
        $bearing = $this->deg2rad($bearing);
        $distance = $distanceMeters / $earthRadius;
        
        $lat2 = asin(sin($lat1) * cos($distance) + cos($lat1) * sin($distance) * cos($bearing));
        $lon2 = $lon1 + atan2(sin($bearing) * sin($distance) * cos($lat1), cos($distance) - sin($lat1) * sin($lat2));
        
        // Normalize longitude
        $lon2 = fmod($lon2 + 3 * pi(), 2 * pi()) - pi();
        
        return [
            'latitude' => $this->rad2deg($lat2),
            'longitude' => $this->rad2deg($lon2)
        ];
    }
}
?>