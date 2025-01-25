<?php

class OpenAI_Tool {
	public string $name;
	public $callback;
	public string $description;
	public array $parameters;
	public bool $strict = true;

	public function __construct( string $name, string $description, array $parameters, callable|null $callback = null ) {
		$this->name        = $name;
		$this->description = $description;
		$this->parameters  = $parameters;
		if ( $callback ) {
			$this->callback = $callback;
		} else {
			$this->callback = function ( $arguments ) {
				return new WP_Error( 'tool-not-callable', 'Tool not callable ' . $this->name );
			};
		}
	}

	public static function get_tools() {
		return apply_filters( 'pos_openai_tools', array() );
	}

	public static function get_tool( string $name ) {
		$matching = array_filter( self::get_tools(), function( $tool ) use ( $name ) {
			return $tool->name === $name;
		} );
		return array_shift( $matching );
	}

	public function get_function_signature() {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $this->name,
				'strict'      => $this->strict,
				'description' => $this->description,
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => (object) $this->parameters,
					'required'             => array_keys( $this->parameters ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function get_function_signature_for_realtime_api() {
		$signature = array(
			'type'     => 'function',
			'name'        => $this->name,
			'description' => $this->description,
		);
		if ( ! empty( $this->parameters ) ) {
			$signature['parameters'] = array(
				'type'                 => 'object',
				'properties'           => $this->parameters,
				'required'             => array_keys( $this->parameters ),
				'additionalProperties' => false,
			);
		}
		return $signature;
	}

	public function invoke( array $arguments ) {
		return call_user_func( $this->callback, $arguments );
	}

	public function invoke_for_function_call( array $arguments ): string {
		$result = $this->invoke( $arguments );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		} else if ( is_string( $result ) ) {
			return $result;
		} else {
			return wp_json_encode( $result );
		}
	}
}
