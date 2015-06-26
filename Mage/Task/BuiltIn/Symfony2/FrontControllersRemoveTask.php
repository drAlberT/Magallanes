<?php
namespace Task;

use Mage\Task\AbstractTask;

/**
 * @author Muhammad Surya Ihsanuddin <surya.kejawen@gmail.com>
 */
class FrontControllersRemoveTask extends AbstractTask
{
    /**
     * (non-PHPdoc)
     * @see \Mage\Task\AbstractTask::getName()
     */
    public function getName()
    {
        return 'Remove unwanted front controllers';
    }
    
    /**
     * Removes unwanted front controllers
     *
     * @see \Mage\Task\AbstractTask::run()
     */
    public function run()
    {
        $controllers = (array)$this->getParameter('controllers');
        
        $result = 0;
        foreach ($controllers as $controller) {
            $command = 'rm -f web/app_'.$controller.'.php';
            $result += $this->runCommandRemote($command);
        }

        return (bool)$result;
    }
}
