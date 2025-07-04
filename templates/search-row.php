<?php
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Universal search row template with consistent structure
 */

// Get default values or use provided ones
$type = isset($args['type']) ? $args['type'] : 'standard';
$icon = isset($args['icon']) ? $args['icon'] : '';
$term = isset($args['term']) ? $args['term'] : '';
$term_link = isset($args['term_link']) ? $args['term_link'] : '';
$count = isset($args['count']) ? $args['count'] : '';
$time = isset($args['time']) ? $args['time'] : '';
$status = isset($args['status']) ? $args['status'] : '';
$status_icon = isset($args['status_icon']) ? $args['status_icon'] : '';
$status_text = isset($args['status_text']) ? $args['status_text'] : '';
$success_rate = isset($args['success_rate']) ? $args['success_rate'] : '';
$extra_stats = isset($args['extra_stats']) ? $args['extra_stats'] : array();

// If we don't have a term link but have a term, generate the link
if (empty($term_link) && !empty($term)) {
    $term_link = WPSI::$admin->get_term_link($term);
}

switch ($type):
    default:
        // All other row types use the same div structure for consistency
        // The classes maintain backward compatibility
        $container_class = 'wpsi-search-row';
        if ($type === 'dashboard') $container_class .= ' dashboard-row';
        if ($type === 'grid') $container_class .= ' grid-row';
        ?>
        <div class="<?php echo esc_attr($container_class); ?>">
            <div class="wpsi-row-term">
                <?php if (!empty($icon)): ?>
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php endif; ?>

                <?php if (!empty($status)): ?>
                    <div class="wpsi-status wpsi-status-<?php echo esc_attr($status); ?>">
                        <?php if (!empty($status_icon)): ?>
                            <span class="dashicons <?php echo esc_attr($status_icon); ?>"></span>
                        <?php endif; ?>
                        <?php if (!empty($status_text)): ?>
                            <?php echo esc_html($status_text); ?>
                        <?php else: ?>
                            <?php echo wp_kses_post($term_link); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php echo wp_kses_post($term_link); ?>
                <?php endif; ?>
            </div>

            <div class="wpsi-row-stats">
                <?php if (!empty($success_rate)): ?>
                    <span class="wpsi-stat-success-rate">
                        <?php
                        /* translators: %s: success rate percentage value */
                        echo esc_html(sprintf(__("%.0f%%", "wp-search-insights"), $success_rate));
                        ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($count)): ?>
                    <span class="wpsi-stat-count">
            <?php echo wp_kses_post($count); ?>
        </span>
                <?php endif; ?>

                <?php if (!empty($time)): ?>
                    <span class="wpsi-stat-time">
            <?php echo esc_html($time); ?>
        </span>
                <?php endif; ?>

                <?php // Display any additional stats
                foreach ($extra_stats as $label => $value): ?>
                    <span class="wpsi-stat-<?php echo esc_attr(sanitize_title($label)); ?>">
            <?php echo wp_kses_post($value); ?>
        </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        break;
endswitch;