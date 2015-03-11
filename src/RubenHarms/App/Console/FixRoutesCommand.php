<?php
/*
 * Copyright (c) 2015 - Ruben Harms <info@rubenharms.nl>
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace RubenHarms\App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixRoutesCommand extends Command
{

    protected function configure()
    {
        $this->setName('routefix')
            ->setDescription('Greet someone')
            ->addArgument('name', InputArgument::OPTIONAL, 'Who do you want to greet?')
            ->addOption('yell', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln( "\n\n".
'Route fix for Cloud VPS Openstack - (c) 2015 Ruben Harms
            
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.' . "\r\n\r\n");
        
        $version = `cat /proc/version`;
        
        if (! preg_match("/Red Hat/i", $version)) {
            $output->writeln("<error>Error: This software is Red hat / CentOS / Fedora core only!</error>");
            return;
        }
        
        if (posix_getuid() != 0) {
            $output->writeln("<error>Error: Please run this script as root user.</error>");
            return;
        }
        
        $output->write("<info>Going further is at own risk! </info>");
        
        $q = "<question>Do you agree?</question> [n]: ";
        
        $further = $this->getHelper('dialog')->askAndValidate($output, $q, function ($typeInput) {
            if (empty($typeInput) || array_search($typeInput, array(
                "y",
                "n"
            )) === false) {
                throw new \InvalidArgumentException('Wrong input! Possible options: [y,n]');
            }
            return $typeInput;
        }, 10, 'n');
        
        if ($further != "y") {
            $output->writeln("<comment>Aborted!</comment>");
            return;
        }
        
        $ipConfig = `ip -4 a`;
        $lines = explode("\n", $ipConfig);
        
        $eth = array();
        $i = - 1;
        foreach ($lines as $l) {
            if (preg_match("/^[0-9]+: ([A-Za-z0-9]+):/", $l, $matches)) {
                $eth[++ $i] = array(
                    'name' => $matches[1],
                    'ip' => '',
                    'gateway' => ''
                );
            } elseif (preg_match("/\s+inet ([0-9]+)\.([0-9]+)\.([0-9]+).([0-9]+)\/([0-9]+)/", $l, $matches)) {
                $eth[$i]['ip'] = $matches[1] . '.' . $matches[2] . '.' . $matches[3];
                $eth[$i]['gateway'] = $eth[$i]['ip'] . '.1';
                $eth[$i]['ip'] .= '.' . $matches[4];
            }
        }
        
        foreach($eth as $key => $value){
            if(substr($value['name'],0,3) != "eth"){
                unset($eth[$key]);                
            }
        }
        
        $output->writeln("\r\n<comment>Your current ethernet configuration:</comment>\r\n");
        
        $output->writeln("DEVICE\t\t|IP\t\t\t\t|GATEWAY");
        $output->writeln("---------------------------------------------------");
        
        foreach ($eth as $key => $nic) {
            $output->writeln($nic['name'] . "\t\t|" . $nic['ip'] . "\t\t|" . $nic['gateway']);
        }
        
        $q = "\r\n<question>Is the configuration above correct?</question> [y]: ";
        
        $further = $this->getHelper('dialog')->askAndValidate($output, $q, function ($typeInput) {
            if (empty($typeInput) || array_search($typeInput, array(
                "y",
                "n"
            )) === false) {
                throw new \InvalidArgumentException('Wrong input! Possible options: [y,n] ');
            }
            return $typeInput;
        }, 10, 'y');
        
        if ($further != "y") {
            $output->writeln("<comment>Aborted!</comment>");
            return;
        }
        
        foreach($eth as $key => $value){
            if($value['name'] == "eth0"){
                unset($eth[$key]);
            }
        }
        
        $rtBack = "/etc/iproute2/rt_tables_" . date("d-m-y_h:i:s") . ".bak";
        
        $output->write("\r\nCreating backup file of your route tables (/etc/iproute2/rt_tables) to " . $rtBack . "...");
        if (! copy("/etc/iproute2/rt_tables", $rtBack)) {
            $output->writeln("<error>Error: Unable to create backup</error>");
            return;
        }
        
        $output->writeln('<fg=green>OK</fg=green>');
        
        $rcLocalBack = "/etc/rc.local_" . date("d-m-y_h.i.s") . ".bak";
        $output->write("Creating backup file of /etc/rc.local to " . $rcLocalBack . "...");
        if (! copy("/etc/rc.local", $rcLocalBack)) {
            $output->writeln("<error>Error: Unable to create backup</error>");
            return;
        }
        
        $output->writeln('<fg=green>OK</fg=green>');
        
        $output->write("\r\nUpdating route tables...");
        
        $path = '/etc/iproute2/rt_tables';
        $rcTbl = file_get_contents($path);
        $rtRules = '#/START RHFIX' . "\n";
        $rcRules = '#/START RHFIX' . "\n";
        
        $routeCommands = array();
        
        $i = 1;
        foreach ($eth as $nic) {
            $rtRules .= $i ++ . " out-" . $nic['name'] . "\n";
            
            $routeCommands[] = "ip route add default via " . $nic['gateway'] . " dev " . $nic['name'] . " table out-" . $nic['name'];
            $routeCommands[] = "ip rule add from " . $nic['ip'] . "/32 lookup out-" . $nic['name'];
        }
        $rtRules .= '#/END RHFIX' . "\n";
        $rcRules .= implode("\n", $routeCommands) . "\n";
        $rcRules .= '#/END RHFIX' . "\n";
        
        // print_r($routeCommands);
        
        if (preg_match("|(#/START RHFIX)(.*)(#/END RHFIX)|s", $rcTbl))
            $rcTbl = preg_replace("|(#/START RHFIX)(.*)(#/END RHFIX)|s", $rtRules, $rcTbl);
        else
            $rcTbl = $rtRules . $rcTbl;
        
        if (! file_put_contents($path, $rcTbl)) {
            $output->writeln("<error>Error: Unable to update route tables</error>");
            return $this->rollbackChanges($output, $rcLocalBack, $rtBack );
        }
        $output->writeln('<fg=green>OK</fg=green>');
        
        $output->write("\r\nUpdating routes...");
        
        foreach ($routeCommands as $command) {
            $output->writeln($command);
          exec($command, $ou, $ret);
          if($ret){
              $output->writeln("<error>Error: Unable to add route!</error>");
              return $this->rollbackChanges($output, $rcLocalBack, $rtBack );  
          }
        }
        $output->writeln('<fg=green>OK</fg=green>');
        
        $output->writeln("\nPlease try to reach the following addresses: \n");
        
        foreach ($eth as $nic) {
            $output->writeln($nic['ip']);
        }
        
        $q = "\n<question>Does the adresses work well?</question> [y]: ";
        
        $further = $this->getHelper('dialog')->askAndValidate($output, $q, function ($typeInput) {
            if (empty($typeInput) || array_search($typeInput, array(
                "y",
                "n"
            )) === false) {
                throw new \InvalidArgumentException('Wrong input! Possible options: [y,n]');
            }
            return $typeInput;
        }, 10, 'y');
        
        if ($further != "y") {                       
            return $this->rollbackChanges($output, $rcLocalBack, $rtBack );
        }
        
        $output->write("\r\nAdding routes for the next boot...");
        
        $path = '/etc/rc.local';
        $rcLocal = file_get_contents($path);
       
        
        if (preg_match("|(#/START RHFIX)(.*)(#/END RHFIX)|s", $rcLocal))
            $rcLocal = preg_replace("|(#/START RHFIX)(.*)(#/END RHFIX)|s", $rcRules, $rcLocal);
        else {
            
            $rcLocal = "#!/bin/sh\n\n" . $rcRules .  preg_replace("|#!/bin/sh|s", "", $rcLocal);
        }
        if (! file_put_contents($path, $rcLocal)) {
            $output->writeln("<error>Error: Unable to add route for next boot</error>");
            return $this->rollbackChanges($output, $rcLocalBack, $rtBack );           
        }
        $output->writeln('<fg=green>OK</fg=green>');       

        $q = "To test your new configuration, you have to reboot. <question>Would you like to reboot now?</question> [n]: ";        
        $further = $this->getHelper('dialog')->askAndValidate($output, $q, function ($typeInput) {
            if (empty($typeInput) || array_search($typeInput, array(
                "y",
                "n"
            )) === false) {
                throw new \InvalidArgumentException('Wrong input! Possible options: [y,n]');
            }
            return $typeInput;
        }, 10, 'n');
        
        if ($further == "y") {
            $output->writeln('<fg=green>Reboot now...</fg=green>');        
            exec("reboot");       
            return;
        }      
        
        $output->writeln('<fg=green>Finished</fg=green>');
    }
    
    
    public function rollbackChanges( OutputInterface $output, $rcLocalBack, $rtBack ){
        $output->write("<info>Rollback changes...</info>");
        exec("cp ". $rcLocalBack . " /etc/rc.local -f", $oa, $retRcLocal);
        if($retRcLocal){
            $output->writeln("<error>Error: Unable to restore ". $rcLocalBack. " to /etc/rc.local </error> Please do it manual!");
        }
        print exec("cp ". $rtBack . " /etc/iproute2/rt_tables -f", $oa, $retRoute);
        if($retRoute){
            $output->writeln("<error>Error: Unable to restore ". $rtBack. " to /etc/iproute2/rt_tables </error> Please do it manual!");
        }
        
        if(!$retRoute && !$retRcLocal) $output->writeln('<fg=green>OK</fg=green>');
        $output->writeln("<comment>Aborted!</comment>");
    }
    
}