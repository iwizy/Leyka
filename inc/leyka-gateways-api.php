<?php if( !defined('WPINC') ) die;
/**
 * Leyka Gateways API
 **/

/**
 * Functions to register and deregister a gateway
 **/
function leyka_add_gateway($class_name) {
    leyka()->add_gateway($class_name);
}

function leyka_remove_gateway($class_name) {
    leyka()->remove_gateway($class_name);
}

function leyka_get_gateways() {
	return leyka()->get_gateways();
}

/**
 * @param mixed $activity True to select only active PMs, false for only non-active ones,
 * NULL for both types altogether.
 * @param $currency mixed
 * @param $sorted boolean
 * @return array
 */
function leyka_get_pm_list($activity = null, $currency = false, $sorted = true) {

    $pm_list = array();

    if($sorted) {

        $pm_order = explode('pm_order[]=', leyka_options()->opt('pm_order'));
        array_shift($pm_order);

        foreach($pm_order as $pm) {

            $pm = leyka_get_pm_by_id(str_replace(array('&amp;', '&'), '', $pm), true);
            if( !$pm ) {
                continue;
            }

            if( ( !$activity || $pm->active == $activity ) && ( !$currency || $pm->has_currency_support($currency) ) ) {
                $pm_list[] = $pm;
            }
        }

    } else {
        foreach(leyka()->get_gateways() as $gateway) { /** @var Leyka_Gateway $gateway */
            $pm_list = array_merge($pm_list, $gateway->get_payment_methods($activity, $currency));
        }
    }

    return apply_filters('leyka_active_pm_list', $pm_list, $activity, $currency);

}

/** @return boolean True if at least one PM supports recurring, false otherwise. */
function leyka_is_recurring_supported() {

    foreach(leyka_get_pm_list(true) as $pm) { /** @var $pm Leyka_Payment_Method */
        if($pm->has_recurring_support()) {
            return true;
        }
    }

    return false;

}

/**
 * @param $pm_id string
 * @param $is_full_id boolean
 * @return mixed Leyka_Payment_Method object or false, if no PM found.
 */
function leyka_get_pm_by_id($pm_id, $is_full_id = false) {

    $pm = false;
    if($is_full_id) {

		$id = explode('-', $pm_id);
        $gateway = leyka_get_gateway_by_id(reset($id)); // Otherwise error in PHP 5.4.0
        if( !$gateway ) {
            return false;
        }

        $pm = $gateway->get_payment_method_by_id(end($id));

    } else {

        foreach(leyka()->get_gateways() as $gateway) { /** @var Leyka_Gateway $gateway */

            $pm = $gateway->get_payment_method_by_id($pm_id);
            if($pm) {
                break;
            }
        }
    }

    return $pm;

}

/**
 * @param $gateway_id string
 * @return mixed Leyka_Gateway object or false if none found.
 */
function leyka_get_gateway_by_id($gateway_id) {

    $gateways = leyka()->get_gateways();
    return empty($gateways[$gateway_id]) ? false : $gateways[$gateway_id];

}

function leyka_get_special_fields_settings(array $params = array()) {

    $params = $params + array(
        'field_types' => array(),
    );

    $pm_fields = array();
    foreach(leyka_get_pm_list() as $pm) {
        foreach($pm->specific_fields as $field_settings) {

            if($params['field_types'] && !in_array($field_settings['type'], $params['field_types'])) {
                continue;
            }

            $pm_fields[$pm->full_id][] = $field_settings;

        }
    }

    return $pm_fields;

}

/**
 * @param Leyka_Gateway $gateway
 * @return array
 */
function leyka_get_gateway_icons_list($gateway) {

    $pm_list = $gateway->get_payment_methods();
    $icons = array();
    
    foreach($pm_list as $pm) {
        if($pm->icons) {
            $icons = array_merge($icons, $pm->icons);
        }
        else {
            $icons[] = $pm->main_icon_url;
        }
    }
    
    return array_unique($icons);

}

