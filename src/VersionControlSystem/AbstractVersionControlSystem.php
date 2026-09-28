<?php

declare(strict_types=1);

namespace Jundayw\ComposerVersion\VersionControlSystem;

use Jundayw\ComposerVersion\Exception\GitException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * VCS 抽象基类，封装命令执行与错误处理逻辑。
 */
abstract class AbstractVersionControlSystem implements VersionControlSystemInterface
{
    public function __construct(
        protected readonly OutputInterface $output,
    ) {
    }

    /**
     * 创建进程实例（子类可覆盖以定制环境变量等）。
     *
     * @param list<string> $command
     */
    protected function createProcess(array $command): Process
    {
        return new Process($command);
    }

    /**
     * 执行命令并返回标准输出（已 trim）。
     *
     * @param list<string> $command
     *
     * @throws GitException 命令执行失败时抛出
     */
    protected function run(array $command): string
    {
        $process = $this->createProcess($command);

        try {
            $process->mustRun();
        } catch (Throwable $e) {
            $errorMessage = trim($process->getErrorOutput());

            if ($errorMessage) {
                $this->output->writeln(
                    sprintf('<error>[%s] %s</error>', $this->getIdentifier(), $errorMessage)
                );
            }

            throw new GitException(
                $errorMessage ?: $e->getMessage(),
                $process->getExitCode() ?? 0,
                $e,
            );
        }

        return trim($process->getOutput());
    }

    /**
     * 执行命令并返回是否成功（不抛异常）。
     *
     * @param list<string> $command
     */
    protected function tryRun(array $command): bool
    {
        try {
            $this->run($command);
        } catch (GitException) {
            return false;
        }

        return true;
    }
}