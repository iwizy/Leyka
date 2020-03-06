<?php if( !defined('WPINC') ) die;
/**
 * Leyka Extension: Engagement Banner
 * Version: 0.1.0
 * Author: Teplitsa of social technologies
 * Author URI: https://te-st.ru
 * Text Domain: leyka_engb
 **/

class Leyka_Engagement_Banner_Extension extends Leyka_Extension {

    protected static $_instance;

    protected $options_data;


    /** Required methods **/
    protected function _set_attributes() {

        $this->load_textdomain();

    	$this->_id = 'engagement_banner'; 

        $this->_title = __('Engagement Banner', 'leyka_engb'); 

        $this->_description = __('Display fundrising banner on website pages, control appearance logic.', 'leyka_engb');

        $this->_full_description = __('Display fundrising banner on website pages. Customise its appearance and behaviour through set of simple options.', 'leyka_engb');

        $this->_settings_description = __('Setup content of a banner and  color scheme, tune appeacne logic to you need and attract more donations.', 'leyka_engb');

        $this->_connection_description = '<p><strong>Шорткоды</strong></p><p>В заглавной части баннера (поле <em>Заголовок</em>) можно использовать следующие шорткоды:</p><p><code>[leyka_engb_scale id="campaign_id"]</code><br>прогрессбар сбора по кампании</p><p><code>[leyka_engb_photo img="media_lib_id" name="Иван Чернов" role="главный редактор"]</code><br>фото с подписью (2 уровня) - например фото человека с указанием имени и должности.</p><p>Фото должно быть загружено в медиа-библиотеку и в параметрах шорткода указывается его ID.</p>';

        $this->_user_docs_link = false; 

        $this->_has_wizard = false;

        $this->_has_color_options = true;

    }


	protected function _set_options_defaults() {
 
        require_once( self::get_base_path() . '/inc/config-options.php' );

        $options_config = leyka_engb_options($this->_id);

		$this->_options = apply_filters('leyka_'.$this->_id.'_extension_options', $options_config);

	}


    protected function _initialize_always() {

        add_action('admin_enqueue_scripts', array($this, 'load_admin_cssjs'));

        add_action('leyka_render_custom_engb_multiselect', array($this, 'render_custom_multiselect'), 10, 2);

        // process custom multiselect options
        $custom_options = $this->get_multiselect_fields();

        if( !empty($custom_options) ) {
            foreach ($custom_options as $option_id) {
                add_action(
                "leyka_save_custom_option-{$option_id}", 
                array($this, 'save_custom_multiselect'));
            }
        }
    }


    /** Support options with custom_multiselect type **/
    public function render_custom_multiselect( $option_id, $option_info ) {

        $option_info = $this->get_multiselect_field_config($option_id); 

        $items_list = $option_info['list_entries'];
        
        $field_key = "leyka_{$option_id}"; 

        $selection = get_option($field_key);     
    ?>
    <div id="<?php echo esc_attr($option_id);?>" class="settings-block option-block type-engb-multiselect">
        <div id="<?php echo esc_attr($option_id);?>-wrapper" class="engb-multiselect-field-wrapper ">
            <input type="hidden" name="<?php echo esc_attr($field_key);?>_submition" value="1">
            <label for="<?php echo esc_attr($field_key);?>">
                <span class="field-component title">
                    <span class="text"><?php echo esc_html($option_info['title']);?></span>
                 </span>

                <span class="field-component field">
                    <select 
                        class="engb-multiselect" 
                        name="<?php echo esc_attr($field_key);?>[]" 
                        multiple="multiple">
                        <?php foreach ($items_list as $key => $label) { ?>
                            <option 
                                value="<?php echo esc_attr($key);?>"
                                <?php if(in_array($key, $selection)) { echo  "selected='selected'";} ?>
                                ><?php echo esc_html($label);?></option>
                        <?php } ?>
                    </select>
                </span>
            </label>

        </div>
        <div class="field-errors "></div>
    </div>
    <?php
    }


