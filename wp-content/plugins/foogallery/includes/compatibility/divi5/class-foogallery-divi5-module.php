<?php
/**
 * Native Divi module. API usage adapted from Elegant Themes GPL2 module examples.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Native module dependency; independent of ET_Builder_Module. */
class FooGallery_Divi5_Module implements \ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface {
	/** Register on init as required by Divi's module dependency lifecycle. */
	public function load() {
		add_action(
			'init',
			function () {
				\ET\Builder\Packages\ModuleLibrary\ModuleRegistration::register_module(
					__DIR__ . '/gallery/',
					array( 'render_callback' => array( self::class, 'render_callback' ) )
				);
			}
		);
	}

	/**
	 * Render the native wrapper, inherited decorations and selected gallery.
	 *
	 * @param array    $attrs Attributes.
	 * @param string   $content Inner content.
	 * @param WP_Block $block Parsed Divi block.
	 * @param object   $elements Divi ModuleElements.
	 * @param array    $default_printed_style_attrs Default styles.
	 * @return string Rendered module.
	 */
	public static function render_callback( $attrs, $content, $block, $elements, $default_printed_style_attrs = array() ) {
		$parent = \ET\Builder\FrontEnd\BlockParser\BlockParserStore::get_parent( $block->parsed_block['id'], $block->parsed_block['storeInstance'] );
		return \ET\Builder\Packages\Module\Module::render(
			array(
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'id'                  => $block->parsed_block['id'],
				'name'                => $block->block_type->name,
				'moduleCategory'      => $block->block_type->category,
				'attrs'               => $attrs,
				'elements'            => $elements,
				'parentAttrs'         => $parent->attrs ?? array(),
				'parentId'            => $parent->id ?? '',
				'parentName'          => $parent->blockName ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Official Divi parser property.
				'childrenIds'         => array(),
				'stylesComponent'     => array( self::class, 'module_styles' ),
				'scriptDataComponent' => array( self::class, 'module_script_data' ),
				'children'            => array(
					$elements->style_components( array( 'attrName' => 'module' ) ),
					FooGallery_Divi5::gallery_markup( $attrs['galleryId']['innerContent']['desktop']['value'] ?? '' ),
				),
			)
		);
	}

	/**
	 * Output inherited module styles and legacy custom CSS fields.
	 *
	 * @param array $args Divi style arguments.
	 */
	public static function module_styles( $args ) {
		\ET\Builder\FrontEnd\Module\Style::add(
			array(
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => array(
					$args['elements']->style(
						array(
							'attrName'   => 'module',
							'styleProps' => array(
								'defaultPrintedStyleAttrs' => $args['defaultPrintedStyleAttrs']['module']['decoration'] ?? array(),
								'disabledOn'               => array( 'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null ),
							),
						)
					),
					\ET\Builder\Packages\Module\Options\Css\CssStyle::style(
						array(
							'selector'  => $args['orderClass'],
							'attr'      => $args['attrs']['css'] ?? array(),
							'cssFields' => array(),
						)
					),
				),
			)
		);
	}

	/**
	 * Provide animation, scroll, sticky, link and other decoration runtime data.
	 *
	 * @param array $args Divi script arguments.
	 */
	public static function module_script_data( $args ) {
		\ET\Builder\Packages\Module\Options\Element\ElementScriptData::set(
			array(
				'id'            => $args['id'],
				'selector'      => $args['selector'],
				'attrs'         => array_merge( $args['attrs']['module']['decoration'] ?? array(), array( 'link' => $args['attrs']['module']['advanced']['link'] ?? array() ) ),
				'storeInstance' => $args['storeInstance'] ?? null,
			)
		);
	}
}