/**
 * @param Leyka_Gateway $gateway
 * @return string
 */
function leyka_get_gateway_settings_url($gateway, $type = 'adaptive') {

    $gateway_activation_status = $gateway ? $gateway->get_activation_status() : null;
    $wizard_id = leyka_gateway_setup_wizard($gateway);

    return $type === 'wizard' || ($gateway_activation_status !== 'active' && $wizard_id && $type === 'adaptive') ?
        admin_url('/admin.php?page=leyka_settings_new&screen=wizard-'.$wizard_id) :
        admin_url('/admin.php?page=leyka_settings&stage=payment&gateway='.$gateway->id);

}

/**
 * @param Leyka_Gateway $gateway
 * @return mixed; string wizard suffix or false if wizard unavailable for gateway
 */
function leyka_gateway_setup_wizard($gateway) {
    return $gateway->has_wizard ? $gateway->id : false;
}

abstract class Leyka_Gateway extends Leyka_Singleton {

    protected static $_instance;

	protected $_id = ''; // Must be a unique string, like "quittance", "yandex" or "chronopay"
	protected $_title = ''; // A human-readable title of a gateway, like "Bank quittances" or "Yandex.Kassa"
	protected $_description = ''; // A human-readable description of a gateway (for backoffice)
    protected $_icon = ''; // A gateway icon URL
    protected $_docs_link = ''; // Gateways user manual page URL
    protected $_registration_link = ''; // Gateway registration page URL
    protected $_has_wizard = false;

    protected $_min_commission = 0.0;
    protected $_receiver_types = array('legal'); // legal|physical

    protected $_may_support_recurring = false; // Are recurring payments possible via gateway at all
    protected $_recurring_auto_cancelling_supported = true; // Is it possible to cancel recurring payments via Gateway API

    protected $_payment_methods = array(); // Supported PMs array
    protected $_options = array(); // Gateway configs

    protected function __construct() {

        // A gateway icon is an attribute that is persistent for all gateways, it's just changing values:
        $this->_icon = apply_filters(
            'leyka_icon_'.$this->_id,
            file_exists(LEYKA_PLUGIN_DIR."/gateways/{$this->_id}/icons/{$this->_id}.png") ?
                LEYKA_PLUGIN_BASE_URL."/gateways/{$this->_id}/icons/{$this->_id}.png" :
                '' /** @todo Set an URL to the anonymous gateway icon?? */
        );

        $this->_set_attributes(); // Initialize main Gateway's attributes

        $this->_set_options_defaults(); // Set configurable options in admin area

        $this->_set_gateway_pm_list(); // Initialize or restore Gateway's PMs list and all their options

        do_action('leyka_initialize_gateway', $this, $this->_id); // So one could change some of gateway's attributes

        // Set a gateway class method to process a service calls from gateway:
        add_action('leyka_service_call-'.$this->_id, array($this, '_handle_service_calls'));

        add_action("leyka_{$this->_id}_save_donation_data", array($this, 'save_donation_specific_data'));
        add_action("leyka_{$this->_id}_add_donation_specific_data", array($this, 'add_donation_specific_data'), 10, 2);

        add_filter('leyka_'.$this->_id.'_get_unknown_donation_field', array($this, 'get_specific_data_value'), 10, 3);
        add_action('leyka_'.$this->_id.'_set_unknown_donation_field', array($this, 'set_specific_data_value'), 10, 3);

        add_action('leyka_do_recurring_donation-'.$this->_id, array($this, 'do_recurring_donation'));
        add_filter(
            "leyka_{$this->_id}_recurring_subscription_cancelling_link",
            array($this, 'get_recurring_subscription_cancelling_link'),
            10, 2
        );
        add_action(
            "leyka_{$this->_id}_cancel_recurring_subscription",
            array($this, 'cancel_recurring_subscription_by_link')
        );

        $this->_initialize_options();

        add_action('leyka_enqueue_scripts', array($this, 'enqueue_gateway_scripts'));

        add_action('leyka_payment_form_submission-'.$this->id, array($this, 'process_form'), 10, 4);
        add_action('leyka_payment_form_submission-'.$this->id, array($this, 'process_form_default'), 100, 4);
        add_action('leyka_log_donation-'.$this->id, array($this, 'log_gateway_fields'));

        add_filter('leyka_submission_redirect_url-'.$this->id, array($this, 'submission_redirect_url'), 10, 2);
        add_filter('leyka_submission_redirect_type-'.$this->id, array($this, 'submission_redirect_type'), 10, 3);
        add_filter('leyka_submission_form_data-'.$this->id, array($this, 'submission_form_data'), 10, 3);
        add_action('leyka_'.$this->id.'_redirect_page_content', array($this, 'gateway_redirect_page_content'), 10, 2);

    }

