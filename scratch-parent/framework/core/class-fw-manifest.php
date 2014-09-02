<?php if (!defined('FW')) die('Forbidden');

abstract class FW_Manifest
{
	/**
	 * @var array
	 */
	protected $manifest;

	/**
	 * The first requirement that was not met
	 * (that marks that the requirements are not met)
	 *
	 * @var array
	 * array(
	 *  'requirement'  => 'wordpress|framework',
	 *  'requirements' => array('min_version' => '1.2.3', ...)
	 * )
	 * or
	 * array(
	 *  'requirement'  => 'extension',
	 *  'extension'    => 'extension_name',
	 *  'requirements' => array('min_version' => '1.2.3', ...)
	 * )
	 */
	private $not_met_requirement;

	/**
	 * When an requirement that sure will not change is not met and have no sense to execute check_requirements() again
	 * @var bool
	 */
	private $not_met_is_final = false;

	/**
	 * Not met requirement and skipped (not verified) requirements after $this->not_met_requirement was found
	 * @var array
	 */
	private $requirements_for_verification;

	private $requirements_verification_never_called = true;

	/**
	 * @param array $manifest
	 */
	protected function __construct(array $manifest)
	{
		$manifest = array_merge(array(
			'name'        => null, // title
			'uri'         => null,
			'description' => null,
			'version'     => '0.0.0',
			'author'      => null,
			'author_uri'  => null,

			// Custom fields
			'requirements' => array(),
		), $manifest);

		/**
		 * Merge $manifest['requirements']
		 */
		{
			$requirements = $manifest['requirements'];

			$manifest['requirements'] = array();

			foreach ($this->get_default_requirements() as $default_requirement => $default_requirements) {
				$manifest['requirements'][ $default_requirement ] = isset($requirements[$default_requirement])
					? array_merge(
						$default_requirements,
						$requirements[$default_requirement]
					)
					: $default_requirements;
			}

			unset($requirements);
		}

		$this->requirements_for_verification = $manifest['requirements'];

		$this->manifest = $manifest;
	}

	/**
	 * @return array { 'requirement' => array('min_version' => '..', 'max_version' => '..') }
	 */
	abstract protected function get_default_requirements();

	/**
	 * @return bool
	 */
	public function requirements_met()
	{
		if ($this->not_met_is_final) {
			return false;
		}

		if ($this->requirements_verification_never_called) {
			$this->requirements_verification_never_called = false;

			$this->check_requirements();
		}

		return empty($this->requirements_for_verification) && empty($this->not_met_requirement);
	}

	/**
	 * @return bool
	 */
	public function check_requirements()
	{
		if ($this->not_met_is_final) {
			return false;
		}

		if ($this->requirements_met()) {
			return true;
		}

		$this->not_met_requirement = array();

		global $wp_version;

		foreach ($this->requirements_for_verification as $requirement => $requirements) {
			switch ($requirement) {
				case 'wordpress':
					if (
						isset($requirements['min_version'])
						&&
						version_compare($wp_version, $requirements['min_version'], '<')
					) {
						$this->not_met_requirement = array(
							'requirement'  => $requirement,
							'requirements' => $requirements
						);
						$this->not_met_is_final = true;
						break 2;
					}

					if (
						isset($requirements['max_version'])
						&&
						version_compare($wp_version, $requirements['max_version'], '>')
					) {
						$this->not_met_requirement = array(
							'requirement'  => $requirement,
							'requirements' => $requirements
						);
						$this->not_met_is_final = true;
						break 2;
					}

					// met
					unset($this->requirements_for_verification[$requirement]);
					break;
				case 'framework':
					if (
						isset($requirements['min_version'])
						&&
						version_compare(fw()->manifest->get_version(), $requirements['min_version'], '<')
					) {
						$this->not_met_requirement = array(
							'requirement'  => $requirement,
							'requirements' => $requirements
						);
						$this->not_met_is_final = true;
						break 2;
					}

					if (
						isset($requirements['max_version'])
						&&
						version_compare(fw()->manifest->get_version(), $requirements['max_version'], '>')
					) {
						$this->not_met_requirement = array(
							'requirement'  => $requirement,
							'requirements' => $requirements
						);
						$this->not_met_is_final = true;
						break 2;
					}

					// met
					unset($this->requirements_for_verification[$requirement]);
					break;
				case 'extensions':
					$extensions =& $requirements;

					foreach ($extensions as $extension => $extension_requirements) {
						$extension_instance = fw()->extensions->get($extension);

						if (!$extension_instance) {
							/**
							 * extension in requirements does not exists
							 * maybe try call this method later and maybe will exist, or it really does not exists
							 */
							$this->not_met_requirement = array(
								'requirement'  => $requirement,
								'extension'    => $extension,
								'requirements' => $extension_requirements
							);
							break 3;
						}

						if (
							isset($extension_requirements['min_version'])
							&&
							version_compare($extension_instance->manifest->get_version(), $extension_requirements['min_version'], '<')
						) {
							$this->not_met_requirement = array(
								'requirement'  => $requirement,
								'extension'    => $extension,
								'requirements' => $extension_requirements
							);
							$this->not_met_is_final = true;
							break 3;
						}

						if (
							isset($extension_requirements['max_version'])
							&&
							version_compare($extension_instance->manifest->get_version(), $extension_requirements['max_version'], '>')
						) {
							$this->not_met_requirement = array(
								'requirement'  => $requirement,
								'extension'    => $extension,
								'requirements' => $extension_requirements
							);
							$this->not_met_is_final = true;
							break 3;
						}

						// met
						unset($this->requirements_for_verification[$requirement][$extension]);

					}

					if (empty($this->requirements_for_verification[$requirement])) {
						// all extensions requirements met
						unset($this->requirements_for_verification[$requirement]);
					}
					break;
			}
		}

		return $this->requirements_met();
	}