    public function save_custom_multiselect() {

        $custom_options = $this->get_multiselect_fields();

        if(empty($custom_options)) {
            return;
        }

        foreach ($custom_options as $option_id) {
            
            // our submition
            $test_key = "leyka_{$option_id}_submition"; 

            if( !isset($_POST[$test_key]) ) {
                continue;
            }

            if( (int)$_POST[$test_key] !== 1 ) {
                continue;
            }

            $config = $this->get_multiselect_field_config($option_id);

            $callback = (isset($config['update_callback'])) ? $config['update_callback'] : '';
 
            if( !empty($callback) && is_callable($callback) ) {
                call_user_func($callback);
            }
        }
    }


    protected function get_multiselect_fields() {

        $fields = array();

        if(empty($this->_options)) {
            return $fields;
        }

        foreach ($this->_options as $i => $section) {

            if( !isset($section['section']['options']) )
                continue;

            foreach ($section['section']['options'] as $key => $config) {
                if(isset($config['type']) && $config['type'] == 'custom_engb_multiselect') {
                    $fields[] = $key;
                }
            }
        }

        return $fields;
    }


    protected function get_multiselect_field_config($option_id) {

        $config = array();

        if(empty($this->_options)) {
            return $config;
        }

        foreach ($this->_options as $i => $section) {

            if( isset($section['section']['options'][$option_id]) ) {
                $config = $section['section']['options'][$option_id];
                break;
            }
        }
            
        return $config;
    }



    protected function _initialize_active() {

        $this->load_files();

        add_action( 'wp_enqueue_scripts', array( $this, 'load_cssjs') );

        add_action( 'wp_footer', array( $this, 'display_banner') );

        // shortcodes
        add_shortcode('leyka_engb_scale', array($this, 'shortcode_scale_screen'));
        add_shortcode('leyka_engb_photo', array($this, 'shortcode_photo_screen'));
    }

	
    /** Paths **/
    public static function get_base_path() {

        return __DIR__; 
    }

    public static function get_base_url() {

        $path = str_replace( LEYKA_PLUGIN_DIR, '',  __DIR__ );

        return LEYKA_PLUGIN_BASE_URL . $path;
    }

    
	/* Core **/
    protected function load_textdomain() {

        $locale = get_locale();
        $mofile = self::get_base_path()."/languages/leyka-engb-{$locale}.mo";

        if( file_exists( $mofile ) ) {
            load_textdomain( 'leyka_engb', $mofile );
        }
        
    }

	protected function load_files() {

		$path = self::get_base_path().'/inc/'; 

		require_once( $path.'class-controller.php' );
		require_once( $path.'class-banner.php'  );

	}

	public function load_cssjs() {

		$css_url = self::get_base_url() . '/assets/css/engb.css';
        $css_stamp = ( defined('WP_DEBUG') && WP_DEBUG ) ? uniqid() : null;

		wp_enqueue_style(
            $this->_id.'-front',
            $css_url,
            array(),
            $css_stamp
        );

        // color 
        $colors_css  = $this->build_colors_css();
        wp_add_inline_style( $this->_id.'-front', $colors_css );

        $js_url = self::get_base_url() . '/assets/js/engb.js';
        $js_stamp = ( defined('WP_DEBUG') && WP_DEBUG ) ? uniqid() : null;

        wp_enqueue_script(
            $this->_id.'-front',
            $js_url,
            array('jquery'),
            $js_stamp
        );

        wp_enqueue_style(
            $this->_id.'-front',
            $css_url,
            array(),
            $css_stamp
        );
	}


    public function load_admin_cssjs() {

        $sel2_css = self::get_base_url() . '/assets/css/select2.min.css';
        $css_url = self::get_base_url() . '/assets/css/engb-admin.css';
        $css_stamp = ( defined('WP_DEBUG') && WP_DEBUG ) ? uniqid() : null;

        wp_enqueue_style(
            $this->_id.'-select2',
            $sel2_css, 
            array(), 
            null
        );

        wp_enqueue_style(
            $this->_id.'-admin',
            $css_url, 
            array($this->_id.'-select2'), 
            $css_stamp
        );

        $sel2_js = self::get_base_url() . '/assets/js/select2.min.js';
        $js_url = self::get_base_url() . '/assets/js/engb-admin.js';
        $js_stamp = ( defined('WP_DEBUG') && WP_DEBUG ) ? uniqid() : null;


        wp_enqueue_script(
            $this->_id.'-select2',
            $sel2_js,
            array('jquery'), 
            null, 
            true
        );

        wp_enqueue_script(
            $this->_id.'-admin',
            $js_url,
            array('jquery', $this->_id.'-select2'), 
            $js_stamp, 
            true
        );

        $js_strings = array(
            'placeholder' => __('Select user role', 'leyka_engb'),
        );

        wp_localize_script($this->_id.'-admin', 'engb', $js_strings);
    }

