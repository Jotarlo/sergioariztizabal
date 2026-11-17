<?php

defined( 'ABSPATH' ) || exit;

function saretos_evaluators_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permiso para administrar evaluadores.', '', array( 'response' => 403 ) );
	}
	$users = get_users( array( 'role' => 'saretos_evaluator', 'orderby' => 'display_name', 'order' => 'ASC' ) );
	?>
	<div class="wrap">
		<h1>Evaluadores de retos</h1>
		<p>La evaluación usa las cuentas seguras de WordPress. Comparte con cada evaluador el enlace privado: <a href="<?php echo esc_url( saretos_page_url( 'evaluation' ) ); ?>"><?php echo esc_html( saretos_page_url( 'evaluation' ) ); ?></a>. Los evaluadores inician sesión con su usuario y contraseña de WordPress.</p>
		<?php if ( '1' === saretos_query( 'creado' ) ) : ?><div class="notice notice-success"><p>La cuenta de evaluador quedó creada.</p></div><?php endif; ?>
		<h2>Cuentas autorizadas</h2>
		<?php if ( ! $users ) : ?><p>Aún no hay cuentas con el perfil Evaluador de retos.</p><?php else : ?>
		<table class="widefat striped"><thead><tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Acceso</th></tr></thead><tbody>
		<?php foreach ( $users as $user ) : ?><tr><td><?php echo esc_html( $user->display_name ); ?></td><td><?php echo esc_html( $user->user_login ); ?></td><td><?php echo esc_html( $user->user_email ); ?></td><td><?php if ( current_user_can( 'edit_user', $user->ID ) ) : ?><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) . '#password' ); ?>">Editar cuenta o cambiar contraseña</a><?php else : ?>Requiere permiso para editar usuarios<?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table><?php endif; ?>
		<?php if ( current_user_can( 'create_users' ) ) : ?>
		<h2>Crear cuenta de evaluador</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="saretos_evaluator_create">
			<?php wp_nonce_field( 'saretos_create_evaluator' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="saretos-evaluator-login">Nombre de usuario</label></th><td><input class="regular-text" type="text" id="saretos-evaluator-login" name="user_login" autocomplete="off" required maxlength="60"><p class="description">Debe ser único en el sitio.</p></td></tr>
				<tr><th><label for="saretos-evaluator-name">Nombre visible</label></th><td><input class="regular-text" type="text" id="saretos-evaluator-name" name="display_name" required maxlength="100"></td></tr>
				<tr><th><label for="saretos-evaluator-email">Correo</label></th><td><input class="regular-text" type="email" id="saretos-evaluator-email" name="user_email" required maxlength="100"></td></tr>
				<tr><th><label for="saretos-evaluator-password">Contraseña inicial</label></th><td><input class="regular-text" type="password" id="saretos-evaluator-password" name="user_pass" autocomplete="new-password" required minlength="16"><p class="description">Usa una contraseña única de al menos 16 caracteres y compártela en privado. WordPress permite cambiarla después desde el perfil.</p></td></tr>
			</table>
			<?php submit_button( 'Crear evaluador' ); ?>
		</form><?php endif; ?>
	</div>
	<?php
}

function saretos_create_evaluator(): void {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'create_users' ) ) {
		wp_die( 'No tienes permiso para crear evaluadores.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'saretos_create_evaluator' );
	$login = sanitize_user( saretos_post( 'user_login' ), true );
	$name = sanitize_text_field( saretos_post( 'display_name' ) );
	$email = sanitize_email( saretos_post( 'user_email' ) );
	$password = saretos_post( 'user_pass' );
	if ( ! $login || ! validate_username( $login ) || username_exists( $login ) ) {
		saretos_admin_error( 'El usuario no es válido o ya existe.' );
	}
	if ( ! $name || mb_strlen( $name ) > 100 || ! is_email( $email ) || email_exists( $email ) ) {
		saretos_admin_error( 'Revisa el nombre visible y el correo. El correo debe ser válido y único.' );
	}
	if ( strlen( $password ) < 16 || strlen( $password ) > 4096 ) {
		saretos_admin_error( 'La contraseña inicial debe tener al menos 16 caracteres.' );
	}
	$result = wp_insert_user( array(
		'user_login' => $login, 'user_pass' => $password, 'user_email' => $email,
		'display_name' => $name, 'role' => 'saretos_evaluator',
	) );
	if ( is_wp_error( $result ) ) {
		saretos_admin_error( $result->get_error_message() );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'saretos-evaluators', 'creado' => 1 ), admin_url( 'admin.php' ) ), 303 );
	exit;
}
