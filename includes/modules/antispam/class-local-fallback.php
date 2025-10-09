<?php
/**
 * SpamGuard Local Fallback
 * 
 * Sistema de detección local cuando API no está disponible
 * 
 * @package SpamGuard
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpamGuard_Local_Fallback {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Constructor privado
    }
    
    /**
     * Analizar comentario con reglas locales
     * 
     * @param array $comment_data Datos del comentario
     * @return array Resultado del análisis
     */
    public function analyze($comment_data) {
        $spam_score = 0;
        $flags = array();
        
        $content = isset($comment_data['comment_content']) ? $comment_data['comment_content'] : '';
        $author_email = isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '';
        
        // ============================================
        // HONEYPOT CHECK
        // ============================================
        if (isset($comment_data['honeypot_field']) && !empty($comment_data['honeypot_field'])) {
            $spam_score += 50;
            $flags[] = 'honeypot_triggered';
        }
        
        // ============================================
        // TIME CHECK
        // ============================================
        if (isset($comment_data['submit_time'])) {
            $elapsed = time() - intval($comment_data['submit_time']);
            $min_time = get_option('spamguard_time_check', 3);
            
            if ($elapsed < $min_time) {
                $spam_score += 30;
                $flags[] = 'submitted_too_fast';
            }
        }
        
        // ============================================
        // CONTENT ANALYSIS
        // ============================================
        
        // Palabras spam
        $spam_keywords = array(
            'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner',
            'congratulations', 'click here', 'buy now', 'order now',
            'limited time', 'act now', 'free money', 'no cost',
            'risk free', 'weight loss', 'forex', 'bitcoin', 'crypto'
        );
        
        $content_lower = strtolower($content);
        $keyword_count = 0;
        
        foreach ($spam_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $keyword_count++;
                $flags[] = 'spam_keyword_' . str_replace(' ', '_', $keyword);
            }
        }
        
        if ($keyword_count > 0) {
            $spam_score += min($keyword_count * 15, 60);
        }
        
        // ============================================
        // LINKS
        // ============================================
        $link_count = substr_count($content_lower, 'http');
        
        if ($link_count > 3) {
            $spam_score += 25;
            $flags[] = 'excessive_links';
        } elseif ($link_count > 1) {
            $spam_score += 10;
            $flags[] = 'multiple_links';
        }
        
        // ============================================
        // CAPITALIZACIÓN
        // ============================================
        $content_len = strlen($content);
        if ($content_len > 0) {
            $caps_count = strlen(preg_replace('/[^A-Z]/', '', $content));
            $caps_ratio = $caps_count / $content_len;
            
            if ($caps_ratio > 0.5) {
                $spam_score += 30;
                $flags[] = 'excessive_caps';
            } elseif ($caps_ratio > 0.3) {
                $spam_score += 15;
                $flags[] = 'high_caps';
            }
        }
        
        // ============================================
        // EXCLAMACIONES
        // ============================================
        $exclamation_count = substr_count($content, '!');
        
        if ($exclamation_count > 5) {
            $spam_score += 20;
            $flags[] = 'excessive_exclamation';
        } elseif ($exclamation_count > 3) {
            $spam_score += 10;
            $flags[] = 'multiple_exclamation';
        }
        
        // ============================================
        // EMAIL DESECHABLE
        // ============================================
        $disposable_domains = array(
            'tempmail.com', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org'
        );
        
        foreach ($disposable_domains as $domain) {
            if (strpos($author_email, $domain) !== false) {
                $spam_score += 40;
                $flags[] = 'disposable_email';
                break;
            }
        }
        
        // ============================================
        // CONTENIDO MUY CORTO O MUY LARGO
        // ============================================
        if ($content_len < 10) {
            $spam_score += 20;
            $flags[] = 'very_short_content';
        } elseif ($content_len > 5000) {
            $spam_score += 15;
            $flags[] = 'very_long_content';
        }
        
        // ============================================
        // CARACTERES ESPECIALES
        // ============================================
        $special_chars = preg_match_all('/[^\w\s.,!?\'-]/', $content);
        if ($special_chars > 20) {
            $spam_score += 15;
            $flags[] = 'excessive_special_chars';
        }
        
        // ============================================
        // CALCULAR RESULTADO
        // ============================================
        $is_spam = $spam_score > 50;
        $confidence = min($spam_score / 100, 1.0);
        
        // Ajustar confidence basado en sensibilidad
        $sensitivity = get_option('spamguard_sensitivity', 50);
        $threshold = 50 + (($sensitivity - 50) * 0.5);
        
        $is_spam = $spam_score > $threshold;
        
        // Risk level
        if ($spam_score > 80) {
            $risk_level = 'high';
        } elseif ($spam_score > 50) {
            $risk_level = 'medium';
        } else {
            $risk_level = 'low';
        }
        
        return array(
            'is_spam' => $is_spam,
            'category' => $is_spam ? 'spam' : 'ham',
            'confidence' => $confidence,
            'risk_level' => $risk_level,
            'scores' => array(
                'ham' => 1 - $confidence,
                'spam' => $confidence,
                'phishing' => 0.0
            ),
            'flags' => $flags,
            'processing_time_ms' => 0,
            'spam_score' => $spam_score,
            'request_id' => 'local_' . uniqid(),
            'cached' => false
        );
    }
}