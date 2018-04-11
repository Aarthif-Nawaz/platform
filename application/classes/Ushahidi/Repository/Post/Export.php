<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Posts Export Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2016 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Entity\Post;
use Ushahidi\Core\Entity\PostRepository;
use Ushahidi\Core\Entity\PostExportRepository;

class Ushahidi_Repository_Post_Export extends Ushahidi_Repository_CSVPost implements PostExportRepository
{

	public function getFormIdsForHeaders() {
		$searchQuery = $this->getSearchQuery();
		$result = $searchQuery->resetSelect()
			->select([DB::expr('DISTINCT(posts.form_id)'), 'form_id'])->execute($this->db);
		$result =  $result->as_array();
		return array_column($result, 'form_id');
	}
	public function getHeaders($form_ids) {
		$sql = "SELECT form_attributes.key as form_attribute_key, form_attributes.label as form_attribute_label, " .
			"form_attributes.type as form_attribute_type " .
			"FROM form_attributes " .
			"INNER JOIN form_stages ON form_attributes.form_stage_id = form_stages.form_id " .
			"INNER JOIN forms ON form_stages.form_id = forms.id " .
			"where forms.id IN :forms";
		$results = DB::query(Database::SELECT, $sql)
			->bind(':forms', $form_ids)
			->execute($this->db);
		return $results->as_array();
	}
	/**
	 * @param $data
	 * @return array
	 */
	public function retrieveColumnNameData($data)
	{

		/**
		 * Tags (native) should not be shown in the CSV Export
		 */
		unset($data['tags']);
		// Set attribute keys
		$attributes = [];
		foreach ($data['values'] as $key => $val) {
			$attribute = $this->form_attribute_repo->getByKey($key);
			$attributes[$key] = [
				'label' => $attribute->label,
				'input' => $attribute->input,
				'priority' => $attribute->priority,
				'stage' => $attribute->form_stage_id,
				'type' => $attribute->type,
				'form_id' => $data['form_id']
			];

			// Set attribute names. This is for categories (custom field) to show their label and not the ids
			if ($attribute->type === 'tags') {
				$data['values'][$key] = $this->retrieveTagNames($val);
			}
		}

		$data += ['attributes' => $attributes];

		// Get contact
		if (!empty($data['contact_id'])) {
			$contact = $this->contact_repo->get($data['contact_id']);
			$data['contact_type'] = $contact->type;
			$data['contact'] = $contact->contact;
		}

		// Set Form name
		if (!empty($data['form_id'])) {
			$form = $this->form_repo->get($data['form_id']);
			$data['form_name'] = $form->name;
		}
		return $data;
	}

	public function retrieveTagNames($tag_ids)
	{
		$tag_repo = service('repository.tag');
		$names = [];
		foreach ($tag_ids as $tag_id) {
			$tag = $tag_repo->get($tag_id);
			array_push($names, $tag->tag);
		}
		return $names;
	}

	public function retrieveSetNames($set_ids)
	{
		$set_repo = service('repository.set');
		$names = [];
		foreach ($set_ids as $set_id) {
			$set = $set_repo->get($set_id);
			array_push($names, $set->name);
		}
		return $names;
	}

	public function retrieveCompletedStageNames($stage_ids)
	{
		$names = [];
		foreach ($stage_ids as $stage_id) {
			$stage = $this->form_stage_repo->get($stage_id);
			array_push($names, $stage->label);
		}
		return $names;
	}
}
