<?php
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Universal container template for search row collections
 */

$container_type = isset($args['container_type']) ? $args['container_type'] : 'standard';
$title = isset($args['title']) ? $args['title'] : '';
$content = isset($args['content']) ? $args['content'] : '';
$help_text = isset($args['help_text']) ? $args['help_text'] : '';
$empty_text = isset($args['empty_text']) ? $args['empty_text'] : __('No data available', 'wp-search-insights');
$scrollable = isset($args['scrollable']) ? $args['scrollable'] : true;
$is_dashboard_widget = isset($args['is_dashboard_widget']) ? $args['is_dashboard_widget'] : false;

// Container attributes
$container_class = 'wpsi-row-container';
if (!empty($args['container_class'])) {
    $container_class .= ' ' . $args['container_class'];
}

// Add legacy classes for backward compatibility
if ($container_type === 'dashboard') {
    $container_class .= ' wpsi-dashboard-widget-grid';
}

// Determine content wrapper tag and class
if ($container_type === 'table') {
    // Only tables need specific HTML structure
    $content_tag = 'table';
    $container_class .= ' wpsi-trending-terms';
} else {
    // All other containers use div for consistency
    $content_tag = 'div';
}

// Content wrapper class
$content_class = $scrollable ? 'wpsi-rows-wrap' : '';
if (!empty($args['content_class'])) {
    $content_class .= ' ' . $args['content_class'];
}

// Add legacy class for backward compatibility with dashboard
if ($container_type === 'dashboard') {
    $content_class .= ' wpsi-dashboard-list';
}
?>

<div class="<?php echo esc_attr($container_class); ?>">
    <?php if (!empty($title)): ?>
        <div class="wpsi-container-header">
            <h3>
                <?php echo esc_html($title); ?>
                <?php if (!empty($help_text) && !$is_dashboard_widget) echo wp_kses_post(WPSI::$help->get_help_tip($help_text)); ?>
            </h3>
        </div>
    <?php endif; ?>

    <?php if (!empty($content)): ?>
    <<?php echo esc_attr($content_tag); ?> class="<?php echo esc_attr($content_class); ?>">
    <?php
    // For table content, we need table structure
    if ($container_type === 'table'): ?>
    <?php else: ?>
        <?php echo wp_kses_post($content); ?>
    <?php endif; ?>
</<?php echo esc_attr($content_tag); ?>>
<?php else: ?>
    <div class="wpsi-empty-content">
        <p><?php echo esc_html($empty_text); ?></p>
    </div>
<?php endif; ?>
</div>