    public function __get($param) {

        switch($param) {
            case 'id': return $this->_id;
            case 'title':
            case 'name':
            case 'label': return $this->_title;
            case 'description': return $this->_description;
            case 'icon':
            case 'icon_url':
                $icon = false;
                if($this->_icon) {
                    $icon = $this->_icon;
                } else if(file_exists(LEYKA_PLUGIN_DIR."gateways/{$this->_id}/icons/{$this->_id}.svg")) {
                    $icon = LEYKA_PLUGIN_BASE_URL."gateways/{$this->_id}/icons/{$this->_id}.svg";
                } else if(file_exists(LEYKA_PLUGIN_DIR."gateways/{$this->_id}/icons/{$this->_id}.png")) {
                    $icon = LEYKA_PLUGIN_BASE_URL."gateways/{$this->_id}/icons/{$this->_id}.png";
                }
                return $icon;

            case 'has_recurring':
            case 'has_recurring_support':
                return !!$this->_may_support_recurring;
            case 'has_recurring_auto_cancelling':
            case 'has_recurring_auto_cancelling_support':
                return $this->_may_support_recurring && !!$this->_recurring_auto_cancelling_supported;

            case 'min_commission': return $this->_min_commission ? round((float)$this->_min_commission, 2) : 0.0;
            case 'receiver_types': return $this->_receiver_types ? (array)$this->_receiver_types : array('legal');

            case 'docs':
            case 'docs_url':
            case 'docs_href':
            case 'docs_link': return $this->_docs_link ? $this->_docs_link : false;
            case 'registration_url':
            case 'registration_href':
            case 'registration_link': return $this->_registration_link ? $this->_registration_link : false;
            case 'has_wizard': return !!$this->_has_wizard;
            case 'wizard_url':
            case 'wizard_href':
            case 'wizard_link': return admin_url('admin.php?page=leyka_settings_new&screen=wizard-'.$this->_id);

            default:
                return false;
        }
    }

    public function get_options_names() {

        $option_names = array();
        foreach($this->_options as $option_name => $params) {
            $option_names[] = $option_name;
        }

        return $option_names;

    }

    /** Allocate gateway options, if needed */
    public function allocate_gateway_options($options) {

        $gateway_section_index = -1;
        foreach($options as $index => $option) {
            if( !empty($option['section']) && $option['section']['name'] == $this->_id ) {
                $gateway_section_index = $index;
                break;
            }
        }

        $gateway_options_names = $this->get_options_names();
        if($gateway_section_index < 0) {
            $options[] = array('section' => array(
                'name' => $this->_id,
                'title' => $this->_title,
                'is_default_collapsed' => false,
                'options' => $gateway_options_names
            ));
        } else {
            $options[$gateway_section_index]['section']['options'] = array_unique(array_merge(
                $gateway_options_names,
                $options[$gateway_section_index]['section']['options']
            ));
        }

        return $options;

    }

    /** Register a gateway in the plugin */
    public function add_gateway() {
        leyka()->add_gateway(self::get_instance());
    }

    /** Register a gateway's scripts in the plugin */
    public function enqueue_gateway_scripts() {
    }

    abstract protected function _set_attributes(); // Attributes are constant, like id, title, etc.
    protected function _set_options_defaults() {} // Options are admin configurable parameters
    abstract protected function _initialize_pm_list(); // PM list is specific for each Gateway

