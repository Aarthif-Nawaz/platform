<?php

namespace Ushahidi\App\DataSource;

/**
 * Base class for all Data Providers
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\DataSource
 * @copyright  2013 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */


class DataSourceStorage
{

    protected $receiveUsecase;
    protected $messageRepo;

    public function __construct()
    {
        $this->receiveUsecase = service('factory.usecase')->get('messages', 'receive');
        $this->messageRepo = service('repository.message');
    }

    /**
     * Receive Messages From data source
     *
     * @todo  convert params to some kind of DTO
     *
     * @param  string type    Message type
     * @param  string from    From contact
     * @param  string message Received Message
     * @param  string to      To contact
     * @param  string title   Received Message title
     * @param  string data_provider_message_id Message ID
     * @return void
     */
    public function receive(
        $source_id,
        $type,
        $contact_type,
        $from,
        $message,
        $to = null,
        $title = null,
        $data_provider_message_id = null,
        array $additional_data = null
    ) {
        $data_provider = $source_id;

        try {
            return $this->receiveUsecase->setPayload(compact([
                    'type',
                    'from',
                    'message',
                    'to',
                    'title',
                    'data_provider_message_id',
                    'data_provider',
                    'contact_type',
                    'additional_data'
                ]))
                ->interact();
        } catch (\Ushahidi\Core\Exception\NotFoundException $e) {
            abort(404, $e->getMessage());
        } catch (\Ushahidi\Core\Exception\AuthorizerException $e) {
            abort(403, $e->getMessage());
        } catch (\Ushahidi\Core\Exception\ValidatorException $e) {
            abort(422, 'Validation Error: ' . $e->getMessage() . '; ' .  implode(', ', $e->getErrors()));
        } catch (\InvalidArgumentException $e) {
            abort(400, 'Bad request: ' . $e->getMessage() . '; ' . implode(', ', $e->getErrors()));
        }
    }

    /**
     * Get pending messages for source
     *
     * @param  string  $source  data source id
     * @param  boolean $limit   maximum number of messages to send at a time
     */
    public function getPendingMessages($limit = 20, $source = false)
    {
        $sources = array();
        $count = 0;

        // Grab latest messages
        return $this->messageRepo->getPendingMessages(Message\Status::PENDING, $source, $limit);
    }

    /**
     * Update message status
     *
     * @param  [type] $id          [description]
     * @param  [type] $status      [description]
     * @param  [type] $tracking_id [description]
     * @return [type]              [description]
     */
    public function updateMessageStatus($id, $status, $tracking_id = null)
    {
        // @todo validate message status
        $this->messageRepo->updateMessageStatus($id, $status, $tracking_id);
    }
}
