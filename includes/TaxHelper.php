<?php
// /linen-closet/includes/TaxHelper.php

class TaxHelper {
    /**
     * Get tax settings from database
     * 
     * @param PDO $db Database connection
     * @return array Tax settings [enabled, rate]
     */
    public static function getTaxSettings($db) {
        $taxEnabled = '1'; // Default: enabled
        $taxRate = 16.0;   // Default: 16%
        
        try {
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('tax_enabled', 'tax_rate')");
            if ($stmt->execute()) {
                while ($row = $stmt->fetch()) {
                    if ($row['setting_key'] == 'tax_enabled') {
                        $taxEnabled = $row['setting_value'] ?? '1';
                    } elseif ($row['setting_key'] == 'tax_rate') {
                        $taxRate = floatval($row['setting_value'] ?? 16);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("TaxHelper Error: " . $e->getMessage());
        }
        
        return [
            'enabled' => $taxEnabled,
            'rate' => $taxRate
        ];
    }
    
    /**
     * Calculate tax amount
     * 
     * @param float $subtotal Cart subtotal
     * @param array $taxSettings Tax settings from getTaxSettings()
     * @return float Tax amount
     */
    public static function calculateTax($subtotal, $taxSettings) {
        if ($taxSettings['enabled'] == '1') {
            return $subtotal * ($taxSettings['rate'] / 100);
        }
        return 0;
    }
}
?>