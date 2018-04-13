<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi API Formatter for CSV export
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\SearchData;

use Ushahidi\Core\Tool\Filesystem;
use Ushahidi\Core\Tool\FileData;
use League\Flysystem\Util\MimeType;

class Ushahidi_Formatter_Post_CSV extends Ushahidi_Formatter_API
{

	public static $csvIgnoreFieldsByType = array(
		'published_to',
		'lock',
		'parent_id',
		'locale'
	);
	public static $csvFieldFormat = array(
		'tags' => 'single_array',
		'sets' => 'single_array',
		'point'=> 'single_value_array'
	);
	/**
	 * @var SearchData
	 */
	protected $search;
	protected $fs;
	protected $tmpfname;
	protected $add_header = true;
	protected $heading;

	// Formatter
	public function __invoke($records, $attributes = [])
	{
		if ($this->heading) {
			return $this->generateCSVRecords($records, $attributes);
		} else {
			//throw exception
		}
	}

	public function setAddHeader($add_header)
	{
		$this->add_header = $add_header;
	}


	public function setHeading($heading)
	{
		$this->heading = $heading;
	}

	public function setFilesystem($fs)
	{
		$this->tmpfname = "tmp" . DIRECTORY_SEPARATOR . strtolower(uniqid() . '-' . strftime('%G-%m-%d') . '.csv');
		$this->fs = $fs;
	}

	/**
	 * @param $attributes
	 * @param $records
	 * @return array
	 * Attributes are sorted with this criteria:
	 * - Survey "native" fields such as title from the post table go first. These are sorted alphabetically.
	 * - Form_attributes are grouped by stage, and sorted in ASC order by priority

	 */
	public function createHeading($attributes, $records)
	{
		$this->heading = $this->createSortedHeading($attributes);
		
		return $this->heading;
	}

	/**
	 * Generates records that are suitable to save in CSV format.
	 *
	 * Since search records will have mixed forms, rows that
	 * do not have a matching form field will be padded.
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	protected function generateCSVRecords($records, $attributes)
	{
		//$stream = fopen('php://memory', 'w');
		$stream = tmpfile();

		/**
		 * Before doing anything, clean the ouput buffer and avoid garbage like unnecessary space paddings in our csv export
		 */
		ob_clean();

		// Add heading
		if ($this->add_header) {
			fputcsv($stream, array_values($this->heading));
		}

		foreach ($records as $record)
		{
			// Transform post_date to a string
			if ($record['post_date'] instanceof \DateTimeInterface) {
				$record['post_date'] = $record['post_date']->format("Y-m-d H:i:s");
			}
			// Transform post_date to a string
			if (is_numeric($record['created'])) {
				$record['created'] = date("Y-m-d H:i:s", $record['created']);
			}
			if (is_numeric($record['updated'])) {
				$record['updated'] = date("Y-m-d H:i:s", $record['updated']);
			}

			$values = [];

			foreach ($this->heading as $key => $value) {
				$values[] = $this->getValueFromRecord($record, $key, $attributes);
			}
			fputcsv($stream, $values);
		}