    // Handler for Gateway's service calls (activate the donations, etc.):
    public function _handle_service_calls($call_type = '') {}

    /** Default behavior, may be substituted in descendants: */
    public function get_init_recurrent_donation($donation) {

        if(is_a($donation, 'Leyka_Donation')) {
            return new Leyka_Donation($donation->init_recurring_donation_id);
        } elseif( !empty($donation) && (int)$donation > 0 ) {

            $donation = new Leyka_Donation($donation);

            return new Leyka_Donation($donation->init_recurring_donation_id);

        } else {
            return false;
        }

    }

    /**
     * The main recurring subsciption auto-cancelling method.
     *
     * @param $donation Leyka_Donation
     * @return bool|WP_Error True if cancelling request succeeded, false otherwise, WP_Error if request error can be verbal.
     */
    public function cancel_recurring_subscription(Leyka_Donation $donation) {
    }

    /** A wrapper to fire when recurring subscription is cancelled via link. Should use the cancel_recurring_subscription(). */
    public function cancel_recurring_subscription_by_link(Leyka_Donation $donation) {
    }

    public function get_recurring_subscription_cancelling_link($link_text, Leyka_Donation $donation) {
        return $link_text;
    }

    public function do_recurring_donation(Leyka_Donation $init_recurring_donation) {
        return false;
    }

    // Handler to use Gateway's responses in Leyka UI:
    abstract public function get_gateway_response_formatted(Leyka_Donation $donation);

    protected function _get_gateway_pm_list($pm_id = false) {
        return $pm_id ? array_keys($this->_payment_methods, $pm_id) : array_keys($this->_payment_methods);
    }

    protected function _set_gateway_pm_list() {

        $this->_initialize_pm_list();

        do_action('leyka_init_pm_list', $this);

    }

    protected function _initialize_options() {

        foreach($this->_options as $option_name => $params) {
            if( !leyka_options()->option_exists($option_name) ) {
                leyka_options()->add_option($option_name, $params['type'], $params);
            }
        }

        add_filter('leyka_payment_options_allocation', array($this, 'allocate_gateway_options'), 1, 1);

    }

    public function is_setup_complete($pm_id = false) {
        return false;
    }

    /**
     * A service method to get a a gateway inner system payment method ID by according Leyka pm_id, and vice versa.
     *
     * @param $pm_id string PM ID (either Leyka or the gateway system).
     * @return string|false A PM ID in gateway/Leyka system, or false if PM ID is unknown.
     */
    protected function _get_gateway_pm_id($pm_id) {
        return $pm_id;
    }

    abstract public function process_form($gateway_id, $pm_id, $donation_id, $form_data);

    abstract public function submission_redirect_url($current_url, $pm_id);

    abstract public function submission_form_data($form_data, $pm_id, $donation_id);

    /**
     * Save some gateway specific donation metadata. Default implementation is empty.
     * @param $donation_id int
     */
    public function log_gateway_fields($donation_id) {
    }

