<?php
/*
* This file is part of the Magallanes package.
*
* (c) Andrés Montañez <andres@andresmontanez.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Mage\Task\BuiltIn\Deployment;

use Mage\Task\AbstractTask;
use Mage\Task\Releases\IsReleaseAware;
use Mage\Task\Releases\SkipOnOverride;

/**
 * Task for Releasing a Deploy
 *
 * @author Andrés Montañez <andres@andresmontanez.com>
 */
class ReleaseTask extends AbstractTask implements IsReleaseAware, SkipOnOverride
{
    /**
     * sudo command string, to execute privileged tasks
     *
     * string $sudo_cmd
     */
    private $sudo_cmd = '';

    /**
     * (non-PHPdoc)
     * @see \Mage\Task\AbstractTask::getName()
     */
    public function getName()
    {
        return 'Releasing [built-in]';
    }

    /**
     * Releases a Deployment: points the current symbolic link to the release directory
     * @see \Mage\Task\AbstractTask::run()
     */
    public function run()
    {
        $resultFetch = false;
        if ($this->getConfig()->release('enabled', false) === true) {
            $releasesDirectory = $this->getConfig()->release('directory', 'releases');
            $symlink = $this->getConfig()->release('symlink', 'current');

            if (substr($symlink, 0, 1) == '/') {
                $releasesDirectory = rtrim($this->getConfig()->deployment('to'), '/') . '/' . $releasesDirectory;
            }

            $releaseId = $this->getConfig()->getReleaseId();

            $currentCopy = $releasesDirectory . '/' . $releaseId;

            //Check if target user:group is specified
            $userGroup = $this->getConfig()->deployment('owner');
            // Fetch the user and group from base directory; defaults usergroup to 33:33
            if (empty($userGroup)) {
                $user = '33';
                $group = '33';
                $directoryInfos = '';
                // Get raw directory info and parse it in php.
                // "stat" command don't behave the same on different systems, ls output format also varies
                // and awk parameters need special care depending on the executing shell
                $resultFetch = $this->runCommandRemote("ls -ld .", $directoryInfos);
                if (!empty($directoryInfos)) {
                    //uniformize format as it depends on the system deployed on
                    $directoryInfos = trim(str_replace(array("  ", "\t"), ' ', $directoryInfos));
                    $infoArray = explode(' ', $directoryInfos);
                    if (!empty($infoArray[2])) {
                        $user = $infoArray[2];
                    }
                    if (!empty($infoArray[3])) {
                        $group = $infoArray[3];
                    }
                }
                $userGroup = $user . ':' . $group;
            }

            $command = 'chown -R ' . $userGroup . ' ' . $currentCopy
                . ' && '
                . 'chown ' . $userGroup . ' ' . $releasesDirectory;
            $result = $this->runCommandRemote($command);
            if (!$result) {
                return $result;
            }

            // Switch symlink and change owner
            $tmplink = $symlink . '.tmp';
            $command = $this->sudo_cmd . "ln -sfn {$currentCopy} {$tmplink}";
            if ($resultFetch && $userGroup != '') {
                $command .= " && chown -h {$userGroup} {$tmplink}";
            }
            $command .= " && mv -fT {$tmplink} {$symlink}";
            $result = $this->runCommandRemote($command);

            if ($result) {
                $this->cleanUpReleases();
            }

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Removes old releases
     */
    protected function cleanUpReleases()
    {
        $return = 0;
        if (!$this->getConfig()->release('enabled', false)) {
            return 0;
        }

        $symlink = $this->getConfig()->release('symlink', 'current');
        $releasesDirectory = $this->getConfig()->release('directory', 'releases');
        if (substr($symlink, 0, 1) == '/') {
            $releasesDirectory = rtrim($this->getConfig()->deployment('to'), '/') . '/' . $releasesDirectory;
        }

        // Count Releases
        $maxReleases = $this->getConfig()->release('max', 0);
        if ($maxReleases > 0) {
            $releasesList = '';
            $this->runCommandRemote('ls -1 ' . $releasesDirectory, $releasesList);
            $releasesList = trim($releasesList);

            if ('' != $releasesList) {
                $releasesList = explode(PHP_EOL, $releasesList);
                if (count($releasesList) > $maxReleases) {
                    $releasesToDelete = array_diff($releasesList, array($this->getConfig()->getReleaseId()));
                    sort($releasesToDelete);
                    $releasesToDelete = array_slice($releasesToDelete, 0, count($releasesToDelete) - $maxReleases + 1);

                    foreach ($releasesToDelete as $release) {
                        $directoryToDelete = $releasesDirectory . '/' . $release;
                        if ($directoryToDelete == '/') {
                            continue;
                        }
                        $command = $this->sudo_cmd . 'rm -rf ' . $directoryToDelete;
                        $return += $this->runCommandRemote($command);
                    }
                }
            }
        }

        return $return;
    }
}