		return $this->writeStreamToFS($stream);
	}

	private function writeStreamToFS($stream)
	{

		$filepath = implode(DIRECTORY_SEPARATOR, [
			'csv',
			$this->tmpfname,
			]);

		// Remove any leading slashes on the filename, path is always relative.
		$filepath = ltrim($filepath, DIRECTORY_SEPARATOR);

		$extension = pathinfo($filepath, PATHINFO_EXTENSION);
		
		$mimeType = MimeType::detectByFileExtension($extension) ?: 'text/plain';
		
		$config = ['mimetype' => $mimeType];
		
		$this->fs->putStream($filepath, $stream, $config);

		if (is_resource($stream)) {
			fclose($stream);
		}

		$size = $this->fs->getSize($filepath);
		$type = $this->fs->getMimetype($filepath);

		return new FileData([
			'file'   => $filepath,
			'type'   => $type,
			'size'   => $size,
			]);

	}

	private function getValueFromRecord($record, $keyParam, $attributes){
		$return = '';
		$keySet = explode('.', $keyParam); //contains key + index of the key
		$headingKey = isset($keySet[0]) ? $keySet[0] : null;
		$key = isset($keySet[1]) ? $keySet[1] : null;
		$recordAttributes = isset($attributes[$headingKey]) ? $attributes[$headingKey] : null;
		$format = 'single_raw';
		if (is_array($recordAttributes) && isset($recordAttributes['type']) && isset(self::$csvFieldFormat[$recordAttributes['type']])){
			$format = self::$csvFieldFormat[$recordAttributes['type']];
		}
		$isInValuesArray = isset ($record['values']) && isset($record['values'][$headingKey]);
		$headingKey = $headingKey === 'description' ? 'content' : $headingKey;
		$recordValue = $isInValuesArray ? $record['values']: $record;
		$isDateField = $recordAttributes['input'] === 'date' && $recordAttributes['type'] === 'datetime';

		if ($isDateField && isset($recordValue[$headingKey])) {
			$date = new DateTime($recordValue[$headingKey][$key]);
			$recordValue[$headingKey][$key] = $date->format('Y-m-d');
		}
		if($format === 'single_value_array'){
			/*
			 * Lat/Lon are never multivalue fields so we can get the first index  only
			 */
			$return = $this->singleValueArray($recordValue, $headingKey, $key);
		} else if ($format === 'single_array') {
			/**
			 * we need to join the array items in a single comma separated string
			 */
			$return = $this->singleColumnArray($recordValue, $headingKey);
		} else if ($format ==='multiple_array' || ($key !== null && isset($recordValue[$headingKey]) && is_array($recordValue[$headingKey]))) {
			/**
			 * we work with multiple posts which means our actual count($record[$key])
			 * value might not exist in all of the posts we are posting in the CSV
			 */
			$return = $this->multiColumnArray($recordValue, $headingKey, $key);
		} else if ($format === 'single_raw') {
			$return = $this->singleRaw($recordValue, $record, $headingKey, $key);
		}
		return $return;
	}
	private function singleRaw($recordValue, $record, $headingKey, $key){
	 	if ($key !== null) {
			return isset($recordValue[$headingKey])? ($recordValue[$headingKey]): '';
		} else {
			$emptyRecord = !isset($record[$headingKey]) || (is_array($record[$headingKey]) && empty($record[$headingKey]));
			return $emptyRecord ? '' : $record[$headingKey];
		}
	}
	private function multiColumnArray($recordValue, $headingKey, $key) {
		return isset($recordValue[$headingKey][$key])? ($recordValue[$headingKey][$key]): '';
	}
	private function singleColumnArray($recordValue, $headingKey, $separator = ',') {
		/**
	 	* we need to join the array items in a single comma separated string
	 	*/
		return isset($recordValue[$headingKey])? (implode($separator, $recordValue[$headingKey])): '';
	}

	private function singleValueArray($recordValue, $headingKey, $key) {
		/**
		 * we need to join the array items in a single comma separated string
		 */
		return isset($recordValue[$headingKey][0][$key])? ($recordValue[$headingKey][0][$key]): '';
	}

	/**
	 * @param $fields: an array with the form: ["key": (value)] where value can be anything that the user chose.
	 * @return array of sorted fields with a zero based index. Multivalue keys have the format keyxyz.index index being an arbitrary count of the amount of fields.
	 */
	private function createSortedHeading($fields){
		/**
		 * sort each field by priority inside the stage
		 */
		$headingResult = $this->sortGroupedFieldsByPriority($fields);
		return $headingResult;
	}

	/**
	 * @param $groupedFields is an associative array with fields grouped in arrays by their stage
	 * @return array . Flat, associative. Example => ['keyxyz'=>'label for key', 'keyxyz2'=>'label for key2']
	 */
	private function sortGroupedFieldsByPriority($fields){

		$groupedFields = [];
		foreach($fields as $key => $item)
		{
			$groupedFields[$item['form_id'].$item['form_stage_priority'].$item['priority']][$item['key']] = $item;
		}

		ksort($groupedFields, SORT_NUMERIC);

		$attributeKeysWithStageFlat = [];
		foreach ($groupedFields as $stageKey => $attributeKeys){
			/**
			 * uasort is used here to preserve the associative array keys when they are sorted
			 */
			uasort($attributeKeys, function ($item1, $item2) {
				if ($item1['priority'] === $item2['priority']){
					/**
					 * if they are the same in priority, then that means we will fall back to alphabetical priority for them
					 */
					return $item1['label'] < $item2['label'] ? -1 : 1;
				}
				return $item1['priority'] < $item2['priority'] ? -1 : 1;
			});
			/**
			 * Finally, we can flatten the array, and set the fields (key->labels) with the user-selected order.
			 */
			foreach ($attributeKeys as $attributeKey => $attribute){
				if (is_array($attribute) && $attribute['type'] !== 'point'){
					/**
					 * If the attribute has a count key, it means we want to show that as key.index in the header.
					 * This is to make sure we don't miss values in multi-value fields
					 */
					$attributeKeysWithStageFlat[$attributeKey.'.0'] = $attribute['label'];
				} else if (isset($attribute['type']) && $attribute['type'] === 'point'){
					$attributeKeysWithStageFlat[$attributeKey.'.lat'] = $attribute['label'].'.lat';
					$attributeKeysWithStageFlat[$attributeKey.'.lon'] = $attribute['label'].'.lon';
				}
			}
		}
		return $attributeKeysWithStageFlat;
	}

	/**
	 * Checks if value is a locaton
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	protected function isLocation($value)
	{
		return is_array($value) &&
			array_key_exists('lon', $value) &&
			array_key_exists('lat', $value);
	}

	/**
	 * Store search parameters.
	 *
	 * @param  SearchData $search
	 * @return $this
	 */
	public function setSearch(SearchData $search)
	{
		$this->search = $search;
		return $this;
	}
}
