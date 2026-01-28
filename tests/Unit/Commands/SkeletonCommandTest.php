<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Proxynth\Larawebhook\Commands\SkeletonCommand;

describe('SkeletonCommand handle method', function () {
    it('returns SUCCESS constant value', function () {
        $command = new SkeletonCommand;

        // Mock the output
        $command->setLaravel($this->app);

        // Create a mock output
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new Illuminate\Console\OutputStyle(
            new Symfony\Component\Console\Input\ArrayInput([]),
            $output
        ));

        $result = $command->handle();

        expect($result)->toBe(Command::SUCCESS)
            ->and($result)->toBe(0);
    });

    it('writes comment to output', function () {
        $command = new SkeletonCommand;
        $command->setLaravel($this->app);

        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new Illuminate\Console\OutputStyle(
            new Symfony\Component\Console\Input\ArrayInput([]),
            $output
        ));

        $command->handle();

        $outputContent = $output->fetch();

        expect($outputContent)->toContain('All done');
    });
});