    static public function process_form_default($gateway_id, $pm_id, $donation_id, $form_data) {

        if(empty($form_data['leyka_donation_amount']) || (float)$form_data['leyka_donation_amount'] <= 0) {

            $error = new WP_Error(
                'wrong_donation_amount',
                __('Donation amount must be specified to submit the form', 'leyka')
            );
            leyka()->add_payment_form_error($error);

        }

        $currency = $form_data['leyka_donation_currency'];
        if(empty($currency)) {

            $error = new WP_Error(
                'wrong_donation_currency',
                __('Wrong donation currency in submitted form data', 'leyka')
            );
            leyka()->add_payment_form_error($error);

        }

        if( !empty($form_data['top_'.$currency]) && $form_data['leyka_donation_amount'] > $form_data['top_'.$currency] ) {

            $error = new WP_Error(
                'donation_amount_too_great',
                sprintf(
                    __('Donation amount you entered is too great (maximum %s allowed)', 'leyka'),
                    $form_data['top_'.$currency].' '.leyka_options()->opt("currency_{$currency}_label")
                )
            );
            leyka()->add_payment_form_error($error);

        }

        if( !empty($form_data['bottom_'.$currency]) && $form_data['leyka_donation_amount'] < $form_data['bottom_'.$currency] ) {

            $bottom_amount_allowed = $form_data['bottom_'.$currency];
            $error = new WP_Error(
                'donation_amount_too_small',
                sprintf(
                    __('Donation amount you entered is too small (minimum %s allowed)', 'leyka'),
                    $bottom_amount_allowed.' '.leyka_options()->opt("currency_{$currency}_label")
                )
            );
            leyka()->add_payment_form_error($error);

        }

        if(empty($form_data['leyka_agree']) && leyka_options()->opt('agree_to_terms_needed')) {

            $error = new WP_Error('terms_not_agreed', __('You must agree to the terms of donation service', 'leyka'));
            leyka()->add_payment_form_error($error);

        }

    }

    /**
     * @param Leyka_Payment_Method $pm New PM to add to a gateway.
     * @param bool $replace_if_exists True to replace an existing PM (if it exists). False by default.
     * @return bool True if PM was added/replaced, false otherwise.
     */
    public function add_payment_method(Leyka_Payment_Method $pm, $replace_if_exists = false) {

        if($pm->gateway_id != $this->_id) {
            return false;
        }

        if(empty($this->_payment_methods[$pm->id]) || !!$replace_if_exists) {
            $this->_payment_methods[$pm->id] = $pm;
            return true;
        }

        return false;

    }

    /** @param mixed $pm A PM object or it's ID to remove from gateway. */
    public function remove_payment_method($pm) {

        if(is_object($pm) && $pm instanceof Leyka_Payment_Method) {
            unset($this->_payment_methods[$pm->id]);
        } else if(strlen($pm) && !empty($this->_payment_methods[$pm])) {
            unset($this->_payment_methods[$pm->id]);
        }

    }

    /**
     * @param mixed $activity True to select only active PMs, false for only non-active ones,
     * NULL for both types altogether.
     * @param mixed $currency
     * @param boolean $by_categories
     * @return array Of Leyka_Payment_Method objects.
     */
    public function get_payment_methods($activity = null, $currency = false, $by_categories = false) {

        $pm_list = array();
        foreach($this->_payment_methods as $pm_name => $pm) {

            /** @var $pm Leyka_Payment_Method */
            if((($activity || $activity === null) && $pm->is_active) || empty($activity)) {

                if(empty($currency)) {
                    $pm_list[] = $pm;
                } elseif($currency && $pm->has_currency_support($currency)) {
                    $pm_list[] = $pm;
                }

            }

        }

        if( !!$by_categories ) {

            // Get the PM categories in the right order:
            $tmp = array_map(function($value){ return array(); }, leyka_get_pm_categories_list());

            foreach($pm_list as $pm) { /** @var $pm Leyka_Payment_Method */
                if($pm->category) {
                    $tmp[$pm->category][] = $pm;
                }
            }

            foreach($tmp as $category => $pm_list_in_category) { // Remove empty PM categories
                if( !$pm_list_in_category ) {
                    unset($tmp[$category]);
                }
            }

            $pm_list = $tmp;

        }

        return $pm_list;

    }

    /**
     * @param string $pm_id
     * @return Leyka_Payment_Method Object|boolean.
     */
    public function get_payment_method_by_id($pm_id) {
        return empty($this->_payment_methods[$pm_id]) ? false : $this->_payment_methods[$pm_id];
    }

    /** Default filter for the donation page redirect type parameter */
    public function submission_redirect_type($redirect_type, $pm_id, $donation_id) {
        return 'auto';
    }

    /** Default action for the gateway redirect page content */
    public function gateway_redirect_page_content($pm_id, $donation_id) {
    }

