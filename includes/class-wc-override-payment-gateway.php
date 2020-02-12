<?php

if ( ! class_exists( 'WC_Payment_Gateway_Xpay' ) ) :
	class WC_Payment_Gateway_Xpay extends WC_Payment_Gateway {
		/**
		 * Generate Text HTML.
		 *
		 * @param  mixed $key
		 * @param  mixed $data
		 * @since  1.0.0
		 * @return string
		 */
		public function generate_html_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'type'              => 'html',
				'description'       => '',
			);
			$data = wp_parse_args( $data, $defaults );
			ob_start();
			?>
				<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				</th>
				<td class="forminp">
					<?php echo $data['description']; ?>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

	}
endif;