	public function get_version()
	{
		return $this->manifest['version'];
	}

	public function get_name()
	{
		return $this->manifest['name'];
	}

	/**
	 * @param string $multi_key
	 * @return mixed|null
	 */
	public function get($multi_key)
	{
		return fw_akg($multi_key, $this->manifest);
	}
}

class FW_Framework_Manifest extends FW_Manifest
{
	public function __construct(array $manifest)
	{
		parent::__construct($manifest);

		if (empty($manifest['name'])) {
			$manifest['name'] = __('Framework', 'fw');
		}

		unset($manifest);
	}

	protected function get_default_requirements()
	{
		return array(
			'wordpress' => array(
				'min_version' => '3.9',
				/*'max_version' => '10000.0.0',*/
			),
		);
	}
}

class FW_Theme_Manifest extends FW_Manifest
{
	public function __construct(array $manifest)
	{
		$manifest_defaults = array(
			'id' => 'default',
		);

		$theme = wp_get_theme();

		foreach(array(
			'name'        => 'Name',
			'uri'         => 'ThemeURI',
			'description' => 'Description',
			'version'     => 'Version',
			'author'      => 'Author',
			'author_uri'  => 'AuthorURI',
		) as $manifest_key => $stylesheet_header) {
			$header_value = trim($theme->get($stylesheet_header));

			if (FW_CT) {
				switch ($manifest_key) {
					case 'version':
					case 'uri':
					case 'author':
					case 'author_uri':
					case 'license':
						// force parent theme value
						$header_value = $theme->parent()->get($stylesheet_header);
						break;
					default:
						if (!$header_value) {
							// use parent theme value only if child theme value is empty
							$header_value = $theme->parent()->get($stylesheet_header);
						}
				}
			}

			if ($header_value) {
				$manifest_defaults[$manifest_key] = $header_value;
			}
		}

		parent::__construct(array_merge($manifest_defaults, $manifest));
	}

	protected function get_default_requirements()
	{
		return array(
			'wordpress' => array(
				'min_version' => '3.9',
				/*'max_version' => '10000.0.0',*/
			),
			'framework' => array(
				/*'min_version' => '0.0.0',
				'max_version' => '1000.0.0'*/
			),
			'extensions' => array(
				/*'extension_name' => array(
					'min_version' => '0.0.0',
					'max_version' => '1000.0.0'
				)*/
			)
		);
	}

	public function get_id()
	{
		return $this->manifest['id'];
	}
}

class FW_Extension_Manifest extends FW_Manifest
{
	public function __construct(array $manifest)
	{
		parent::__construct($manifest);

		unset($manifest);

		// unset unnecessary keys
		unset($this->manifest['id']);
	}

	protected function get_default_requirements()
	{
		return array(
			'wordpress' => array(
				'min_version' => '3.9',
				/*'max_version' => '10000.0.0',*/
			),
			'framework' => array(
				/*'min_version' => '0.0.0',
				'max_version' => '1000.0.0'*/
			),
			'extensions' => array(
				/*'extension_name' => array(
					'min_version' => '0.0.0',
					'max_version' => '1000.0.0'
				)*/
			)
		);
	}

	public function get_required_extensions()
	{
		return $this->manifest['requirements']['extensions'];
	}
}