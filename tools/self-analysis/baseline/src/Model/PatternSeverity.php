<?php declare(strict_types=1);

namespace PhpArchitector\Model;

/**
 * Severity levels for pattern findings.
 */
enum PatternSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';
}
