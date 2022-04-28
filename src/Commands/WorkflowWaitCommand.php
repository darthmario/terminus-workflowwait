<?php

namespace Pantheon\TerminusWorkflowWait\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;

/**
 * Wait for a workflow to complete
 */
class WorkflowWaitCommand extends TerminusCommand implements SiteAwareInterface
{

    use SiteAwareTrait;

    /**
     * Wait for a workflow to complete. Usually this will be used to wait
     * for code commits, since Terminus will already wait for workflows
     * that it starts through the API.
     *
     * @command workflowwait
     * @param $site_env_id The pantheon site to wait for.
     * @param $maxWaitInSeconds The maximum wait for a workflow to finish in seconds. Optional
     * @option start Ignore any workflows started prior to the start time (epoch)
     */
    public function workflowWait(
        $site_env_id,
        $maxWaitInSeconds = null,
        $options = [
            'start' => 0,
        ]
    ) {
        list($site, $env) = $this->getSiteEnv($site_env_id);
        $env_name = $env->getName();

        $startTime = $options['start'];
        if (!$startTime) {
            $startTime = time() - 60;
        }

        $this->waitForWorkflow($startTime, $site, $env_name, $maxWaitInSeconds);
    }

    protected function waitForWorkflow($startTime, $site, $env_name, $maxWaitInSeconds = null, $maxNotFoundAttempts = null)
    {

        if (null === $maxWaitInSeconds) {
            $maxWaitInSecondsEnv = 10;
            $maxWaitInSeconds = $maxWaitInSecondsEnv ? $maxWaitInSecondsEnv : self::DEFAULT_WORKFLOW_TIMEOUT;
        }

        $startWaiting = time();
        $firstWorkflowID = null;
        $notFoundAttempts = 0;
        $workflows = $site->getWorkflows();

        while (true) {
            $site = $this->getsite($site->id);
            // Refresh env on each interation.
            $index = 0;
            $workflows->reset();
            $workflow_items = $workflows->fetch(['paged' => false,])->all();
            $found = false;
            foreach ($workflow_items as $workflow) {
                $workflowCreationTime = $workflow->get('created_at');
                $workflowDescription = $workflow->get('description');
                $workflowEnv = $workflow->get('environment');
                $workflowID = $workflow->get('id');
                if ($index === 0) {
                    $firstWorkflowID = $workflowID;
                }
                $index++;

                if ($workflowCreationTime < $startTime) {
                    // We already passed the start time.
                    break;
                }

                if (($workflowEnv === $env_name)) {
                    $workflow->fetch();
                    $this->log()->notice("Workflow '{current}' {status}.", [
                        'current' => $workflowDescription,
                        'status' => $workflow->getStatus(),
                        ]);
                    $found = true;
                    if ($workflow->isSuccessful()) {
                        $this->log()->notice("Workflow succeeded");
                        return;
                    }
                }
            }
            if (!$found) {
                $notFoundAttempts++;
                $this->log()->notice("Current workflow env is '{current}'; waiting for env '{expected}'", [
                    'current' => $workflowEnv,
                    'expected' => $env_name
                ]);
                if ($maxNotFoundAttempts && $notFoundAttempts === $maxNotFoundAttempts) {
                    $this->log()->warning("Attempted '{max}' times, giving up waiting for workflow to be found", [
                        'max' => $maxNotFoundAttempts
                    ]);
                    break;
                }
            }
            // Wait a bit, then spin some more
            sleep(5);
            if (time() - $startWaiting >= $maxWaitInSeconds) {
                $this->log()->warning("Waited '{max}' seconds, giving up waiting for workflow", [
                    'max' => $maxWaitInSeconds
                ]);
                break;
            }
        }
    }
}
