<?php

declare(strict_types=1);

namespace Sitegeist\Pandora\Instruction;

interface InstructionsProvider
{
    public function getInstructions(): string;
}
