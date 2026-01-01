<?php
class Shipping {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Calculate shipping cost based on county
     */
    public function calculateShipping($county, $subtotal = 0) {
        try {
            // First check if shipping is enabled
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'shipping_enabled'");
            $stmt->execute();
            $shippingEnabled = $stmt->fetchColumn();
            
            if ($shippingEnabled !== '1') {
                return ['cost' => 0, 'message' => 'Free shipping', 'zone_id' => null];
            }
            
            // Check for free shipping minimum
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_min'");
            $stmt->execute();
            $freeShippingMin = (float)$stmt->fetchColumn();
            
            if ($freeShippingMin > 0 && $subtotal >= $freeShippingMin) {
                return [
                    'cost' => 0,
                    'message' => 'Free shipping on orders over Ksh ' . number_format($freeShippingMin, 2),
                    'zone_id' => null
                ];
            }
            
            // Find matching shipping zone
            $zone = $this->findShippingZone($county);
            
            if ($zone) {
                return [
                    'cost' => (float)$zone['cost'],
                    'message' => $zone['zone_name'] . ' delivery (' . $zone['delivery_days'] . ' days)',
                    'zone_id' => $zone['id'],
                    'delivery_days' => $zone['delivery_days']
                ];
            }
            
            // Use flat rate as fallback
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'shipping_flat_rate'");
            $stmt->execute();
            $flatRate = (float)$stmt->fetchColumn();
            
            return [
                'cost' => $flatRate ?: 300, // Default to 300 if not set
                'message' => 'Standard shipping',
                'zone_id' => null
            ];
            
        } catch (Exception $e) {
            // Fallback
            return [
                'cost' => 300,
                'message' => 'Standard shipping',
                'zone_id' => null
            ];
        }
    }
    
    /**
     * Find shipping zone for a county
     */
    private function findShippingZone($county) {
        if (empty($county)) {
            return null;
        }
        
        $stmt = $this->db->query("
            SELECT * FROM shipping_zones 
            WHERE is_active = 1 
            ORDER BY cost ASC
        ");
        $zones = $stmt->fetchAll();
        
        foreach ($zones as $zone) {
            // Check if county is in the counties field
            if (!empty($zone['counties']) && $this->isAreaInList($county, $zone['counties'])) {
                return $zone;
            }
        }
        
        return null;
    }
    
    /**
     * Check if area exists in list (counties or towns/areas)
     */
    private function isAreaInList($area, $areaList) {
        if (empty($areaList)) {
            return false;
        }
        
        $area = strtolower(trim($area));
        $areas = preg_split('/[\n\r,;]+/', strtolower($areaList));
        
        foreach ($areas as $listArea) {
            $listArea = trim($listArea);
            if ($listArea && strpos($area, $listArea) !== false || strpos($listArea, $area) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all active shipping zones
     */
    public function getActiveZones() {
        $stmt = $this->db->query("
            SELECT * FROM shipping_zones 
            WHERE is_active = 1 
            ORDER BY zone_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get all unique counties from shipping zones
     */
    public function getAllCounties() {
        $stmt = $this->db->query("
            SELECT counties FROM shipping_zones 
            WHERE is_active = 1 AND counties IS NOT NULL AND counties != ''
        ");
        $zones = $stmt->fetchAll();
        
        $allCounties = [];
        foreach ($zones as $zone) {
            if (!empty($zone['counties'])) {
                $countyList = preg_split('/[\n\r,;]+/', $zone['counties']);
                foreach ($countyList as $county) {
                    $county = trim($county);
                    if (!empty($county) && !in_array($county, $allCounties)) {
                        $allCounties[] = $county;
                    }
                }
            }
        }
        
        // Remove duplicates and sort
        $allCounties = array_unique($allCounties);
        sort($allCounties);
        return $allCounties;
    }
    
    /**
     * Get shipping zones for dropdown
     */
    public function getZonesForDropdown() {
        $stmt = $this->db->query("
            SELECT id, zone_name, cost, counties, towns_areas, delivery_days 
            FROM shipping_zones 
            WHERE is_active = 1
            ORDER BY zone_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get shipping zones with towns/areas for display
     */
    public function getZonesWithTowns() {
        $stmt = $this->db->query("
            SELECT id, zone_name, cost, counties, towns_areas, delivery_days 
            FROM shipping_zones 
            WHERE is_active = 1
            ORDER BY zone_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get specific zone by ID
     */
    public function getZoneById($zoneId) {
        $stmt = $this->db->prepare("
            SELECT * FROM shipping_zones 
            WHERE id = ?
        ");
        $stmt->execute([$zoneId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all towns/areas from shipping zones (for search/suggestions)
     */
    // public function getAllTownsAreas() {
    //     $stmt = $this->db->query("
    //         SELECT towns_areas FROM shipping_zones 
    //         WHERE is_active = 1 AND towns_areas IS NOT NULL AND towns_areas != ''
    //     ");
    //     $zones = $stmt->fetchAll();
        
    //     $allTowns = [];
    //     foreach ($zones as $zone) {
    //         if (!empty($zone['towns_areas'])) {
    //             $townList = preg_split('/[\n\r,;]+/', $zone['towns_areas']);
    //             foreach ($townList as $town) {
    //                 $town = trim($town);
    //                 if (!empty($town) && !in_array($town, $allTowns)) {
    //                     $allTowns[] = $town;
    //                 }
    //             }
    //         }
    //     }
        
    //     // Remove duplicates and sort
    //     $allTowns = array_unique($allTowns);
    //     sort($allTowns);
    //     return $allTowns;
    // }
    
    /**
     * Check if a specific town/area is served by any zone
     */
    public function isTownServed($town) {
        $stmt = $this->db->query("
            SELECT COUNT(*) as count FROM shipping_zones 
            WHERE is_active = 1 
            AND (towns_areas LIKE ? OR counties LIKE ?)
        ");
        $searchTerm = "%" . $town . "%";
        $stmt->execute([$searchTerm, $searchTerm]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    /**
     * Get zones that serve a specific county
     */
    public function getZonesByCounty($county) {
        $stmt = $this->db->prepare("
            SELECT * FROM shipping_zones 
            WHERE is_active = 1 
            AND counties LIKE ?
            ORDER BY cost ASC
        ");
        $searchTerm = "%" . $county . "%";
        $stmt->execute([$searchTerm]);
        return $stmt->fetchAll();
    }
    // Add this method to your Shipping.php class
/**
 * Get all unique towns/areas from shipping zones
 */
public function getAllTownsAreas() {
    $stmt = $this->db->query("
        SELECT towns_areas FROM shipping_zones 
        WHERE is_active = 1 AND towns_areas IS NOT NULL AND towns_areas != ''
    ");
    $zones = $stmt->fetchAll();
    
    $allTowns = [];
    foreach ($zones as $zone) {
        if (!empty($zone['towns_areas'])) {
            $townList = preg_split('/[\n\r,;]+/', $zone['towns_areas']);
            foreach ($townList as $town) {
                $town = trim($town);
                if (!empty($town) && !in_array($town, $allTowns)) {
                    $allTowns[] = $town;
                }
            }
        }
    }
    
    // Remove duplicates and sort
    $allTowns = array_unique($allTowns);
    sort($allTowns);
    return $allTowns;
}
}