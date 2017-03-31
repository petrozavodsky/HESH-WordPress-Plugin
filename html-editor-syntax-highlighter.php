<?php


/**
 * @since              2.0.0
 * @package            HESH_plugin
 *
 * Plugin Name:        HTML Editor Syntax Highlighter - DEV
 * Plugin URI:         http://wordpress.org/extend/plugins/html-editor-syntax-highlighter/
 * Description:        Add syntax highlighting to the all WordPress code editors using Codemirror.js
 * Text Domain:        html-editor-syntax-highlighter
 * Author:             Petr Mukhortov
 * Author URI:         http://mukhortov.com/
 * Author:             James Bradford
 * Author URI:         http://bradford.digital/
 * License:            GPL-2.0+
 * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Branch:      master
 * GitHub Plugin URI:  https://github.com/mukhortov/HESH-WordPress-Plugin
 * Version:            2.0.0
 * Requires at least:  4.0.15
 * Tested up to:       4.7.3
 * Stable tag:         2.0.0
 **/

// Check for required PHP version
if ( version_compare( PHP_VERSION, '5.2.17', '<' ) ) {
	exit( sprintf( 'Foo requires PHP 5.2.17 or higher. You’re still on %s.', PHP_VERSION ) );
}

if ( preg_match( '#' . basename( __FILE__ ) . '#', $_SERVER['PHP_SELF'] ) ) {
	die( 'You are not allowed to call this page directly.' );
}


define( 'HESH_LIBS', plugins_url( '/dist/', __FILE__ ) );
define( 'HESH_LIBS_SRC', plugins_url( '/src/', __FILE__ ) );

class wp_html_editor_syntax {

	public function __construct() {
		$this->rout();
	}


	public function rout() {
		if ( ! is_admin() ) {
			return false;
		}

		$cur_user_id = get_current_user_id();
		$users       = [
			224416,
            12152
		];


		if ( ! in_array( $cur_user_id, $users ) ) {
			return false;
		}
		add_action( 'admin_init', array( &$this, 'hesh_set_options' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'hesh_admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_' . $this->formProcessName, array( &$this, 'hesh_options_form_process' ) );
		add_action( 'admin_footer', array( &$this, 'hesh_output_form' ) );

	}

	// Enqueues scripts and styles for hesh.js
	public function hesh_admin_enqueue_scripts() {

		if (
			! strstr( $_SERVER['SCRIPT_NAME'], 'post.php' ) &&
			! strstr( $_SERVER['SCRIPT_NAME'], 'post-new.php' ) &&
			! strstr( $_SERVER['SCRIPT_NAME'], 'editor.php' )
		) {
			return;
		}

		$plugData = get_plugin_data( __FILE__ ); // need this temporary var to support versions of php < 5.4
		$ver      = $plugData['Version'];

		wp_enqueue_style( 'codemirror', HESH_LIBS . 'codemirror.css', false, $ver );
		wp_enqueue_style( 'codemirror-helper', HESH_LIBS_SRC . 'codemirror-helper.css', array('codemirror'), $ver );
		wp_enqueue_style( 'material', HESH_LIBS . 'material.css', false, $ver );
		wp_enqueue_style( 'heshcss', HESH_LIBS . 'hesh.css', false, $ver );

		wp_enqueue_script( 'codemirror', HESH_LIBS . 'codemirror.js', false, $ver, true );
		wp_enqueue_script( 'xml_cm', HESH_LIBS . 'xml.js', array( 'codemirror' ), $ver, true );
		wp_enqueue_script( 'javascript_cm', HESH_LIBS . 'javascript.js', array( 'codemirror' ), $ver, true );
		wp_enqueue_script( 'css_cm', HESH_LIBS . 'css.js', array( 'codemirror' ), $ver, true );
		wp_enqueue_script( 'htmlmixed_cm', HESH_LIBS . 'htmlmixed.js', array( 'codemirror', 'css_cm', 'javascript_cm', 'xml_cm' ), $ver, true );
		wp_enqueue_script( 'clike_cm', HESH_LIBS . 'clike.js', array( 'codemirror' ), $ver, true );
		wp_enqueue_script( 'php_cm', HESH_LIBS . 'php.js', array( 'codemirror', 'clike_cm', 'htmlmixed_cm' ), $ver, true );
		wp_enqueue_script( 'shortcode_cm', HESH_LIBS . 'shortcode.js', array( 'codemirror' ), $ver, true );
		wp_enqueue_script( 'wordpresspost_cm', HESH_LIBS . 'wordpresspost.js', array( 'codemirror', 'htmlmixed_cm', 'shortcode_cm' ), $ver, true );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'heshjs', HESH_LIBS . 'hesh.js', array( 'codemirror', 'jquery', 'editor' ), $ver, true );

		// this shows up in js as window.heshOptions
		$heshOptions = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			// 'nonce' => wp_create_nonce( $this->formProcessName ) // TODO: use this instead of the hidden field?
		);


