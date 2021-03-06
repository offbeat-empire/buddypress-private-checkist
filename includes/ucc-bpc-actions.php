<?php


if ( ! defined( 'ABSPATH' ) ) exit;


if ( ! function_exists( 'ucc_bpc_action_edit' ) ) {
function ucc_bpc_action_edit( $post_id = 0 ) {
	global $bp, $current_user;

	// Do not proceed if user is not logged in, not viewing checklist, or action is not edit.
	if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'edit' ) )
	       	return false;

	if ( empty( $_POST ) )
		return false;

	if ( empty( $post_id ) && bp_action_variable( 0 ) )
		$post_id = (int) bp_action_variable( 0 );

	if ( empty( $post_id ) && isset( $_POST[ucc_bpc_get_id_field_name()] ) && ( $_POST[ucc_bpc_get_id_field_name()] > 0 ) )
		$post_id = (int) $_POST[ucc_bpc_get_id_field_name()];

	if ( empty( $post_id ) ) {
		$post_id = null;
	} else {
		$post = get_post( $post_id );
		if ( empty( $post->post_author ) || ! ucc_bpc_user_can_edit( $post ) )
			return false;	
	}

	// Check the nonce.
	check_admin_referer( '_ucc_bpc_action_edit' );

	// Deal with checkit first.
	if ( ! empty( $post ) && isset( $_POST[ucc_bpc_get_is_checkit_field_name()] ) && ! empty( $_POST[ucc_bpc_get_is_checkit_field_name()] ) ) {
		$incomplete = apply_filters( 'ucc_bpc_checkit_incomplete', intval( term_exists( 'not-started' ) ) );
		$completed = apply_filters( 'ucc_bpc_checkit_complete', intval( term_exists( 'completed' ) ) );

		$current_status = wp_get_object_terms( $post->ID, 'ucc_bpc_status', array( 'fields' => 'ids' ) );
		if ( is_array( $current_status ) )
			$current_status = intval( $current_status[0] );
		$prev_status = intval( get_post_meta( $post->ID, '_ucc_bpc_task_status_prev', true ) );

		if ( (int) $_POST[ucc_bpc_get_checkit_field_name()] == 0 ) {
			// Switching from completed, try to revert to last known status.
			if ( ! empty( $prev_status ) && term_exists( intval( $prev_status ) ) && ( $completed != $prev_status ) ) {
				wp_set_object_terms( $post->ID, array( intval( $prev_status ) ), 'ucc_bpc_status' );
			} else {
				wp_set_object_terms( $post->ID, array( intval( $incomplete ) ), 'ucc_bpc_status' );
			}
			update_post_meta( $post->ID, '_ucc_bpc_task_status_prev', $completed );
		} else {
			// Switching from some other status to completed.
			if ( ! empty( $current_status ) && ( $completed != $current_status ) )
				update_post_meta( $post->ID, '_ucc_bpc_task_status_prev', $current_status );
			wp_set_object_terms( $post->ID, array( intval( $completed ) ), 'ucc_bpc_status' );

			// This is totally a pony.
			$msg1 = __( 'Task completed (1).', 'buddypress-private-checklist' );
			$msg2 = __( 'Task completed (2).', 'buddypress-private-checklist' );
			$msg3 = __( 'Task completed (3).', 'buddypress-private-checklist' );
			$msg4 = __( 'Task completed (4).', 'buddypress-private-checklist' );
			$msg5 = __( 'Task completed (5).', 'buddypress-private-checklist' );
			$task_completed = array( $msg1, $msg2, $msg3, $msg4, $msg5 );
			bp_core_add_message( $task_completed[array_rand( $task_completed )] );
		}
		clean_object_term_cache( $post->ID, 'ucc_bpc_task' );

		bp_core_redirect( ucc_bpc_get_referer_url( ucc_bpc_get_referer_vars() ) );
	}

	// Get activity info.
	if ( isset( $_POST[ucc_bpc_get_title_field_name()] ) )
		$title = apply_filters( 'ucc_bpc_title', $_POST[ucc_bpc_get_title_field_name()] );
	if ( isset( $_POST[ucc_bpc_get_content_field_name()] ) )
		$content = apply_filters( 'ucc_bpc_content', $_POST[ucc_bpc_get_content_field_name()] );
	if ( isset( $_POST[ucc_bpc_get_category_field_name()] ) )
		$categories = apply_filters( 'ucc_bpc_category', $_POST[ucc_bpc_get_category_field_name()] );
	if ( isset( $_POST[ucc_bpc_get_status_field_name()] ) )
		$status = apply_filters( 'ucc_bpc_status', $_POST[ucc_bpc_get_status_field_name()] );

	// No activity content so provide feedback and redirect.
	if ( empty( $title ) ) {
		bp_core_add_message( __( 'Please enter a task before saving.', 'buddypress-private-checklist' ), 'error' );
		bp_core_redirect( ucc_bpc_get_referer_url( ucc_bpc_get_referer_vars() ) );
	}

	$author = $current_user->ID;
	
	$post = array(
		'ID' => $post_id,
		'post_title' => $title,
		'post_content' => $content,
		'post_author' => $author,
		'post_type' => 'ucc_bpc_task',
		'post_status' => 'publish'
	);
	$task_id = wp_insert_post( $post );

	if ( term_exists( intval( $categories ) ) )
		wp_set_object_terms( $task_id, array( intval( $categories ) ), 'ucc_bpc_category' );
	else
		wp_set_object_terms( $task_id, null, 'ucc_bpc_category' );

	if ( term_exists( intval( $status ) ) )
		wp_set_object_terms( $task_id, array( intval( $status ) ), 'ucc_bpc_status' );
	else
		wp_set_object_terms( $task_id, null, 'ucc_bpc_status' );

	// Provide user feedback.
	if ( ! empty( $task_id ) ) {
		$url = user_trailingslashit( trailingslashit( ucc_bpc_get_url() ) . 'edit/' . $task_id );
		if ( $post_id > 0 )
			bp_core_add_message( __( 'Your task has been updated.', 'buddypress-private-checklist' ) );
		else
			bp_core_add_message( __( 'Your task has been added.', 'buddypress-private-checklist' ) ); 
	} else {
		bp_core_add_message( __( 'There was an error when posting your task, please try again.', 'buddypress-private-checklist' ), 'error' );
	}

	// Redirect.
	bp_core_redirect( ucc_bpc_get_referer_url( ucc_bpc_get_referer_vars() ) );
} }
add_action( 'bp_actions', 'ucc_bpc_action_edit' );


