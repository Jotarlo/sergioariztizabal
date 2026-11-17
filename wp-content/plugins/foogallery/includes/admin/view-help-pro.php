<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$foogallery_plans = array(
	FOOGALLERY_PRO_PLAN_STARTER  => __( 'PRO Starter', 'foogallery' ),
	FOOGALLERY_PRO_PLAN_EXPERT   => __( 'PRO Expert', 'foogallery' ),
	FOOGALLERY_PRO_PLAN_COMMERCE => __( 'PRO Commerce', 'foogallery' ),
);

$foogallery_pro_features = foogallery_pro_features();

?>

<div id="pro_section" class="foogallery-admin-help-section" style="display: none">
	<section class="fgah-feature">
		<header>
			<h3><?php esc_html_e( 'FooGallery PRO Plans', 'foogallery' );?></h3>
			<p><?php esc_html_e( 'Choose from a PRO Plan that suits your requirements and budget : ', 'foogallery' );?>
				<span class="fgah-plan-prostarter"><?php esc_html_e( 'PRO Starter', 'foogallery' );?></span>,
				<span class="fgah-plan-pro"><?php esc_html_e( 'PRO Expert', 'foogallery' );?></span> <?php esc_html_e( 'or', 'foogallery' );?>
				<span class="fgah-plan-commerce"><?php esc_html_e( 'PRO Commerce', 'foogallery' );?></span>
			</p>
		</header>
		<footer>
			<a class="foogallery-admin-help-button-cta" href="<?php echo esc_url ( $plans_url ); ?>"><?php esc_html_e( 'Compare FooGallery PRO Plans', 'foogallery' ); ?></a>
		</footer>
	</section>
    <section class="fgah-feature">
        <?php if ( $show_trial_message ) { ?>
            <header>
                <h3><?php esc_html_e( 'FooGallery PRO Free Trial 🤩', 'foogallery' );?></h3>
                <p><?php echo esc_html( $fs_instance->is_registered() ? __( 'You can start a 7-day trial. Install and activate the PRO plugin to use its features.', 'foogallery' ) : __( 'Connect this site to check trial availability. If eligible, choose a trial plan and install PRO to use its features.', 'foogallery' ) ); ?></p>
            </header>
            <footer>
                <a class="foogallery-admin-help-button-cta" href="<?php echo esc_url( FooGallery_Admin_Setup::start_trial_url() ); ?>"><?php echo esc_html( $fs_instance->is_registered() ? __( 'Start Your 7-day Free Trial', 'foogallery' ) : __( 'Connect to Check Trial Availability', 'foogallery' ) ); ?></a>
            </footer>
        <?php } else if ( $show_thanks_for_pro ) { 
            $current_plan_name = isset( $foogallery_plans[ $foogallery_current_plan ] ) ? $foogallery_plans[ $foogallery_current_plan ] : '';
            ?>
            <header>
                <?php /* translators: %s: Value inserted at runtime. */ ?>
                <h3><?php echo sprintf( esc_html__( 'Thanks for your support by purchasing a %s license 😍', 'foogallery' ), '<span class="fgah-plan-' . esc_attr( $foogallery_current_plan ) . '">' . esc_html( $current_plan_name ) . '</span>' );?></h3>
                <p><?php echo esc_html( 'ready' === $foogallery_setup_state['phase'] ? __( 'Explore the PRO features available with your active plan.', 'foogallery' ) : __( 'Your PRO access needs setup before these features can run.', 'foogallery' ) ); ?></p>
            </header>
        <?php } else if ( $is_trial ) { ?>
            <header>
                <h3><?php esc_html_e( 'Thanks for trying out PRO 😍', 'foogallery' );?></h3>
                <p><?php echo esc_html( 'ready' === $foogallery_setup_state['phase'] ? __( 'Explore the PRO features available during your trial.', 'foogallery' ) : __( 'Finish PRO setup before trial features can run on this site.', 'foogallery' ) ); ?></p>
            </header>
        <?php } ?>
		<?php if ( 'ready' !== $foogallery_setup_state['phase'] ) : ?>
			<p><a href="<?php echo esc_url( foogallery_admin_url_for_page( 'foogallery-license-trial' ) ); ?>"><?php esc_html_e( 'Check License & Trial setup', 'foogallery' ); ?></a></p>
		<?php endif; ?>
    </section>

	<?php
    $i = -1;
    foreach ( $foogallery_pro_features as $feature ) {
        if ( isset( $feature['hide_from_help'] ) && $feature['hide_from_help'] === true ) {
            continue;
        }
        $i++;
        ?>
    <section class="fgah-feature fgah-feature-pro<?php echo ( $i % 2 === 0 ) ? " fgah-feature-right" : ""; ?> fgah-feature-plan-<?php echo esc_attr( $feature['plan'] ); ?>">
        <div>
            <figure>
                <a href="<?php echo esc_url( foogallery_admin_url( $feature['link'], 'help', $feature['utm_content'] ) ); ?>" target="_blank">
                    <img src="<?php echo esc_url( $feature['image'] ); ?>" alt="<?php echo esc_html( $feature['title'] ); ?>" />
                </a>
            </figure>
            <dl>
                <dt><?php echo esc_html( $feature['title']); ?></dt>
                <dd>
	                <div class="fgah-feature-sub-title">
		                <h4><?php esc_html_e( 'Available in:', 'foogallery' ); ?></h4>
		                <?php
		                $available_plans = array();
		                $feature_plans = isset( $feature['plans'] ) && is_array( $feature['plans'] ) ? $feature['plans'] : array();
		                foreach ( $feature_plans as $plan ) {
			                if ( ! isset( $foogallery_plans[ $plan ] ) ) {
				                continue;
			                }

			                $available_plans[] = '<span class="fgah-plan-' . esc_attr( $plan ) . '">' . esc_html( $foogallery_plans[ $plan ] ) . '</span>';
		                }
		                echo wp_kses_post( implode( ' ', $available_plans ) );
	                    ?>
	                </div>
	                <p>
		                <?php echo esc_html( $feature['desc'] ); ?>
	                </p>
	                <p>
                        <a href="<?php echo esc_url( foogallery_admin_url( $feature['link'], 'help', $feature['utm_content'] ) ); ?>"
                           target="_blank"><?php echo esc_html( $feature['link_text']); ?></a>
	                </p>
                </dd>
            </dl>
        </div>
    </section>
    <?php } ?>
</div>
