<?php
/**
 * SpamGuard API Helper
 * 
 * Funciones auxiliares para API
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_API_Helper {
    
    /**
     * Formatear tiempo relativo
     * 
     * @param string $datetime Fecha/hora
     * @return string Tiempo relativo
     */
    public static function time_ago($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return __('Just now', 'spamguard');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return sprintf(_n('%d minute ago', '%d minutes ago', $mins, 'spamguard'), $mins);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'spamguard'), $hours);
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'spamguard'), $days);
        } else {
            return date_i18n(get_option('date_format'), $time);
        }
    }
    
    /**
     * Formatear número con separadores
     * 
     * @param int $number Número
     * @return string Número formateado
     */
    public static function format_number($number) {
        return number_format_i18n($number);
    }
    
    /**
     * Formatear porcentaje
     * 
     * @param float $value Valor (0-1)
     * @param int $decimals Decimales
     * @return string Porcentaje formateado
     */
    public static function format_percentage($value, $decimals = 1) {
        return number_format_i18n($value * 100, $decimals) . '%';
    }
    
    /**
     * Obtener clase de color según risk level
     * 
     * @param string $risk_level Risk level
     * @return string Clase CSS
     */
    public static function get_risk_class($risk_level) {
        $classes = array(
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger'
        );
        
        return isset($classes[$risk_level]) ? $classes[$risk_level] : 'secondary';
    }
    
    /**
     * Obtener icono según categoría
     * 
     * @param string $category Categoría
     * @return string Dashicon class
     */
    public static function get_category_icon($category) {
        $icons = array(
            'ham' => 'yes-alt',
            'spam' => 'dismiss',
            'phishing' => 'warning',
            'ai_generated' => 'admin-generic',
            'fraud' => 'shield-alt'
        );
        
        return isset($icons[$category]) ? $icons[$category] : 'marker';
    }
    
    /**
     * Sanitizar API key
     * 
     * @param string $api_key API key
     * @return string API key sanitizada
     */
    public static function sanitize_api_key($api_key) {
        // Remover espacios y caracteres raros
        $api_key = trim($api_key);
        $api_key = preg_replace('/[^a-zA-Z0-9_-]/', '', $api_key);
        
        return $api_key;
    }
    
    /**
     * Validar formato de API key
     * 
     * @param string $api_key API key
     * @return bool Válida o no
     */
    public static function validate_api_key_format($api_key) {
        // Formato: sg_test_xxxxx o sg_live_xxxxx
        return preg_match('/^sg_(test|live)_[a-zA-Z0-9_-]{20,}$/', $api_key);
    }
    
    /**
     * Obtener tipo de API key (test o live)
     * 
     * @param string $api_key API key
     * @return string 'test' o 'live'
     */
    public static function get_api_key_type($api_key) {
        if (strpos($api_key, 'sg_test_') === 0) {
            return 'test';
        } elseif (strpos($api_key, 'sg_live_') === 0) {
            return 'live';
        }
        return 'unknown';
    }
    
    /**
     * Ofuscar API key para mostrar
     * 
     * @param string $api_key API key
     * @return string API key ofuscada
     */
    public static function obfuscate_api_key($api_key) {
        if (strlen($api_key) < 20) {
            return str_repeat('*', strlen($api_key));
        }
        
        // Mostrar primeros 10 y últimos 4 caracteres
        $start = substr($api_key, 0, 10);
        $end = substr($api_key, -4);
        $middle = str_repeat('*', strlen($api_key) - 14);
        
        return $start . $middle . $end;
    }
}