if ( ! function_exists( 'ucc_bpc_action_delete' ) ) {
function ucc_bpc_action_delete( $post_id = 0 ) {
	global $bp, $current_user;

	// Do not proceed if user is not logged in, not viewing checklist, or action is not delete.
	if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'delete' ) )
		return false;

	if ( empty( $post_id ) && bp_action_variable( 0 ) )
		$post_id = (int) bp_action_variable( 0 );

	if ( empty( $post_id ) )
		return false;

	// Check the nonce.
	check_admin_referer( '_ucc_bpc_action_delete' );

	$post = get_post( $post_id );
	if ( empty( $post->post_author ) || ! ucc_bpc_user_can_delete( $post ) ) 
		return false;
		
	do_action( 'ucc_bpc_before_action_delete_task', $post_id, $current_user->ID );

	if ( wp_delete_post( $post_id, true ) )
		bp_core_add_message( __( 'Task deleted successfully.', 'buddypress-private-checklist' ) );
	else
		bp_core_add_message( __( 'There was an error when deleting that task.', 'buddypress-private-checklist' ) );

	do_action( 'ucc_bpc_after_action_delete_task', $post_id, $current_user->ID );

	bp_core_redirect( ucc_bpc_get_referer_url( ucc_bpc_get_referer_vars() ) );
} }
add_action( 'bp_actions', 'ucc_bpc_action_delete' );


