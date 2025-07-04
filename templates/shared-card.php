<?php
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Shared template for data cards used across different blocks
 * Expected variables:
 * $title - Card title
 * $number - Main number/value to display
 * $footer - Optional footer text/content
 * $card_class - Additional classes for card styling (success, warning, etc)
 * $help_text - Optional help tooltip text
 */
?>
<div class="wpsi-card-container">
    <h3 class="wpsi-card-title">
        <?php echo esc_html($title); ?>
        <?php if (!empty($help_text)) echo wp_kses_post(WPSI::$help->get_help_tip(esc_html($help_text))); ?>
    </h3>
    <div class="wpsi-data-card <?php echo esc_attr($card_class); ?>">
        <div class="wpsi-card-number">
            <?php echo wp_kses_post($number); ?>
        </div>
        <?php if (!empty($footer)): ?>
            <div class="wpsi-card-footer">
                <?php echo wp_kses_post($footer); ?>
            </div>
        <?php endif; ?>
    </div>
</div>