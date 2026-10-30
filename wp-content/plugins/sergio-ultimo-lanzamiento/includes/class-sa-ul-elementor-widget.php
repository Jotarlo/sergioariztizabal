<?php

defined( 'ABSPATH' ) || exit;

class SA_UL_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'sa-ultimo-lanzamiento';
	}

	public function get_title() {
		return 'Sergio – Último lanzamiento';
	}

	public function get_icon() {
		return 'eicon-video-camera';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'sergio', 'lanzamiento', 'youtube', 'spotify', 'musica', 'música' );
	}

	protected function render() {
		echo sa_ul_render();
	}
}
