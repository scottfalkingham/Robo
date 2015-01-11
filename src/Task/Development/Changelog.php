<?php
namespace Robo\Task\Development;

use Robo\Task\Shared;
use Robo\Task\FileSystem;
use Robo\Output;
use Robo\Task\string;
use Robo\Result;
use Robo\Task\FileSystem\ReplaceInFile;
use Robo\Contract\TaskInterface;
use Robo\Task\Development;

/**
 * Helps to manage changelog file.
 * Creates or updates `changelog.md` file with recent changes in current version.
 *
 * ``` php
 * <?php
 * $version = "0.1.0";
 * $this->taskChangelog()
 *  ->version($version)
 *  ->change("released to github")
 *  ->run();
 * ?>
 * ```
 *
 * Changes can be asked from Console
 *
 * ``` php
 * <?php
 * $this->taskChangelog()
 *  ->version($version)
 *  ->askForChanges()
 *  ->run();
 * ?>
 * ```
 *
 * @method Development\Changelog filename(string $filename)
 * @method Development\Changelog anchor(string $anchor)
 * @method Development\Changelog version(string $version)
 */
class Changelog implements TaskInterface
{
    use Output;
    use \Robo\Common\DynamicConfig;
    use FileSystem;

    protected $filename;
    protected $log = [];
    protected $anchor = "# Changelog";
    protected $version = "";

    /**
     * @param string $filename
     * @return \Robo\Task\Development\Changelog
     */
    public static function init($filename = 'CHANGELOG.md')
    {
        return new Changelog($filename);
    }

    public function askForChanges()
    {
        while ($resp = $this->ask("Changed in this release: ")) {
            $this->log[] = $resp;
        };
        return $this;
    }

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function changes(array $data)
    {
        $this->log = array_merge($this->log, $data);
        return $this;
    }

    public function change($change)
    {
        $this->log[] = $change;
        return $this;
    }

    public function getChanges()
    {
        return $this->log;
    }

    public function run()
    {
        if (empty($this->log)) {
            return Result::error($this, "Changelog is empty");
        }
        $text = implode(
                "\n", array_map(
                    function ($i) {
                        return "* $i *" . date('Y-m-d') . "*";
                    }, $this->log
                )
            ) . "\n";
        $ver = "#### {$this->version}\n\n";
        $text = $ver . $text;

        if (!file_exists($this->filename)) {
            $this->printTaskInfo("Creating {$this->filename}");
            $res = file_put_contents($this->filename, $this->anchor);
            if ($res === false) {
                return Result::error($this, "File {$this->filename} cant be created");
            }
        }

        // trying to append to changelog for today
        $result = (new ReplaceInFile($this->filename))
            ->from($ver)
            ->to($text)
            ->run();

        if (!$result->getData()['replaced']) {
            $result = (new ReplaceInFile($this->filename))
                ->from($this->anchor)
                ->to($this->anchor . "\n\n" . $text)
                ->run();
        }

        return new Result($this, $result->getExitCode(), $result->getMessage(), $this->log);
    }
}