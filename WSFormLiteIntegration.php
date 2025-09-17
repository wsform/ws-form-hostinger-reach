<?php

namespace Hostinger\Reach\Integrations;

use Hostinger\Reach\Api\Handlers\IntegrationsApiHandler;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use WP_Post;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class WSFormLiteIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'ws-form-lite';
    protected ReachApiHandler $reach_api_handler;
    protected IntegrationsApiHandler $integrations_api_handler;

    public function __construct( ReachApiHandler $reach_api_handler, IntegrationsApiHandler $integrations_api_handler ) {
        parent::__construct();
        $this->reach_api_handler        = $reach_api_handler;
        $this->integrations_api_handler = $integrations_api_handler;
    }

    public function init(): void {
        if ( $this->integrations_api_handler->is_active( self::INTEGRATION_NAME ) ) {
            add_action( 'wsf_submit_post_complete', array( $this, 'handle_submission' ), 10, 1 );
            add_filter( 'hostinger_reach_forms', array( $this, 'load_forms' ), 10, 2 );
            add_filter( 'hostinger_reach_after_form_state_is_set', array( $this, 'on_form_activation_change' ), 10, 3 );
        }
    }

    public function handle_submission( object $submit_object ): void {

        // Check if form status is valid for form submissions
        if (
            !isset($submit_object->form_object) ||
            !isset($submit_object->form_object->status) ||
            !in_array($submit_object->form_object->status, array('publish', 'draft'))
        ) {
            return;
        }

        // Get email address
        $email = $this->find_field( $submit_object, 'email' );

        if ( $email ) {

            // Get form object from submit object
            $form_object = $submit_object->form_object;

            $response = $this->reach_api_handler->post_contact(
                array(
                    // translators: %s - form id.
                    'group'    => $form_object->label ?? sprintf( __( 'WS Form LITE %s', 'hostinger-reach' ), $form->label ),
                    'email'    => $email,
                    'metadata' => array(
                        'plugin' => self::INTEGRATION_NAME,
                    ),
                )
            );

            if ( $response->get_status() < 300 ) {
                $this->update_form_submissions( $form_data['id'] );
            }
        }
    }

    public function find_field( object $submit_object ): string {

        // Get form object from submit object
        $form_object = $submit_object->form_object;

        // Get form fields from form object
        $fields = \WS_Form_Common::get_fields_from_form( $form_object, true );

        // Process fields
        $field_id = false;
        foreach ( $fields as $field ) {

            if ( !isset($field->type) ) { continue; }

            // Look for first email field
            if( $field->type == 'email' ) {

                $field_id = $field->id;
                break;
            }
        }

        // If email field found, get email from submission
        if ( $field_id === false ) { return false; }

        // Build meta key
        $meta_key = sprintf( 'field_%u', $field_id );

        // Get submit value
        return \WS_Form_Action::get_submit_value(

            $submit_object,
            $meta_key
        );
    }


    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public function get_post_type(): string {
 
        // WS Form uses its own custom tables instead of posts for performance
        return false;
    }

    public function is_form_valid( WP_Post $post ): bool {
        $form_fields = ws-form_get_form_fields( $post->ID, array( 'email' ) );

        return ! empty( $form_fields );
    }

    public function get_plugin_data( array $plugin_data ): array {
        $plugin_data[ self::INTEGRATION_NAME ] = array(
            'folder'       => 'ws-form',
            'file'         => 'ws-form.php',
            'admin_url'    => 'admin.php?page=ws-form',
            'add_form_url' => 'admin.php?page=ws-form-add',
            'edit_url'     => 'admin.php?page=ws-form-edit&id=8967={form_id}',
            'url'          => 'https://wordpress.org/plugins/ws-form/',
            'download_url' => 'https://downloads.wordpress.org/plugin/ws-form.zip',
            'title'        => __( 'WS Form LITE', 'hostinger-reach' ),
        );

        return $plugin_data;
    }
}
