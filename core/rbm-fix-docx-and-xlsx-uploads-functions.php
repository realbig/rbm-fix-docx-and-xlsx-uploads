<?php
/**
 * Provides helper functions.
 *
 * @since   {{VERSION}}
 *
 * @package RBM_Fix_DOCX_And_XLSX_Uploads
 * @subpackage RBM_Fix_DOCX_And_XLSX_Uploads/core
 */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Returns the main plugin object
 *
 * @since   {{VERSION}}
 *
 * @return  RBM_Fix_DOCX_And_XLSX_Uploads
 */
function RBMFIXDOCXANDXLSXUPLOADS() {
    return RBM_Fix_DOCX_And_XLSX_Uploads::instance();
}