    protected function build_colors_css() {

        $button_bg = leyka()->opt( $this->_id .'_main_color');
        $button_text  = leyka()->opt( $this->_id .'_caption_color');
        $body_bg  = leyka()->opt( $this->_id .'_background_color');
        $body_text  = leyka()->opt( $this->_id .'_text_color');

        $style = "
        :root {
            --engb-color-button-bg: {$button_bg};
            --engb-color-button-text: {$button_text};
            --engb-color-body-bg: {$body_bg};
            --engb-color-body-text: {$body_text};
        }";

        return $style;
    }


    /** Main action */
	public function display_banner() {

		try {
			$prefix = $this->_id;
			$controller = new Leyka_Engagement_Banner_Controller( $prefix );
			$controller->display();
		}
		catch ( Exception $ex ) {

			$err = $e->getMessage();

			if( defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ) {
				echo  $err;
			}

			error_log( $err );
		}
	}


	/** Shortcodes **/
    public function shortcode_scale_screen( $atts ) {

        $atts = shortcode_atts( array(
            'id' => 0
        ), $atts );

        $campaign = get_post( $atts['id'] );

        if( !class_exists('Leyka_Campaign_Management') ) {
            return '';
        }

        if( !$campaign || $campaign->post_type != Leyka_Campaign_Management::$post_type ) { 
            return '';
        }

        $campaign = leyka_get_validated_campaign($campaign);
        if( !$campaign ) {
            return '';
        }

        // progress scale 
        $target = (int)$campaign->target;
        $funded = (int)$campaign->total_funded;
        
        if($target == 0) {
            return '';
        }

        $percentage = ceil(($funded/$target)*100);

        $template = self::get_base_path() . '/inc/template-scale.php';
        $template = apply_filters( 'leyka_engb_scale_template', $template );

        $out = '';

        if( file_exists( $template ) ) {

            ob_start();

            $scale = array();
            $scale['currency'] = leyka_get_currency_label('rur');
            $scale['percentage'] = ($percentage > 100) ? 100 : $percentage;
            $scale['delta'] = ($funded < $target) ? $target - $funded : 0;
            $scale['target'] = number_format($target, 0, '.', ' ');

            include $template;

            $out = ob_get_contents();

            ob_end_clean();
        }


        return $out;
    }


    public function shortcode_photo_screen( $atts, $content = null ) {

        $photo = shortcode_atts( array(
            'img' => 0,
            'name' => '',
            'role' => ''
        ), $atts );


        if( empty($photo['name']) || (int)$photo['img'] === 0 ) {
            return '';
        }

        $image = wp_get_attachment_image( $photo['img'], 'thumbnail' );

        if( !$image ) {
            return '';
        }

        $template = self::get_base_path() . '/inc/template-photo.php';
        $template = apply_filters( 'leyka_engb_photo_template', $template );
        $out = '';

        if( file_exists( $template ) ) {

            ob_start();

            $photo['image'] = $image;
            include $template;

            $out = ob_get_contents();

            ob_end_clean();
        }

        return $out;
    }


} // class 


/** Access to options **/
function leyka_engb_get_banner() {

	//return Leyka_Engagement_Banner_Extension::get_instance()->get_banner();
}

/** Register **/
function leyka_add_extension_engagement_banner() { 

    leyka()->add_extension(Leyka_Engagement_Banner_Extension::get_instance());
}

add_action('leyka_init_actions', 'leyka_add_extension_engagement_banner');