if ( ! function_exists( 'ucc_bpc_action_bulk' ) ) {
function ucc_bpc_action_bulk() {
	global $bp, $current_user;

	// Do not proceed if user is not logged in, not viewing checklist, or action is not bulk.
	if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'bulk' ) )
		return;

	// Don't allow multiple bulk adds.
	$user_has_added = get_user_meta( $current_user->ID, '_ucc_bpc_action_bulk', true );
	if ( (int) $user_has_added > 1 ) {
		bp_core_add_message( __( 'You have already bulk added tasks.', 'buddypress-private-checklist' ) );
		bp_core_redirect( user_trailingslashit( ucc_bpc_get_url() ) );
	}

	if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) || ! isset( $_REQUEST['ucc_bpc_bulk_date'] ) )
		return;

	// Check the nonce.
	check_admin_referer( '_ucc_bpc_action_bulk' );

	// Set a timeout for a bulk add.
	$user_has_timeout = get_user_meta( $current_user->ID, '_ucc_bpc_action_bulk_timeout', true );
	$timeout = apply_filters( 'ucc_bpc_action_bulk_timeout', 60 * 5 );
	$now = time();
	if ( $user_has_timeout && (int) $user_has_timeout + $timeout < $now ) {
		bp_core_add_message( __( 'Your import has timed out. An import reset is required.', 'buddypress-private-checklist' ) );
		bp_core_redirect( user_trailingslashit( ucc_bpc_get_url() ) );
	}

	// Is there a CSV available?
	$options = get_option( 'ucc_bpc_options' );
	if ( ! empty( $options ) && isset( $options['bulk_add_tasks'] ) )
		$csv = $options['bulk_add_tasks'];
	if ( empty( $csv ) ) {
		bp_core_add_message( __( 'You cannot use the bulk add feature because no default tasks have been set.', 'buddypress-private-checklist' ) );
		bp_core_redirect( user_trailingslashit( ucc_bpc_get_url() ) );
	}

	// Make sure the date is valid.
	$bulk_date = strtotime( $_REQUEST['ucc_bpc_bulk_date'] );

	if ( ! $bulk_date ) {
		bp_core_add_message( __( 'You need to set a valid date: mm/dd/YYYY.', 'buddypress-private-checklist' ) );
		bp_core_redirect( user_trailingslashit( trailingslashit( ucc_bpc_get_url() ) . 'bulk' ) );	
	}

	$now = time();
	if ( ( $bulk_date < strtotime( '+2 days' ) ) ) {
		bp_core_add_message( __( 'You need to set a date that is in the future.', 'buddypress-private-checklist' ) );
		unset( $_REQUEST['ucc_bpc_bulk_date'] );
		bp_core_redirect( user_trailingslashit( trailingslashit( ucc_bpc_get_url() ) . 'bulk' ) ); 
	}

	$lines = str_getcsv( $csv, "\n" );

	// Import N posts and loop to prevent memory/time issues.
	$length = apply_filters( 'ucc_bpc_bulk_add_tasks_length', 20 );
	$offset = (int) $_REQUEST['ucc_bpc_bulk_offset'];
	$tasks = array_slice( $lines, $offset * $length, $length ); 
	$count = count( $tasks );

	if ( ! empty( $tasks ) ) {
		// Set (or reset) the timeout to now. 
		update_user_meta( $current_user->ID, '_ucc_bpc_action_bulk_timeout', time() );

		$first = $offset * $length + 1;
		$last = $offset * $length + $count;
		if ( isset( $_REQUEST[ucc_bpc_get_bulk_autosubmit_field_name()] ) && (int) $_REQUEST[ucc_bpc_get_bulk_autosubmit_field_name()] > 0 )
			bp_core_add_message( sprintf( _n( '%1$d task added: #%2$d. Checking for more, please wait while the page refreshes.', '%1$d tasks added: #%2$d - #%3$d. Checking for more, please wait while the page refreshes.', $count, 'buddypress-private-checklist' ), $count, $first, $last ) );
		else 
			bp_core_add_message( sprintf( _n( '%1$d task added: #%2$d. If this page does not automatically refresh, click on the ', '%1$d tasks added: #%2$d - #%3$d. If this page does not automatically refresh, click on the ', $count, 'buddypress-private-checklist' ), $count, $first, $last ) . __( 'Bulk Add Tasks', 'buddypress-private-checklist' ) . __( ' button to continue adding tasks.', 'buddypress-private-checklist' ) );

		$author = $current_user->ID;

		//don't count terms after each insert
 		wp_defer_term_counting(true);

		foreach ( $tasks as $task ) {
			list( $time, $category, $status, $content ) = str_getcsv( $task, ',' );
			$status = term_exists( $status, 'ucc_bpc_status' );
			$status = $status['term_id'];
			$category = term_exists( $category, 'ucc_bpc_category' );
			$category = $category['term_id'];
			
			$post = array(
				'post_title' => $content,
				'post_author' => $author,
				'post_content' => '',
				'post_type' => 'ucc_bpc_task',
				'post_status' => 'publish'
			);
			$task_id = wp_insert_post( $post );

			if ( $category )
				wp_set_object_terms( $task_id, array( intval( $category ) ), 'ucc_bpc_category' );
			else
				wp_set_object_terms( $task_id, null, 'ucc_bpc_category' );

			if ( $status )
				wp_set_object_terms( $task_id, array( intval( $status ) ), 'ucc_bpc_status' );
			else
				wp_set_object_terms( $task_id, null, 'ucc_bpc_status' );

			$due_date = strtotime( $time, $bulk_date );
			update_post_meta( $task_id, '_ucc_bpc_task_date', $due_date );
		}
	}

	if ( empty( $tasks ) || ( count( $tasks ) < $length ) ) {
		update_user_meta( $current_user->ID, '_ucc_bpc_action_bulk', time() );
		delete_user_meta( $current_user->ID, '_ucc_bpc_action_bulk_timeout' );
		bp_core_add_message( sprintf( __( '%1$d tasks have been added to your checklist.', 'buddypress-private-checklist' ), count( $lines ) ) );
		bp_core_redirect( user_trailingslashit( ucc_bpc_get_url() ) );
	}
} }
add_action( 'bp_actions', 'ucc_bpc_action_bulk' );


