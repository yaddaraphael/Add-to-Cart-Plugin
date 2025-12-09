<?php
/**
 * Plugin Name: WC – Enquiry Form On Add To Cart
 * Description: Replaces Add to cart with an enquiry modal (name, email, phone, state, address, comment). Also allows products with no prices to show the Add to cart button, and lets you configure notification emails.
 * Version: 1.4.1
 * Author: Yadda
 * License: GPL-2.0+
 * Text Domain: wc-enquiry-add-to-cart
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WC_Enquiry_On_Add_To_Cart {

    const NONCE_ACTION = 'wc_enquiry_submit';

    public function __construct() {
        // Allow products without a price to still show Add to cart.
        add_filter('woocommerce_is_purchasable', [$this, 'allow_no_price_purchasable'], 10, 2);

        // Frontend assets (CSS/JS).
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Modal markup in footer.
        add_action('wp_footer', [$this, 'render_enquiry_modal']);

        // AJAX handlers.
        add_action('wp_ajax_wc_enquiry_submit', [$this, 'handle_enquiry_submit']);
        add_action('wp_ajax_nopriv_wc_enquiry_submit', [$this, 'handle_enquiry_submit']);

        // Admin settings page + options.
        add_action('admin_menu',  [$this, 'add_settings_page']);
        add_action('admin_init',  [$this, 'register_settings']);
    }

    /* ---------------------------------------------------------
     *  Frontend: Woo logic
     * ------------------------------------------------------ */

    /**
     * Allow products without a price to be treated as purchasable
     * so WooCommerce shows the Add to cart button.
     */
    public function allow_no_price_purchasable($purchasable, $product) {
        if ('' === $product->get_price() || null === $product->get_price()) {
            return true;
        }
        return $purchasable;
    }

    /**
     * Enqueue CSS & JS.
     * We load this on all frontend pages as long as WooCommerce is active,
     * so it works on homepage product grids too.
     */
    public function enqueue_assets() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        wp_enqueue_style(
            'wc-enquiry-style',
            plugin_dir_url(__FILE__) . 'assets/css/wc-enquiry.css',
            [],
            '1.4.1'
        );

        // We depend on WooCommerce's wc-add-to-cart script so we can override its handlers.
        wp_enqueue_script(
            'wc-enquiry-script',
            plugin_dir_url(__FILE__) . 'assets/js/wc-enquiry.js',
            ['jquery', 'wc-add-to-cart'],
            '1.4.1',
            true
        );

        wp_localize_script('wc-enquiry-script', 'WCEnquiry', [
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce(self::NONCE_ACTION),
            'success_message' => __('Thank you for your enquiry. Our team will contact you shortly.', 'wc-enquiry-add-to-cart'),
            'error_message'   => __('Something went wrong. Please try again.', 'wc-enquiry-add-to-cart'),
        ]);
    }

    /**
     * Render the enquiry modal markup.
     */
    public function render_enquiry_modal() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        ?>
        <div id="wc-enquiry-modal" class="wc-enquiry-modal" aria-hidden="true">
            <div class="wc-enquiry-modal-inner" role="dialog" aria-modal="true" aria-labelledby="wc-enquiry-title">
                <button
                    type="button"
                    id="wc-enquiry-close"
                    class="wc-enquiry-close"
                    aria-label="<?php esc_attr_e('Close', 'wc-enquiry-add-to-cart'); ?>"
                >
                    &times;
                </button>

                <h2 id="wc-enquiry-title" class="wc-enquiry-title">
                    <?php esc_html_e('Product Enquiry', 'wc-enquiry-add-to-cart'); ?>
                </h2>

                <p class="wc-enquiry-product-label">
                    <?php esc_html_e('You are enquiring about:', 'wc-enquiry-add-to-cart'); ?>
                    <strong id="wc-enquiry-product-name"></strong>
                </p>

                <form id="wc-enquiry-form">
                    <input type="hidden" name="action" value="wc_enquiry_submit" />
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce(self::NONCE_ACTION)); ?>" />
                    <input type="hidden" id="wc-enquiry-product-id" name="product_id" value="" />
                    <input type="hidden" id="wc-enquiry-product-url" name="product_url" value="" />

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-name"><?php esc_html_e('Your Name', 'wc-enquiry-add-to-cart'); ?> *</label>
                        <input type="text" id="wc-enquiry-name" name="name" required />
                    </div>

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-email"><?php esc_html_e('Your Email', 'wc-enquiry-add-to-cart'); ?> *</label>
                        <input type="email" id="wc-enquiry-email" name="email" required />
                    </div>

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-phone"><?php esc_html_e('Phone Number', 'wc-enquiry-add-to-cart'); ?></label>
                        <input type="text" id="wc-enquiry-phone" name="phone" />
                    </div>

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-state"><?php esc_html_e('State / County / Region', 'wc-enquiry-add-to-cart'); ?></label>
                        <input type="text" id="wc-enquiry-state" name="state" />
                    </div>

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-address"><?php esc_html_e('Address', 'wc-enquiry-add-to-cart'); ?></label>
                        <input type="text" id="wc-enquiry-address" name="address" />
                    </div>

                    <div class="wc-enquiry-field">
                        <label for="wc-enquiry-message"><?php esc_html_e('Comment / Enquiry', 'wc-enquiry-add-to-cart'); ?> *</label>
                        <textarea id="wc-enquiry-message" name="message" rows="4" required></textarea>
                    </div>

                    <div class="wc-enquiry-actions">
                        <button type="submit" class="wc-enquiry-submit">
                            <?php esc_html_e('Send Enquiry', 'wc-enquiry-add-to-cart'); ?>
                        </button>
                    </div>

                    <p id="wc-enquiry-feedback" class="wc-enquiry-feedback" style="display:none;"></p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX form submission.
     */
    public function handle_enquiry_submit() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email       = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone       = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $state       = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        $address     = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
        $message     = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $product_id  = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product_url = isset($_POST['product_url']) ? esc_url_raw(wp_unslash($_POST['product_url'])) : '';

        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error(__('Please fill in all required fields (Name, Email, Comment).', 'wc-enquiry-add-to-cart'));
        }

        // --- Normalize product URL: always use the real product permalink if we have an ID ---
        if ($product_id) {
            $permalink = get_permalink($product_id);
            if ($permalink) {
                $product_url = $permalink;
            }
        }

        // --------------- email recipients from settings ---------------
        // Primary notification email from settings; fallback to WP admin email.
        $to_email = trim(get_option('wc_enquiry_notification_email'));
        if (!is_email($to_email)) {
            $to_email = get_option('admin_email');
        }

        $subject = sprintf(__('New product enquiry from %s', 'wc-enquiry-add-to-cart'), $name);

        // Product info
        $product_info = '';
        if ($product_id) {
            $product_post = get_post($product_id);
            if ($product_post) {
                $product_info .= "Product: " . $product_post->post_title . "\n";
            }
        }
        if ($product_url) {
            $product_info .= "Product URL: {$product_url}\n";
        }

        // Email body
        $body  = "You have received a new product enquiry:\n\n";
        $body .= "Name: {$name}\n";
        $body .= "Email: {$email}\n";
        if (!empty($phone)) {
            $body .= "Phone: {$phone}\n";
        }
        if (!empty($state)) {
            $body .= "State / Region: {$state}\n";
        }
        if (!empty($address)) {
            $body .= "Address: {$address}\n";
        }
        $body .= "\n{$product_info}\n";
        $body .= "Message:\n{$message}\n";

        // Headers
        $headers = [];

        // Reply-To header so you can reply directly to client
        if (!empty($email) && is_email($email)) {
            $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
        }

        // CC emails from settings (comma-separated)
        $cc_raw = get_option('wc_enquiry_cc_emails', '');
        if (!empty($cc_raw)) {
            $cc_parts = array_filter(array_map('trim', explode(',', $cc_raw)));
            $valid_cc = [];
            foreach ($cc_parts as $cc) {
                if (is_email($cc)) {
                    $valid_cc[] = $cc;
                }
            }
            if (!empty($valid_cc)) {
                $headers[] = 'Cc: ' . implode(', ', $valid_cc);
            }
        }

        $sent = wp_mail($to_email, $subject, $body, $headers);

        if ($sent) {
            wp_send_json_success(__('Thank you for your enquiry. Our team will contact you shortly.', 'wc-enquiry-add-to-cart'));
        } else {
            wp_send_json_error(__('Unable to send email. Please try again later.', 'wc-enquiry-add-to-cart'));
        }
    }

    /* ---------------------------------------------------------
     *  Admin settings
     * ------------------------------------------------------ */

    /**
     * Add settings page under Settings → WC Enquiry Settings.
     */
    public function add_settings_page() {
        add_options_page(
            __('WC Enquiry Settings', 'wc-enquiry-add-to-cart'),
            __('WC Enquiry Settings', 'wc-enquiry-add-to-cart'),
            'manage_options',
            'wc-enquiry-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings fields.
     */
    public function register_settings() {
        // Primary notification email
        register_setting(
            'wc_enquiry_settings_group',
            'wc_enquiry_notification_email',
            ['sanitize_callback' => 'sanitize_email']
        );

        // CC emails (comma-separated)
        register_setting(
            'wc_enquiry_settings_group',
            'wc_enquiry_cc_emails',
            ['sanitize_callback' => [$this, 'sanitize_cc_emails']]
        );
    }

    /**
     * Sanitize CC email list (comma-separated).
     */
    public function sanitize_cc_emails($value) {
        if (empty($value)) {
            return '';
        }

        $parts = array_filter(array_map('trim', explode(',', $value)));
        $valid = [];

        foreach ($parts as $email) {
            if (is_email($email)) {
                $valid[] = $email;
            }
        }

        return implode(', ', $valid);
    }

    /**
     * Render settings page content.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notification_email = get_option('wc_enquiry_notification_email', '');
        $cc_emails          = get_option('wc_enquiry_cc_emails', '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WC Enquiry Settings', 'wc-enquiry-add-to-cart'); ?></h1>
            <p><?php esc_html_e('Configure where product enquiry notifications are sent.', 'wc-enquiry-add-to-cart'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('wc_enquiry_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wc_enquiry_notification_email">
                                <?php esc_html_e('Primary enquiry notification email', 'wc-enquiry-add-to-cart'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="wc_enquiry_notification_email"
                                name="wc_enquiry_notification_email"
                                value="<?php echo esc_attr($notification_email); ?>"
                                class="regular-text"
                                placeholder="sales@yourstore.com"
                            />
                            <p class="description">
                                <?php esc_html_e('All product enquiries will be sent to this address. If left blank, the WordPress admin email will be used.', 'wc-enquiry-add-to-cart'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wc_enquiry_cc_emails">
                                <?php esc_html_e('CC email(s)', 'wc-enquiry-add-to-cart'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="wc_enquiry_cc_emails"
                                name="wc_enquiry_cc_emails"
                                value="<?php echo esc_attr($cc_emails); ?>"
                                class="regular-text"
                                placeholder="owner@yourstore.com, manager@yourstore.com"
                            />
                            <p class="description">
                                <?php esc_html_e('Optional. Separate multiple email addresses with commas. These addresses will receive a copy (Cc) of each enquiry.', 'wc-enquiry-add-to-cart'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new WC_Enquiry_On_Add_To_Cart();
