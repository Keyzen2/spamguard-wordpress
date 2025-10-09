<?php
/**
 * SpamGuard Local Fallback
 * Sistema de detección local cuando la API no está disponible
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
        // Constructor privado (Singleton)
    }
    
    /**
     * ✅ Analizar comentario con reglas locales
     */
    public function analyze($comment_data) {
        
        $spam_score = 0;
        $reasons = array();
        
        $content = isset($comment_data['comment_content']) ? $comment_data['comment_content'] : '';
        $author = isset($comment_data['comment_author']) ? $comment_data['comment_author'] : '';
        $author_email = isset($comment_data['comment_author_email']) ? $comment_data['comment_author_email'] : '';
        
        // ============================================
        // 1. HONEYPOT CHECK
        // ============================================
        if (isset($comment_data['honeypot_field']) && !empty($comment_data['honeypot_field'])) {
            $spam_score += 50;
            $reasons[] = 'Honeypot field filled (bot detected)';
        }
        
        // ============================================
        // 2. TIME CHECK
        // ============================================
        if (isset($comment_data['submit_time'])) {
            $elapsed = time() - intval($comment_data['submit_time']);
            $min_time = get_option('spamguard_time_check', 3);
            
            if ($elapsed < $min_time) {
                $spam_score += 30;
                $reasons[] = sprintf('Submitted too fast (%d seconds)', $elapsed);
            }
        }
        
        // ============================================
        // 3. PALABRAS SPAM
        // ============================================
        $spam_keywords = array(
            'viagra', 'cialis', 'casino', 'poker', 'lottery', 'winner',
            'congratulations', 'click here', 'buy now', 'order now',
            'limited time', 'act now', 'free money', 'no cost',
            'risk free', 'weight loss', 'forex', 'bitcoin', 'crypto',
            'payday loan', 'dating', 'singles', 'meet women', 'meet men'
        );
        
        $content_lower = strtolower($content);
        $keyword_count = 0;
        
        foreach ($spam_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $keyword_count++;
                $reasons[] = sprintf('Spam keyword: "%s"', $keyword);
            }
        }
        
        if ($keyword_count > 0) {
            $spam_score += min($keyword_count * 15, 60);
        }
        
        // ============================================
        // 4. ENLACES
        // ============================================
        $link_count = substr_count($content_lower, 'http');
        
        if ($link_count > 5) {
            $spam_score += 40;
            $reasons[] = sprintf('Excessive links (%d)', $link_count);
        } elseif ($link_count > 3) {
            $spam_score += 25;
            $reasons[] = sprintf('Multiple links (%d)', $link_count);
        } elseif ($link_count > 1) {
            $spam_score += 10;
            $reasons[] = sprintf('Several links (%d)', $link_count);
        }
        
        // ============================================
        // 5. CAPITALIZACIÓN
        // ============================================
        $content_len = strlen($content);
        if ($content_len > 0) {
            $caps_count = strlen(preg_replace('/[^A-Z]/', '', $content));
            $caps_ratio = $caps_count / $content_len;
            
            if ($caps_ratio > 0.5) {
                $spam_score += 30;
                $reasons[] = 'Excessive capitalization';
            } elseif ($caps_ratio > 0.3) {
                $spam_score += 15;
                $reasons[] = 'High capitalization';
            }
        }
        
        // ============================================
        // 6. EXCLAMACIONES
        // ============================================
        $exclamation_count = substr_count($content, '!');
        
        if ($exclamation_count > 5) {
            $spam_score += 20;
            $reasons[] = sprintf('Excessive exclamation marks (%d)', $exclamation_count);
        } elseif ($exclamation_count > 3) {
            $spam_score += 10;
            $reasons[] = 'Multiple exclamation marks';
        }
        
        // ============================================
        // 7. EMAIL DESECHABLE
        // ============================================
        $disposable_domains = array(
            'tempmail.com', '10minutemail.com', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email', 'temp-mail.org',
            'trashmail.com', 'yopmail.com', 'fakeinbox.com'
        );
        
        foreach ($disposable_domains as $domain) {
            if (strpos($author_email, $domain) !== false) {
                $spam_score += 40;
                $reasons[] = 'Disposable email address';
                break;
            }
        }
        
        // ============================================
        // 8. CONTENIDO MUY CORTO O MUY LARGO
        // ============================================
        if ($content_len < 10) {
            $spam_score += 20;
            $reasons[] = 'Very short content';
        } elseif ($content_len > 5000) {
            $spam_score += 15;
            $reasons[] = 'Very long content';
        }
        
        // ============================================
        // 9. CARACTERES ESPECIALES
        // ============================================
        $special_chars = preg_match_all('/[^\w\s.,!?\'-]/', $content);
        if ($special_chars > 30) {
            $spam_score += 15;
            $reasons[] = 'Excessive special characters';
        }
        
        // ============================================
        // 10. NOMBRE DE AUTOR SOSPECHOSO
        // ============================================
        if (preg_match('/\d{4,}/', $author)) {
            $spam_score += 20;
            $reasons[] = 'Suspicious author name (contains numbers)';
        }
        
        if (strlen($author) < 3) {
            $spam_score += 15;
            $reasons[] = 'Very short author name';
        }
        
        // ============================================
        // CALCULAR RESULTADO FINAL
        // ============================================
        
        // Obtener sensibilidad del usuario
        $sensitivity = get_option('spamguard_sensitivity', 50);
        $threshold = 50 + (($sensitivity - 50) * 0.5);
        
        $is_spam = $spam_score > $threshold;
        $confidence = min($spam_score / 100, 1.0);
        
        // Risk level
        if ($spam_score > 80) {
            $risk_level = 'high';
        } elseif ($spam_score > 50) {
            $risk_level = 'medium';
        } else {
            $risk_level = 'low';
        }
        
        // Si no es spam, dar razones positivas
        if (!$is_spam && empty($reasons)) {
            $reasons[] = 'No spam indicators detected';
        }
        
        return array(
            'is_spam' => $is_spam,
            'confidence' => $confidence,
            'spam_score' => $spam_score,
            'reasons' => $reasons,
            'comment_id' => '',
            'explanation' => array(
                'source' => 'local_fallback',
                'threshold' => $threshold,
                'sensitivity' => $sensitivity
            ),
            'risk_level' => $risk_level,
            'category' => $is_spam ? 'spam' : 'ham'
        );
    }
}
