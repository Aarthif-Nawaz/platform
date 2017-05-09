<?php defined('SYSPATH') or die('No direct script access');

/**
 * Ushahidi Intercom Listener
 *
 * Listens for new posts that are added to a set
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use League\Event\AbstractListener;
use League\Event\EventInterface;

use Intercom\IntercomClient;

use GuzzleHttp\Exception\ClientException;

class Ushahidi_Listener_IntercomCompanyListener extends AbstractListener
{
  protected $config_repo;

  public function setConfigRepo(ConfigRepository $config_repo)
  {
  		$this->config_repo = $config_repo;
  }

  public function handle(EventInterface $event, $data = null)
  {

    $config = service('repository.config')->get('thirdparty');
    $company = [
      "company_id" => $config->intercomCompanyId
    ];

    Kohana::$log->add(Log::ERROR, print_r($config));

		if ($config->intercomAppToken) {

      $client = new IntercomClient($config->intercomAppToken, null);

			try {
        // Create company with current date if it does not already exist
        if (!$config->intercomCompanyId) {
          $company->name = Kohana::$config->load('site.name') ?: 'Ushahidi';
          $config->intercomCompanyId = Kohana::$config->load('site.client_url');

          service('repository.config')->update($config);
          $company->company_id = $config->intercomCompanyId;

          $data['created'] = date("Y-m-d H:i:s");
        }

        $company->custom_attributes = $data;
        // Update company
        $client->companies->create($company);

			} catch(ClientException $e) {
				Kohana::$log->add(Log::ERROR, print_r($e,true));
			}
		}
  }
}
