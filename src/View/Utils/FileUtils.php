<?php

namespace AuroraLumina\View\Utils;

class FileUtils
{
    /**
     * Load the contents of a file.
     *
     * @param string $filename The name of the file to load.
     * @return string The contents of the file.
     */
	public static function loadContents(string $filename): string
	{
		$contents = file_get_contents($filename);

		if ($contents === false)
		{
			return '';
		}
		
		if (substr($contents, 0, 3) === "\xEF\xBB\xBF")
		{
			return substr($contents, 3);
		}

		return $contents;
	}

	/**
     * Create the output directory if it does not exist.
     *
     * @param string $output_filename The name of the output file.
     * @return void
     */
	public static function createOutputDirectory(string $output_filename): void
	{
		$output_directory = dirname($output_filename);
		
		if (!is_dir($output_directory))
		{
			mkdir($output_directory, 0777, true);
		}
	}

	/**
     * Write the contents to a file.
     *
     * @param string $output_filename The name of the output file.
     * @param string $contents The contents to write to the file.
     * @return bool True if writing was successful, false otherwise.
     */
	public static function writeToFile(string $output_filename, string $contents): bool
	{
		$result = file_put_contents($output_filename, $contents, LOCK_EX);

		return $result !== false;
	}
}