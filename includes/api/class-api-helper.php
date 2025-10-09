<?php
/**
 * SpamGuard API Helper
 * Funciones auxiliares para la API
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Helper {
    
    /**
     * Sanitizar API key
     */
    public static function sanitize_api_key($api_key) {
        // Remover espacios
        $api_key = trim($api_key);
        
        // Validar formato (sg_test_ o sg_live_)
        if (!preg_match('/^sg_(test|live)_[a-zA-Z0-9_-]+$/', $api_key)) {
            return '';
        }
        
        return $api_key;
    }
    
    /**
     * Validar email
     */
    public static function is_valid_email($email) {
        return is_email($email);
    }
}
