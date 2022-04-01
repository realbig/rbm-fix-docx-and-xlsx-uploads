<?php
/**
 * Plugin Name: RBM Fix Google-exported .docx and .xlsx Uploads
 * Plugin URI: https://github.com/realbig/rbm-fix-docx-and-xlsx-uploads
 * Description: .docx and .xlsx files that are exported from Google Docs and Google Sheets report an incorrect File MIME Type which causes WordPress to reject them on upload. This plugin accounts for this incorrect MIME Type to allow them through.
 * Version: 1.0.0
 * Text Domain: rbm-fix-docx-and-xlsx-uploads
 * Author: Real Big Marketing
 * Author URI: https://realbigmarketing.com/
 * Contributors: d4mation
 * GitHub Plugin URI: https://github.com/realbig/rbm-fix-docx-and-xlsx-uploads
 * GitHub Branch: master
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'RBM_Fix_DOCX_And_XLSX_Uploads' ) ) {

    /**
     * Main RBM_Fix_DOCX_And_XLSX_Uploads class
     *
     * @since      1.0.0
     */
    final class RBM_Fix_DOCX_And_XLSX_Uploads {
        
        /**
         * @var          array $plugin_data Holds Plugin Header Info
         * @since        1.0.0
         */
        public $plugin_data;
        
        /**
         * @var          array $admin_errors Stores all our Admin Errors to fire at once
         * @since        1.0.0
         */
        private $admin_errors = array();

        /**
         * Get active instance
         *
         * @access     public
         * @since      1.0.0
         * @return     object self::$instance The one true RBM_Fix_DOCX_And_XLSX_Uploads
         */
        public static function instance() {
            
            static $instance = null;
            
            if ( null === $instance ) {
                $instance = new static();
            }
            
            return $instance;

        }
        
        protected function __construct() {
            
            $this->setup_constants();
            $this->load_textdomain();
            
            if ( version_compare( get_bloginfo( 'version' ), '4.4' ) < 0 ) {
                
                $this->admin_errors[] = sprintf( _x( '%s requires v%s of %sWordPress%s or higher to be installed!', 'First string is the plugin name, followed by the required WordPress version and then the anchor tag for a link to the Update screen.', 'rbm-fix-docx-and-xlsx-uploads' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '4.4', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>', '</strong></a>' );
                
                if ( ! has_action( 'admin_notices', array( $this, 'admin_errors' ) ) ) {
                    add_action( 'admin_notices', array( $this, 'admin_errors' ) );
                }
                
                return false;
                
            }
            
            $this->require_necessities();
            
            // Register our CSS/JS for the whole plugin
            add_action( 'init', array( $this, 'register_scripts' ) );

            add_filter( 'wp_check_filetype_and_ext', array( $this, 'wp_check_filetype_and_ext' ), 10, 5 );
            
        }

        /**
         * Setup plugin constants
         *
         * @access     private
         * @since      1.0.0
         * @return     void
         */
        private function setup_constants() {
            
            // WP Loads things so weird. I really want this function.
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }
            
            // Only call this once, accessible always
            $this->plugin_data = get_plugin_data( __FILE__ );

            if ( ! defined( 'RBM_Fix_DOCX_And_XLSX_Uploads_VER' ) ) {
                // Plugin version
                define( 'RBM_Fix_DOCX_And_XLSX_Uploads_VER', $this->plugin_data['Version'] );
            }

            if ( ! defined( 'RBM_Fix_DOCX_And_XLSX_Uploads_DIR' ) ) {
                // Plugin path
                define( 'RBM_Fix_DOCX_And_XLSX_Uploads_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
            }

            if ( ! defined( 'RBM_Fix_DOCX_And_XLSX_Uploads_URL' ) ) {
                // Plugin URL
                define( 'RBM_Fix_DOCX_And_XLSX_Uploads_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
            }
            
            if ( ! defined( 'RBM_Fix_DOCX_And_XLSX_Uploads_FILE' ) ) {
                // Plugin File
                define( 'RBM_Fix_DOCX_And_XLSX_Uploads_FILE', __FILE__ );
            }

        }

        /**
         * Internationalization
         *
         * @access     private 
         * @since      1.0.0
         * @return     void
         */
        private function load_textdomain() {

            // Set filter for language directory
            $lang_dir = trailingslashit( RBM_Fix_DOCX_And_XLSX_Uploads_DIR ) . 'languages/';
            $lang_dir = apply_filters( 'rbm_fix_docx_and_xlsx_uploads_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'rbm-fix-docx-and-xlsx-uploads' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'rbm-fix-docx-and-xlsx-uploads', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = trailingslashit( WP_LANG_DIR ) . 'rbm-fix-docx-and-xlsx-uploads/' . $mofile;

            if ( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/rbm-fix-docx-and-xlsx-uploads/ folder
                // This way translations can be overridden via the Theme/Child Theme
                load_textdomain( 'rbm-fix-docx-and-xlsx-uploads', $mofile_global );
            }
            else if ( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/rbm-fix-docx-and-xlsx-uploads/languages/ folder
                load_textdomain( 'rbm-fix-docx-and-xlsx-uploads', $mofile_local );
            }
            else {
                // Load the default language files
                load_plugin_textdomain( 'rbm-fix-docx-and-xlsx-uploads', false, $lang_dir );
            }

        }
        
        /**
         * Include different aspects of the Plugin
         * 
         * @access     private
         * @since      1.0.0
         * @return     void
         */
        private function require_necessities() {
            
        }

        /**
         * Fix Google-exported .docx and .xlsx uploads
         * Based on https://awest.uk/php-bug-when-uploading-word-or-excel-files-to-wordpress/
         * It appears to have been fixed with PHP 7.4+, but Google Docs/Sheets still exports with the incorrect MIME Type
         *
         * @param array        $args {
         *     Values for the extension, mime type, and corrected filename.
         *
         *     @type string|false $ext              File extension, or false if the file doesn't match a mime type.
         *     @type string|false $type             File mime type, or false if the file doesn't match a mime type.
         *     @type string|false $proper_filename  File name with its correct extension, or false if it cannot be determined.
         * }
         * @param string       $file                      Full path to the file.
         * @param string       $filename                  The name of the file (may differ from $file due to
         *                                                $file being in a tmp directory).
         * @param string[]     $mimes                     Array of mime types keyed by their file extension regex.
         * @param string|false $real_mime                 The actual mime type or false if the type cannot be determined.
         *
         * @access  public
         * @since   1.0.0
         * @return  array                                 Values for the extension, mime type, and corrected filename.
         */
        public function wp_check_filetype_and_ext( $args, $file, $filename, $mimes, $real_mime ) {

            $wp_filetype = wp_check_filetype( $filename, $mimes );
            $ext = $wp_filetype['ext'];

            if ( $ext == 'docx' && $real_mime == 'application/vnd.openxmlformats-officedocument.wordprocessingml.documentapplication/vnd.openxmlformats-officedocument.wordprocessingml.document' ) {

                return array(
                    'ext' => 'docx',
                    'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'proper_filename' => $filename,
                );

            }
            else if ( $ext == 'xlsx' && $real_mime == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheetapplication/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) {

                return array(
                    'ext' => 'xlsx',
                    'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'proper_filename' => $filename,
                );

            }

            return $args;
            
        } 
        
        /**
         * Show admin errors.
         * 
         * @access     public
         * @since      1.0.0
         * @return     HTML
         */
        public function admin_errors() {
            ?>
            <div class="error">
                <?php foreach ( $this->admin_errors as $notice ) : ?>
                    <p>
                        <?php echo $notice; ?>
                    </p>
                <?php endforeach; ?>
            </div>
            <?php
        }
        
        /**
         * Register our CSS/JS to use later
         * 
         * @access     public
         * @since      1.0.0
         * @return     void
         */
        public function register_scripts() {
            
            wp_register_style(
                'rbm-fix-docx-and-xlsx-uploads',
                RBM_Fix_DOCX_And_XLSX_Uploads_URL . 'dist/assets/css/app.css',
                null,
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBM_Fix_DOCX_And_XLSX_Uploads_VER
            );
            
            wp_register_script(
                'rbm-fix-docx-and-xlsx-uploads',
                RBM_Fix_DOCX_And_XLSX_Uploads_URL . 'dist/assets/js/app.js',
                array( 'jquery' ),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBM_Fix_DOCX_And_XLSX_Uploads_VER,
                true
            );
            
            wp_localize_script( 
                'rbm-fix-docx-and-xlsx-uploads',
                'rbmFixDOCXandXLSXUploads',
                apply_filters( 'rbm_fix_docx_and_xlsx_uploads_localize_script', array() )
            );
            
            wp_register_style(
                'rbm-fix-docx-and-xlsx-uploads-admin',
                RBM_Fix_DOCX_And_XLSX_Uploads_URL . 'dist/assets/css/admin.css',
                null,
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBM_Fix_DOCX_And_XLSX_Uploads_VER
            );
            
            wp_register_script(
                'rbm-fix-docx-and-xlsx-uploads-admin',
                RBM_Fix_DOCX_And_XLSX_Uploads_URL . 'dist/assets/js/admin.js',
                array( 'jquery' ),
                defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : RBM_Fix_DOCX_And_XLSX_Uploads_VER,
                true
            );
            
            wp_localize_script( 
                'rbm-fix-docx-and-xlsx-uploads-admin',
                'rbmFixDOCXandXLSXUploads',
                apply_filters( 'rbm_fix_docx_and_xlsx_uploads_localize_admin_script', array() )
            );
            
        }
        
    }
    
} // End Class Exists Check

/**
 * The main function responsible for returning the one true RBM_Fix_DOCX_And_XLSX_Uploads
 * instance to functions everywhere
 *
 * @since      1.0.0
 * @return     \RBM_Fix_DOCX_And_XLSX_Uploads The one true RBM_Fix_DOCX_And_XLSX_Uploads
 */
add_action( 'plugins_loaded', 'rbm_fix_docx_and_xlsx_uploads_load' );
function rbm_fix_docx_and_xlsx_uploads_load() {

    require_once trailingslashit( __DIR__ ) . 'core/rbm-fix-docx-and-xlsx-uploads-functions.php';
    RBMFIXDOCXANDXLSXUPLOADS();

}