<?php if( !defined('WPINC') ) die;


class Leyka_Engagement_Banner  {


	public function __construct( $prefix ) {

		$this->prefix = $prefix;
	}


	protected function get_option( $key ) {

		$prefix = $this->prefix;
		$access_key = "{$prefix}_{$key}";

		return leyka()->opt($access_key);
	}

	
	public function get_header() {

		$value = $this->get_option('title');

		if( empty( $value ) ) {
			return '';
		}

		if(false !== strpos( $value, '[' ) ) {
			$value = html_entity_decode($value, ENT_QUOTES);
			return do_shortcode($value);
		}

		$value = esc_html(strip_tags($value));

		return  "<span>{$value}</span>";
	}


	public function get_text() {

		$out = array();

		$text = $this->get_option('text');

		if( !empty($text) ) {
			$text = html_entity_decode($text, ENT_QUOTES);
			$out[] = $text;
		}

		$selection = $this->get_option('selection');
		if( !empty($selection) ) {
			$out[] = "<span class='selection'>".esc_html($selection)."</span>";
		}

		return implode(' ', $out);
	}


	public function get_button_link() {

		return $this->get_option('button_link');
	}


	public function get_button_label() {
		
		return $this->get_option('button_label');
	}


	public function get_classes() {

		$classes = array();
		$classes[] = $this->get_position_class();
		$classes[] = $this->get_header_class();

		return implode(' ', $classes);
	}


	protected function get_position_class() {

		$position = $this->get_option('screen_position');
		$position = (empty($position)) ? 'bottom' : $position;

		return 'engb-position--' . $position;
	}


	protected function get_header_class() {

		$header = $this->get_option('title');
		
		if( has_shortcode( $header, 'leyka_engb_scale' ) ) {
			return 'engb--format-scale';
		}

		if( has_shortcode( $header, 'leyka_engb_photo' ) ) {
			return 'engb--format-photo';
		}

		return 'engb--format-text';
	}


	public function get_attributes() {

		$data = array();
		$attrs = '';

		$data['delay'] = $this->get_delay_attribute();
		$data['remember_close'] = $this->get_remember_close_attribute();

		foreach ($data as $key => $obj) {
			$value = (is_array($obj)) ? wp_json_encode( $obj ) : $obj;
			$value = esc_attr($value);

			$attrs .= "data-{$key}='{$value}' ";
		}

		return $attrs;
	}


	protected function get_delay_attribute() {

		$delay_type = $this->get_option('delay_type');

		if( empty($delay_type) ) {
			$delay_type = 'time';
		}

		$delay_value = $this->get_option("{$delay_type}_amount");

		if(empty($delay_value)) {
			$delay_value = ($delay_type == 'time') ? 30 : 50;
		}

		return array( $delay_type => $delay_value );
	}
	

	protected function get_remember_close_attribute() {

		$remember_close = $this->get_option('remember_close');

		if( empty($remember_close) ) {
			$remember_close = 'day';
		}

		return $remember_close;
	}

} // class 