    /** Get gateway specific donation fields for an "add/edit donation" page ("donation data" metabox). */
    public function display_donation_specific_data_fields($donation = false) {
    }

    /** For "leyka_get_unknown_donation_field" filter hook, to get gateway specific donation data values. */
    public function get_specific_data_value($value, $field_name, Leyka_Donation $donation) {
        return $value;
    }

    /** For "leyka_set_unknown_donation_field" action hook, to set gateway specific donation data values. */
    public function set_specific_data_value($field_name, $value, Leyka_Donation $donation) {
    }

    /** To save gateway specific fields when donation editing page is being saved */
    public function save_donation_specific_data(Leyka_Donation $donation) {
    }

    /** Action called when new donation (Leyka_Donation::add()) is being created to add gateway-specific fields. */
    public function add_donation_specific_data($donation_id, array $donation_params) {
    }
    
    /**
     * @return array; list of possible values in leyka_get_gateways_filter_categories_list function
     */
    public function get_filter_categories() {

        $categories = $this->receiver_types;

        if($this->has_recurring) {
            $categories[] = 'recurring';
        }

        return $categories;

    }

    /**
     * @return string: active inactive activating
     */
    public function get_activation_status() {

        $status = 'inactive';

        $wizard_id = leyka_gateway_setup_wizard($this);

        if(count($this->get_payment_methods(true))) {
            $status = 'active';
        } else if($wizard_id && leyka_wizard_started($wizard_id)) {
            $status = 'activating';
        }

        return $status;

    }

}

/**
 * Class Leyka_Payment_Method
 */
abstract class Leyka_Payment_Method extends Leyka_Singleton {

    protected static $_instance;

    protected $_id = '';
    protected $_gateway_id = '';
    protected $_category = 'misc';

    protected $_active = true;
    protected $_label = '';
    protected $_label_backend = '';
    protected $_description = '';
    protected $_icons = array();
    protected $_main_icon = '';
    protected $_submit_label = '';

    protected $_global_fields = array();
    protected $_support_global_fields = true;
    protected $_specific_fields = array();
    protected $_custom_fields = array();
    protected $_supported_currencies = array();
    protected $_default_currency = '';
    protected $_options = array();

    protected $_processing_type = 'default';
    protected $_ajax_without_form_submission = false;

    protected function __construct() {

        $this->_submit_label = '';

        $this->_set_attributes();
        $this->_initialize_options();
        $this->_set_dynamic_attributes();

    }

    public function __get($param) {

        switch($param) {
            case 'id': $param = $this->_id; break;
            case 'full_id': $param = $this->_gateway_id.'-'.$this->_id; break;
            case 'gateway_id': $param = $this->_gateway_id; break;
            case 'gateway': $param = leyka_get_gateway_by_id($this->_gateway_id); break;
            case 'category':
            case 'category_id':
                $param = array_key_exists($this->_category, leyka_get_pm_categories_list()) ? $this->_category : false;
                break;
            case 'category_label':
                $param = $this->category ? leyka_get_pm_category_label($this->_category) : '';
                break;
            case 'active':
            case 'is_active': $param = $this->_active; break;
            case 'label':
            case 'title':
            case 'name':
                $param = leyka_options()->opt_safe($this->full_id.'_label');
                $param = apply_filters('leyka_get_pm_label', $param && $param != $this->_label ? $param : $this->_label, $this);
                break;
            case 'label_backend':
            case 'title_backend':
            case 'name_backend': $param = $this->_label_backend ? $this->_label_backend : $this->_label;
                break;
            case 'desc':
            case 'description': $param = html_entity_decode($this->_description); break;
            case 'has_global_fields': $param = $this->_support_global_fields; break;
            case 'specific_fields': $param = $this->_specific_fields ? $this->_specific_fields : array(); break;
            case 'custom_fields': $param = $this->_custom_fields ? $this->_custom_fields : array(); break;
            case 'icons': $param = $this->_icons; break;
            case 'main_icon': /** @todo Mb, add a filter here to set a custom main icon */
                $param = $this->_main_icon ? $this->_main_icon : 'pic-main-'.$this->full_id;
                break;
            case 'main_icon_url': $param = LEYKA_PLUGIN_BASE_URL."gateways/{$this->gateway_id}/icons/{$this->main_icon}.svg"; break;
            case 'submit_label': $param = $this->_submit_label; break;
            case 'currencies': $param = $this->_supported_currencies; break;
            case 'default_currency': $param = $this->_default_currency; break;
            case 'processing_type':
            case 'processing':
                $param = $this->_processing_type;
                break;
            case 'ajax_without_form_submission':
                $param = !!$this->_ajax_without_form_submission;
                break;
            default:
                $param = null;
        }

        return $param;

    }

