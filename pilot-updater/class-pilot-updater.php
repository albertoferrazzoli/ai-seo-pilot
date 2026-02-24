<?php
/**
 * Pilot Updater - Self-hosted update checker for Pilot plugins.
 *
 * Include this file in your plugin and instantiate it:
 *
 *   require_once __DIR__ . '/pilot-updater/class-pilot-updater.php';
 *   new Pilot_Updater('chatbot-pilot', __FILE__);
 *
 * Then call this in your settings page General tab to render the license field:
 *
 *   Pilot_Updater::render_license_field('chatbot-pilot');
 *
 * @version 1.1.0
 */

if (!defined('ABSPATH')) exit;

if (class_exists('Pilot_Updater')) return;

class Pilot_Updater {

    const API_BASE = 'https://www.slotix.ai/wp-json/pilot-updates/v1';
    const CACHE_TTL = 43200; // 12 hours

    /** @var array<string, Pilot_Updater> Registry of all instances */
    private static $instances = [];

    /** @var string Plugin slug */
    private $slug;

    /** @var string Plugin basename */
    private $basename;

    /** @var string Current installed version */
    private $version;

    /** @var string Option name for the license key */
    private $license_option;

    /** @var string Transient name for caching update info */
    private $cache_key;

    /**
     * @param string $slug        Plugin slug (must match server-side registration)
     * @param string $plugin_file Path to the main plugin file (__FILE__ from the plugin)
     */
    public function __construct($slug, $plugin_file) {
        $this->slug = $slug;
        $this->basename = plugin_basename($plugin_file);
        $this->license_option = "pilot_license_{$slug}";
        $this->cache_key = "pilot_update_{$slug}";

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data($plugin_file, false, false);
        $this->version = $data['Version'] ?? '0.0.0';

        // Register instance
        self::$instances[$slug] = $this;

        // WordPress update hooks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_action('in_plugin_update_message-' . $this->basename, [$this, 'update_message'], 10, 2);

        // AJAX handler for saving license from settings page
        add_action('wp_ajax_pilot_save_license_' . $this->slug, [$this, 'ajax_save_license']);

        // Clear cache when license key changes
        add_action('update_option_' . $this->license_option, [$this, 'clear_cache']);
    }

