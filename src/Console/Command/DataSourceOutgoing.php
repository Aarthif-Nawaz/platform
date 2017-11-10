<?php

/**
 * Ushahidi Data Source Console Commands
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Console
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Console\Command;

use Illuminate\Console\Command;

use Ushahidi\Core\Usecase;
use \Ushahidi\Factory\UsecaseFactory;
use Ushahidi\App\DataSource\DataSourceManager;
use Ushahidi\App\DataSource\DataSourceStorage;
use Ushahidi\App\DataSource\OutgoingAPIDataSource;

class DataSourceOutgoing extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'datasource:outgoing';

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'datasource:outgoing {--source=} {--all} {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send outgoing messages via data sources';

    /**
     * @var DataSourceManager
     */
    protected $sources;

    /**
     * @var DataSourceStorage
     */
    protected $storage;

    public function __construct(DataSourceManager $sources, DataSourceStorage $storage) {
        parent::__construct();
        $this->sources = $sources;
        $this->storage = $storage;
    }

    protected function getSources()
    {
        if ($source = $this->option('source')) {
            $sources = array_filter([$source => $this->sources->getSource($source)]);
        } elseif ($this->option('all')) {
            $sources = $this->sources->getSource();
        } else {
            $sources = $this->sources->getEnabledSources();

            // Hack: always include email no matter what!
            if (!isset($sources['email'])) {
                $sources['email'] = $this->sources->getSource('email');
            }
        }
        return $sources;
    }

    public function handle()
    {
        $sources = $this->getSources();
        $limit = $this->option('limit');

        $totals = [];
        // @todo do we even need to do this by source at all? Could we just grab a chunk of pending messages and iterate through those instead?!
        foreach ($sources as $id => $source) {
            if (!($source instanceof OutgoingAPIDataSource)) {
                // Data source doesn't have an API we can push messages to
                continue;
            }

            $totals[] = [
                'Source'   => $source->getName(),
                'Total'    => $this->processSource($source, $id)
            ];
        }

        return $this->table(['Source', 'Total'], $totals);
    }

    protected function processSource($source, $id) // @todo can we drop $id
    {
        $messages = $this->storage->getPendingMessages($limit, $id);

        foreach ($messages as $message) {
            list($new_status, $tracking_id) = $source->send($message->contact, $message->message, $message->title);

            $this->storage->updateMessageStatus($message->id, $new_status, $tracking_id);

            $count ++;
        }

        return $count;
    }

}
