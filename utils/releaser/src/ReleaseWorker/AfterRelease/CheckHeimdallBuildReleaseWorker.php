<?php

declare(strict_types=1);

namespace Shopsys\Releaser\ReleaseWorker\AfterRelease;

use PharIo\Version\Version;
use Shopsys\Releaser\ReleaseWorker\AbstractShopsysReleaseWorker;
use Shopsys\Releaser\Stage;

final class CheckHeimdallBuildReleaseWorker extends AbstractShopsysReleaseWorker
{
    /**
     * @param \PharIo\Version\Version $version
     * @param string $initialBranchName
     * @return string
     */
    public function getDescription(Version $version, string $initialBranchName = AbstractShopsysReleaseWorker::MAIN_BRANCH_NAME): string
    {
        return '[Manually] Check builds on Heimdall';
    }

    /**
     * @return string
     */
    public function getStage(): string
    {
        return Stage::AFTER_RELEASE;
    }

    /**
     * @param \PharIo\Version\Version $version
     * @param string $initialBranchName
     */
    public function work(Version $version, string $initialBranchName = AbstractShopsysReleaseWorker::MAIN_BRANCH_NAME): void
    {
        $this->symfonyStyle->note(
            sprintf(
                'You should discard the deletion of the application in Kubernetes for the new tag, ie. in the job configuration (http://heimdall:8080/job/%s/configure), remove "kubectl delete namespace ${JOB_NAME} || true" from post-build tasks) so the e-shop instance is available.',
                $version->getOriginalString()
            )
        );
        $this->confirm(
            sprintf(
                'Confirm Heimdall build of %s job, as well as builds of all special jobs, are passing',
                $version->getVersionString()
            )
        );
    }
}
