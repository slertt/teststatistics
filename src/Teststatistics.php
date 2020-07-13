<?php
/**
 * @package     teststatistics
 * @subpackage
 *
 * @copyright   Copyright (C) 2005 - 2015 redCOMPONENT.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Codeception\Extension;

use Codeception\Events;
use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Event\StepEvent;

class Teststatistics extends \Codeception\Platform\Extension
{
    /**
     * Maximum time in second allowed for a step to be performant
     *
     * @var int
     */
    public static $maxStepPerformantTime = 3;

    public static $testTimes = array();
    public static $notPerformantStepsByTest = array();
    public static $tmpCurrentTest = 0;
    public static $tmpStepStartTime = 0;
    public static $ignoreFileName = false;
    public static $ignoreStep = false;

    public function _initialize()
    {
        $this->options['silent'] = false; // turn on printing for this extension
        //$this->_reconfigure(['settings' => ['silent' => true]]); // turn off printing for everything else
    }

    // we are listening for events
    static $events = array(
        Events::TEST_BEFORE  => 'beforeTest',
        Events::TEST_END     => 'afterTest',
        Events::SUITE_BEFORE => 'beforeSuite',
        Events::SUITE_AFTER  => 'afterSuite',
        Events::STEP_BEFORE  => 'beforeStep',
        Events::STEP_AFTER   => 'afterStep'
    );

    /**
     * List of files that should be ignored in statistics
     * Example: tests/api/MessagesResourceCest.php
     *
     * @var array
     */
    static $ignoredTestFileNames = [];

    /**
     * Tests which should be ignored in statistics
     * Name of tests should be unique
     *
     * @var array
     */
    static $ignoredTestMethods = [];

    /**
     * Steps that should be ignored in statistics
     * Example: getAllLoggedInUserData
     *
     * @var array
     */
    static $ignoredTestSteps = [];

    // reset times and not performant tests arrays, in case multiple suites are launched
    public function beforeSuite(SuiteEvent $e)
    {
        self::$testTimes = array();
        self::$notPerformantStepsByTest = array();
    }

    // we are printing test status and time taken
    public function beforeTest(TestEvent $e)
    {
        if(true === $this->checkIfStatisticShouldBeIgnored($e)) return;
        self::$tmpCurrentTest = \Codeception\Test\Descriptor::getTestFileName($e->getTest());
    }

    // we are printing test status and time taken
    public function beforeStep(StepEvent $e)
    {
        if(true === $this->checkIfStatisticShouldBeIgnored($e)) return;
        list($usec, $sec) = explode(" ", microtime());
        self::$tmpStepStartTime = (float) $sec;
    }

    // we are printing test status and time taken
    public function afterStep(StepEvent $e)
    {
        if(self::$ignoreStep) return;

        list($usec, $sec) = explode(" ", microtime());
        $stepEndTime = (float) $sec;

        $stepTime = $stepEndTime - self::$tmpStepStartTime;

        // If the Step has taken more than X seconds
        if ($stepTime > self::$maxStepPerformantTime)
        {
            $step = new \stdClass;
            $currentStep = (string) $e->getStep();
            $step->testMethod = $e->getTest()->getTestMethod();
            $step->name = $currentStep;
            $step->time = $stepTime;
            $step->action = ($e->getStep()->getMetaStep() !== null ? $e->getStep()->getMetaStep()->getAction() : $e->getTest()->getTestMethod());
            $step->actionURI = $e->getStep()->getArguments()[0];
            $line = ($e->getStep()->getMetaStep() !== null ? $e->getStep()->getMetaStep()->getLine() : '');
            $step->line = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            self::$notPerformantStepsByTest[self::$tmpCurrentTest][] = $step;
        }
    }

    public function afterTest(TestEvent $e)
    {
        if(self::$ignoreFileName) return;

        $test = new \stdClass;
        $test->name = \Codeception\Test\Descriptor::getTestFileName($e->getTest());
        $test->method = $e->getTest()->getTestMethod();

        // stack overflow: http://stackoverflow.com/questions/16825240/how-to-convert-microtime-to-hhmmssuu
        $seconds_input = $e->getTime();
        $seconds = (int)($milliseconds = (int)($seconds_input * 1000)) / 1000;
        $time    = ($seconds % 60);

        $test->time = $time;


        self::$testTimes[] = $test;
    }

    public function afterSuite(SuiteEvent $e)
    {
        $this->writeln("");
        $this->writeln("Tests Performance times");
        $this->writeln("-----------------------------------------------");

        foreach (self::$testTimes as $test)
        {
            $this->writeln(str_pad($test->name, 35) . ' ' . $test->method . ' ' . $test->time . 's');
        }

        if(count(self::$notPerformantStepsByTest) > 0) {
            $this->writeln("");
            $this->writeln("");
            $this->writeln("Slow Steps (Steps taking more than " . self::$maxStepPerformantTime . "s)");
            $this->writeln("-----------------------------------------------");
            foreach (self::$notPerformantStepsByTest as $testname => $steps)
            {
                $this->writeln("");
                $this->writeln("  TEST: " . $testname);
                $this->writeln("  ------------------------------------------");
                foreach ($steps as $step)
                {
                    $this->writeln('    ' . $testname . ':' . $step->line . ' Method: ' . $step->testMethod . ' URI: ' . $step->actionURI . ' (' . $step->time . 's)');
                }
            }
        }
    }

    /**
     * Check if file,test or step is in ignore lists
     */
    protected function checkIfStatisticShouldBeIgnored($e){

        //check if whole file should be ignored
        if($e instanceof TestEvent && in_array(\Codeception\Test\Descriptor::getTestFileName($e->getTest()), self::$ignoredTestFileNames)) {
            self::$ignoreFileName = true;
            return true;
        }

        //check if method should be ignored
        //TODO:

        //check if step should be ignored
        if($e instanceof StepEvent && in_array($e->getStep()->getAction(), self::$ignoredTestSteps)) {
            self::$ignoreStep = true;
            return true;
        }

    }
}
