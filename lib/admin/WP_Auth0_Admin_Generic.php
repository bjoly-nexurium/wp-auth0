<?php

class WP_Auth0_Admin_Generic {

	protected $options;
	protected $_option_name;
	protected $_description;
	protected $_textarea_rows = 4;

	protected $actions_middlewares = array();

	/**
	 * WP_Auth0_Admin_Generic constructor.
	 *
	 * @param WP_Auth0_Options_Generic $options
	 */
	public function __construct( WP_Auth0_Options_Generic $options ) {
		$this->options = $options;
		$this->_option_name = $options->get_options_name();
	}

	protected function init_option_section( $sectionName, $id, $settings ) {
		$options_name = $this->_option_name . '_' . strtolower( $id );

		add_settings_section(
			"wp_auth0_{$id}_settings_section",
			$sectionName,
			array( $this, 'render_description' ),
			$options_name
		);

		foreach ( $settings as $setting ) {
			add_settings_field(
				$setting['id'],
				__( $setting['name'], 'wp-auth0' ),
				array( $this, $setting['function'] ),
				$options_name,
				"wp_auth0_{$id}_settings_section",
				array( 'label_for' => $setting['id'] )
			);
		}
	}

	/**
	 * Render description at the top of the settings block
	 */
	public function render_description() {
		if ( ! empty( $this->_description ) ) {
			printf( '<p class="a0-step-text">%s</p>', $this->_description );
		}
	}

	public function input_validator( $input, $old_options = null ) {
		if ( empty( $old_options ) ) {
			$old_options = $this->options->get_options();
		}

		foreach ( $this->actions_middlewares as $action ) {
			$input = $this->$action( $old_options, $input );
		}

		return $input;
	}

	/**
	 * Wrapper for add_settings_error
	 *
	 * @param string $error - translated error message
	 */
	protected function add_validation_error( $error ) {
		add_settings_error(
			$this->_option_name,
			$this->_option_name,
			$error,
			'error'
		);
	}

	protected function rule_validation( $old_options, $input, $key, $rule_name, $rule_script ) {
		$input[$key] = ( isset( $input[$key] ) ? $input[$key] : null );

		if ( ( $input[$key] !== null && $old_options[$key] === null ) || ( $input[$key] === null && $old_options[$key] !== null ) ) {

			try {

				$operations = new WP_Auth0_Api_Operations( $this->options );
				$input[$key] = $operations->toggle_rule ( $this->options->get( 'auth0_app_token' ), ( is_null( $input[$key] ) ? $old_options[$key] : null ), $rule_name, $rule_script );

			} catch ( Exception $e ) {
				$this->add_validation_error( $e->getMessage() );
				$input[$key] = null;
			}
		}

		return $input;
	}


	// TODO: Deprecate
	protected function render_a0_switch( $id, $name, $value, $checked ) {
?>

    <div class="a0-switch">
      <input type="checkbox" name="<?php echo $this->_option_name; ?>[<?php echo $name; ?>]" id="<?php echo $id; ?>" value="<?php echo $value; ?>" <?php echo checked( $checked ); ?>/>
      <label for="<?php echo $id; ?>"></label>
    </div>

    <?php
	}

	/**
	 * Output a stylized switch on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 * @param string $expand_id - id of a field that should be hidden until this switch is active
	 */
	protected function render_switch( $id, $input_name, $expand_id = '' ) {
		$value = $this->options->get( $input_name );
		printf(
			'<div class="a0-switch"><input type="checkbox" name="%s[%s]" id="%s" data-expand="%s" value="1"%s>
			<label for="%s"></label></div>',
			esc_attr( $this->option_name ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			! empty( $expand_id ) ? esc_attr( $expand_id ) : '',
			checked( empty( $value ), FALSE, FALSE ),
			esc_attr( $id )
		);
	}

	/**
	 * Output a stylized text field on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 * @param string $type - input type attribute
	 * @param string $placeholder - input placeholder
	 * @param string $style - inline CSS
	 */
	protected function render_text_field( $id, $input_name, $type = 'text', $placeholder = '', $style = '' ) {
		$value = $this->options->get( $input_name );
		// Secure fields are not output by default; validation keeps last value if a new one is not entered
		if ( 'password' === $type ) {
			$placeholder = ! empty( $value ) ? 'Not visible' : '';
			$value = '';
		}
		printf(
			'<input type="%s" name="%s[%s]" id="%s" value="%s" placeholder="%s" style="%s">',
			esc_attr( $type ),
			esc_attr( $this->option_name ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			esc_attr( $value ),
			$placeholder ? esc_attr( $placeholder ) : '',
			$style ? esc_attr( $style ) : ''
		);
	}

	/**
	 * Output a stylized social key text field on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 */
	protected function render_social_key_field( $id, $input_name ) {
		printf(
			'<input type="text" name="%s[%s]" id="wpa0_%s" value="%s">',
			esc_attr( $this->option_name ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			esc_attr( $this->options->get_connection( $input_name ) )
		);
	}

	/**
	 * Output a stylized textarea field on the options page
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 */
	protected function render_textarea_field( $id, $input_name ) {
		$value = $this->options->get( $input_name );
		printf(
			'<textarea name="%s[%s]" id="%s" rows="%d" class="code">%s</textarea>',
			esc_attr( $this->option_name ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			$this->textarea_rows,
			esc_textarea( $value )
		);
	}

	/**
	 * Output a radio button
	 *
	 * @param string $id - input id attribute
	 * @param string $input_name - input name attribute
	 * @param string|integer|float $value - input value attribute
	 * @param string $label - input label text
	 * @param bool $selected - is it active?
	 */
	protected function render_radio_button( $id, $input_name, $value, $label = '', $selected = FALSE ) {
		printf(
			'<label for="%s"><input type="radio" name="%s[%s]" id="%s" value="%s" %s>&nbsp;%s</label>',
			esc_attr( $id ),
			esc_attr( $this->option_name ),
			esc_attr( $input_name ),
			esc_attr( $id ),
			esc_attr( $value ),
			checked( $selected, TRUE, FALSE ),
			sanitize_text_field( ! empty( $label ) ? $label : ucfirst( $value ) )
		);
	}

	/**
	 * Output a field description
	 *
	 * @param string $text - description text to display
	 */
	protected function render_field_description( $text ) {
		printf( '<div class="subelement"><span class="description">%s.</span></div>', $text );
	}

	/**
	 * Output translated dashboard HTML link
	 *
	 * @param string $path - dashboard sub-section, if any
	 *
	 * @return string
	 */
	protected function get_dashboard_link( $path = '' ) {
		return sprintf( '<a href="https://manage.auth0.com/#/%s" target="_blank">%s</a>',
			$path,
			__( 'Auth0 dashboard', 'wp-auth0' )
		);
	}

	/**
	 * Output a docs HTML link
	 *
	 * @param string $path - docs sub-page, if any
	 * @param string $text - link text, should be translated before passing
	 *
	 * @return string
	 */
	protected function get_docs_link( $path, $text = '' ) {
		$path = '/' === $path[0] ? substr( $path, 1 ) : $path;
		$text = empty( $text ) ? __( 'here', 'wp-auth0' ) : sanitize_text_field( $text );
		return sprintf( '<a href="https://auth0.com/docs/%s" target="_blank">%s</a>',	$path, $text );
	}

}