    abstract protected function _set_attributes();

    /** To set some custom options-dependent attributes */
    protected function _set_dynamic_attributes() {}

    public function has_recurring_support() {
        return false;
    }

    public function has_currency_support($currency = false) {

        if( !$currency ) {
            return true;
        } else if(is_array($currency) && !array_diff($currency, $this->_supported_currencies)) {
            return true;
        } else if(in_array($currency, $this->_supported_currencies)) {
            return true;
        } else {
            return false;
        }

    }

    /** To set PM specific options */
    protected function _set_options_defaults() {}

    protected final function _add_options() {
        foreach($this->_options as $option_name => $params) {
            if( !leyka_options()->option_exists($option_name) ) {
                leyka_options()->add_option($option_name, $params['type'], $params);
            }
        }
    }

    protected function _initialize_options() {

        $this->_set_options_defaults();

        $this->_add_options();

        /** PM frontend label is a special persistent option, universal for each PM */
        if( !leyka_options()->option_exists($this->full_id.'_label') ) {
            leyka_options()->add_option($this->full_id.'_label', 'text', array(
                'value' => '',
                'default' => $this->_label,
                'title' => __('Payment method custom label', 'leyka'),
                'description' => __('A label for this payment method that will appear on all donation forms.', 'leyka'),
                'required' => false,
                'placeholder' => sprintf(__('E.g., %s', 'leyka'), $this->_label),
                'validation_rules' => array(), // List of regexp?..
            ));
        }

        $custom_label = leyka_options()->opt_safe($this->full_id.'_label');
        $this->_label = $custom_label && $custom_label != $this->_label ?
            $custom_label : apply_filters('leyka_get_pm_label_original', $this->_label, $this);

        $this->_active = $this->_active = is_array(leyka_options()->opt('pm_available')) ?
            in_array($this->full_id, leyka_options()->opt('pm_available')) : array();
        //in_array($this->full_id, leyka_options()->opt('pm_available'));

        add_filter('leyka_payment_options_allocation', array($this, 'allocate_pm_options'), 10, 1);

    }

    public function get_pm_options_names() {

        $option_names = array();
        foreach($this->_options as $option_name => $params) {
            $option_names[] = $option_name;
        }

        return $option_names;

    }

    /** Allocate gateway options, if needed */
    public function allocate_pm_options($options) {

        $gateway = leyka_get_gateway_by_id($this->_gateway_id);
        $gateway_section_index = -1;

        foreach($options as $index => $option) {
            if( !empty($option['section']) && $option['section']['name'] == $gateway->id ) {

                $gateway_section_index = $index;
                break;

            }
        }

        $pm_options_names = $this->get_pm_options_names();
        $pm_options_names[] = $this->full_id.'_label';

        if($gateway_section_index < 0) {
            $options[] = array('section' => array(
                'name' => $gateway->id,
                'title' => $gateway->title,
                'is_default_collapsed' => false,
                'options' => $pm_options_names,
            ));
        } else {
            $options[$gateway_section_index]['section']['options'] = array_unique(array_merge(
                $pm_options_names,
                $options[$gateway_section_index]['section']['options']
            ));
        }

        return $options;

    }

    /** For PM with a static processing type, this method should display some static data. Otherwise, it may stay empty. */
    public function display_static_data() {}

} // Leyka_Payment_Method end