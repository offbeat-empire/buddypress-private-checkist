<?php get_header( 'buddypress' ); ?>

	<?php do_action( 'ucc_bpc_before_directory_checklist_page' ); ?>

	<div id="content">
		<div class="padder">

		<?php if ( is_user_logged_in() ) : ?>

			<?php do_action( 'ucc_bpc_before_directory_checklist' ); ?>

			<?php do_action( 'template_notices' ); ?>

			<?php do_action( 'ucc_bpc_before_checklist_action_bulk' ); ?>
	
			<?php do_action( 'ucc_bpc_before_action_bulk_form' ); ?>

			<div id="bulk-add-tasks">

				<?php do_action( 'ucc_bpc_before_bulk_form' ); ?>

				<?php include( 'edit.php' ); ?>

				<?php do_action( 'ucc_bpc_after_bulk_form' ); ?>

			</div><!-- #bulk-add-tasks -->

			<?php do_action( 'ucc_bpc_after_action_bulk_form' ); ?>

			<?php do_action( 'ucc_bpc_after_checklist_action_bulk' ); ?>

			<?php do_action( 'ucc_bpc_after_directory_checklist' ); ?>

		<?php else : ?>

		<?php _e( 'This is only available to logged-in users.', 'buddypress-private-checklist' ); ?>

		<?php endif; ?>

		</div><!-- .padder -->
	</div><!-- #content -->

	<?php do_action( 'ucc_bpc_after_directory_checklist_page' ); ?>

<?php get_sidebar( 'buddypress' ); ?>
<?php get_footer( 'buddypress' ); ?>

