<?php

/**
 * Ushahidi Post Media Validator
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\App\Validator\Post;

use Ushahidi\Core\Entity\TagRepository;

class Tags extends ValueValidator
{
    protected $repo;

    public function __construct(TagRepository $tags_repo)
    {
        $this->repo = $tags_repo;
    }

    protected function validate($value)
    {
        if (is_array($value) && !isset($value['confidence_score'])) {
            $value = $value['id'];
        } else if (isset($value['confidence_score'])) {
            $value = $value['value'];
        }

        if (!$this->repo->doesTagExist($value)) {
            return 'tagExists';
        }
    }
}
