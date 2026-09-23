<?php

if (!defined('ABSPATH')) {
    exit;
}

final class BVTN_Updater
{
    private $plugin_file;
    private $plugin_basename;
    private $version;
    private $owner;
    private $repository;

    public function __construct(string $plugin_file, string $version, string $owner, string $repository)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->version = $version;
        $this->owner = $owner;
        $this->repository = $repository;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_updates'));
        add_filter('plugins_api', array($this, 'plugin_information'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'normalize_folder'), 10, 4);
    }

    public function check_updates($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }
        $release = $this->release();
        if (!$release || empty($release['version']) || version_compare($this->version, $release['version'], '>=')) {
            return $transient;
        }
        $transient->response[$this->plugin_basename] = (object) array(
            'slug' => $this->repository,
            'plugin' => $this->plugin_basename,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['package'],
            'icons' => array(),
            'banners' => array(),
            'tested' => '',
            'requires_php' => '7.4',
        );
        return $transient;
    }

    public function plugin_information($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->repository) {
            return $result;
        }
        $release = $this->release();
        if (!$release) {
            return $result;
        }
        return (object) array(
            'name' => 'べるぼ ボーカル研究ノート',
            'slug' => $this->repository,
            'version' => $release['version'],
            'author' => 'Umbrella Parade',
            'homepage' => $release['html_url'],
            'download_link' => $release['package'],
            'requires' => '6.0',
            'requires_php' => '7.4',
            'sections' => array(
                'description' => '歌の気づき、Codexの考察、練習結果を分けて蓄積する個人用研究ノートです。',
                'changelog' => nl2br(esc_html($release['notes'])),
            ),
        );
    }

    public function normalize_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }
        $desired = trailingslashit($remote_source) . $this->repository . '/';
        if ($source !== $desired && is_dir($source) && !file_exists($desired)) {
            if (@rename($source, $desired)) {
                return $desired;
            }
        }
        return $source;
    }

    private function release(): ?array
    {
        $cache_key = 'bvtn_release_' . md5($this->owner . '/' . $this->repository);
        $cached = get_site_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', rawurlencode($this->owner), rawurlencode($this->repository));
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
            ),
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient($cache_key, array(), 15 * MINUTE_IN_SECONDS);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['tag_name'])) {
            return null;
        }

        $package = '';
        foreach (($body['assets'] ?? array()) as $asset) {
            if (($asset['name'] ?? '') === $this->repository . '.zip') {
                $package = esc_url_raw($asset['browser_download_url'] ?? '');
                break;
            }
        }
        if ($package === '') {
            return null;
        }

        $release = array(
            'version' => ltrim((string) $body['tag_name'], 'vV'),
            'html_url' => esc_url_raw($body['html_url'] ?? ''),
            'package' => $package,
            'notes' => sanitize_textarea_field($body['body'] ?? ''),
        );
        set_site_transient($cache_key, $release, 30 * MINUTE_IN_SECONDS);
        return $release;
    }
}
