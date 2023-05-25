<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateNoTriggerSingle(): void
    {
        $this->validateModule(__DIR__ . '/../NoTriggerSingle');
    }

    public function testValidateNoTriggerGroup(): void
    {
        $this->validateModule(__DIR__ . '/../NoTriggerGroup');
    }
}