if ( ! function_exists( 'ucc_bpc_action_export' ) ) {
function ucc_bpc_action_export() {
	global $current_user;

	// Do not proceed if user is not logged in, not viewing checklist, or action is not export.
	if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'export' ) )
		return;

	if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) ) 
		return;

	// Check the nonce.
	check_admin_referer( '_ucc_bpc_action_export' );
} }
add_action( 'bp_actions', 'ucc_bpc_action_export' );


if ( ! function_exists( 'ucc_bpc_action_print' ) ) {
function ucc_bpc_action_print() {
	global $current_user;

        // Do not proceed if user is not logged in, not viewing checklist, or action is not print.
        if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'print' ) )
                return;

        if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) )
                return;

        // Check the nonce.
        check_admin_referer( '_ucc_bpc_action_print' );
} }
add_action( 'bp_actions', 'ucc_bpc_action_print' );


if ( ! function_exists( 'ucc_bpc_action_email' ) ) {
function ucc_bpc_action_email() {
	global $current_user;

	// Do not proceed if user is not logged in, not viewing checklist, or action is not email.
	if ( ! is_user_logged_in() || ! ucc_bpc_is_component() || ! bp_is_current_action( 'email' ) )
		return;

	if ( empty( $_REQUEST ) || ! isset( $_REQUEST['_wpnonce'] ) || ! isset( $_REQUEST[ucc_bpc_get_email_address_field_name()] ) )
		return;

	if ( empty( $_REQUEST[ucc_bpc_get_email_address_field_name()] ) || ! is_email( $_REQUEST[ucc_bpc_get_email_address_field_name()] ) ) {
		bp_core_add_message( __( 'A valid email address is required.', 'buddypress-private-checklist' ) );
		bp_core_redirect( user_trailingslashit( trailingslashit( ucc_bpc_get_url() ) . 'email' ) ); 
	}

	$to = sanitize_email( $_REQUEST[ucc_bpc_get_email_address_field_name()] );

	// Check the nonce.
	check_admin_referer( '_ucc_bpc_action_email' );
	
	$subject = sprintf( __( '[%1$s] My Tasks', 'buddypress-private-checklist' ), get_bloginfo( 'name' ) );

	ob_start();
	ucc_bp_locate_template( 'templates/checklist/print/index.php', true, true, __FILE__ );
	$message = ob_get_contents();

	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";	

	$mailed = wp_mail( $to, $subject, $message, $headers );
	if ( $mailed )
		bp_core_add_message( sprintf( __( 'A copy of your tasks has been mailed to %1$s.', 'buddypress-private-checklist' ), $to ) );
	else
		bp_core_add_message( __( 'There was an error when emailing your task list, please try again.', 'buddypress-private-checklist' ) );
	bp_core_redirect( ucc_bpc_get_referer_url( ucc_bpc_get_referer_vars() ) );
} }
add_action( 'bp_actions', 'ucc_bpc_action_email' );
