<?php

namespace Task;

use Mage\Task\AbstractTask;
use Mage\Task\SkipException;
use Mage\Task\Releases\IsReleaseAware;
use Mage\Console;

class SetFacls extends AbstractTask implements IsReleaseAware
{
    /**
     * Returns the Title of the Task.
     *
     * @return string
     */
    public function getName()
    {
        return 'Set symfony2 file ACLs on remote system';
    }

    /**
     * Runs the task.
     *
     * @return bool
     *
     * @throws SkipException
     */
    public function run()
    {
        $releasesDirectory = $this->getConfig()->release('directory', 'releases');
        $currentCopy = $releasesDirectory.'/'.$this->getConfig()->getReleaseId();

        $users = (array) $this->getParameter('users');
        $groups = (array) $this->getParameter('groups');
        if (!$users && !$groups) {
            throw new SkipException('You have to specify "users" or "groups".');
        }

        $folders = (array) $this->getParameter('folders');
        if (!$folders) {
            throw new SkipException('Missing parameter: "folders".');
        }

        $recursive = $this->getParameter('recursive', false) ? ' -R ' : ' ';

        $params = (array) $this->getParameter('parameters');

        $users_str = '';
        foreach ($users as $u => $perms) {
            if (!preg_match('~^[rwxX]{1,3}$~', $perms)) {
                throw new SkipException('Unsupported value for "permission" parameter: "'.$perms.'".');
            }
            $users_str .= sprintf(' -m u:%s:%s', $u, $perms);
        }

        $groups_str = '';
        foreach ($groups as $g => $perms) {
            if (!preg_match('~^[rwxX]{1,3}$~', $perms)) {
                throw new SkipException('Unsupported value for "permission" parameter: "'.$perms.'".');
            }
            $groups_str .= sprintf(' -m g:%s:%s', $g, $perms);
        }

        $params_str = '';
        foreach ($params as $p) {
            $params_str .= ' '.$p;
        }

		$result = 0;
        $cmd_str = "sudo setfacl $params_str $recursive $users_str $groups_str";
		foreach ($folders as $folder) {
			$command = sprintf('%s %s/%s', $cmd_str, $currentCopy, $folder);
			$result += $this->runCommandRemote($command, $output);
			Console::log($command.' -> '.($result)?'OK':'FAIL');
		}

		return (bool)$result;
    }
}
