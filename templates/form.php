<?php

/**
 * Template for displaying Zarinpal payment form.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/zarinpal-payment/form.php.
 *
 * @author   Amirhossein Taghizadeh
 * @link     https://zarinpal.com
 * @package  LearnPress/Zarinpal/Classes
 * @version  2.0.0
 */

/**
 * Prevent loading this file directly
 */
defined('ABSPATH') || exit();
?>

<?php
$settings = LP()->settings; ?>

<p><?php
    echo $this->get_description(); ?></p>

<div id="learn-press-zarinpal-form" class="<?php
if (is_rtl()) {
    echo ' learn-press-form-zarinpal-rtl';
} ?>">
    <p class="learn-press-form-row">
        <label><?php
            echo wp_kses(__('Email', 'learnpress-zarinpal'), array('span' => array())); ?></label>
        <input type="text" name="learn-press-zarinpal[email]" id="learn-press-zarinpal-payment-email" maxlength="19"
               value="" placeholder="test@zarinpal.com"/>
    <div class="learn-press-zarinpal-form-clear"></div>
    </p>
    <div class="learn-press-zarinpal-form-clear"></div>
    <p class="learn-press-form-row">
        <label><?php
            echo wp_kses(__('Mobile', 'learnpress-zarinpal'), array('span' => array())); ?></label>
        <input type="text" name="learn-press-zarinpal[mobile]" id="learn-press-zarinpal-payment-mobile" value=""
               placeholder="09123456789 حتما با 09 شروع شود"/>
    <div class="learn-press-zarinpal-form-clear"></div>
    </p>
    <div class="learn-press-zarinpal-form-clear"></div>
</div>
<script>
    jQuery(document).ready(function($) {
        $('.payment_method_zarinpal').css('display', 'block');
    });
</script>
