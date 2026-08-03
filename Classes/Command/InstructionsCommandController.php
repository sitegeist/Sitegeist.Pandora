<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Command;

use Neos\Flow\Cli\CommandController;
use Neos\Utility\Arrays;
use Sitegeist\Pandora\Instruction\InstructionsRegistry;

/**
 * Diagnostics for the MCP instructions available in this installation.
 */
class InstructionsCommandController extends CommandController
{
    public function __construct(
        private readonly InstructionsRegistry $instructionsRegistry,
    ) {
        parent::__construct();
    }

    public function showCommand(string $roles): void
    {
        $this->outputLine('Instructions for roles ' . $roles . ':');
        $this->outputLine(' ');
        $this->outputLine($this->instructionsRegistry->findForRoles(Arrays::trimExplode(',', $roles)) ?: '');
    }
}
