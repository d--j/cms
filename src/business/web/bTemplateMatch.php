<?php

/**
 *
 */
class bTemplateMatch
{
	private $_relativePath = null;
	private $_fileName = null;
	private $_fullTemplatePath = null;
	private $_matchType = null;
	private $_matchExtension = null;

	/**
	 * @param $path
	 */
	public function __construct($path)
	{
		$this->_fullTemplatePath = $path;
		$this->init($path);
	}

	/**
	 * @access private
	 * @param $path
	 */
	private function init($path)
	{
		$relativeTemplatePath = '';
		$segments = null;

		$path = Blocks::app()->path->normalizeDirectorySeparators($path);
		$pathSegments = array_merge(array_filter(explode('/', $path)));

		if ($pathSegments)
		{
			$file = $pathSegments[count($pathSegments) - 1];

			for ($counter = 0; $counter < count($pathSegments) - 1; $counter++)
			{
				$relativeTemplatePath .= $pathSegments[$counter];

				if ($counter != count($pathSegments) - 2)
					$relativeTemplatePath .= '/';
			}
		}
		else
		{
			$file = 'index';
		}

		$this->_fileName = $file;
		$this->_relativePath = $relativeTemplatePath;
	}

	/**
	 * @return null
	 */
	public function getFullTemplatePath()
	{
		return $this->_fullTemplatePath;
	}

	/**
	 * @return null
	 */
	public function getFileName()
	{
		return $this->_fileName;
	}

	/**
	 * @return null
	 */
	public function getRelativePath()
	{
		return $this->_relativePath;
	}

	/**
	 * @return null
	 */
	public function getMatchExtension()
	{
		return $this->_matchExtension;
	}

	/**
	 * @param $matchType
	 */
	public function setMatchType($matchType)
	{
		$this->_matchType = $matchType;
	}

	/**
	 * @param $extension
	 */
	public function setMatchExtension($extension)
	{
		$this->_matchExtension = $extension;
	}

}
