<?php
// Nepal-specific configuration file

class NepalConfig {
    // Currency settings
    const CURRENCY_CODE = 'NPR';
    const CURRENCY_SYMBOL = 'Rs.';
    
    // Timezone
    const TIMEZONE = 'Asia/Kathmandu';
    
    // Company information
    const COMPANY_NAME = 'TravelNepal';
    const COMPANY_EMAIL = 'info@travelnepal.com';
    const COMPANY_PHONE = '+977-9999999999';
    const COMPANY_ADDRESS = 'Thamel, Kathmandu, Nepal';
    
    // Popular destinations in Nepal
    const DESTINATIONS = [
        'Kathmandu Valley',
        'Pokhara',
        'Chitwan',
        'Everest Region',
        'Annapurna Region',
        'Langtang Region',
        'Mustang',
        'Bandipur',
        'Gorkha',
        'Lumbini'
    ];
    
    // Package categories
    const PACKAGE_CATEGORIES = [
        'trekking' => 'Trekking & Hiking',
        'cultural' => 'Cultural Tours',
        'wildlife' => 'Wildlife Safari',
        'adventure' => 'Adventure Sports',
        'pilgrimage' => 'Pilgrimage Tours',
        'mountain' => 'Mountain Expeditions'
    ];
    
    // Phone number format for Nepal
    public static function formatPhoneNumber($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add Nepal country code if not present
        if (strlen($phone) == 10 && substr($phone, 0, 2) == '98') {
            return '+977-' . $phone;
        } elseif (strlen($phone) == 10) {
            return '+977-' . $phone;
        }
        
        return $phone;
    }
    
    // Format currency for Nepal
    public static function formatCurrency($amount) {
        return 'Rs. ' . number_format($amount, 2);
    }
    
    // Get Nepal provinces
    public static function getProvinces() {
        return [
            'Province 1',
            'Madhesh Province',
            'Bagmati Province',
            'Gandaki Province',
            'Lumbini Province',
            'Karnali Province',
            'Sudurpashchim Province'
        ];
    }
}
?>
