<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Ushahidi Form Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Core\Entity;
use Ushahidi\Core\Entity\Form;
use Ushahidi\Core\Entity\FormRepository;
use Ushahidi\Core\SearchData;

class Ushahidi_Repository_Form extends Ushahidi_Repository implements
    FormRepository
{
    use Ushahidi_FormsTagsTrait;
    
    // Ushahidi_Repository
    protected function getTable()
    {
        return 'forms';
    }

    // CreateRepository
    // ReadRepository
    public function getEntity(Array $data = null)
    {
	    if (isset($data["id"])) {
            $can_create = $this->getRolesThatCanCreatePosts($data['id']);
            $data = $data + [
	            'can_create' => $can_create['roles'],
            ];
            $data['tags'] = $this->getTagsForForm($data['id']);
	    }
        return new Form($data);
    }

    // SearchRepository
    public function getSearchFields()
    {
        return ['parent', 'q' /* LIKE name */];
    }

    // Ushahidi_Repository
    protected function setSearchConditions(SearchData $search)
    {
        $query = $this->search_query;
        if ($search->parent) {
            $query->where('parent_id', '=', $search->parent);
        }

        if ($search->q) {
            // Form text searching
            $query->where('name', 'LIKE', "%{$search->q}%");
        }
    }

    // CreateRepository
    public function create(Entity $entity)
    {
        $record = clone($entity);
        unset($record->tags);
        $id = parent::create($record->setState(['created' => time()]));
        //updating forms_tags-table
        if(isset($entity->tags) && $id !== null) {
            $this->updateFormsTags($id, $entity->tags);
        }
        // todo ensure default group is created
        return $id;
    }

    // UpdateRepository
    public function update(Entity $entity)
    {
        $record = clone($entity);
        unset($record->tags);
            $id = parent::update($record->setState(['updated' => time()]));
        // updating forms_tags-table
        if(isset($entity->tags) && $id !== null) {
            $this->updateFormsTags($id, $entity->tags);
        }

        return $id;
    }

    /**
     * Get total count of entities
     * @param  Array $where
     * @return int
     */
    public function getTotalCount(Array $where = [])
    {
        return $this->selectCount($where);
    }

    /**
     * Get `everyone_can_create` and list of roles that have access to post to the form
     * @param  $form_id
     * @return Array
     */
    public function getRolesThatCanCreatePosts($form_id)
    {
        $query = DB::select('forms.everyone_can_create', 'roles.name')
            ->distinct(TRUE)
            ->from('forms')
            ->join('form_roles', 'LEFT')
            ->on('forms.id', '=', 'form_roles.form_id')
            ->join('roles', 'LEFT')
            ->on('roles.id', '=', 'form_roles.role_id')
            ->where('forms.id', '=', $form_id);

        $results =  $query->execute($this->db)->as_array();

        $everyone_can_create = (count($results) == 0 ? 1 : $results[0]['everyone_can_create']);

        $roles = [];

        foreach($results as $role) {
            if (!is_null($role['name'])) {
                $roles[] = $role['name'];
            }
        }

        return [
            'everyone_can_create' => $everyone_can_create,
            'roles' => $roles,
            ];

    }

}
