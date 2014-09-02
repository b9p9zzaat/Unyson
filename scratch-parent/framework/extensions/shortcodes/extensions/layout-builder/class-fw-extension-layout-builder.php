<?php if (!defined('FW')) die('Forbidden');

class FW_Extension_Layout_Builder extends FW_Extension
{
	private $builder_option_key = 'layout-builder';

	/**
	 * @internal
	 */
	protected function _init()
	{
		if (is_admin()) {
			$this->add_admin_filters();
			$this->add_admin_actions();
		} else {
			$this->add_theme_filters();
		}
	}

	private function add_admin_filters()
	{
		add_filter('fw_post_options',      array($this, '_admin_filter_fw_post_options'), 10, 2);
	}

	private function add_admin_actions()
	{
		add_action('fw_save_post_options', array($this, '_admin_action_fw_save_post_options'), 10, 2);
	}

	private function add_theme_filters()
	{
		add_action('the_content', array($this, '_theme_filter_remove_autop'), 1);
	}

	/*
	 * Adds the layout builder metabox if the $post_type is supported
	 * @internal
	 */
	public function _admin_filter_fw_post_options($post_options, $post_type)
	{
		if (in_array($post_type, $this->get_config('supported_post_types'))) {
			$layout_builder_options = array(
				'layout-builder-box' => array(
					'title'    => false,
					'type'     => 'box',
					'priority' => 'high',
					'options'  => array(
						$this->builder_option_key => array(
							'label'              => false,
							'desc'               => false,
							'type'               => 'layout-builder',
							'editor_integration' => true
						)
					)
				)
			);
			$post_options = array_merge($layout_builder_options, $post_options);
		}

		return $post_options;
	}

	/**
	 * @internal
	 */
	public function _admin_action_fw_save_post_options($post_id , $post)
	{
		if (in_array($post->post_type, $this->get_config('supported_post_types'))) {
			$builder_shortcodes = fw_get_db_post_option($post_id, $this->builder_option_key);
			if (
				!$builder_shortcodes['builder_active'] ||
				!$builder_shortcodes['shortcode_notation']
			) {
				return;
			}

			// remove then add again to avoid infinite loop
			remove_action('fw_save_post_options', array($this, '_admin_action_fw_save_post_options'));
			wp_update_post(array(
				'ID'            => $post_id,
				'post_content'  => $builder_shortcodes['shortcode_notation']
			));
			add_action('fw_save_post_options', array($this, '_admin_action_fw_save_post_options'), 10, 2);
		}
	}

	/**
	 * Removes the autop filter if the post was created via the layout builder
	 *
	 * @internal
	 */
	public function _theme_filter_remove_autop($content)
	{
		global $post;

		if (in_array($post->post_type, $this->get_config('supported_post_types'))) {
			$builder_meta = fw_get_db_post_option($post->ID, $this->builder_option_key);
			if ($builder_meta['builder_active']) {
				remove_filter('the_content', 'wpautop');
			}
		}

		return $content;
	}
}
