<?php
/**
 * PixTypes.
 *
 * @package   PixEXIF
 * @author    pixelgrade <contact@pixelgrade.com>
 * @license   GPL-2.0+
 * @link      https://pixelgrade.com
 * @copyright 2015 PixelGrade
 */
class PixExifPlugin {

    /**
     * Plugin version, used for cache-busting of style and script file references.
     *
     * @since   1.0.0
     *
     * @const   string
     */
    protected $version = '1.0.0';
    /**
     * Unique identifier for your plugin.
     *
     * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
     * match the Text Domain file header in the main plugin file.
     *
     * @since    1.0.0
     *
     * @var      string
     */
    protected $plugin_slug = 'pixexif';

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    private $prefix = 'pix_exif_';

    /**
     * Fields to fetch from the EXIF data.
     * This is not the complete list of fields. Just the ones that we felt were the most important
     */
    protected $fields = array();

    /**
     * Path to the plugin.
     *
     * @since    1.0.0
     * @var      string
     */
    protected $plugin_basepath = null;

    /**
     * Plain old constructor.
     */
	public function __construct() {
        $this->plugin_basepath = plugin_dir_path( __FILE__ );

        $this->fields = array(
            'camera' => array (
                'label' => __( 'Camera', 'pixexif' ),
                'description' => __( 'Camera model.', 'pixexif' ),
            ),
            'aperture' => array(
                'label' => __( 'Aperture', 'pixexif' ),
                'description' => __( 'The aperture f-stop (only the number, without f/).', 'pixexif' ),
            ),
            'focal_length' => array(
                'label' => __( 'Focal Length', 'pixexif' ),
                'description' => __( 'The focal length that the image was shot with (in mm).', 'pixexif' ),
            ),
            'shutter_speed' => array(
                'label' => __( 'Shutter Speed', 'pixexif' ),
                'description' => __( 'The exposure time in fractional format (ie. 1/100).', 'pixexif' ),
            ),
            'iso' => array(
                'label' => __( 'ISO', 'pixexif' ),
                'description' => __( 'The ISO speed.', 'pixexif' ),
            ),
        );

        // Load plugin text domain
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		$this->setupFilters();
	}

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance() {

        // If the single instance hasn't been set, set it now.
        if ( null == self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

	/**
	 * Setup filters to add fields and process them when saving the attachment changes.
	 */
	private function setupFilters() {
        //register fields
		add_filter( 'attachment_fields_to_edit', array($this, 'registerAttachmentFields'), 10, 2 );
        //process the field values when the user hits Update
		add_filter( 'attachment_fields_to_save', array($this, 'saveAttachmentFields'), 10, 2 );
	}

	/**
	 * Register the EXIF fields.
	 *
	 * @param array $form_fields Fields to add to the attachment form
	 * @param object $post Attachment post object
	 * @return array $form_fields Modified form fields
	 */
	public function registerAttachmentFields($form_fields, $post) {
		// Check if this is actually an image.
        // bail if not
		if( ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}

        //add a text field for each field in $fields
		foreach( $this->fields as $fieldName => $value ) {
			$form_fields[ $this->prefix . $fieldName ] = array(
                'value' => $this->get_exif_meta( $post->ID, $fieldName ),
				'label' => $value['label'],
				'helps' => $value['description'],
			);

            //handle those special cases when we need to undo what WordPress does to the EXIF meta
            switch ( $fieldName ) {
                case 'shutter_speed':
                    $form_fields[ $this->prefix . $fieldName ]['value'] = timber_convert_exposure_to_frac( $form_fields[ $this->prefix . $fieldName ]['value'] );
                    break;
            }
		}

		return $form_fields;
	}

    /**
     * Update EXIF fields.
     *
     * @param array $post The post data
     * @param array $attachment Attachment fields from the $_POST form
     * @return array $post Modified post data
     */
	public function saveAttachmentFields($post, $attachment) {
        // Check if this is actually an image.
        // bail if not
		if( ! wp_attachment_is_image( $post['ID'] ) ) {
			return $post;
		}

        //First read the current attachment metadata
        $meta_data = wp_get_attachment_metadata( $post['ID'] );

		foreach( $this->fields as $fieldName => $value ) {
            //check existence just to be sure
            //WordPress will create empty entries even if the EXIF entry in not present in the image EXIF metadata
			if( isset( $attachment[ $this->prefix . $fieldName ] ) ) {
                //depending on each field do what WordPress does to them - a sort of normalization
                switch ( $fieldName ) {
                    case 'aperture':
                        $meta_data['image_meta'][$fieldName] = round( wp_exif_frac2dec( $attachment[ $this->prefix . $fieldName ] ), 2 );
                        break;
                    case 'focal_length':
                    case 'shutter_speed':
                        $meta_data['image_meta'][$fieldName] = (string) wp_exif_frac2dec( $attachment[ $this->prefix . $fieldName ] );
                        break;
                    default:
                        $meta_data['image_meta'][$fieldName] = trim( $attachment[ $this->prefix . $fieldName ] );
                }
			}
		}

        //save the modified EXIF data into the database
        wp_update_attachment_metadata( $post['ID'], $meta_data );

		return $post;
	}

    /**
     * Read a certain EXIF field from the database
     *
     * @param int $attachment_ID The attachment ID
     * @param string $field_name The EXIF field (as WordPress calls it)
     * @return string EXIF field value or empty string
     */
    private function get_exif_meta( $attachment_ID, $field_name ) {
        $meta_data = wp_get_attachment_metadata( $attachment_ID );
        if ( isset( $meta_data['image_meta'] ) ) {
            if ( isset( $meta_data['image_meta'][$field_name] ) ) {
                return $meta_data['image_meta'][$field_name];
            }
        }

        return '';
    }

    /**
     * Fired when the plugin is deactivated.
     * @since    1.0.0
     * @param    boolean    $network_wide    True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
     */
    static function deactivate( $network_wide ) {
        // TODO: Define deactivation functionality here
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    function load_plugin_textdomain() {

        $domain = $this->plugin_slug;
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
    }

} // class PixExifEditor