    /**
     * Render the license key field as a <tr> inside a form-table.
     * Call this from your plugin's settings page General tab.
     *
     * @param string $slug Plugin slug
     */
    public static function render_license_field($slug) {
        $instance = self::$instances[$slug] ?? null;
        if (!$instance) return;

        $license_key = get_option($instance->license_option, '');
        $nonce = wp_create_nonce('pilot_license_' . $slug);
        $field_id = 'pilot-key-' . $slug;

        ?>
        <tr valign="top">
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>">License Key</label>
            </th>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input
                        type="text"
                        id="<?php echo esc_attr($field_id); ?>"
                        value="<?php echo esc_attr($license_key); ?>"
                        placeholder="PILOT-XXXX-XXXX-XXXX-XXXX"
                        class="regular-text"
                        style="font-family:monospace;"
                    >
                    <button type="button" class="button" id="pilot-verify-<?php echo esc_attr($slug); ?>">
                        Verify &amp; Save
                    </button>
                    <span id="pilot-status-<?php echo esc_attr($slug); ?>">
                        <?php if ($license_key): ?>
                            <span style="color:green;">&#10003; Active</span>
                        <?php endif; ?>
                    </span>
                </div>
                <p class="description">
                    Enter your license key to enable automatic updates.
                    You can find it in your <a href="https://www.slotix.ai/my-account/orders/" target="_blank">order confirmation</a>.
                </p>
            </td>
        </tr>
        <script>
        (function() {
            var slug = <?php echo wp_json_encode($slug); ?>;
            var btn = document.getElementById('pilot-verify-' + slug);
            var input = document.getElementById('pilot-key-' + slug);
            var status = document.getElementById('pilot-status-' + slug);

            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = 'Verifying...';
                status.innerHTML = '';

                var data = new FormData();
                data.append('action', 'pilot_save_license_' + slug);
                data.append('license_key', input.value.trim());
                data.append('_wpnonce', <?php echo wp_json_encode($nonce); ?>);

                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(r) {
                        btn.disabled = false;
                        btn.textContent = 'Verify & Save';
                        if (r.success) {
                            status.innerHTML = '<span style="color:green;">&#10003; ' + r.data + '</span>';
                        } else {
                            status.innerHTML = '<span style="color:red;">&#10007; ' + r.data + '</span>';
                        }
                    })
                    .catch(function() {
                        btn.disabled = false;
                        btn.textContent = 'Verify & Save';
                        status.innerHTML = '<span style="color:red;">&#10007; Connection error</span>';
                    });
            });
        })();
        </script>
        <?php
    }

    /**
     * Check for plugin updates.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote['version'])) {
            return $transient;
        }

        if (version_compare($remote['version'], $this->version, '>')) {
            $transient->response[$this->basename] = (object) [
                'slug'         => $this->slug,
                'plugin'       => $this->basename,
                'new_version'  => $remote['version'],
                'url'          => $remote['plugin_uri'] ?? '',
                'package'      => $remote['download_url'] ?? '',
                'icons'        => [],
                'banners'      => [],
                'requires'     => $remote['requires'] ?? '6.0',
                'requires_php' => $remote['requires_php'] ?? '7.4',
                'tested'       => $remote['tested'] ?? '',
            ];
        } else {
            $transient->no_update[$this->basename] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $remote['version'],
                'url'         => $remote['plugin_uri'] ?? '',
                'package'     => '',
            ];
        }

        return $transient;
    }

    /**
     * Show a message in the update row when license key is missing.
     * Hooks into in_plugin_update_message-{basename}.
     */
    public function update_message($plugin_data, $response) {
        $license_key = get_option($this->license_option, '');
        if (empty($license_key)) {
            echo ' <strong>' . sprintf(
                __('Please enter your license key in %s to enable automatic updates.', 'pilot-updater'),
                '<a href="' . esc_url($this->get_settings_url()) . '">Settings</a>'
            ) . '</strong>';
        }
    }

    /**
     * Get the URL to the plugin's settings page.
     *
     * @return string
     */
    private function get_settings_url() {
        $settings_urls = [
            'chatbot-pilot'   => admin_url('options-general.php?page=chatbot-pilot'),
            'mail-pilot'      => admin_url('admin.php?page=mail-pilot&tab=general'),
            'polyglot'        => admin_url('admin.php?page=polyglot'),
            'security-pilot'  => admin_url('admin.php?page=security-pilot'),
            'cookie-consent'  => admin_url('options-general.php?page=cookie-consent'),
            'tfa-auth-pilot'  => admin_url('options-general.php?page=two-factor-auth'),
        ];

        return $settings_urls[$this->slug] ?? admin_url('plugins.php');
    }

    /**
     * Provide plugin information for the "View details" modal.
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }

        $remote = $this->get_remote_info();
        if (!$remote) {
            return $result;
        }

        return (object) [
            'name'           => $remote['name'] ?? $this->slug,
            'slug'           => $this->slug,
            'version'        => $remote['version'],
            'author'         => $remote['author'] ?? 'Slotix',
            'author_profile' => $remote['author_uri'] ?? 'https://slotix.ai',
            'homepage'       => $remote['plugin_uri'] ?? '',
            'description'    => $remote['description'] ?? '',
            'requires'       => $remote['requires'] ?? '6.0',
            'requires_php'   => $remote['requires_php'] ?? '7.4',
            'tested'         => $remote['tested'] ?? '',
            'download_link'  => $remote['download_url'] ?? '',
            'sections'       => [
                'description' => $remote['description'] ?? '',
            ],
        ];
    }

    /**
     * AJAX handler: save license key and validate it.
     */
    public function ajax_save_license() {
        check_ajax_referer('pilot_license_' . $this->slug);

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $license_key = sanitize_text_field($_POST['license_key'] ?? '');

        if (empty($license_key)) {
            delete_option($this->license_option);
            $this->clear_cache();
            wp_send_json_success('License removed');
        }

        $response = wp_remote_get(self::API_BASE . "/info/{$this->slug}?license_key=" . urlencode($license_key), [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['license_valid'])) {
            update_option($this->license_option, $license_key, false);
            $this->clear_cache();
            wp_send_json_success('Active');
        } else {
            wp_send_json_error('Invalid license key');
        }
    }

    /**
     * Fetch remote plugin info (cached).
     *
     * @return array|null
     */
    private function get_remote_info() {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $license_key = get_option($this->license_option, '');
        $url = self::API_BASE . "/info/{$this->slug}";
        if ($license_key) {
            $url .= '?license_key=' . urlencode($license_key);
        }

        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response)) {
            return null;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['version'])) {
            return null;
        }

        set_transient($this->cache_key, $body, self::CACHE_TTL);

        return $body;
    }

    /**
     * Clear the update cache.
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
        delete_site_transient('update_plugins');
    }
}
