<?php
/*
Plugin Name: افزونه پرداخت زرین پال برای لرن پرس
Description: افزونه پرداخت امن زرین پال برای لرن پرس
Author: Amirhossein Taghizadeh
Link: https://zarinpal.com
Version: 2.0.0
Author URI: https://github.com/Amyrosein
Tags: learnpress,zarinpal,gateway,payment,زیبال,lms,لرن پرس
Text Domain: learnpress-zarinpal
Domain Path: /languages/
*/

// Prevent loading this file directly
defined('ABSPATH') || exit;

define('LP_ADDON_ZARINPAL_PAYMENT_FILE', __FILE__);
define('LP_ADDON_ZARINPAL_PAYMENT_VER', '1.0.0');
define('LP_ADDON_ZARINPAL_PAYMENT_REQUIRE_VER', '1.0.0');

/**
 * Class LP_Addon_Zarinpal_Payment_Preload
 */
class LP_Addon_Zarinpal_Payment_Preload
{

	/**
	 * LP_Addon_Zarinpal_Payment_Preload constructor.
	 */
	public function __construct()
	{
		load_plugin_textdomain('learnpress-zarinpal', false, basename(dirname(__FILE__)) . '/languages');
		add_action('learn-press/ready', array($this, 'load'));
		add_action('admin_notices', array($this, 'admin_notices'));
	}

	/**
	 * Load addon
	 */
	public function load()
	{
		LP_Addon::load('LP_Addon_Zarinpal_Payment', 'inc/load.php', __FILE__);
		remove_action('admin_notices', array($this, 'admin_notices'));
	}

	/**
	 * Admin notice
	 */
	public function admin_notices()
	{
?>
		<div class="error">
			<p><?php echo wp_kses(
					sprintf(
						__('<strong>%s</strong> addon version %s requires %s version %s or higher is <strong>installed</strong> and <strong>activated</strong>.', 'learnpress-zarinpal'),
						__('LearnPress Zarinpal Payment', 'learnpress-zarinpal'),
						LP_ADDON_ZARINPAL_PAYMENT_VER,
						sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', admin_url('plugin-install.php?tab=search&type=term&s=learnpress'), __('LearnPress', 'learnpress-zarinpal')),
						LP_ADDON_ZARINPAL_PAYMENT_REQUIRE_VER
					),
					array(
						'a'      => array(
							'href'  => array(),
							'blank' => array()
						),
						'strong' => array()
					)
				); ?>
			</p>
		</div>
<?php
	}
}

new LP_Addon_Zarinpal_Payment_Preload();
