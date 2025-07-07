<?php

defined('ABSPATH') or die("you do not have access to this page!");

if (!class_exists('WPSI_Spam_Filter')) {
    class WPSI_Spam_Filter
    {
        private static $_this;
        private float $pattern_weight = 0.6;
        private float $entropy_weight = 0.4;

        function __construct()
        {
            if (isset(self::$_this)) {
                wp_die(
                    esc_html(
                        sprintf(
                        /* translators: %s: class name */
                            __('%s is a singleton class and you cannot create a second instance.', 'wp-search-insights'),
                            get_class($this)
                        )
                    )
                );
            }

            self::$_this = $this;
        }

        static function this()
        {
            return self::$_this;
        }

        /**
         * Main spam detection method
         *
         * @param string $search_term The search term to analyze
         * @return bool True if spam, false if legitimate
         */
        public function is_spam($search_term)
        {

            // Emergency disable constant check (highest priority)
            if (defined('WPSI_DISABLE_SPAM_FILTER') && WPSI_DISABLE_SPAM_FILTER) {
                return false;
            }

            // Decode HTML entities to analyze the actual characters
            $decoded_term = html_entity_decode($search_term, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Allow filtering to override spam detection
            $override = apply_filters('wpsi_spam_filter_override', null, $decoded_term);
            if ($override !== null) {
                return $override;
            }

            $score = $this->calculate_spam_score($decoded_term);

            $threshold = apply_filters('wpsi_spam_threshold', 60);

            return $score > $threshold;
        }

        /**
         * Calculate spam confidence score (0-100)
         *
         * @param string $search_term
         * @return int Spam score
         */
        public function calculate_spam_score($search_term)
        {

            // === PATTERN-BASED RULES (High confidence) ===
            $pattern_score = $this->check_patterns($search_term);
            if ($pattern_score > 60) {
                // High confidence patterns - skip detailed analysis
                return min($pattern_score, 100);
            }

            // === STATISTICAL ANALYSIS (Fine-tuning) ===
            $entropy_score = $this->calculate_entropy_score($search_term);

            // Weighted combination
            $final_score =
                $pattern_score * $this->pattern_weight +
                $entropy_score * $this->entropy_weight;

            return min(round($final_score), 100);
        }

        /**
         * Check for high-confidence spam patterns
         *
         * @param string $search_term
         * @return int Pattern score
         */
        private function check_patterns($search_term)
        {
            $high_confidence_patterns = [
                '/^[!@#$%^&*()_+=\|\\\[\]{};:,<.>\/?~`-]{3,}/' => 80, // 3+ special chars at start (definitely spam)
                '/^[!@#$%^&*()_+=\|\\\[\]{};:,<.>\/?~`-]{2}/' => 55, // 2 special chars at start (programming commands like --, ::)
                '/[!@#$%^&*()_+=\|\\\[\]{};:,<.>\/?~`-]{2,}$/' => 55, // 2+ special chars at end (spam indicators)
                '/^(?=.*[0-9])(?=.*[a-z])[a-z0-9]{14,}$/i' => 85, // Mixed alphanumeric 14+ chars (must have both letters AND numbers)
                '/(.)\1{4,}/' => 70,                  // aaaaa repeated chars
                '/^[0-9]{8,}$/' => 75,                // Pure numbers 8+ digits
                '/[<>"\{\}\[\]]/' => 90,              // Injection attempts (excluding apostrophe for Ukrainian)
                '/^[!@#$%^&*()_+=\|\\\[\]{};:,<.>\/?~`-]+$/' => 70, // Only ASCII punctuation (excluding apostrophe for Ukrainian)
                '/^[A-Z]{5,}$/' => 50,                // All caps words (moderate spam)
                '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION)\b.*\b(FROM|WHERE|TABLE|INTO|SET|VALUES)\b/i' => 95, // SQL injection (requires SQL context)
                '/javascript:/i' => 95,               // JavaScript injection
                '/[!@#$%^&*()_+=\|\\\[\]{};:,<.>\/?]{3,}/' => 70, // 3+ suspicious special chars (excluding scientific notation)
                '/^[a-zA-Z0-9+\/]+=+$/' => 80,        // Base64 encoded strings (ends with =)
                '/^(%[0-9A-Fa-f]{2}){4,}/' => 85,    // URL encoded strings (%xx pattern)
            ];

            // Add custom spam keywords from filter (for future extensibility)
            $spam_keywords = apply_filters('wpsi_spam_keywords', []);
            if (!empty($spam_keywords)) {
                $keywords_pattern = '/\b(' . implode('|', array_map('preg_quote', $spam_keywords)) . ')\b/i';
                $high_confidence_patterns[$keywords_pattern] = 95;
            }

            $total_score = 0;
            $matched_patterns = [];

            foreach ($high_confidence_patterns as $pattern => $penalty) {
                if (preg_match($pattern, $search_term)) {
                    // High confidence patterns (80+) return immediately
                    if ($penalty >= 80) {
                        return $penalty;
                    }
                    // Lower patterns accumulate (but only once per unique pattern)
                    $pattern_key = md5($pattern);
                    if (!isset($matched_patterns[$pattern_key])) {
                        $total_score += $penalty;
                        $matched_patterns[$pattern_key] = true;
                    }
                }
            }

            return $total_score;
        }

        /**
         * Calculate entropy-based score
         *
         * @param string $search_term
         * @return int Entropy score
         */
        private function calculate_entropy_score($search_term)
        {
            $score = 0;
            $length = strlen($search_term);

            // Length analysis
            if ($length > 30) $score += 20;
            if ($length < 2) $score += 30;
            if ($length > 50) $score += 40;

            // Character composition analysis
            $numbers = preg_match_all('/[0-9]/', $search_term);
            $letters = preg_match_all('/[a-zA-Z]/', $search_term);
            $special = preg_match_all('/[^a-zA-Z0-9\s]/', $search_term);

            // High number ratio
            if ($letters > 0 && $numbers > $letters * 2) {
                $score += 25;
            }

            // Too many special chars
            if ($special > $length * 0.3) {
                $score += 30;
            }

            // All caps (common in spam) - but only for longer strings
            if (strlen($search_term) > 2 && ctype_upper(str_replace([' ', '-', '_'], '', $search_term))) {
                $score += 15;
            }

            // Entropy analysis (randomness)
            $entropy = $this->calculate_entropy($search_term);
            if ($entropy > 4.5) {
                $score += 25;
            }
            if ($entropy > 5.0) {
                $score += 35;
            }

            // Consonant clusters
            if ($this->has_excessive_consonant_clusters($search_term)) {
                $score += 20;
            }

            return $score;
        }

        /**
         * Calculate Shannon entropy
         *
         * @param string $string
         * @return float Entropy value
         */
        private function calculate_entropy($string)
        {
            $length = strlen($string);
            if ($length <= 1) return 0;

            $frequencies = array_count_values(str_split(strtolower($string)));
            $entropy = 0;

            foreach ($frequencies as $frequency) {
                $probability = $frequency / $length;
                $entropy -= $probability * log($probability, 2);
            }

            return $entropy;
        }

        /**
         * Check for excessive consonant clusters
         *
         * @param string $search_term
         * @return bool
         */
        private function has_excessive_consonant_clusters($search_term)
        {
            // Count consonant clusters of 4+ characters
            $consonant_clusters = preg_match_all('/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]{4,}/', $search_term);
            return $consonant_clusters > 0;
        }


        /**
         * Get spam detection statistics
         *
         * @return array Statistics
         */
        public function get_stats()
        {
            $stats = get_option('wpsi_spam_filter_stats', [
                'total_checked' => 0,
                'spam_blocked' => 0,
                'false_positives' => 0,
                'last_reset' => time()
            ]);

            return $stats;
        }

        /**
         * Update spam detection statistics
         *
         * @param string $action 'checked', 'blocked', 'false_positive'
         */
        public function update_stats($action)
        {
            $stats = $this->get_stats();

            switch ($action) {
                case 'checked':
                    $stats['total_checked']++;
                    break;
                case 'blocked':
                    $stats['spam_blocked']++;
                    break;
                case 'false_positive':
                    $stats['false_positives']++;
                    break;
            }

            update_option('wpsi_spam_filter_stats', $stats);
        }
    }
}
