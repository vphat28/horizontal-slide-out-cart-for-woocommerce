<?php
/*
Plugin Name: WooCommerce Slide Out cart
Plugin URI:
Description: Plugin from Xavi, please edit this intro in plugin files.
Version: 1.0
Author: Sundaysea
Author URI: http://www.sundaysea.com
*/

$dirsoc = plugin_dir_path(__FILE__);

require_once $dirsoc . 'libs/Mobile_Detect.php';
$detect = new Mobile_Detect;
if (!$detect->isMobile()) {


    function sundaysea_update_shipping()
    {

        // Check cart class is loaded or abort
        if (is_null(WC()->cart)) {
            return;
        }

        // Constants
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }

        // Update Shipping
        if (!empty($_POST['calc_shipping'])) {

            try {
                WC()->shipping->reset_shipping();

                $country = wc_clean($_POST['calc_shipping_country']);
                $state = isset($_POST['calc_shipping_state']) ? wc_clean($_POST['calc_shipping_state']) : '';
                $postcode = apply_filters('woocommerce_shipping_calculator_enable_postcode', true) ? wc_clean($_POST['calc_shipping_postcode']) : '';
                $city = apply_filters('woocommerce_shipping_calculator_enable_city', false) ? wc_clean($_POST['calc_shipping_city']) : '';

                if ($postcode && !WC_Validation::is_postcode($postcode, $country)) {
                    throw new Exception(__('Please enter a valid postcode/ZIP.', 'woocommerce'));
                } elseif ($postcode) {
                    $postcode = wc_format_postcode($postcode, $country);
                }

                if ($country) {
                    WC()->customer->set_location($country, $state, $postcode, $city);
                    WC()->customer->set_shipping_location($country, $state, $postcode, $city);
                } else {
                    WC()->customer->set_to_base();
                    WC()->customer->set_shipping_to_base();
                }

                WC()->customer->calculated_shipping(true);

                wc_add_notice(__('Shipping costs updated.', 'woocommerce'), 'notice');

                do_action('woocommerce_calculated_shipping');

            } catch (Exception $e) {

                if (!empty($e))
                    wc_add_notice($e->getMessage(), 'error');
            }
        }

        // Check cart items are valid
        do_action('woocommerce_check_cart_items');

        // Calc totals
        WC()->cart->calculate_totals();
    }

    function sundaysea_update_cart2($cart_totals)
    {
        // Add Discount
        if (!empty($_POST['coupon_code'])) {

            WC()->cart->add_discount(sanitize_text_field($_POST['coupon_code']));
        } // Remove Coupon Codes
        elseif (isset($_GET['remove_coupon'])) {

            WC()->cart->remove_coupon(wc_clean($_GET['remove_coupon']));

        }
        global $woocommerce;

        if (sizeof($woocommerce->cart->get_cart()) > 0) {
            foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {

                // Skip product if no updated quantity was posted
                if (!isset($cart_totals[$cart_item_key]['qty'])) {
                    continue;
                }

                // Sanitize
                $quantity = apply_filters(
                    'woocommerce_stock_amount_cart_item',
                    apply_filters(
                        'woocommerce_stock_amount',
                        preg_replace("/[^0-9\.]/", "", $cart_totals[$cart_item_key]['qty'])
                    ),
                    $cart_item_key
                );
                if ("" === $quantity || $quantity == $values['quantity']) {
                    continue;
                }

                // Update cart validation
                $passed_validation = apply_filters('woocommerce_update_cart_validation', true, $cart_item_key, $values, $quantity);
                $_product = $values['data'];

                // is_sold_individually
                if ($_product->is_sold_individually() && $quantity > 1) {
                    $woocommerce->add_error(sprintf(__('You can only have 1 %s in your cart.', 'woocommerce'), $_product->get_title()));
                    $passed_validation = false;
                }

                if ($passed_validation) {
                    $woocommerce->cart->set_quantity($cart_item_key, $quantity, false);
                }
            }

            $woocommerce->cart->calculate_totals();
        }


    }

    function sundaysea_wp_head()
    {
        ?>
        <script type='text/javascript'>
            var sundaysea_base_url = '<?php echo get_site_url(); ?>';
        </script>
        <?php
    }

    add_action('wp_head', 'sundaysea_wp_head');
    function cart_needs_payment($val)
    {
        if (is_ajax()) {
            $val = true;
        }

        return $val;
    }

    function sundaysea_action_callback()
    {

        $view = $_REQUEST['view'];

        if ($view == 'cart') {
            echo do_shortcode('[woocommerce_cart]');
        } else if ($view == 'updatecart') {
            if (!defined('WOOCOMMERCE_CART')) {
                define('WOOCOMMERCE_CART', true);
            }
            sundaysea_update_cart2(filter_input(INPUT_POST, 'cart', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY));
        } else if ($view == 'updateship') {
            sundaysea_update_shipping();
        } else if ($view == 'checkout') {
            add_filter('woocommerce_cart_needs_payment', 'cart_needs_payment', 10, 1);
            global $woocommerce;
            if (!defined('WOOCOMMERCE_CHECKOUT')) {
                define('WOOCOMMERCE_CHECKOUT', true);
            }


            $woocommerce->cart->calculate_totals();

            echo do_shortcode('[woocommerce_checkout]');
        }
        die;
    }

    add_action('wp_ajax_sundaysea_action', 'sundaysea_action_callback');
    add_action('wp_ajax_nopriv_sundaysea_action', 'sundaysea_action_callback');


    function sundaysea_woocommerce_loop_add_to_cart_link($markup)
    {

        // change the markup only when single product page context
        global $product_id;

        $html = simplexml_load_string($markup);

        $class = (string)$html->attributes()->class;

        $html->attributes()->class = str_replace('product_type_simple', ' product_type_simple add_to_cart_button_sundaysea_slout', $class);

        $markup = $html->asXML();


        return $markup;
    }

    add_filter('woocommerce_loop_add_to_cart_link', 'sundaysea_woocommerce_loop_add_to_cart_link', 10, 1);


    function sundaysea_wp_enqueue_scripts()
    {
        wp_enqueue_style('sundaysea-style', plugins_url('css/sundaysea.css', __FILE__));
        wp_enqueue_script('sundaysea-script', plugins_url('js/sundaysea.js', __FILE__), array(), '1.0.0', true);
    }

    add_action('wp_enqueue_scripts', 'sundaysea_wp_enqueue_scripts');
    function sundaysea_woocommerce_before_add_to_cart_button()
    {
        if (defined('SUNDAYSEA_SLIDEOUTCART_INJECT_MODAL')) {
            RETURN;
        }
        $wc_checkout_params = apply_filters('wc_checkout_params', array(
            'ajax_url' => WC()->ajax_url(),
            'update_order_review_nonce' => wp_create_nonce("update-order-review"),
            'apply_coupon_nonce' => wp_create_nonce("apply-coupon"),
            'option_guest_checkout' => get_option('woocommerce_enable_guest_checkout'),
            'checkout_url' => add_query_arg('action', 'woocommerce_checkout', WC()->ajax_url()),
            'is_checkout' => is_page(wc_get_page_id('checkout')) && empty($wp->query_vars['order-pay']) && !isset($wp->query_vars['order-received']) ? 1 : 0
        ));
        ?>
        <script type='text/javascript'>//<![CDATA[
            var seaops_checkout_data =<?php echo json_encode($wc_checkout_params); ?>;
            //]]></script>
        <?php
        ?>
        <div id="ssea-modal-background"></div>
        <div class="show-post-modal" id="ssea-show-post-modal">

            <div class="modal-container-parent" style='position: relative; '>
                <div class="modal-container" style='position: relative; '>
                    <div class="modal-containerloading">
                        <div class="loader-gif"><img
                                    src="<?php echo plugins_url('images/ajax-loader.gif', __FILE__); ?>"></div>
                        <span class="preparing">Preparing Your Selection...</span>
                    </div>
                    <div class="slideout-header layer">
                        <div class="columns large-4">
                            <h2>Shopping Cart</h2>
                        </div>
                        <div class="columns large-8 buttons">
                            <button data-product_sku="" class="button  closemodal">
                                Keep shopping
                            </button>
                            <button id="sundaysea-slidwoo-checkout" class="button sundaysea-slidwoo-checkout">
                                Checkout
                            </button>
                        </div>
                    </div>
                    <div class="cart-wrap">
                        <div id="sundaysea-woo-cart"></div>

                    </div>
                </div>
            </div>
        </div>
        <?php

        if (!defined('SUNDAYSEA_SLIDEOUTCART_INJECT_MODAL')) {
            define('SUNDAYSEA_SLIDEOUTCART_INJECT_MODAL', true);
        }
    }

    add_action('wp_footer', 'sundaysea_woocommerce_before_add_to_cart_button');

}