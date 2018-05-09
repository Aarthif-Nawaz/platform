<?php

namespace Ushahidi\App\Http\Controllers\API\HXL;

use Ushahidi\App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ushahidi\App\Http\Controllers\RESTController;

/**
 * HXL Licenses controller
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application\Controllers
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

class HXLLicensesController extends RESTController
{

	protected function getResource()
	{
		return 'hxl_licenses';
	}

	/**
	 * Retrieve a basic information about the API
	 *
	 * GET /api
	 *
	 * @return void
	 */
	public function index(Request $request)
	{
		$this->usecase = $this->usecaseFactory
			->get($this->getResource(), 'search');
		return $this->prepResponse($this->executeUsecase($request), $request);
	}
}
