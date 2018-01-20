#!/usr/bin/php
<?php
 
require __DIR__ . '/../../vendor/autoload.php';
 
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\ProcessBuilder;
 
class PHPCodeQualityTool extends Application
{
    private $commit;
    private $input;
    private $output;
    private $projectRoot;
 
    //The locations of the files you want to measure. Add/remove as needed.
    const PHP_FILES = '/^(.*)(\.php)$/';
 
    public function __construct($commit = false)
    {
        $this->commit = $commit;

        /** OS agnostic */
        $this->projectRoot = realpath(__DIR__ . '/../../');
        parent::__construct('PHP Code Quality Tool', '1.0.0');
    }
 
    /**
     * @param $file
     *
     * @return bool
     */
    private function shouldProcessFile($file)
    {
        return preg_match(self::PHP_FILES, $file);
    }
 
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $error = false;
        $this->input  = $input;
        $this->output = $output;
 
        $output->writeln('<fg=white;options=bold;bg=cyan> -- PHP Code Quality Check -- </fg=white;options=bold;bg=cyan>');
        $output->writeln('<info>Fetching files</info>');
        $files = $this->extractCommitedFiles($this->commit);
 
        $output->writeln('<info>Checking composer</info>');
        if (!$this->checkComposer($files)) {
            $output->writeln('composer.lock must be commited if composer.json is modified!');
            $error = true;
        }

        $output->writeln('<info>Running PHPLint</info>');
        if (!$this->phpLint($files)) {
            $output->writeln('There are some PHP syntax errors!');
            $error = true;
        }

        $output->writeln('<info>Running Code Style</info>');
        if (!$this->codeStyle()) {
            $output->writeln(sprintf('<error>%s</error>', trim($this->codeStyle())));
            $error = true;
        }
 
        $output->writeln('<info>Running PHPMD</info>');
        if (!$this->checkPhpMd($files)) {
            throw new Exception('<fg=white;options=bold;bg=red> -- Code Quality Check: FAILED! -- </fg=white;options=bold;bg=red>');
            $output->writeln('<fg=white;options=bold;bg=red> -- Code Quality Check: FAILED! -- </fg=white;options=bold;bg=red>');
            $error = true;
        }
        
        if(!$error){
            $output->writeln('<fg=white;options=bold;bg=green> -- Code Quality Check: PASSED! -- </fg=white;options=bold;bg=green>');
        }

        if($error){
            if(!$this->commit){
                throw new Exception('<fg=white;options=bold;bg=red> -- CANNOT COMMIT! --');
            }   
        }
    }
 
    /**
     * @return array
     */
    private function extractCommitedFiles($commit = false)
    {
        $files  = [];
        $output = [];
        echo($commit);

        ($commit) ? exec('git diff --name-status --diff-filter=ACM master...' . $commit, $output) : exec("git diff --cached --name-status --diff-filter=ACM", $output);
 
        foreach ($output as $line) {
            $this->output->writeln($line);
            $action  = trim($line[0]);
            $files[] = trim(substr($line, 1));
        }
 
        return $files;
    }
 
    /**
     * @param $files
     *
     * This function ensures that when the composer.json file is edited
     * the composer.lock is also updated and commited
     *
     * @throws \Exception
     */
    private function checkComposer($files)
    {
        $composerJsonDetected = false;
        $composerLockDetected = false;
 
        foreach ($files as $file) {
            if ($file === 'composer.json') {
                $composerJsonDetected = true;
            }
 
            if ($file === 'composer.lock') {
                $composerLockDetected = true;
            }
        }
 
        if ($composerJsonDetected && !$composerLockDetected) {
            return false;
        }
        return true;
    }
 
    /**
     *
     * @return bool
     */
    private function codeStyle()
    {
        $result = shell_exec($this->projectRoot.'/.git/hooks/codestyle.sh');
        echo $result;

        return $result;
    }
 
    /**
     * @param $files
     *
     * @return bool
     */
    private function checkPhpMd($files)
    {
        $succeed = true;
 
        foreach ($files as $file) {
            if (!$this->shouldProcessFile($file)) {
                continue;
            }
            $processBuilder = new ProcessBuilder([
                'bin/phpmd',
                $file,
                'text',
                '../PHPCodeQuality/ruleset/ruleset.xml'
            ]);
            $processBuilder->setWorkingDirectory($this->projectRoot);
            $process = $processBuilder->getProcess();
            $process->run();
 
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                $this->output->writeln(sprintf('<info>%s</info>', trim($process->getOutput())));
                $succeed = false;
            }
        }
 
        return $succeed;
    }

    /**
     * @param $files
     *
     * @return bool
     */
    private function phpLint($files)
    {
        $needle = '/(\.php)|(\.inc)$/';
        $succeed = true;
 
        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }
 
            $processBuilder = new ProcessBuilder(array('php', '-l', $this->projectRoot.'/'.$file));
            $process = $processBuilder->getProcess();
            $process->run();
 
            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
 
                if ($succeed) {
                    $succeed = false;
                }
            }
        }
 
        return $succeed;
    }
}

if($argv[1]){
    $console = new PhpCodeQualityTool(false);
    $console->run();
}