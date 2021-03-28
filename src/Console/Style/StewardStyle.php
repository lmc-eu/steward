<?php declare(strict_types=1);

namespace Lmc\Steward\Console\Style;

use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;

class StewardStyle extends OutputStyle
{
    /** @var SymfonyStyle */
    protected $symfonyStyle;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        parent::__construct($output);

        $this->symfonyStyle = new SymfonyStyle($input, $output);
    }

    public static function getTimestampPrefix(): string
    {
        return '[' . date('Y-m-d H:i:s') . ']';
    }

    /**
     * Output progress message status
     */
    public function runStatus(string $message): void
    {
        $this->writeln(static::getTimestampPrefix() . ' ' . $message);
    }

    /**
     * Output success message in progress status
     */
    public function runStatusSuccess(string $message): void
    {
        $this->runStatus('<fg=green>' . $message . '</>');
    }

    /**
     * Output error message in progress status
     */
    public function runStatusError(string $message): void
    {
        $this->runStatus('<fg=red>' . $message . '</>');
    }

    public function title($message): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function section($message): void
    {
        $this->newLine();
        $this->writeln([
            sprintf('<comment>%s</>', $message),
            sprintf(
                '<comment>%s</>',
                str_repeat('-', Helper::strlenWithoutDecoration($this->getFormatter(), $message))
            ),
        ]);
    }

    /**
     * Print output from process
     */
    public function output(string $output, string $identifier): void
    {
        if (empty($output)) {
            return;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // color lines containing "[WARN]" or "[DEBUG]"
            if (mb_strpos($line, '[WARN]') !== false) {
                $line = '<fg=black;bg=yellow>' . $line . '</fg=black;bg=yellow>';
            } elseif (mb_strpos($line, '[DEBUG]') !== false) {
                $line = '<comment>' . $line . '</comment>';
            }

            $this->write($identifier . '> ');
            $this->writeln($line);
        }
    }

    /**
     * Print error output from process
     */
    public function errorOutput(string $output, string $identifier): void
    {
        $output = rtrim($output);

        if (empty($output)) {
            return;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $this->write('<error>' . $identifier . ' ERR> ');
            $this->writeln($line . '</>');
        }
    }

    public function listing(array $elements): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function text($message): void
    {
        $this->writeln($message);
    }

    public function success($message): void
    {
        $this->symfonyStyle->block($message, 'OK', 'fg=black;bg=green', ' ', true);
    }

    public function error($message): void
    {
        $this->symfonyStyle->block($message, 'ERROR', 'fg=white;bg=red', ' ', true);
    }

    public function warning($message): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function note($message): void
    {
        $this->symfonyStyle->block($message, 'NOTE', 'fg=yellow', ' ');
    }

    public function suggestion($message): void
    {
        $this->symfonyStyle->block($message, '💡', 'fg=yellow', ' ');
    }

    public function caution($message): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function table(array $headers, array $rows): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function ask($question, $default = null, $validator = null)
    {
        return $this->symfonyStyle->ask($question, $default, $validator);
    }

    public function askHidden($question, $validator = null): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function confirm($question, $default = true): bool
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function choice($question, array $choices, $default = null): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function progressStart($max = 0): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function progressAdvance($step = 1): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }

    public function progressFinish(): void
    {
        throw new \BadMethodCallException('Method not implemented');
    }
}
