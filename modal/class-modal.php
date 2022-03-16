<?php

defined('ABSPATH') or die("you do not have access to this page!");

if ( ! class_exists( 'rsssl_modal' ) ) {
    class rsssl_modal {
        private $capability  = 'manage_options';
        private $id;
        private $title;
        private $content;
        private $buttons;

        function __construct( $args ) {
			$this->id = $args['id'] ?? '' ;
            $this->title = $args['title'] ?? '' ;
			$this->subtitle = $args['subtitle'] ?? '';
            $this->content = $args['content'] ?? '' ;
            $this->buttons = $args['buttons'] ?? '' ;
            $this->generate_modal();
        }

	    /**
	     *
	     * @return void
         *
	     * Generate a modal
	     * @since 5.2.1
	     *
	     */

        private function generate_modal() {
            if ( ! current_user_can( $this->capability ) ) return;
			$content = '';
	        $header = $this->get_modal_template('modal-header.php', array('title' => $this->title) );
	        $footer = isset ( $this->buttons ) ? $this->generate_modal_buttons() : '';
			if ( isset( $this->content ) ) {
                if ( is_array( $this->content ) ) {
					$content = implode('<br>', $this->content);
                } else {
                    $content = $this->content;
                }
            }

	        echo $this->get_modal_template('modal-container.php', array(
		        'id' => $this->id,
		        'header' => $header,
				'subtitle' => $this->subtitle,
		        'content' => $content,
		        'footer' => $footer,
	        ));
        }

        /**
         * @return string
         *
         * Generate modal buttons
         */
        private function generate_modal_buttons() {
            $output = '';
            foreach ( $this->buttons as $button ) {
                if ( $button['type'] === 'data' ) {
					$output .= $this->get_modal_template('button-data.php',  array(
						'id' => $button['id'] ?? '',
						'button_text' => $button['text'] ?? '',
						'class' => $button['class'] ?? '',
						'nonce' => $button['nonce'] ?? '',
					));
                } else if ($button['type'] === 'link' ) {
	                $output .= $this->get_modal_template('button-link.php',  array(
		                'id' => $button['id'] ?? '',
		                'button_text' => $button['text'] ?? '',
		                'class' => $button['class'] ?? '',
		                'href' => $button['href'] ?? '',
	                ));
                }
            }

            return $output;
        }

        /**
         * Get template
         * @param string $file
         * @param array  $args
         *
         * @return string
         */
        private function get_modal_template($file, $args = array()) {
            $file = trailingslashit(plugin_dir_path(__FILE__)) . 'templates/' . $file;
            ob_start();
            require $file;
            $contents = ob_get_clean();
            if ( !empty($args) && is_array($args) ) {
                foreach($args as $fieldname => $value ) {
                    $contents = str_replace( '{'.$fieldname.'}', $value, $contents );
                }
            }

            return $contents;
        }

    }//class closure
}

/**
 * Enqueue scripts and styles
 */

function rsssl_modals_enqueue_modal_assets( $hook ) {
    if ( $hook !== 'settings_page_really-simple-ssl' && $hook !== 'settings_page_rlrsssl_really_simple_ssl' ) return;
	$minified = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    if ( is_rtl() ) {
        wp_register_style('rsssl-modals', trailingslashit(rsssl_pro_url) . "modal/assets/css/modals-rtl$minified.css", array(), rsssl_pro_version);
    } else {
        wp_register_style('rsssl-modals', trailingslashit(rsssl_pro_url) . "modal/assets/css/modals$minified.css", array(), rsssl_pro_version );
    }

    wp_register_script('rsssl-modals', trailingslashit(rsssl_pro_url) . "modal/assets/js/modals$minified.js", array(), rsssl_pro_version);
    wp_enqueue_script('rsssl-modals');
    wp_enqueue_style('rsssl-modals');
}
add_action('admin_enqueue_scripts', 'rsssl_modals_enqueue_modal_assets' );