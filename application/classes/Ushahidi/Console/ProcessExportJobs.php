<?php defined('SYSPATH') or die('No direct script access');

/**
 * Ushahidi Data Provider Console Commands
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Console
 * @copyright  2018 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use Ushahidi\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Ushahidi\Core\Entity\ExportJobRepository;

/* Simple console command that processes pending jobs in the DB */

class Ushahidi_Console_ProcessExportJobs extends Command {

    private $data;
    private $exportJobRepository;


    public function setExportJobRepo(ExportJobRepository $repo)
    {
        $this->exportJobRepository = $repo;
    }

	protected function configure()
	{
		$this
			->setName('processexports')
			->setDescription('Processes pending export jobs.')
			->addArgument('action', InputArgument::OPTIONAL, 'list, pending', 'pending')
			;
	}

    //display all jobs, including pending, failed, and successful
   protected function executeList(InputInterface $input, OutputInterface $output)
   {
       $jobs = $this->getJobs($input, $output);
       foreach ($jobs as $job)
       {
           $list[] = [
               'ID'       => $job->id,
               'Fields'     => $job->fields,
               'Filters'    => $job->filters,
               'Status'     => $job->status,
           ];
       }
       return $list;
   }

	protected function getJobs()
	{
        // @TODO: handle input options, e.g., to find just pending jobs
		$jobs = $this->exportJobRepository->getAllJobs();
        return $jobs;
	}

    protected function updateJobWithResponse($jobId, $responseInfo)
	{
        // update that job with success/failure
        $resultStatus = 'pending';
        if (array_key_exists('success', $responseInfo))
        { if($responseInfo['success'] == TRUE)
            {
                $resultStatus = 'SUCCESS';
            }else {
                $resultStatus = 'FAILED';
            }
        }

        $jobEntity = $this->exportJobRepository->get($jobId);
        // @TODO: get accessible path for this URL from config!
         $accessiblePath = 'http://192.168.33.110/media/uploads/';

        $jobEntity->setState(['id' => $jobId, 'status' => $resultStatus, 'url' => $accessiblePath.$responseInfo['file'] ]);
        $this->exportJobRepository->update($jobEntity);
        return $resultStatus;
	}

	protected function executePending(InputInterface $input, OutputInterface $output)
	{
        $pendingJobs = $this->getJobs();
        $jobsProcessed = ['count' => 0, 'job_info' => []];

		foreach ($pendingJobs as $job)
		{
            if ($job->status == 'pending')
            {
                //do a full export without limits
                $exportResponse = $this->doExportAsCli($job->id);
                $status = $this->updateJobWithResponse($job->id, $exportResponse);
                $jobsProcessed['count']++;
                array_push($jobsProcessed['job_info'], [$job->id => $status]);
            }
        }
       $this->handleResponse($jobsProcessed, $output, 'json');
	}


    protected function doExportAsCli($job_id)
    {
        $exportCommand = $this->getApplication()->find('exporter');
        $output = new BufferedOutput();

		// Construct console command input
		$input = new ArrayInput(array(
			'--offset' => 0,
			'--job' => $job_id,
			'--include_header' => 'true',
        ));

         //$greetInput = new ArrayInput($arguments);
         $returnCode = $exportCommand->run($input, $output);
         $executionResults['success'] = false;
         if($returnCode == 0)
         {
             $executionResults['success'] = true;
             $response = json_decode($output->fetch());
             $executionResults['file'] = $response[0]->file;
        }
        return $executionResults;
    }

}
