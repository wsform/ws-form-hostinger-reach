<?php

use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Models\Form;

class WS_Form_Hostinger_Reach extends Integration {

    /**
     * Unique name for WS Form integration
     */
	public static function get_name(): string {
		return WS_FORM_NAME;
	}

    /**
     * WS Form integration data
     *
     * @return PluginData Plugin data model.
     *
     */
	public function get_plugin_data(): PluginData {

		// Configure integration
		return PluginData::from_array([
			'id'                  => $this->get_name(),
			'title'               => WS_FORM_NAME_GENERIC,
			'folder'              => WS_FORM_PLUGIN_FOLDER,
			'file'                => 'ws-form.php',
			'admin_url'           => 'admin.php?page=ws-form',
			'add_form_url'        => 'admin.php?page=ws-form-add',
			'edit_url'            => 'admin.php?page=ws-form-edit&id={form_id}',
			'url'                 => WS_FORM_URL_HOME,
			'download_url'        => WS_FORM_URL_DOWNLOAD,
			'icon'                => WS_FORM_URL_ICON,
			'is_view_form_hidden' => true,
			'is_edit_form_hidden' => false,
			'can_toggle_forms'    => true
		]);
	}

    /**
     * Hooks to run ONLY when the integration is active.
     * i.e Form submission should be here.
     */
	public function active_integration_hooks(): void {

		// Process WS Form submissions
		add_action( 

			'wsf_submit_post_complete', 
			array( $this, 'handle_submission' ) 
		);
	}

	public function handle_submission( object $submit_object ): void {

		// Check submit object
		if (
			!is_object($submit_object) ||
			!isset($submit_object->form_object) ||
			!isset($submit_object->form_object->status) ||
			!in_array($submit_object->form_object->status, array('publish', 'draft'))
		) {
			return;
		}

		// Get email address
		$email = $submit_object->find_field_value( 'email' );

		// Only process if an email address is found (WS Form already sanitizes email address)
		if ( !empty( $email ) ) {

			// Get form object from submit object
			$form_object = $submit_object->form_object;

			// Build args
			$args = array(
				'group'    => sprintf( __( '%s: %s', 'ws-form' ), WS_FORM_NAME_GENERIC, $form_object->label ),
				'email'    => $email,
				'metadata' => array(
					'plugin'  => WS_FORM_NAME,
					'form_id' => $form_object->id,
				),
			);

			// Get first name
			$first_name = $submit_object->find_field_value( 'first_name' );
			if ( !empty( $first_name ) ) {

				$args['name'] = $first_name;
			}

			// Get last name
			$last_name = $submit_object->find_field_value( 'last_name' );
			if ( !empty( $last_name ) ) {

				$args['surname'] = $last_name;
			}

			// Add contact to Hostinger Reach
			do_action(

				'hostinger_reach_submit',
				$args
			);
		}
	}

	/**
	* Method to return the forms of the integration
	*
	* @return array An array of forms based on Hostinger\Reach\Models\Form
	*
	* 'form_id'     => The Form Unique ID
	* 'form_title'  => The Form Name
	* 'post_id'     => (Optional) associated Post ID
	* 'type'        => $this->get_name(), (Your integration name)
	* 'is_active'   => True or false indicating the state of the form
	* 'submissions' =>Number indicating the submission counter
	*/
	public function get_forms(): array {

		// Create instance of WS_Form_Form
		$ws_form_form = new WS_Form_Form();

		// Get all forms
		$forms = $ws_form_form->get_all(

			false,                   // Published (Allow unpublished for testing)
			'label',                 // Order by
			'ASC',                   // Ascending
			'id,label,count_submit'  // Returns the ID, label and number of submissions
		);

		// Process forms, returning each as an instance of Hostinger\Reach\Models\Form
		$forms = array_map(
			function ( $form ) {
				$form = new Form(
					array(
						'form_id'     => $form['id'],
						'form_title'  => $form['label'],
						'post_id'     => null,
						'type'        => $this->get_name(),
						'is_active'   => true,
						'submissions' => (int) $form['count_submit'],
					)
				);

				return $form->to_array();
			},
			$forms
		);

		return $forms;
	}
}

// Create instance of WS_Form_Hostinger_Reach
$ws_form_hostinger_reach = new WS_Form_Hostinger_Reach();

// Initialize
$ws_form_hostinger_reach->init();