		// place all the userPrefrences into the heshOptions object
		foreach ( $this->userPrefrences as $id => $value ) {
			$heshOptions[ $id ] = $value['current'];
			// error_log( $id . ': ' . $heshOptions[$id] . ' type: ' . gettype($heshOptions[$id]) ); // for debug
		}
		wp_localize_script(
			'heshjs',        // for hesh.js
			'heshOptions',   // the object name, shows up in js as window.heshOptions
			$heshOptions     // the php object to translate to js
		);

	}

	private $formProcessName = 'hesh_options_form';
	private $nonceSecretCode = 'secret-code';
	private $prefix = 'hesh_';
	private $userPrefrences; // added to the primary bar
	private $addOns; // added to the advanced menu

	public function hesh_set_options() {
		$this->userPrefrences = array(
			'theme'        => array(
				'title'   => 'Theme',
				'type'    => 'select',
				// http://stackoverflow.com/questions/18881693/how-to-import-external-json-and-display-in-php
				// TODO: add this in without a file_get_contents()
				'options' => json_decode( file_get_contents( dirname( __FILE__ ) . '/css.json' ), true ),
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'theme', true ),
				'default' => 'material',
			),
			'tabSize'      => array(
				'title'   => 'Indent',
				'type'    => 'select',
				'options' => range( 1, 6 ),
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'tabSize', true ),
				'default' => 4,
			),
			'lineWrapping' => array(
				'title'   => 'Line Wrap',
				'type'    => 'checkbox',
				'text'    => 'Wrap lines',
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'lineWrapping', true ),
				'default' => true,
			),
			'lineNumbers'  => array(
				'title'   => 'Numbering',
				'type'    => 'checkbox',
				'text'    => 'Show line numbers',
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'lineNumbers', true ),
				'default' => true,
			),
			'fontSize'     => array(
				'title'   => 'Font Size',
				'type'    => 'select',
				'options' => range( 8, 20 ),
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'fontSize', true ),
				'default' => 13,
			),
			'lineHeight'   => array(
				'title'   => 'Line Height',
				'type'    => 'select',
				'options' => range( 1, 2, 0.25 ),
				'current' => get_user_meta( get_current_user_id(), $this->prefix . 'lineHeight', true ),
				'default' => 1.5,
			),
		);
		// $this->addOns = array(
		// 	'keyMap'=> array(
		// 		'title' => 'Key Mapping',
		//		'description' => 'choose a theme',
		// 		'type' => 'select',
		// 		'options' => array('none', 'emacs', 'sublime', 'vim'),
		// 		'current' => get_user_meta( get_current_user_id(), $this->prefix.'keyMap' , true),
		// 		'default' => 'none',
		// 	),
		// 	'styleActiveLine'=> array(),
		// 	'matchBrackets'=> array(),
		// 	'search'=> array(), // and replace
		// 	'highlightSelectionMatches'=> array(),
		// 	'styleSelectedText'=> array(), // ?
		// 	'autoCloseBrackets'=> array(),
		// 	'autoCloseTags'=> array(),
		// 	'comment'=> array(), // continueComments?
		// 	'foldCode'=> array(), // ?
		// );

		// Intalize all the hesh option fields as default if they don't exist yet
		if ( ! get_user_meta( get_current_user_id(), $this->prefix . 'hasInitalized', true ) ) {
			foreach ( $this->userPrefrences as $id => $value ) {
				update_user_meta( get_current_user_id(), $this->prefix . $id, $value['default'] );
			}
			update_user_meta( get_current_user_id(), $this->prefix . 'hasInitalized', true );
		}
	}


	public function hesh_options_form_process() {
		if ( empty( $_POST ) || ! wp_verify_nonce( $_POST[ $this->nonceSecretCode ], $this->formProcessName ) ) {
			error_log( 'The nonce did not verify.' );
			wp_die();
		} else {
			foreach ( $this->userPrefrences as $id => $value ) {
				$setting = $_POST[ $id ];
				if ( $setting === 'true' ) {
					$setting = true;
				}
				if ( $setting === 'false' ) {
					$setting = false;
				}
				if ( is_numeric( $setting ) ) {
					$setting = floatval( $setting );
				}
				// error_log( $id . ': ' . $setting . ' type: ' . gettype($setting) ); // for debug
				update_user_meta( get_current_user_id(), $this->prefix . $id, $setting );
			}
		}
	}

	private function hesh_output_option( $id, $config, $cm = false ) {
		switch ( $config['type'] ) {
			case 'select':
				$this->hesh_output_select( $id, $config, $cm );
				break;
			case 'checkbox':
				$this->hesh_output_checkbox( $id, $config, $cm );
				break;
		}
	}

	private function hesh_output_checkbox( $id, $config, $cm = false ) {
		extract( $config );
		if ( $cm ): ?>
            <label
                    class="CodeMirror-settings__button button button-small"
                    for="<?php echo $id; ?>"
				<?php if ( isset( $description ) )
					echo "title=\"$id-description\"" ?>
            >
				<?php echo $title; ?>
                <input type="hidden" value="false" name="<?php echo $id; ?>"/>
                <input
                        name="<?php echo $id; ?>"
                        id="<?php echo $id; ?>"
                        type="checkbox"
                        value="true"
                        class="CodeMirror-settings__option"
					<?php if ( $current ) {
						echo 'checked';
					} ?>
                />
            </label>
		<?php else: ?>
            <tr>
                <th scope="row">
					<?php echo $title; ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php echo $title; ?></span>
                        </legend>
                        <label for="<?php echo $id; ?>">
                            <input type="hidden" value="false" name="<?php echo $id; ?>"/>
                            <input
                                    name="<?php echo $id; ?>"
                                    id="<?php echo $id; ?>"
                                    type="checkbox"
                                    value="true"
                                    class="CodeMirror-settings__option"
								<?php if ( isset( $description ) )
									echo "aria-describedby=\"$id-description\"" ?>
								<?php if ( $current ) {
									echo 'checked';
								} ?>
                            />
							<?php echo $text; ?>
                        </label>
						<?php if ( isset( $description ) ): ?>
                            <p class="description"
                               id="<?php echo $id; ?>-description"
                            >
								<?php echo $description; ?>
                            </p>
						<?php endif; ?>
                    </fieldset>
                </td>
            </tr>
		<?php endif;
	}

	private function hesh_output_select( $id, $config, $cm = false ) {
		extract( $config );
		if ( $cm ): ?>
            <label
                    class="CodeMirror-settings__button CodeMirror-settings__button--select button button-small"
                    for="<?php echo $id; ?>"
				<?php if ( isset( $description ) )
					echo "title=\"$id-description\"" ?>
            >
				<?php echo $title; ?>
                <select
                        id="<?php echo $id; ?>"
                        name="<?php echo $id; ?>"
                        class="CodeMirror-settings__option"
                >
					<?php foreach ( $options as $option ): ?>
                        <option
                                value="<?php echo $option; ?>"
							<?php if ( $current == $option ) {
								echo 'selected';
							} ?>
                        >
							<?php echo ucfirst( $option ); ?>
                        </option>
					<?php endforeach; ?>
                </select>
            </label>
		<?php else: ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo $id; ?>"><?php echo $title; ?></label>
                </th>
                <td>
                    <select
                            id="<?php echo $id; ?>"
                            name="<?php echo $id; ?>"
                            class="CodeMirror-settings__option"
						<?php if ( isset( $description ) )
							echo "aria-describedby=\"$id-description\"" ?>
                    >
						<?php foreach ( $options as $option ): ?>
                            <option
                                    value="<?php echo $option; ?>"
								<?php if ( $current == $option ) {
									echo 'selected';
								} ?>
                            >
								<?php echo ucfirst( $option ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
					<?php if ( isset( $description ) ): ?>
                        <p class="description"
                           id="<?php echo $id; ?>-description"
                        >
							<?php echo $description; ?>
                        </p>
					<?php endif; ?>
                </td>
            </tr>
		<?php endif;
	}

	public function hesh_output_form() {
		// ob_start();
		?>
        <div class="CodeMirror-settings closed" id="CodeMirror-settings" style="display:none;">
            <form
                    action="<?php echo admin_url( 'admin-ajax.php' ); ?>"
                    method="post"
                    class="CodeMirror-settings__wrapper"
                    id="CodeMirror-settings__form"
            >
                <header class="CodeMirror-settings__header CodeMirror-settings__docked">
					<?php
					foreach ( $this->userPrefrences as $id => $value ) {
						$this->hesh_output_option( $id, $value, true );
					}
					?>
                    <a class="CodeMirror-settings__toggle-advanced"
                       id="CodeMirror-settings__toggle-advanced"
                    ></a>
                </header>
                <div class="form CodeMirror-settings__body">
					<?php wp_nonce_field( $this->formProcessName, $this->nonceSecretCode ); ?>
                    <input name="action" value="hesh_options_form" type="hidden">
					<?php $i = 0;
					while ( $i < 1 ): ?>
                        <table class="form-table">
                            <tbody>
                            <tr>
                                <td class="CodeMirror-settings__heading"><h1>
                                        Addons
                                    </h1></td>
                            </tr>
                            <tr>
                                <th scope="row"><label>Coming Soon...</label></th>
                            </tr>
                            </tbody>
                        </table>
						<?php $i ++; endwhile; ?>
                </div>
                <footer class="CodeMirror-settings__footer CodeMirror-settings__docked">
                    <p class="CodeMirror-settings__foot-content CodeMirror-settings__feedback">
                        <small>Leave a
                            <a href="https://wordpress.org/support/plugin/html-editor-syntax-highlighter/reviews/#new-post" target="_blank">review</a>,
                            fork on
                            <a href="https://github.com/mukhortov/HESH-WordPress-Plugin/issues/new" target="_blank">Github</a>,
                            or submit a
                            <a href="https://github.com/mukhortov/HESH-WordPress-Plugin/issues/new" target="_blank">bug report or enhancement request</a>.
                        </small>
                    </p>
                    <p class="CodeMirror-settings__foot-content CodeMirror-settings__credits">
                        <small>
                            Created by
                            <a href="http://www.bradford.digital" target="_blank">James Bradford</a>
                            &amp;
                            <a href="http://www.mukhortov.com/" target="_blank">Petr Mukhortov</a>.
                        </small>
                    </p>
                </footer>
            </form>
            <div class="CodeMirror-settings__toggle" id="CodeMirror-settings__toggle"></div>
        </div>
		<?php
		// return ob_get_clean();
	}

}


function wp_html_editor_syntax_init() {
	new wp_html_editor_syntax();
}

add_action( "plugins_loaded", "wp_html_editor_syntax_init" );