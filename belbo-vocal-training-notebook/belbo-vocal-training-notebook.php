<?php
/**
 * Plugin Name: べるぼ ボーカル研究ノート
 * Plugin URI: https://github.com/UmbrellaParade/belbo-vocal-training-notebook
 * Description: 歌の気づき、Codexの考察、練習による検証結果を分けて蓄積・振り返るための個人用ノートです。
 * Version: 1.0.0
 * Author: Umbrella Parade
 * Update URI: https://github.com/UmbrellaParade/belbo-vocal-training-notebook
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: belbo-vocal-training-notebook
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BVTN_VERSION', '1.0.0');
define('BVTN_FILE', __FILE__);
define('BVTN_DIR', plugin_dir_path(__FILE__));
define('BVTN_URL', plugin_dir_url(__FILE__));

require_once BVTN_DIR . 'includes/class-bvtn-plugin.php';
require_once BVTN_DIR . 'includes/class-bvtn-updater.php';

register_activation_hook(__FILE__, array('BVTN_Plugin', 'activate'));
add_action('plugins_loaded', static function () {
    BVTN_Plugin::instance();
    new BVTN_Updater(
        BVTN_FILE,
        BVTN_VERSION,
        'UmbrellaParade',
        'belbo-vocal-training-notebook'
    );
});
