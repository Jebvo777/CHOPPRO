<?php
declare(strict_types=1);
namespace Choppro\Stage2;
final class Problem extends \RuntimeException
{
    public function __construct(public int $status, public string $codeName, string $message, public array $details = []) { parent::__construct($message); }
}
